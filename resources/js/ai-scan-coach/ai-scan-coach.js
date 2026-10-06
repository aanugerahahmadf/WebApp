/**
 * AI Scan Coach — advise-on-device scan intelligence + voice guidance.
 *
 * WHAT THIS IS (and what it honestly is not)
 * ------------------------------------------
 * The post-capture checks (face match vs. KTP, KTP authenticity) are genuine
 * model inference and live in AI Core (`AI_CORE_URL`, a separate Python
 * service). They CANNOT drive a live overlay: one HTTP round-trip per frame is
 * impossible at any usable rate. So the real-time half of the scan is computed
 * here, on the GPU-less canvas, using classical computer vision:
 *
 *   - Variance of Laplacian      -> focus / motion blur          (real metric)
 *   - Luma histogram             -> exposure, dark / blown out  (real metric)
 *   - Sobel gradient magnitude   -> edge density, card extent   (real metric)
 *   - Projection profiles        -> where the card's borders are (real technique)
 *   - YCbCr chrominance window   -> coarse skin-region estimate (heuristic)
 *
 * Those are the same measurements a scanner app makes before it ever calls a
 * model, and they run at ~12 fps with zero network. The skin-region step is a
 * heuristic, NOT face recognition — it guides framing only and is reported with
 * `faceDetector: 'chroma'` so nothing downstream can mistake it for a
 * biometric match. An authoritative match still requires AI Core.
 *
 * WHY A SEPARATE MODULE
 * ---------------------
 * The Blade modals carry their logic inside `x-data="{ ... }"`, which is
 * wrapped in DOUBLE quotes. A single stray `"` in a comment truncates the
 * attribute and dumps raw JS into the page as text. Keeping the intelligence
 * here means the Blade side stays a handful of quotes-free call sites.
 *
 * NOTE ON STYLING: `resources/css/*` declares `@source '../../views'` only, so
 * Tailwind does NOT scan this file. Any class name written in JS would never be
 * generated. The overlay therefore ships its own CSS via a single injected
 * <style> block and uses no Tailwind.
 */

/* ──────────────────────────────────────────────────────────────────────────
 * Tunables
 * ───────────────────────────────────────────────────────────────────────── */

/** Analysis buffer size. 192x144 keeps every pass under ~2 ms. */
const ANALYSIS_W = 192;
const ANALYSIS_H = 144;

/** Fraction of the 4:3 preview the guide shapes occupy (mirrors the Blade guides). */
/* Which document is being scanned when the form has not said yet. */
const DEFAULT_DOC_TYPE = 'ktp';

export const GUIDE = {
    /* face-scan-modal: SVG ellipse cx=200 cy=150 rx=84 ry=112 in a 400x300 box,
       preserveAspectRatio="none" -> radii normalised against each axis. */
    face: { type: 'oval', cx: 0.5, cy: 0.5, rx: 0.21, ry: 0.3733 },
    /* document-scan-modal: div w-[80%] aspect-[1.586/1], centred in a 4:3 box. */
    document: { type: 'rect', x: 0.1, y: 0.1638, w: 0.8, h: 0.6725 },
};

/* Per-type copy for the opening line. Keyed by document type so the coach can
   name the exact card in front of the user instead of saying "the document". */
const DOC_TYPE_LINES = {
    ktp: {
        id: 'Siap memindai KTP. Letakkan kartu di dalam bingkai.',
        en: 'Ready to scan your national ID. Place the card inside the frame.',
    },
    npwp: {
        id: 'Siap memindai NPWP. Letakkan kartu di dalam bingkai.',
        en: 'Ready to scan your tax ID. Place the card inside the frame.',
    },
    sim: {
        id: 'Siap memindai SIM. Letakkan kartu di dalam bingkai.',
        en: 'Ready to scan your driver licence. Place the card inside the frame.',
    },
    passport: {
        id: 'Siap memindai paspor. Buka ke halaman data lalu letakkan di bingkai.',
        en: 'Ready to scan your passport. Open to the photo page and place it in the frame.',
    },
};

const DOC_TYPE_DEFAULT = DOC_TYPE_LINES[DEFAULT_DOC_TYPE];

/**
 * Opening line naming the actual document type.
 *
 * Returns a plain string, already in the requested language — callers embed it
 * directly in an utterance, so there is nothing to destructure.
 *
 * @param {string} type identity_type (ktp | npwp | sim | passport)
 * @param {string} [lang]
 * @returns {string}
 */
export function docTypeLine(type, lang = 'id') {
    const lines = DOC_TYPE_LINES[resolveDocType(type)] || DOC_TYPE_DEFAULT;
    return String(lang).toLowerCase().startsWith('id') ? lines.id : lines.en;
}

/** Name of the document, for use inside other sentences. */
export function docTypeName(type, lang = 'id') {
    const spec = DOC_TYPES[resolveDocType(type)];
    return String(lang).toLowerCase().startsWith('id') ? spec.id : spec.en;
}

/**
 * The four values /api/ktp/verify is allowed to return as `reason`.
 *
 * Anything else in that field means the caller is talking to the face
 * endpoint (or to an older AI Core), where `reason` really is a code.
 */
export const DOC_REASON_NAMES = new Set([
    'KARTU TANDA PENDUDUK',
    'SURAT IZIN MENGEMUDI',
    'PASSPORT',
    'NOMOR POKOK WAJIB PAJAK',
]);

/**
 * Codes that mean "the service never looked at the photo".
 *
 * These outrank `reason_code`: a request that failed upstream never reaches the
 * point where a reason code is produced, and if one does turn up it is stale.
 * Masking an outage with it would re-arm the camera against a service that
 * will reject every further attempt just the same.
 */
const SERVICE_CODES = new Set(['AI_UNAVAILABLE', 'UNAVAILABLE', 'OFFLINE', 'UPLOAD_FAILED']);

/**
 * Pick the diagnostic code out of an AI Core result.
 *
 * Precedence matters. /api/ktp/verify reports the document's official name in
 * `reason`, so that field is only usable as a code when it does NOT look like
 * one of the four names. `reason_code` is authoritative when present, and
 * `blocking_issue` (an array, possibly empty) is the last resort so a
 * rejection is never mistaken for a retryable blank verdict.
 *
 * @param {{reason?: string, reason_code?: string, blocking_issue?: string[]|null}} result
 * @returns {string} upper-cased diagnostic code, '' when nothing is available
 */
export function verdictReason(result) {
    if (!result) return '';

    const raw = String(result.reason || '').toUpperCase();

    /* A dead service is never overridable by a stale per-photo code. */
    if (SERVICE_CODES.has(raw)) return raw;

    const code = String(result.reason_code || '').toUpperCase();
    if (code) return code;

    if (raw && !DOC_REASON_NAMES.has(raw)) return raw;

    const blocking = Array.isArray(result.blocking_issue) ? result.blocking_issue : [];
    for (const issue of blocking) {
        const name = String(issue || '').toUpperCase();
        if (name) return name;
    }

    return '';
}

/**
 * Exposure band, measured as MEAN luma inside the guide.
 *
 * Mean is only safe for the "too dark" test. A blank white ID card legitimately
 * averages ~235, so a symmetric "too bright" rule on the mean would scold a user
 * for holding the card correctly. Over-exposure is therefore judged by how much
 * of the guide is CLIPPED to white, not by how bright the average is.
 */
const DARK_LUMA = 55;

/** Share of the guide that may be clipped to white before glare is reported. */
const GLARE_RATIO = 0.06;

/** Past this much clipping the frame is washed out, not merely reflective. */
const BLOWN_RATIO = 0.25;

/**
 * Fraction of peak energy a column/row must reach to count as part of the
 * document's own border.
 *
 * Deliberately high. The outer boundary of the card is by far the strongest
 * gradient in frame, so a high floor isolates the border columns and ignores the
 * interior text. A low floor is actively harmful: defocus smears the
 * dark-to-light transition across many columns, and a low threshold then reports
 * the card as WIDER than it is -- which would tell a user who took a blurry
 * photo to move further away. That is the worst possible advice to give.
 */
const BORDER_ENERGY_RATIO = 0.6;

/**
 * Skin share of the face guide.
 *
 * Calibrated against the actual oval: the guide ellipse covers ~78% of its own
 * bounding box, so a head filling the oval lands near 0.79, while a normal
 * head-and-shoulders framing sits around 0.30-0.50.
 */
const FACE_MIN_RATIO = 0.04;   /* below: nothing face-like in view  */
const FACE_FAR_RATIO = 0.12;   /* below: too far back             */
const FACE_CLOSE_RATIO = 0.70; /* above: too close                */

/** How far off centre the subject may sit before we ask for a nudge. */
const CENTER_TOLERANCE = 0.22;

/** Card extent relative to the guide; 1.0 means it fills the box exactly. */
const DOC_MIN_EDGES = 0.02;    /* below: guide is empty           */
const DOC_CLOSE_FILL = 1.12;   /* above: card overflows the guide */
const DOC_FAR_FILL_X = 0.55;
const DOC_FAR_FILL_Y = 0.50;

/**
 * Focus is judged RELATIVE to the best sharpness this session has seen, not
 * against a fixed constant. Variance-of-Laplacian depends heavily on sensor
 * noise and on how aggressively the browser downscales, so a hard threshold
 * either never fires on a clean laptop or fires constantly on a noisy phone.
 */
const FOCUS_RATIO = 0.5;
const FOCUS_WARMUP_SAMPLES = 8;

/** Temporal jitter of sharpness, as a fraction of its mean -> hand shake. */
const SHAKE_RATIO = 0.55;
const SHAKE_WINDOW = 12;

/**
 * Consecutive good frames required before the shutter fires by itself.
 *
 * Roughly 0.7 s at the loop's ~12 fps. Long enough that a single lucky frame
 * does not trigger a capture, short enough that the user is not left waiting
 * while staring at a green ring wondering why nothing happened.
 */
const HOLD_FRAMES = 8;

/**
 * Automatic attempts before giving up and handing control back to the user.
 *
 * A cap is not optional here. Without one, a phone pointed at a wall, or a
 * server that keeps rejecting every frame, produces an endless loop of
 * capture -> upload -> reject -> capture. That hammers the AI service and
 * leaves the user with no way out but closing the tab.
 */
const MAX_AUTO_ATTEMPTS = 4;

/**
 * Verdicts that end the automatic loop.
 *
 * Each of these means the next photo would fail the same way: the service is
 * down, or there is no reference to compare against. Retrying only burns
 * attempts and, worse, makes the user think their face or card is the problem.
 */
const TERMINAL_VERDICTS = [
    'VERDICT_NO_KTP',
    'VERDICT_UNAVAILABLE',
];

/** Classic 8-bit YCbCr skin window. Coarse on purpose; framing aid only. */
const SKIN_CB_MIN = 77, SKIN_CB_MAX = 130;
const SKIN_CR_MIN = 133, SKIN_CR_MAX = 180;

/**
 * Physical card geometry, in millimetres, for every document type the app
 * accepts (`identity_type` in the DB is exactly ktp | npwp | sim | passport).
 *
 * These are ISO/IEC 7810 ID-1 dimensions and drive the on-screen guide, so the
 * frame physically matches the card the user is holding. That is what makes the
 * "too close / too far" instruction correct: it compares the real card against
 * its real size rather than a generic box.
 *
 * `ratio` is width / height. Passport is a booklet, so it is taller and much
 * narrower than the plastic cards.
 */
export const DOC_TYPES = {
    ktp: {
        ratio: 85.6 / 54,
        id: 'Kartu Tanda Penduduk',
        en: 'national ID card',
        hintId: 'letakkan KTP di dalam bingkai, foto menghadap ke atas',
        hintEn: 'place your national ID card in the frame, photo facing up',
    },
    npwp: {
        ratio: 85.6 / 54,
        id: 'Kartu NPWP',
        en: 'tax ID card',
        hintId: 'letakkan kartu NPWP di dalam bingkai',
        hintEn: 'place your tax ID card in the frame',
    },
    sim: {
        ratio: 85.6 / 54,
        id: 'SIM',
        en: 'driver licence',
        hintId: 'letakkan SIM di dalam bingkai',
        hintEn: 'place your driver licence in the frame',
    },
    passport: {
        /* Passport data page: 88 mm wide x 125 mm tall — PORTRAIT.
           Writing 125/88 here flips it to landscape and produces a wide frame
           for the one document that is physically tall. */
        ratio: 88 / 125,
        id: 'Paspor',
        en: 'passport',
        hintId: 'buka paspor ke halaman data, lalu letakkan di dalam bingkai',
        hintEn: 'open your passport to the photo page, then place it in the frame',
    },
};

/** Normalise whatever the form/DB gave us into a known key. */
export function resolveDocType(value) {
    const key = String(value || '').toLowerCase().trim();
    return Object.prototype.hasOwnProperty.call(DOC_TYPES, key) ? key : DEFAULT_DOC_TYPE;
}

/** Widest the guide may be, as a fraction of the preview width. */
const GUIDE_MAX_W = 0.8;

/** Tallest the guide may be, as a fraction of the preview height. */
const GUIDE_MAX_H = 0.94;

/**
 * Build the guide for a document type inside the 4:3 preview.
 *
 * The preview is 4:3, so a normalised fraction of its width is NOT the same
 * number of pixels as the same fraction of its height. Deriving the height:
 *
 *     realAspect = (w / h) * (W / H) = (w / h) * (4/3)
 *
 * solving for h gives  h = w * (4/3) / ratio.
 *
 * Getting that factor backwards shrinks a KTP frame to roughly half its proper
 * height, so every correctly-held card is reported as "too small".
 *
 * A portrait document (passport) can be taller than the box allows, so the
 * whole guide is scaled down proportionally rather than clipped.
 */
export function docGuideFor(type) {
    const spec = DOC_TYPES[resolveDocType(type)];
    const PREVIEW = 4 / 3;

    let w = GUIDE_MAX_W;
    let h = (w * PREVIEW) / spec.ratio;

    if (h > GUIDE_MAX_H) {
        h = GUIDE_MAX_H;
        w = (h * spec.ratio) / PREVIEW;
    }

    return {
        type: 'rect',
        x: (1 - w) / 2,
        y: (1 - h) / 2,
        w,
        h,
    };
}

/* ──────────────────────────────────────────────────────────────────────────
 * Instruction catalogue
 *
 * `tone` drives both colour and how insistently the voice guide speaks:
 *   critical -> interrupts whatever is being said
 *   warn     -> queued, replaces a pending non-critical line
 *   info     -> only spoken when the voice is otherwise idle
 * ───────────────────────────────────────────────────────────────────────── */

export const CATALOG = {
    DOC_NOT_FOUND: {
        tone: 'critical',
        id: 'Arahkan kartu identitas ke dalam kotak.',
        en: 'Place your ID card inside the box.',
    },
    DOC_TOO_CLOSE: {
        tone: 'warn',
        id: 'Kartu terlalu dekat. Jauhkan sedikit.',
        en: 'The card is too close. Move it a little further away.',
    },
    DOC_TOO_FAR: {
        tone: 'warn',
        id: 'Kartu terlalu jauh. Dekatkan sedikit.',
        en: 'The card is too far. Move it a little closer.',
    },
    FACE_NOT_FOUND: {
        tone: 'critical',
        id: 'Wajah belum terlihat. Masukkan wajah ke dalam oval.',
        en: 'No face detected yet. Move your face inside the oval.',
    },
    FACE_TOO_CLOSE: {
        tone: 'warn',
        id: 'Terlalu dekat. Geser sedikit ke belakang.',
        en: 'Too close. Lean back a little.',
    },
    FACE_TOO_FAR: {
        tone: 'warn',
        id: 'Terlalu jauh. Dekatkan sedikit.',
        en: 'Too far away. Come a little closer.',
    },
    FACE_LEFT:      { tone: 'warn', id: 'Geser sedikit ke kiri.', en: 'Shift slightly to the left.' },
    FACE_RIGHT:     { tone: 'warn', id: 'Geser sedikit ke kanan.', en: 'Shift slightly to the right.' },
    FACE_UP:        { tone: 'warn', id: 'Turunkan sedikit.', en: 'Lower your head a little.' },
    FACE_DOWN:      { tone: 'warn', id: 'Angkat sedikit.', en: 'Raise your head a little.' },
    TOO_DARK: {
        tone: 'warn',
        id: 'Terlalu gelap. Cari tempat yang lebih terang.',
        en: 'Too dark. Find a brighter spot.',
    },
    TOO_BRIGHT: {
        tone: 'warn',
        id: 'Terlalu terang. Hindari cahaya langsung.',
        en: 'Too bright. Avoid direct light.',
    },
    GLARE: {
        tone: 'warn',
        id: 'Ada pantulan. Miringkan sedikit agar tidak berkilau.',
        en: 'There is a glare. Tilt it slightly to avoid reflection.',
    },
    BLURRY: {
        tone: 'warn',
        id: 'Gambar kurang tajam. Tahan sebentar, jangan goyang.',
        en: 'The image is blurry. Hold still for a moment.',
    },
    SHAKING: {
        tone: 'warn',
        id: 'Tanganmu goyang. Tahan sebentar, jangan digerakkan.',
        en: 'Your hand is shaking. Hold steady.',
    },
    READY: {
        tone: 'info',
        id: 'Bagus, diam sebentar.',
        en: 'Good, hold still for a moment.',
    },
    CAPTURING: {
        tone: 'info',
        id: 'Mengambil foto.',
        en: 'Taking the photo.',
    },

    /* ── Server-side verdicts ───────────────────────────────────────────
     * Spoken when AI Core rejects a capture. Each one names a concrete thing
     * the person can change, because "verification failed" on its own tells
     * them nothing and they just repeat the same mistake. */
    VERDICT_NO_FACE: {
        tone: 'critical',
        id: 'Wajah tidak terdeteksi. Ambil ulang, wajah harus terlihat jelas.',
        en: 'No face detected. Retake, your face must be clearly visible.',
    },
    VERDICT_MULTIPLE_FACES: {
        tone: 'critical',
        id: 'Terlihat lebih dari satu wajah. Pastikan hanya wajah Anda yang masuk.',
        en: 'More than one face detected. Make sure only your face is in frame.',
    },
    VERDICT_NOT_MATCH: {
        tone: 'warn',
        id: 'Wajah tidak cocok dengan foto KTP. Ambil ulang dengan posisi seperti di KTP.',
        en: 'Your face does not match the ID photo. Retake in the same pose as your ID.',
    },
    VERDICT_EYES_CLOSED: {
        tone: 'warn',
        id: 'Mata tertutup. Buka mata dan ambil ulang.',
        en: 'Your eyes look closed. Open them and retake.',
    },
    VERDICT_BLURRY: {
        tone: 'warn',
        id: 'Foto terlalu buram. Pegang phonesenyap lalu ambil ulang.',
        en: 'The photo is too blurry. Hold steady and retake.',
    },
    VERDICT_NO_KTP: {
        tone: 'critical',
        id: 'Foto KTP belum diunggah, verifikasi tidak bisa dilakukan.',
        en: 'Your ID photo has not been uploaded yet, so verification cannot run.',
    },

    /* ── Document verdicts ──────────────────────────────────────────────
     * Reason strings below are the ones AI Core actually emits
     * (app.py /api/ktp/verify and /api/face/verify), not invented ones. */
    DOC_WRONG_ASPECT: {
        tone: 'warn',
        id: 'Kartu tidak sesuai bentuk. Miringkan agar seluruh kartu terlihat.',
        en: 'The card does not match the frame. Tilt it so the whole card is visible.',
    },
    DOC_NO_FACE_ON_CARD: {
        tone: 'warn',
        id: 'Foto pada kartu tidak terbaca. Pastikan foto dan tulisan terlihat jelas.',
        en: 'The photo on the card is not readable. Make sure the photo and text are clear.',
    },
    DOC_NO_NUMBER: {
        tone: 'warn',
        id: 'Nomor kartu tidak terbaca. Tunggu sebentar, lalu pindai ulang dengan cahaya merata.',
        en: 'The card number could not be read. Hold still and scan again in even light.',
    },
    DOC_OK: {
        tone: 'info',
        id: 'Kartu terbaca dengan baik.',
        en: 'The card was read successfully.',
    },
    /* The card was recognised but AI Core gave no usable failure code. */
    DOC_UNKNOWN_ISSUE: {
        tone: 'warn',
        id: 'Kartu terbaca, tetapi belum memenuhi syarat. Coba pindai ulang.',
        en: 'The card was read but does not qualify yet. Please scan it again.',
    },
    VERDICT_UNAVAILABLE: {
        tone: 'critical',
        id: 'Layanan AI sedang tidak tersedia. Foto Anda tetap aman, coba lagi nanti.',
        en: 'The AI service is unavailable. Your photo is safe, please try again later.',
    },
    VERDICT_RETRY: {
        tone: 'warn',
        id: 'Belum berhasil. Ambil ulang sekali lagi.',
        en: 'Not verified yet. Taking it again.',
    },
    VERIFIED_OK: {
        tone: 'info',
        id: 'Wajah berhasil diverifikasi.',
        en: 'Your face has been verified.',
    },
};

/* ──────────────────────────────────────────────────────────────────────────
 * Pixel analysis (pure — no DOM, so it is unit-testable in Node)
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * Map a guide expressed in 4:3 preview space onto an analysis buffer.
 *
 * The preview is `object-cover`, so only the centred 4:3 slice of the sensor
 * frame is ever visible. Anything the user sees, the coach must measure inside
 * that same slice, otherwise the overlay would judge a region they cannot see.
 *
 * @param {{type: string, cx?: number, cy?: number, rx?: number, ry?: number,
 *          x?: number, y?: number, w?: number, h?: number}} guide
 * @param {number} w analysis buffer width
 * @param {number} h analysis buffer height
 * @returns {{x0: number, y0: number, x1: number, y1: number}} inclusive pixel box
 */
export function guideBox(guide, w, h) {
    const cx = guide.type === 'oval' ? guide.cx : guide.x + guide.w / 2;
    const cy = guide.type === 'oval' ? guide.cy : guide.y + guide.h / 2;
    const halfW = guide.type === 'oval' ? guide.rx : guide.w / 2;
    const halfH = guide.type === 'oval' ? guide.ry : guide.h / 2;

    return {
        x0: Math.max(0, Math.round((cx - halfW) * w)),
        y0: Math.max(0, Math.round((cy - halfH) * h)),
        x1: Math.min(w, Math.round((cx + halfW) * w)),
        y1: Math.min(h, Math.round((cy + halfH) * h)),
    };
}

/** Rec. 601 luma plane from an RGBA buffer. */
function toLuma(data, w, h) {
    const n = w * h;
    const luma = new Float32Array(n);
    for (let p = 0, i = 0; p < n; p++, i += 4) {
        luma[p] = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
    }
    return luma;
}

/**
 * Variance of the 3x3 Laplacian over a box — the standard focus measure.
 * Sharp edges give a large spread of second derivatives; defocus collapses it
 * toward zero.
 */
function laplacianVariance(luma, w, h, box) {
    let sum = 0;
    let sumSq = 0;
    let n = 0;

    for (let y = box.y0 + 1; y < box.y1 - 1; y++) {
        for (let x = box.x0 + 1; x < box.x1 - 1; x++) {
            const i = y * w + x;
            const v = luma[i - w] + luma[i - 1] - 4 * luma[i] + luma[i + 1] + luma[i + w];
            sum += v;
            sumSq += v * v;
            n += 1;
        }
    }

    if (n === 0) return 0;
    const mean = sum / n;

    return sumSq / n - mean * mean;
}

/** Sobel gradient magnitude over a box, plus per-column / per-row energy. */
function sobelEnergy(luma, w, h, box) {
    const bw = box.x1 - box.x0;
    const bh = box.y1 - box.y0;
    const mag = new Float32Array(bw * bh);
    const colE = new Float32Array(bw);
    const rowE = new Float32Array(bh);
    let peak = 0;

    for (let y = 1; y < bh - 1; y++) {
        for (let x = 1; x < bw - 1; x++) {
            const i = (box.y0 + y) * w + (box.x0 + x);
            const gx = -luma[i - w - 1] - 2 * luma[i - 1] - luma[i + w - 1]
                + luma[i - w + 1] + 2 * luma[i + 1] + luma[i + w + 1];
            const gy = -luma[i - w - 1] - 2 * luma[i - w] - luma[i - w + 1]
                + luma[i + w - 1] + 2 * luma[i + w] + luma[i + w + 1];
            const m = Math.sqrt(gx * gx + gy * gy);
            mag[y * bw + x] = m;
            if (m > peak) peak = m;
            colE[x] += m;
            rowE[y] += m;
        }
    }

    return { mag, colE, rowE, bw, bh, peak };
}

/**
 * Span from the first to the last column/row reaching `BORDER_ENERGY_RATIO` of
 * peak — i.e. where the document's own outer edges fall.
 *
 * Contiguity is deliberately NOT required. Interior text is far weaker than the
 * outer border, so demanding an unbroken run would report a text-filled card as
 * nearly empty; taking the span of only the strong columns measures the card
 * itself and degrades gracefully under defocus.
 */
function borderSpan(energy) {
    let peak = 0;
    for (let i = 0; i < energy.length; i++) {
        if (energy[i] > peak) peak = energy[i];
    }
    if (peak <= 0) return { start: 0, length: 0 };

    const floor = peak * BORDER_ENERGY_RATIO;
    let first = -1;
    let last = -1;

    for (let i = 0; i < energy.length; i++) {
        if (energy[i] >= floor) {
            if (first < 0) first = i;
            last = i;
        }
    }

    if (first < 0) return { start: 0, length: 0 };

    return { start: first, length: last - first + 1 };
}

/** Fraction of a box whose chrominance falls inside the coarse skin window. */
function skinStats(data, w, h, box) {
    const gcx = (box.x0 + box.x1) / 2;
    const gcy = (box.y0 + box.y1) / 2;
    const halfW = (box.x1 - box.x0) / 2;
    const halfH = (box.y1 - box.y0) / 2;
    const guideArea = halfW * halfH * 4;

    /* Scanned across the WHOLE frame, not just the guide. If the subject is
       standing outside the oval, restricting the scan to the oval would report
       "no face" and the user would never learn which way to step. */
    let total = 0;
    let inside = 0;
    let sx = 0;
    let sy = 0;

    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const i = (y * w + x) * 4;
            const r = data[i];
            const g = data[i + 1];
            const b = data[i + 2];

            const cb = 128 - 0.168736 * r - 0.331264 * g + 0.5 * b;
            const cr = 128 + 0.5 * r - 0.418688 * g - 0.081312 * b;

            if (cb < SKIN_CB_MIN || cb > SKIN_CB_MAX || cr < SKIN_CR_MIN || cr > SKIN_CR_MAX) {
                continue;
            }

            total += 1;
            sx += x;
            sy += y;

            if (x >= box.x0 && x < box.x1 && y >= box.y0 && y < box.y1) inside += 1;
        }
    }

    if (total === 0 || guideArea === 0) {
        return { ratio: 0, seen: false, dx: 0, dy: 0 };
    }

    const clamp = (v) => Math.max(-1.5, Math.min(1.5, v));

    return {
        /* Skin coverage INSIDE the guide — this is what "too close / too far"
           means for a head-and-shoulders shot. */
        ratio: inside / guideArea,
        /* Skin exists somewhere in frame, which is what turns a bare "not found"
           into an actionable direction. */
        seen: true,
        dx: clamp((sx / total - gcx) / halfW),
        dy: clamp((sy / total - gcy) / halfH),
    };
}

/**
 * Full measurement of one frame.
 *
 * @param {{data: Uint8ClampedArray, width: number, height: number}} imageData
 * @param {'face'|'document'} mode
 * @returns {object} metrics consumed by decide()
 */
export function analyzePixels(imageData, mode, docType) {
    const { data, width: w, height: h } = imageData;

    /* Document guides are per-type: a passport frame must be taller than a KTP
       frame, otherwise every passport is reported as "wrong size". */
    const guide = mode === 'face'
        ? GUIDE.face
        : (docType ? docGuideFor(docType) : GUIDE.document);

    const box = guideBox(guide, w, h);

    if (box.x1 - box.x0 < 8 || box.y1 - box.y0 < 8) {
        return { ok: false, reason: 'box-too-small' };
    }

    const luma = toLuma(data, w, h);

    /* Exposure and clipping are measured INSIDE the guide, because that is the
       region the capture actually crops to. Averaging the whole frame would let
       a dark room around a bright card veto a perfectly readable document. */
    let sum = 0;
    let clipped = 0;
    let crushed = 0;
    let guidePixels = 0;

    for (let y = box.y0; y < box.y1; y++) {
        for (let x = box.x0; x < box.x1; x++) {
            const v = luma[y * w + x];
            sum += v;
            guidePixels += 1;
            if (v >= 245) clipped += 1;
            if (v <= 18) crushed += 1;
        }
    }

    const brightness = guidePixels ? sum / guidePixels : 0;

    const sharpness = laplacianVariance(luma, w, h, box);

    /* Sobel over the WHOLE buffer, not just the guide. A card that overflows the
       guide is exactly the "too close" case, and that can only be seen from
       outside the box -- measuring inside would clamp it to 100% and make the
       condition undetectable. */
    const sobel = sobelEnergy(luma, w, h, { x0: 0, y0: 0, x1: w, y1: h });

    /* Edge density INSIDE the guide: is there actually a card in the box?
       Printed text scores high; a bare table or a plain wall scores near zero. */
    let strong = 0;
    let inside = 0;
    const edgeFloor = sobel.peak * 0.35;
    for (let y = box.y0; y < box.y1; y++) {
        for (let x = box.x0; x < box.x1; x++) {
            inside += 1;
            if (sobel.mag[y * w + x] > edgeFloor) strong += 1;
        }
    }
    const edgeDensity = inside ? strong / inside : 0;

    /* Where the card's own borders fall, expressed relative to the guide so that
       1.0 means "fills the box exactly". */
    const colRun = borderSpan(sobel.colE);
    const rowRun = borderSpan(sobel.rowE);

    const fillX = colRun.length / (box.x1 - box.x0);
    const fillY = rowRun.length / (box.y1 - box.y0);

    const base = {
        ok: true,
        mode,
        brightness,
        glareRatio: guidePixels ? clipped / guidePixels : 0,
        darkRatio: guidePixels ? crushed / guidePixels : 0,
        sharpness,
        edgeDensity,
        fillX,
        fillY,
    };

    if (mode === 'face') {
        const skin = skinStats(data, w, h, box);

        return {
            ...base,
            /* Named honestly: this is a chrominance heuristic used for framing
               guidance, NOT face recognition and NOT a biometric match. */
            faceDetector: 'chroma',
            faceRatio: skin.ratio,
            faceSeen: skin.seen,
            faceDx: skin.dx,
            faceDy: skin.dy,
        };
    }

    return base;
}

/* ──────────────────────────────────────────────────────────────────────────
 * Decision engine
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * Turn measurements into the single most useful thing to say right now.
 *
 * Exactly one instruction is returned on purpose. A coach that fires every
 * failing rule at once is just noise, and unusable as speech — so rules are
 * evaluated in the order a person can act on them: put the subject in frame
 * first, then fix the light, then hold still.
 *
 * `baseline` carries this session's best observed sharpness so focus can be
 * judged relatively.
 *
 * @param {object} m metrics from analyzePixels()
 * @param {{focusRef: number, samples: number}} baseline
 * @returns {{key: string, tone: string, text: {id: string, en: string}}}
 */
export function decide(m, baseline) {
    const pick = (key) => ({ key, tone: CATALOG[key].tone, text: CATALOG[key] });

    if (!m || !m.ok) return pick('DOC_NOT_FOUND');

    if (m.mode === 'face') {
        if (m.faceRatio < FACE_MIN_RATIO) {
            /* Skin exists but outside the oval: name the direction instead of
               repeating "no face", which would leave the user stuck. */
            if (m.faceSeen) {
                if (m.faceDx > CENTER_TOLERANCE) return pick('FACE_RIGHT');
                if (m.faceDx < -CENTER_TOLERANCE) return pick('FACE_LEFT');
                if (m.faceDy > CENTER_TOLERANCE) return pick('FACE_DOWN');
                if (m.faceDy < -CENTER_TOLERANCE) return pick('FACE_UP');
            }
            return pick('FACE_NOT_FOUND');
        }

        if (m.faceRatio > FACE_CLOSE_RATIO) return pick('FACE_TOO_CLOSE');
        if (m.faceRatio < FACE_FAR_RATIO) return pick('FACE_TOO_FAR');

        if (m.faceDx > CENTER_TOLERANCE) return pick('FACE_RIGHT');
        if (m.faceDx < -CENTER_TOLERANCE) return pick('FACE_LEFT');
        if (m.faceDy > CENTER_TOLERANCE) return pick('FACE_DOWN');
        if (m.faceDy < -CENTER_TOLERANCE) return pick('FACE_UP');
    } else {
        /* No usable edges in the guide means we are looking at a bare table:
           there is nothing to judge focus or exposure against yet. */
        if (m.edgeDensity < DOC_MIN_EDGES) return pick('DOC_NOT_FOUND');
        if (m.fillX > DOC_CLOSE_FILL || m.fillY > DOC_CLOSE_FILL) return pick('DOC_TOO_CLOSE');
        if (m.fillX < DOC_FAR_FILL_X || m.fillY < DOC_FAR_FILL_Y) return pick('DOC_TOO_FAR');
    }

    if (m.brightness < DARK_LUMA) return pick('TOO_DARK');
    /* Over-exposure is judged by clipping, not by mean: a blank white card has
       a legitimately high average and must not be called "too bright". */
    if (m.glareRatio > BLOWN_RATIO) return pick('TOO_BRIGHT');
    if (m.glareRatio > GLARE_RATIO) return pick('GLARE');

    /* Skip focus/jitter verdicts until enough frames exist to compare against,
       otherwise the very first frames always read as "blurry". */
    if (baseline && baseline.samples >= FOCUS_WARMUP_SAMPLES && baseline.focusRef > 0) {
        if (m.sharpness < baseline.focusRef * FOCUS_RATIO) return pick('BLURRY');
    }

    return pick('READY');
}

/**
 * Detect hand shake from the spread of recent sharpness values.
 *
 * A still camera holds a tight distribution. Hand movement makes consecutive
 * frames alternate between sharp and soft, which inflates the spread well above
 * the noise floor even when the *average* focus is acceptable.
 *
 * @param {number[]} history recent sharpness values, oldest first
 * @returns {boolean}
 */
export function isShaking(history) {
    if (!history || history.length < SHAKE_WINDOW) return false;

    const window = history.slice(-SHAKE_WINDOW);
    const mean = window.reduce((a, b) => a + b, 0) / window.length;
    if (mean <= 0) return false;

    const variance = window.reduce((a, b) => a + (b - mean) ** 2, 0) / window.length;

    return Math.sqrt(variance) / mean > SHAKE_RATIO;
}

/* ──────────────────────────────────────────────────────────────────────────
 * Voice guidance (Web Speech API)
 *
 * Uses the platform's own synthesiser: no key, no network, no per-word latency,
 * and it respects the OS voice the user already chose. Indonesian and English
 * catalogues are both first-class, and the active language follows the page.
 * ───────────────────────────────────────────────────────────────────────── */

export class VoiceGuide {
    /**
     * @param {{lang?: string, rate?: number, repeatCooldownMs?: number,
     *          minGapMs?: number}} [opts]
     */
    constructor(opts = {}) {
        this.lang = (opts.lang || 'id').toLowerCase();
        this.rate = opts.rate ?? 1.05;
        /* Same line is not repeated inside this window — a framing problem
           persists for seconds, and saying it every 300 ms is unbearable. */
        this.repeatCooldownMs = opts.repeatCooldownMs ?? 7000;
        /* Absolute floor between any two utterances. */
        this.minGapMs = opts.minGapMs ?? 2500;

        this.enabled = true;
        this.lastSpokenKey = null;
        this.lastSpokenAt = 0;
        this.lastAnyAt = 0;
        this.pending = null;
        this.voice = null;
        this.voicesBound = false;
    }

    get supported() {
        return typeof window !== 'undefined' && 'speechSynthesis' in window;
    }

    /** True when the active language is Indonesian (or an Indonesian locale). */
    get isIndonesian() {
        return this.lang.startsWith('id');
    }

    /** The localised line for an instruction entry. */
    textFor(entry) {
        return this.isIndonesian ? entry.id : entry.en;
    }

    /**
     * Chrome populates `getVoices()` asynchronously; without this listener the
     * first few utterances land on the default voice regardless of language.
     */
    bindVoices() {
        if (this.voicesBound || !this.supported) return;
        this.voicesBound = true;

        const pick = () => this.pickVoice();
        pick();
        window.speechSynthesis.addEventListener?.('voiceschanged', pick);
    }

    pickVoice() {
        if (!this.supported) return null;

        const voices = window.speechSynthesis.getVoices() || [];
        if (!voices.length) return null;

        const want = this.isIndonesian ? 'id' : 'en';
        const prefix = want === 'id' ? 'id-' : 'en-';

        this.voice =
            voices.find((v) => v.lang && v.lang.toLowerCase().startsWith(prefix)) ||
            voices.find((v) => v.lang && v.lang.toLowerCase().startsWith(want)) ||
            voices.find((v) => v.default) ||
            voices[0];

        return this.voice;
    }

    /**
     * Speak an instruction, subject to cooldown and priority.
     *
     * @param {{key: string, tone: string, text: {id: string, en: string}}} instruction
     * @param {{force?: boolean}} [opts] force bypasses the repeat cooldown
     */
    speak(instruction, { force = false } = {}) {
        if (!this.enabled || !this.supported || !instruction) return false;

        const now = Date.now();

        if (!force && instruction.key === this.lastSpokenKey
            && now - this.lastSpokenAt < this.repeatCooldownMs) {
            return false;
        }

        /* A critical line outranks a queued softer one. */
        if (this.pending && instruction.tone !== 'critical'
            && this.pending.tone === 'critical') {
            return false;
        }

        if (instruction.tone === 'critical') {
            this.cancel();
        }

        this.pending = { instruction, at: now };
        this.flush();

        return true;
    }

    flush() {
        if (!this.pending || !this.supported) return;

        const { instruction, at } = this.pending;
        const now = Date.now();

        if (now - this.lastAnyAt < this.minGapMs && instruction.tone !== 'critical') {
            /* Come back for it once the gap has elapsed, unless something more
               important arrives in the meantime. */
            setTimeout(() => this.flush(), this.minGapMs - (now - this.lastAnyAt) + 40);
            return;
        }

        this.pending = null;
        this.lastSpokenKey = instruction.key;
        this.lastSpokenAt = now;
        this.lastAnyAt = now;

        const utter = new SpeechSynthesisUtterance(this.textFor(instruction.text));
        if (this.voice) utter.voice = this.voice;
        utter.lang = this.voice?.lang || (this.isIndonesian ? 'id-ID' : 'en-US');
        utter.rate = this.rate;
        utter.pitch = 1;

        try {
            window.speechSynthesis.speak(utter);
        } catch {
            /* A synthesiser that refuses (headless, no voices) must not break
               the scan. Silence is the correct fallback. */
        }
    }

    cancel() {
        this.pending = null;
        if (!this.supported) return;
        try {
            window.speechSynthesis.cancel();
        } catch {
            /* ignore */
        }
    }

    setEnabled(on) {
        this.enabled = !!on;
        if (!this.enabled) this.cancel();
    }
}

/* ──────────────────────────────────────────────────────────────────────────
 * Overlay
 *
 * Self-contained CSS, injected once. No Tailwind (see file header) and no
 * build step, so the overlay cannot break because a stylesheet was not rebuilt.
 * ───────────────────────────────────────────────────────────────────────── */

const OVERLAY_CSS = `
.aisc{position:absolute;inset:0;pointer-events:none;z-index:5;font-family:inherit}
.aisc__bar{position:absolute;left:50%;transform:translateX(-50%);bottom:12px;display:flex;align-items:center;gap:10px;max-width:92%;
background:rgba(9,9,11,.72);border-radius:9999px;padding:8px 16px;color:#fff;font-size:13px;line-height:1.25;
backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);box-shadow:0 4px 16px rgba(0,0,0,.35)}
.aisc__dot{width:9px;height:9px;border-radius:9999px;flex:0 0 auto;background:#a1a1aa;transition:background .2s}
.aisc__text{font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.aisc--ok .aisc__dot{background:#34d399}
.aisc--warn .aisc__dot{background:#fbbf24}
.aisc--bad .aisc__dot{background:#f87171}
.aisc__meter{position:absolute;left:50%;transform:translateX(-50%);bottom:52px;width:44%;height:4px;border-radius:9999px;
background:rgba(255,255,255,.25);overflow:hidden}
.aisc__fill{height:100%;width:0%;border-radius:9999px;background:#34d399;transition:width .18s linear,background .2s}
.aisc__ring{position:absolute;inset:0;box-shadow:inset 0 0 0 0 rgba(52,211,153,0);transition:box-shadow .25s;pointer-events:none}
.aisc--ok .aisc__ring{box-shadow:inset 0 0 0 3px rgba(52,211,153,.95)}
@media (prefers-reduced-motion: reduce){.aisc__fill,.aisc__ring,.aisc__dot{transition:none}}
`;

/* ──────────────────────────────────────────────────────────────────────────
 * Coach
 *
 * Deliberately renders NO buttons. Guidance switches itself on the moment the
 * camera opens: a user holding a card up to a lens should not also be hunting
 * for a toggle. `setVoice()` stays available for callers that need to mute
 * programmatically, but nothing is put on screen.
 * ───────────────────────────────────────────────────────────────────────── */

export class ScanCoach {
    /**
     * @param {{mount: HTMLElement, mode: 'face'|'document', lang?: string,
     *          video?: () => HTMLVideoElement, onReady?: (c: ScanCoach) => void}} opts
     */
    constructor(opts) {
        this.mount = opts.mount;
        this.mode = opts.mode === 'face' ? 'face' : 'document';
        /* identity_type drives the guide shape and every spoken line, so the
           coach measures the card the user is actually holding. */
        this.docType = resolveDocType(opts.docType);
        this.getVideo = opts.video || (() => null);
        this.onReady = opts.onReady || null;

        this.voice = new VoiceGuide({ lang: opts.lang });

        this.buffer = null;
        this.ctx = null;
        this.width = ANALYSIS_W;
        this.height = ANALYSIS_H;

        this.baseline = { focusRef: 0, samples: 0 };
        this.history = [];

        this.lastInstruction = null;
        this.rafId = null;
        this.running = false;
        this.lastFrameAt = 0;
        this.frameCostMs = 0;

        /* Automatic capture bookkeeping. */
        this.autoCapture = opts.autoCapture !== false;
        this.onCapture = opts.onCapture || null;
        this.holdFrames = opts.holdFrames ?? HOLD_FRAMES;
        this.maxAttempts = opts.maxAttempts ?? MAX_AUTO_ATTEMPTS;
        this.goodStreak = 0;
        this.attempts = 0;
        this.firedThisCycle = false;

        this.ui = null;
    }

    /**
     * Begin counting toward an automatic capture.
     *
     * Called by the host whenever a fresh attempt should be allowed: opening
     * the modal, or after a server verdict asked for a retake. This is what
     * stops one good frame from firing the shutter again and again.
     */
    armAutoCapture() {
        this.goodStreak = 0;
        this.firedThisCycle = false;
    }

    /** Record that a capture actually happened (successful or not). */
    noteCapture() {
        this.attempts += 1;
        this.goodStreak = 0;
        this.firedThisCycle = true;
    }

    get attemptsLeft() {
        return Math.max(0, this.maxAttempts - this.attempts);
    }

    /* ── lifecycle ── */

    /** Build the overlay and begin sampling. Safe to call twice. */
    start() {
        if (this.running) return;
        this.running = true;

        if (!this.ctx) {
            const canvas = document.createElement('canvas');
            canvas.width = this.width;
            canvas.height = this.height;
            /* willReadFrequently keeps the buffer on the CPU, which is what we
               want: we read every pixel each frame and never upload to the GPU. */
            this.ctx = canvas.getContext('2d', { willReadFrequently: true });
            this.buffer = canvas;
        }

        this.injectCss();
        this.buildUi();

        this.voice.bindVoices();
        this.loop();
    }

    stop() {
        this.running = false;
        if (this.rafId) cancelAnimationFrame(this.rafId);
        this.rafId = null;
        this.voice.cancel();
        this.removeUi();
    }

    /**
     * Turn the spoken guidance on or off from code.
     *
     * Not exposed as a button on purpose — guidance is automatic. This exists
     * so a host page can honour a user-level "no voice" preference.
     *
     * @param {boolean} on
     */
    setVoice(on) {
        this.voice.setEnabled(on);
    }

    /* ── overlay ── */

    injectCss() {
        if (document.getElementById('aisc-style')) return;
        const style = document.createElement('style');
        style.id = 'aisc-style';
        style.textContent = OVERLAY_CSS;
        document.head.appendChild(style);
    }

    buildUi() {
        if (this.ui || !this.mount) return;

        const root = document.createElement('div');
        root.className = 'aisc';

        const ring = document.createElement('div');
        ring.className = 'aisc__ring';

        const meter = document.createElement('div');
        meter.className = 'aisc__meter';
        const fill = document.createElement('div');
        fill.className = 'aisc__fill';
        meter.appendChild(fill);

        const bar = document.createElement('div');
        bar.className = 'aisc__bar';
        const dot = document.createElement('span');
        dot.className = 'aisc__dot';
        const text = document.createElement('span');
        text.className = 'aisc__text';
        bar.append(dot, text);

        root.append(ring, meter, bar);
        this.mount.appendChild(root);

        this.ui = { root, ring, meter, fill, bar, dot, text };

        /* The status line is advisory, so mirror it into the live region for
           screen readers. It is aria-hidden visually only via the existing
           overlay, so no duplicate announcement is produced. */
        text.setAttribute('role', 'status');
        text.setAttribute('aria-live', 'polite');

        if (this.onReady) this.onReady(this);
    }

    removeUi() {
        this.ui?.root.remove();
        this.ui = null;
    }

    render(instruction, score) {
        if (!this.ui) return;

        const tone = instruction.tone === 'info' ? 'ok' : instruction.tone === 'warn' ? 'warn' : 'bad';
        this.ui.root.className = `aisc aisc--${tone}`;
        this.ui.text.textContent = this.voice.textFor(instruction.text);
        this.ui.fill.style.width = `${Math.round(score * 100)}%`;
        this.ui.fill.style.background = tone === 'ok' ? '#34d399' : tone === 'warn' ? '#fbbf24' : '#f87171';
    }

    /* ── loop ── */

    loop() {
        if (!this.running) return;
        this.rafId = requestAnimationFrame(() => this.loop());

        const now = performance.now();

        /* ~12 fps is plenty: a person cannot act faster than that, and every
           extra frame costs a full-frame pixel pass. */
        if (now - this.lastFrameAt < 80) return;
        this.lastFrameAt = now;

        const video = this.getVideo();
        if (!video || video.readyState < 2 || !video.videoWidth) return;

        const started = performance.now();

        try {
            this.ctx.drawImage(video, 0, 0, this.width, this.height);
            const imageData = this.ctx.getImageData(0, 0, this.width, this.height);
            const metrics = analyzePixels(imageData, this.mode, this.docType);

            if (!metrics.ok) return;

            this.updateBaseline(metrics.sharpness);

            const instruction = decide(metrics, this.baseline);
            const score = this.qualityScore(metrics, this.baseline);
            const shaking = isShaking(this.history);

            this.render(instruction, score);

            /* Shake outranks a green light: if the frame is still moving, telling
               the user the shot is ready would be a lie, and auto-capturing on
               it would defeat the entire purpose. */
            const effective = shaking && instruction.key === 'READY'
                ? { key: 'SHAKING', tone: CATALOG.SHAKING.tone, text: CATALOG.SHAKING }
                : instruction;

            if (effective.key !== this.lastInstruction?.key) {
                this.lastInstruction = effective;
                this.voice.speak(effective);
            }

            this.maybeAutoCapture(effective);

            this.frameCostMs = performance.now() - started;
        } catch {
            /* A tainted or zero-size canvas throws. Never let analysis kill the
               camera: the modal stays usable without guidance. */
        }
    }

    /**
     * Fire an automatic capture once the frame has been good for a sustained
     * stretch.
     *
     * Deliberately silent about failures: if `onCapture` is missing there is
     * nothing useful to say, and shouting at a user who cannot act is worse
     * than staying quiet.
     */
    maybeAutoCapture(instruction) {
        if (!this.autoCapture || !this.onCapture || this.firedThisCycle) return;

        if (instruction.key !== 'READY') {
            /* Any problem resets the streak, so the capture always fires on a
               sustained clean run rather than on scattered good frames. */
            this.goodStreak = 0;

            return;
        }

        if (this.attemptsLeft <= 0) return;

        this.goodStreak += 1;

        if (this.goodStreak < this.holdFrames) return;

        const capturing = {
            key: 'CAPTURING',
            tone: CATALOG.CAPTURING.tone,
            text: CATALOG.CAPTURING,
        };

        this.lastInstruction = capturing;
        this.render(capturing, 1);
        this.voice.speak(capturing, { force: true });

        this.noteCapture();

        try {
            this.onCapture();
        } catch {
            /* A throwing handler must not take the camera loop down with it. */
        }
    }

    /**
     * Speak a server verdict and prepare for another automatic attempt.
     *
     * `reason` is NOT the diagnostic for /api/ktp/verify any more: AI Core
     * always reports the document's official name there ("Kartu Tanda
     * Penduduk", "Surat Izin Mengemudi", ...). The failure code lives in
     * `reason_code`, with `blocking_issue` as the exhaustive fallback. The
     * face endpoint keeps putting its code in `reason`.
     *
     * @param {{verified: boolean, reason?: string, reason_code?: string,
     *          blocking_issue?: string[]|null}} result
     * @returns {string} the catalogue key that was spoken
     */
    reportVerdict(result) {
        const verified = !!(result && result.verified);
        const diagnostic = verdictReason(result);

        const key = this.verdictKey(diagnostic, verified);
        const entry = { key, tone: CATALOG[key].tone, text: CATALOG[key] };

        this.lastInstruction = entry;

        /* Force it: these lines must not be swallowed by the repeat cooldown,
           otherwise the user hears nothing after a rejection. */
        this.voice.speak(entry, { force: true });

        const terminal = TERMINAL_VERDICTS.includes(key);

        /* Re-arm only for problems a different photo could actually fix. An
           offline service or a missing reference will fail identically no
           matter how many more times we shoot. */
        if (!verified && !terminal && this.attemptsLeft > 0) {
            this.armAutoCapture();
        }

        return key;
    }

    /**
     * Translate an AI Core diagnostic into a catalogue key.
     *
     * @param {string} reason upper-cased diagnostic code. See verdictReason():
     *        for /api/ktp/verify this is `reason_code`, not `reason`, which
     *        holds a document name there.
     * @param {boolean} verified
     * @returns {string} catalogue key
     */
    verdictKey(reason, verified) {
        if (verified) return 'VERIFIED_OK';

        if (reason === 'OK') return 'DOC_OK';

        /* Service problems: never worth retrying. */
        if (reason === 'AI_UNAVAILABLE' || reason === 'UNAVAILABLE' || reason === 'OFFLINE') {
            return 'VERDICT_UNAVAILABLE';
        }

        if (reason === 'NO_KTP' || reason === 'NO_FACE_IN_REFERENCE' || reason === 'NEEDS_REFERENCE') {
            return 'VERDICT_NO_KTP';
        }

        /* Document verdicts (from /api/ktp/verify). */
        if (reason === 'WRONG_ASPECT') return 'DOC_WRONG_ASPECT';
        if (reason === 'NO_FACE') return 'DOC_NO_FACE_ON_CARD';
        if (reason.includes('NUMBER')) return 'DOC_NO_NUMBER';

        /* Face verdicts (from /api/face/verify). */
        if (reason === 'NO_FACE_IN_SELFIE' || reason === 'FACE_NOT_DETECTED') return 'VERDICT_NO_FACE';
        if (reason === 'MULTIPLE_FACES') return 'VERDICT_MULTIPLE_FACES';
        if (reason === 'FACE_TOO_SMALL') return 'FACE_TOO_FAR';
        if (reason === 'EYES_CLOSED') return 'VERDICT_EYES_CLOSED';

        /* Shared across both endpoints. Order matters: BLURRY is checked before
           MATCH because "NO_MATCH" contains no other token, but a future
           "BLURRY_NO_MATCH" style string must not be read as a face mismatch. */
        if (reason.includes('BLUR')) return 'VERDICT_BLURRY';
        if (reason.includes('MATCH') || reason.includes('SIMILARITY') || reason.includes('DIFFERENT')) {
            return 'VERDICT_NOT_MATCH';
        }

        /* No code at all, yet the card itself was read: the document is fine,
           something else rejected it. Say so instead of nagging for a retake. */
        if (reason === '' || DOC_REASON_NAMES.has(reason)) return 'DOC_UNKNOWN_ISSUE';

        return 'VERDICT_RETRY';
    }

    /**
     * Speak the opening line, naming the exact document being scanned.
     *
     * @param {string} type identity_type (ktp | npwp | sim | passport)
     */
    announceDocument(type) {
        const key = resolveDocType(type);
        const lines = DOC_TYPE_LINES[key] || DOC_TYPE_DEFAULT;

        const entry = {
            key: 'DOC_ANNOUNCE_' + key,
            tone: 'info',
            text: lines,
        };

        this.lastInstruction = entry;
        this.render(entry, 0);
        this.voice.speak(entry, { force: true });
    }

    /** Track the best focus seen this session so blur is judged relatively. */
    updateBaseline(sharpness) {
        this.baseline.samples += 1;
        this.baseline.focusRef = Math.max(this.baseline.focusRef, sharpness);
        this.history.push(sharpness);
        if (this.history.length > SHAKE_WINDOW * 2) this.history.shift();
    }

    /**
     * Single 0..1 readiness figure for the meter. Blends the things that block
     * a usable photo rather than trusting any single number.
     */
    qualityScore(m, baseline) {
        const exposure = m.brightness < DARK_LUMA
            ? m.brightness / DARK_LUMA
            : 1;

        /* Glare and clipping both cost readiness, so both count here. */
        const glare = Math.max(0, 1 - m.glareRatio / BLOWN_RATIO);

        let focus = 1;
        if (baseline.samples >= FOCUS_WARMUP_SAMPLES && baseline.focusRef > 0) {
            focus = Math.min(1, m.sharpness / (baseline.focusRef * FOCUS_RATIO));
        }

        const framing = m.mode === 'face'
            ? Math.min(1, m.faceRatio / 0.25)
            : Math.min(1, m.edgeDensity / 0.08);

        return Math.max(0, Math.min(1,
            exposure * 0.3 + glare * 0.2 + focus * 0.3 + framing * 0.2));
    }
}

/* ──────────────────────────────────────────────────────────────────────────
 * Global handle
 *
 * Blade modals call `window.AIScanCoach.create(...)` from Alpine, exactly like
 * `window.ScannerUI`. Exposed unconditionally on import so a Blade-only
 * `@vite(...)` include is enough — no dependency on a platform entry file.
 * ───────────────────────────────────────────────────────────────────────── */

if (typeof window !== 'undefined') {
    window.AIScanCoach = {
        create: (opts) => new ScanCoach(opts),
        ScanCoach,
        VoiceGuide,
        analyzePixels,
        decide,
        isShaking,
        guideBox,
        docGuideFor,
        docTypeLine,
        docTypeName,
        resolveDocType,
        GUIDE,
        DOC_TYPES,
        CATALOG,
    };
}

export default {
    ScanCoach,
    VoiceGuide,
    analyzePixels,
    decide,
    isShaking,
    docGuideFor,
    docTypeLine,
    docTypeName,
    resolveDocType,
};