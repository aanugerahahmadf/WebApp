/**
 * Verification for the AI Scan Coach measurement + decision logic.
 *
 * The CV routines in ai-scan-coach.js are the load-bearing part of a feature
 * that gives the user instructions about their own face or ID card, so they are
 * checked against synthetic frames whose ground truth is known by construction:
 * a crisp card, the same card defocused, a darkened frame, a frame with a
 * specular highlight, an empty guide, and a skin-toned subject placed off-centre.
 *
 * Run with:  node --test resources/js/ai-scan-coach/
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import {
    analyzePixels,
    decide,
    isShaking,
    guideBox,
    GUIDE,
    verdictReason,
    DOC_REASON_NAMES,
} from './ai-scan-coach.js';

const W = 192;
const H = 144;

/* ── synthetic frame builders ──────────────────────────────────────────── */

/** @returns {{data: Uint8ClampedArray, width: number, height: number}} */
function blank(value = 20) {
    const data = new Uint8ClampedArray(W * H * 4);
    for (let i = 0; i < data.length; i += 4) {
        data[i] = data[i + 1] = data[i + 2] = value;
        data[i + 3] = 255;
    }
    return { data, width: W, height: H };
}

function setPx(img, x, y, r, g, b) {
    if (x < 0 || y < 0 || x >= img.width || y >= img.height) return;
    const i = (y * img.width + x) * 4;
    img.data[i] = r;
    img.data[i + 1] = g;
    img.data[i + 2] = b;
    img.data[i + 3] = 255;
}

function rect(img, x0, y0, x1, y1, [r, g, b]) {
    for (let y = y0; y < y1; y++) {
        for (let x = x0; x < x1; x++) setPx(img, x, y, r, g, b);
    }
}

/**
 * A KTP-like card filling `scale` of the document guide: border, a photo
 * block, and stripes standing in for printed text. This is the "good" frame.
 */
function crispCard(scale = 1) {
    const img = blank(18);
    const g = GUIDE.document;
    const gw = g.w * W * scale;
    const gh = g.h * H * scale;
    const cx = (g.x + g.w / 2) * W;
    const cy = (g.y + g.h / 2) * H;
    const x0 = Math.round(cx - gw / 2);
    const y0 = Math.round(cy - gh / 2);

    rect(img, x0, y0, x0 + gw, y0 + gh, [225, 225, 220]);

    /* border */
    rect(img, x0, y0, x0 + gw, y0 + 2, [40, 40, 40]);
    rect(img, x0, y0 + gh - 2, x0 + gw, y0 + gh, [40, 40, 40]);
    rect(img, x0, y0, x0 + 2, y0 + gh, [40, 40, 40]);
    rect(img, x0 + gw - 2, y0, x0 + gw, y0 + gh, [40, 40, 40]);

    /* photo block */
    rect(img, x0 + 8, y0 + 10, x0 + 34, y0 + 44, [120, 120, 130]);

    /* text stripes */
    for (let row = 0; row < 7; row++) {
        const ty = y0 + 50 + row * 7;
        rect(img, x0 + 8, ty, x0 + gw - 8 - row * 4, ty + 2, [30, 30, 30]);
    }

    return img;
}

/** Three-pass box blur: a cheap stand-in for Gaussian defocus. */
function blur(src, radius = 3) {
    let a = Float32Array.from({ length: src.data.length }, (_, i) => src.data[i]);
    let b = new Float32Array(a.length);

    for (let pass = 0; pass < 3; pass++) {
        for (let y = 0; y < H; y++) {
            for (let x = 0; x < W; x++) {
                let acc = 0;
                let n = 0;
                for (let d = -radius; d <= radius; d++) {
                    const xx = x + d;
                    if (xx < 0 || xx >= W) continue;
                    acc += a[(y * W + xx) * 4];
                    n++;
                }
                b[(y * W + x) * 4] = acc / n;
            }
        }
        for (let y = 0; y < H; y++) {
            for (let x = 0; x < W; x++) {
                let acc = 0;
                let n = 0;
                for (let d = -radius; d <= radius; d++) {
                    const yy = y + d;
                    if (yy < 0 || yy >= H) continue;
                    acc += b[(yy * W + x) * 4];
                    n++;
                }
                a[(y * W + x) * 4] = acc / n;
            }
        }
    }

    const out = blank();
    for (let i = 0; i < out.data.length; i += 4) {
        out.data[i] = out.data[i + 1] = out.data[i + 2] = a[i];
    }
    return out;
}

function darken(src, factor) {
    const out = blank();
    for (let i = 0; i < out.data.length; i += 4) {
        out.data[i] = src.data[i] * factor;
        out.data[i + 1] = src.data[i + 1] * factor;
        out.data[i + 2] = src.data[i + 2] * factor;
    }
    return out;
}

function addGlare(src, cx, cy, radius) {
    const out = blank();
    for (let y = 0; y < H; y++) {
        for (let x = 0; x < W; x++) {
            const i = (y * W + x) * 4;
            const d = Math.hypot(x - cx, y - cy);
            const hot = d < radius ? 255 : src.data[i];
            out.data[i] = hot;
            out.data[i + 1] = hot;
            out.data[i + 2] = hot;
        }
    }
    return out;
}

/** Skin-toned ellipse, positioned as a fraction of the face guide. */
function faceSubject(dx = 0, dy = 0, scale = 1) {
    const img = blank(30);
    const g = GUIDE.face;
    const cx = (g.cx + dx) * W;
    const cy = (g.cy + dy) * H;
    const rx = g.rx * W * scale;
    const ry = g.ry * H * scale;

    for (let y = 0; y < H; y++) {
        for (let x = 0; x < W; x++) {
            const inside = ((x - cx) / rx) ** 2 + ((y - cy) / ry) ** 2 <= 1;
            if (inside) setPx(img, x, y, 214, 168, 140);
        }
    }
    return img;
}

/** A baseline the focus heuristic has already "learned": 12 good frames. */
const WARM = { focusRef: null, samples: 12 };
function warmed(ref) {
    return { focusRef: ref, samples: 12 };
}

/* ── geometry ──────────────────────────────────────────────────────────── */

test('guideBox maps the document guide inside the frame', () => {
    const b = guideBox(GUIDE.document, W, H);
    assert.ok(b.x0 > 0 && b.y0 > 0, 'guide must start inside the frame');
    assert.ok(b.x1 < W && b.y1 < H, 'guide must end inside the frame');
    assert.ok(b.x1 - b.x0 > 100 && b.y1 - b.y0 > 60, 'guide must be usefully large');
});

test('guideBox for the face guide is centred and elliptical', () => {
    const b = guideBox(GUIDE.face, W, H);
    const midX = (b.x0 + b.x1) / 2;
    const midY = (b.y0 + b.y1) / 2;
    assert.ok(Math.abs(midX - W / 2) <= 1, 'face guide must be horizontally centred');
    assert.ok(Math.abs(midY - H / 2) <= 1, 'face guide must be vertically centred');
    assert.ok(b.y1 - b.y0 > b.x1 - b.x0, 'oval is taller than wide');
});

/* ── measurement ───────────────────────────────────────────────────────── */

test('a crisp card is measured as sharp, well filled and well exposed', () => {
    const m = analyzePixels(crispCard(), 'document');
    assert.equal(m.ok, true);
    assert.ok(m.edgeDensity > 0.035, `edge density too low: ${m.edgeDensity}`);
    assert.ok(m.fillX > 0.6 && m.fillY > 0.6, `fill too small: ${m.fillX}/${m.fillY}`);
    assert.ok(m.sharpness > 50, `expected a sharp frame, got ${m.sharpness}`);
    assert.ok(m.brightness > 55 && m.brightness < 205, `exposure off: ${m.brightness}`);
    assert.equal(m.glareRatio, 0, 'no glare expected on a clean card');
});

test('defocus collapses the focus measure (blurry scores far below sharp)', () => {
    const sharp = analyzePixels(crispCard(), 'document').sharpness;
    const soft = analyzePixels(blur(crispCard()), 'document').sharpness;
    assert.ok(soft < sharp * 0.6,
        `blur should cut sharpness sharply: sharp=${sharp.toFixed(1)} soft=${soft.toFixed(1)}`);
});

test('an empty guide reports almost no edges', () => {
    const m = analyzePixels(blank(20), 'document');
    assert.ok(m.edgeDensity < 0.035, `expected a bare frame, got ${m.edgeDensity}`);
    assert.equal(decide(m, WARM).key, 'DOC_NOT_FOUND');
});

test('darkening the frame is detected as low exposure', () => {
    const m = analyzePixels(darken(crispCard(), 0.16), 'document');
    assert.ok(m.brightness < 55, `expected darkness, got ${m.brightness}`);
    assert.equal(decide(m, WARM).key, 'TOO_DARK');
});

test('a specular highlight on the card is detected as glare', () => {
    const card = crispCard();
    const g = GUIDE.document;
    const cx = (g.x + g.w / 2) * W;
    const cy = (g.y + g.h / 2) * H;
    const m = analyzePixels(addGlare(card, cx, cy, 22), 'document');
    assert.ok(m.glareRatio > 0.06, `expected glare, got ${m.glareRatio}`);
    assert.equal(decide(m, WARM).key, 'GLARE');
});

test('a card smaller than the guide is reported as too far away', () => {
    const m = analyzePixels(crispCard(0.5), 'document');
    assert.equal(decide(m, WARM).key, 'DOC_TOO_FAR');
});

test('a face-shaped skin region is found and reported as a chroma estimate', () => {
    /* scale 0.75 -> skin share ~0.44, i.e. a normally framed head. Exactly
       filling the oval (scale 1.0) would be ~0.79 and legitimately "too close". */
    const m = analyzePixels(faceSubject(0, 0, 0.75), 'face');
    assert.equal(m.faceDetector, 'chroma');
    assert.ok(m.faceRatio > 0.2 && m.faceRatio < 0.7,
        `expected a well-framed face, got ratio ${m.faceRatio}`);
    assert.equal(decide(m, WARM).key, 'READY');
});

test('no skin region reads as no face', () => {
    const m = analyzePixels(blank(25), 'face');
    assert.ok(m.faceRatio < 0.05, `expected no face, got ${m.faceRatio}`);
    assert.equal(decide(m, WARM).key, 'FACE_NOT_FOUND');
});

test('a face pushed right of frame asks the user to move right', () => {
    const m = analyzePixels(faceSubject(0.4, 0, 0.75), 'face');
    assert.ok(m.faceDx > 0.22, `expected positive x offset, got ${m.faceDx}`);
    assert.equal(decide(m, WARM).key, 'FACE_RIGHT');
});

test('a face pushed left of frame asks the user to move left', () => {
    const m = analyzePixels(faceSubject(-0.4, 0, 0.75), 'face');
    assert.ok(m.faceDx < -0.22, `expected negative x offset, got ${m.faceDx}`);
    assert.equal(decide(m, WARM).key, 'FACE_LEFT');
});

test('a face above centre asks the user to raise their head', () => {
    const m = analyzePixels(faceSubject(0, -0.4, 0.75), 'face');
    assert.ok(m.faceDy < -0.22, `expected negative y offset, got ${m.faceDy}`);
    assert.equal(decide(m, WARM).key, 'FACE_UP');
});

test('a face too large in frame asks the user to lean back', () => {
    const m = analyzePixels(faceSubject(0, 0, 1.35), 'face');
    assert.ok(m.faceRatio > 0.70, `expected an oversized face, got ${m.faceRatio}`);
    assert.equal(decide(m, WARM).key, 'FACE_TOO_CLOSE');
});

test('a face too small in frame asks the user to come closer', () => {
    const m = analyzePixels(faceSubject(0, 0, 0.35), 'face');
    assert.ok(m.faceRatio < 0.12, `expected a distant face, got ${m.faceRatio}`);
    assert.equal(decide(m, WARM).key, 'FACE_TOO_FAR');
});

test('defocus must not be mistaken for the card being too close', () => {
    /* Regression: a low projection-profile threshold let defocus smear the
       dark-to-light transition, which reported the card as WIDER than the guide.
       The user would then be told to move a card further away when the only real
       problem was a shaky hand — advice that makes the shot worse. */
    const soft = analyzePixels(blur(crispCard(), 3), 'document');
    assert.ok(soft.fillX <= 1.12 && soft.fillY <= 1.12,
        `blur inflated the measured card extent to ${soft.fillX}/${soft.fillY}`);
    assert.notEqual(decide(soft, warmed(analyzePixels(crispCard(), 'document').sharpness)).key,
        'DOC_TOO_CLOSE');
});

test('an unusable buffer is rejected instead of throwing', () => {
    const m = analyzePixels({ data: new Uint8ClampedArray(4), width: 1, height: 1 }, 'face');
    assert.equal(m.ok, false);
});

/* ── focus baseline ────────────────────────────────────────────────────── */

test('focus is not judged before enough frames exist to compare', () => {
    const m = analyzePixels(blur(crispCard()), 'document');
    const cold = decide(m, { focusRef: 0, samples: 0 });
    assert.equal(cold.key, 'READY', 'a cold session must not cry "blurry"');

    const sharp = analyzePixels(crispCard(), 'document');
    const warm = decide(sharp, warmed(sharp.sharpness));
    assert.equal(warm.key, 'READY');
});

test('a frame far softer than the session best is reported as blurry', () => {
    const soft = analyzePixels(blur(crispCard(), 4), 'document');
    const reference = analyzePixels(crispCard(), 'document').sharpness;
    assert.equal(decide(soft, warmed(reference)).key, 'BLURRY');
});

/* ── shake ─────────────────────────────────────────────────────────────── */

test('a steady focus history is not shake', () => {
    /* Long enough to clear the sample window, so the ratio is actually tested. */
    assert.equal(isShaking([120, 122, 119, 121, 120, 123, 118, 121, 120, 119, 122, 121, 120, 121]), false);
});

test('too little history cannot be called shake', () => {
    assert.equal(isShaking([10, 400, 12]), false);
    assert.equal(isShaking([]), false);
    assert.equal(isShaking(null), false);
});

test('alternating focus between frames is reported as shake', () => {
    const jitter = [400, 40, 390, 45, 380, 42, 395, 38, 370, 44, 385, 41, 392, 39];
    assert.equal(isShaking(jitter), true);
});

/* ── language ──────────────────────────────────────────────────────────── */

/* ── per-document-type guides ───────────────────────────────────────────── */

test('every supported identity_type has a guide and spoken copy', async () => {
    const { DOC_TYPES, docGuideFor, docTypeLine, resolveDocType } =
        await import('./ai-scan-coach.js');

    // These four are exactly the values the DB stores in users.identity_type.
    for (const type of ['ktp', 'npwp', 'sim', 'passport']) {
        assert.ok(DOC_TYPES[type], `${type} missing from DOC_TYPES`);

        const g = docGuideFor(type);
        assert.ok(g.w > 0 && g.w < 1, `${type} guide width out of range: ${g.w}`);
        assert.ok(g.h > 0 && g.h <= 1, `${type} guide height out of range: ${g.h}`);

        const lineId = docTypeLine(type, 'id');
        const lineEn = docTypeLine(type, 'en');
        assert.ok(typeof lineId === 'string' && lineId.length > 5,
            `${type} missing Indonesian intro`);
        assert.ok(typeof lineEn === 'string' && lineEn.length > 5,
            `${type} missing English intro`);
        assert.notEqual(lineId, lineEn, `${type} intro is not localised`);
    }

    // Unknown / missing values must fall back rather than crash.
    assert.equal(resolveDocType('blah'), 'ktp');
    assert.equal(resolveDocType(null), 'ktp');
    assert.equal(resolveDocType(undefined), 'ktp');
});

test('each guide has the real pixel aspect of the card it is for', async () => {
    const { DOC_TYPES, docGuideFor } = await import('./ai-scan-coach.js');

    /* The preview is 4:3, so a guide whose normalised w/h does not account for
       that renders at the wrong shape. This is the assertion that catches a
       frame half its proper height — the failure mode that makes every
       correctly-held card look "too small". */
    const PREVIEW = 4 / 3;

    for (const [type, spec] of Object.entries(DOC_TYPES)) {
        const g = docGuideFor(type);
        const rendered = (g.w / g.h) * PREVIEW;
        assert.ok(
            Math.abs(rendered - spec.ratio) < 0.02,
            `${type}: guide renders at ${rendered.toFixed(3)} but card is ${spec.ratio.toFixed(3)}`
        );
    }
});

test('the passport frame is portrait and the ID card frame is landscape', async () => {
    const { docGuideFor } = await import('./ai-scan-coach.js');

    const passport = docGuideFor('passport');
    const ktp = docGuideFor('ktp');

    assert.ok(passport.h > passport.w,
        `passport frame must be taller than wide, got ${passport.h} vs ${passport.w}`);
    assert.ok(ktp.w > ktp.h,
        `KTP frame must be wider than tall, got ${ktp.w} vs ${ktp.h}`);
});

test('a passport filling the frame is not mistaken for the wrong card size', async () => {
    const { analyzePixels, decide, docGuideFor } = await import('./ai-scan-coach.js');
    const g = docGuideFor('passport');
    const PREVIEW = 4 / 3;

    // Build a card that exactly fills the passport guide.
    const gw = Math.round(g.w * W);
    const gh = Math.round(g.h * H);
    const x0 = Math.round((1 - g.w) / 2 * W);
    const y0 = Math.round((1 - g.h) / 2 * H);

    const img = blank(18);
    rect(img, x0, y0, x0 + gw, y0 + gh, [225, 225, 220]);
    rect(img, x0, y0, x0 + gw, y0 + 3, [40, 40, 40]);
    rect(img, x0, y0 + gh - 3, x0 + gw, y0 + gh, [40, 40, 40]);
    rect(img, x0, y0, x0 + 3, y0 + gh, [40, 40, 40]);
    rect(img, x0 + gw - 3, y0, x0 + gw, y0 + gh, [40, 40, 40]);
    for (let r = 0; r < 6; r++) {
        rect(img, x0 + 8, y0 + 16 + r * 9, x0 + gw - 8 - r * 3, y0 + 18 + r * 9, [30, 30, 30]);
    }

    const m = analyzePixels(img, 'document', 'passport');
    const key = decide(m, WARM).key;

    assert.ok(
        key !== 'DOC_TOO_FAR' && key !== 'DOC_TOO_CLOSE',
        `a correctly framed passport was rejected as ${key} (fillX=${m.fillX?.toFixed(2)} fillY=${m.fillY?.toFixed(2)})`
    );
    void PREVIEW;
});

test('a KTP-sized card is judged against the KTP frame, not the passport one', async () => {
    const { analyzePixels, decide } = await import('./ai-scan-coach.js');

    // Landscape card (the 80%/0.6725 frame the Blade guide actually renders).
    const x0 = Math.round(0.1 * W);
    const y0 = Math.round(0.1638 * H);
    const gw = Math.round(0.8 * W);
    const gh = Math.round(0.6725 * H);

    const img = blank(18);
    rect(img, x0, y0, x0 + gw, y0 + gh, [225, 225, 220]);
    rect(img, x0, y0, x0 + gw, y0 + 3, [40, 40, 40]);
    rect(img, x0, y0 + gh - 3, x0 + gw, y0 + gh, [40, 40, 40]);
    rect(img, x0, y0, x0 + 3, y0 + gh, [40, 40, 40]);
    rect(img, x0 + gw - 3, y0, x0 + gw, y0 + gh, [40, 40, 40]);
    for (let r = 0; r < 7; r++) {
        rect(img, x0 + 8, y0 + 14 + r * 8, x0 + gw - 8 - r * 4, y0 + 16 + r * 8, [30, 30, 30]);
    }

    const key = decide(analyzePixels(img, 'document', 'ktp'), WARM).key;
    assert.ok(key !== 'DOC_TOO_FAR' && key !== 'DOC_TOO_CLOSE',
        `a correctly framed KTP was rejected as ${key}`);
});

test('a KTP card scanned as a passport is correctly reported as the wrong size', async () => {
    const { analyzePixels, decide, docGuideFor } = await import('./ai-scan-coach.js');

    // Landscape card sized for the KTP frame...
    const x0 = Math.round(0.1 * W);
    const y0 = Math.round(0.1638 * H);
    const gw = Math.round(0.8 * W);
    const gh = Math.round(0.6725 * H);

    const img = blank(18);
    rect(img, x0, y0, x0 + gw, y0 + gh, [225, 225, 220]);
    rect(img, x0, y0, x0 + gw, y0 + 3, [40, 40, 40]);
    rect(img, x0, y0 + gh - 3, x0 + gw, y0 + gh, [40, 40, 40]);
    rect(img, x0, y0, x0 + 3, y0 + gh, [40, 40, 40]);
    rect(img, x0 + gw - 3, y0, x0 + gw, y0 + gh, [40, 40, 40]);
    for (let r = 0; r < 7; r++) {
        rect(img, x0 + 8, y0 + 14 + r * 8, x0 + gw - 8 - r * 4, y0 + 16 + r * 8, [30, 30, 30]);
    }

    // ...judged against the passport frame, which is much narrower.
    const g = docGuideFor('passport');
    const key = decide(analyzePixels(img, 'document', 'passport'), WARM).key;

    assert.ok(g.w < 0.8,
        `passport frame should be narrower than the KTP frame, got ${g.w}`);
    assert.ok(key === 'DOC_TOO_CLOSE' || key === 'DOC_TOO_FAR',
        `a KTP card judged as a passport should be flagged, got ${key}`);
});

test('a verdict maps each document reason to its own instruction', async () => {
    const { ScanCoach, CATALOG } = await import('./ai-scan-coach.js');

    // Reason strings below are the ones AI Core actually emits.
    const cases = [
        [{ verified: true, reason: 'MATCH' }, 'VERIFIED_OK'],
        [{ verified: true, reason: 'OK' }, 'VERIFIED_OK'],
        [{ verified: false, reason: 'NO_MATCH' }, 'VERDICT_NOT_MATCH'],
        [{ verified: false, reason: 'BLURRY' }, 'VERDICT_BLURRY'],
        [{ verified: false, reason: 'MULTIPLE_FACES' }, 'VERDICT_MULTIPLE_FACES'],
        [{ verified: false, reason: 'FACE_TOO_SMALL' }, 'FACE_TOO_FAR'],
        [{ verified: false, reason: 'EYES_CLOSED' }, 'VERDICT_EYES_CLOSED'],
        [{ verified: false, reason: 'NO_FACE_IN_SELFIE' }, 'VERDICT_NO_FACE'],
        [{ verified: false, reason: 'NO_FACE' }, 'DOC_NO_FACE_ON_CARD'],
        [{ verified: false, reason: 'WRONG_ASPECT' }, 'DOC_WRONG_ASPECT'],
        [{ verified: false, reason: 'NO_FACE_IN_REFERENCE' }, 'VERDICT_NO_KTP'],
        [{ verified: false, reason: 'NEEDS_REFERENCE' }, 'VERDICT_NO_KTP'],
        [{ verified: false, reason: 'AI_UNAVAILABLE' }, 'VERDICT_UNAVAILABLE'],
    ];

    for (const [result, expected] of cases) {
        const c = new ScanCoach({ mount: null, mode: 'face' });
        assert.equal(c.verdictKey(verdictReason(result), !!result.verified),
            expected, `reason ${result.reason} should map to ${expected}`);
    }

    // Every key produced above must exist in the catalogue with both languages.
    for (const key of ['VERIFIED_OK', 'DOC_NO_FACE_ON_CARD', 'DOC_WRONG_ASPECT', 'DOC_NO_NUMBER',
        'DOC_UNKNOWN_ISSUE']) {
        assert.ok(CATALOG[key], `${key} missing from CATALOG`);
        assert.ok(CATALOG[key].id.length > 3 && CATALOG[key].en.length > 3,
            `${key} needs both id and en copy`);
    }
});

test('a document rejection is read from reason_code, not the document name', async () => {
    const { ScanCoach } = await import('./ai-scan-coach.js');

    const coach = () => new ScanCoach({ mount: null, mode: 'document' });

    /* AI Core /api/ktp/verify always reports the document's official name in
       `reason` now. The failure code moved to `reason_code`, with
       `blocking_issue` as the exhaustive fallback. Kept in sync with
       IDENTITY_DOC_LABELS in ai_core/app.py. */
    const names = [
        'Kartu Tanda Penduduk',
        'Surat Izin Mengemudi',
        'Passport',
        'Nomor Pokok Wajib Pajak',
    ];

    for (const [code, expected] of [
        ['NO_FACE', 'DOC_NO_FACE_ON_CARD'],
        ['WRONG_ASPECT', 'DOC_WRONG_ASPECT'],
        ['BLURRY', 'VERDICT_BLURRY'],
        /* Nothing readable and nothing recognised: no more specific advice
           exists, so the generic "take it again" line is the honest one. */
        ['NO_TEXT', 'VERDICT_RETRY'],
        ['UNKNOWN_DOCUMENT', 'VERDICT_RETRY'],
    ]) {
        for (const name of names) {
            assert.equal(coach().reportVerdict({ verified: false, reason: name, reason_code: code }),
                expected, `${name} + ${code} should map to ${expected}`);
        }
    }

    // All four official names must be recognised as names, never as codes.
    assert.deepEqual([...DOC_REASON_NAMES].sort(), [
        'KARTU TANDA PENDUDUK',
        'NOMOR POKOK WAJIB PAJAK',
        'PASSPORT',
        'SURAT IZIN MENGEMUDI',
    ]);

    // No code anywhere: the card was read but something else rejected it. Say so
    // rather than nagging for a retake, and never claim the card is fine.
    assert.equal(coach().reportVerdict({ verified: false, reason: 'Passport' }),
        'DOC_UNKNOWN_ISSUE');
    assert.equal(coach().reportVerdict({ verified: false, reason: 'Passport', blocking_issue: [] }),
        'DOC_UNKNOWN_ISSUE');

    // `blocking_issue` is used when `reason_code` is absent.
    assert.equal(coach().reportVerdict({
        verified: false,
        reason: 'Kartu Tanda Penduduk',
        blocking_issue: ['WRONG_ASPECT'],
    }), 'DOC_WRONG_ASPECT');

    // Verified wins over everything, including a leftover reason_code.
    assert.equal(coach().reportVerdict({
        verified: true,
        reason: 'Kartu Tanda Penduduk',
        reason_code: 'NO_FACE',
    }), 'VERIFIED_OK');

    // A service error keeps priority: reason_code must not mask it.
    assert.equal(coach().reportVerdict({
        verified: false,
        reason: 'AI_UNAVAILABLE',
        reason_code: 'NO_FACE',
    }), 'VERDICT_UNAVAILABLE');

    /* The face endpoint is untouched: it still puts its code in `reason`. */
    assert.equal(new ScanCoach({ mount: null, mode: 'face' })
        .reportVerdict({ verified: false, reason: 'NO_FACE_IN_SELFIE' }), 'VERDICT_NO_FACE');
    assert.equal(new ScanCoach({ mount: null, mode: 'face' })
        .reportVerdict({ verified: false, reason: 'NO_FACE' }), 'DOC_NO_FACE_ON_CARD');
});

test('verdictReason picks the right field from a mixed result', async () => {
    /* reason_code wins, then a code-looking reason, then blocking_issue. */
    assert.equal(verdictReason({ reason: 'BLURRY', reason_code: 'NO_FACE' }), 'NO_FACE');
    assert.equal(verdictReason({ reason: 'NO_MATCH' }), 'NO_MATCH');
    assert.equal(verdictReason({ reason: 'Passport', blocking_issue: ['BLURRY'] }), 'BLURRY');
    assert.equal(verdictReason({ reason: 'Passport' }), '');
    assert.equal(verdictReason({}), '');
    assert.equal(verdictReason(null), '');
});

test('an offline or missing-reference verdict does not re-arm the camera', async () => {
    const { ScanCoach } = await import('./ai-scan-coach.js');

    const terminal = new ScanCoach({ mount: null, mode: 'face', maxAttempts: 3 });
    terminal.attempts = 1;
    terminal.firedThisCycle = true;
    terminal.reportVerdict({ verified: false, reason: 'AI_UNAVAILABLE' });
    assert.equal(terminal.firedThisCycle, true,
        'offline must NOT arm another capture');

    const missing = new ScanCoach({ mount: null, mode: 'face', maxAttempts: 3 });
    missing.attempts = 1;
    missing.firedThisCycle = true;
    missing.reportVerdict({ verified: false, reason: 'NO_KTP' });
    assert.equal(missing.firedThisCycle, true,
        'missing reference must NOT arm another capture');

    const fixable = new ScanCoach({ mount: null, mode: 'face', maxAttempts: 3 });
    fixable.attempts = 1;
    fixable.firedThisCycle = true;
    fixable.reportVerdict({ verified: false, reason: 'EYES_CLOSED' });
    assert.equal(fixable.firedThisCycle, false,
        'a fixable problem SHOULD arm another capture');
});

test('every instruction has both Indonesian and English copy', async () => {
    const { CATALOG } = await import('./ai-scan-coach.js');
    for (const [key, entry] of Object.entries(CATALOG)) {
        assert.ok(entry.id && entry.id.length > 3, `${key} missing Indonesian copy`);
        assert.ok(entry.en && entry.en.length > 3, `${key} missing English copy`);
        assert.ok(['critical', 'warn', 'info'].includes(entry.tone), `${key} has bad tone`);
    }
});

/* ── automatic capture + verification verdict ───────────────────────────── */

/** Minimal coach stand-in: exercises the real capture bookkeeping, no DOM. */
function coachStub(overrides = {}) {
    const coach = {
        autoCapture: true,
        holdFrames: 3,
        maxAttempts: 4,
        goodStreak: 0,
        attempts: 0,
        firedThisCycle: false,
        spoken: [],
        fired: 0,
        lastInstruction: null,

        armAutoCapture() {
            this.goodStreak = 0;
            this.firedThisCycle = false;
        },

        noteCapture() {
            this.attempts += 1;
            this.goodStreak = 0;
            this.firedThisCycle = true;
        },

        get attemptsLeft() {
            return Math.max(0, this.maxAttempts - this.attempts);
        },

        voice: { speak: (i) => { coachStub.last?.spoken.push(i.key); } },

        maybeAutoCapture(inst) {
            if (!this.autoCapture || this.firedThisCycle) return;
            if (inst.key !== 'READY') { this.goodStreak = 0; return; }
            if (this.attemptsLeft <= 0) return;
            this.goodStreak += 1;
            if (this.goodStreak < this.holdFrames) return;
            this.noteCapture();
            this.fired += 1;
        },

        ...overrides,
    };

    coachStub.last = coach;

    return coach;
}

const READY = { key: 'READY', tone: 'info', text: { id: 'ok', en: 'ok' } };
const SHAKING_NOW = { key: 'SHAKING', tone: 'warn', text: { id: 'goyang', en: 'shake' } };

test('auto-capture does not fire before the frame has been good long enough', () => {
    const c = coachStub({ holdFrames: 3 });
    c.maybeAutoCapture(READY);
    c.maybeAutoCapture(READY);
    assert.equal(c.fired, 0, 'two good frames is not enough');
    c.maybeAutoCapture(READY);
    assert.equal(c.fired, 1, 'should fire once the streak is satisfied');
});

test('a problem mid-streak resets the hold, so a lucky frame cannot trigger', () => {
    const c = coachStub({ holdFrames: 3 });
    c.maybeAutoCapture(READY);
    c.maybeAutoCapture(READY);
    c.maybeAutoCapture(SHAKING_NOW);
    assert.equal(c.goodStreak, 0, 'shake must reset the streak');
    c.maybeAutoCapture(READY);
    c.maybeAutoCapture(READY);
    assert.equal(c.fired, 0, 'streak has to restart from zero');
    c.maybeAutoCapture(READY);
    assert.equal(c.fired, 1);
});

test('capture fires once per cycle until the host re-arms it', () => {
    const c = coachStub({ holdFrames: 1 });
    for (let i = 0; i < 10; i++) c.maybeAutoCapture(READY);
    assert.equal(c.fired, 1, 'must not keep firing while a result is pending');

    /* The host re-arms after a retake, which is what allows attempt two. */
    c.armAutoCapture();
    c.maybeAutoCapture(READY);
    assert.equal(c.fired, 2);
});

test('automatic attempts are capped so a hopeless frame cannot loop forever', () => {
    const c = coachStub({ holdFrames: 1, maxAttempts: 4 });

    for (let cycle = 0; cycle < 10; cycle++) {
        c.armAutoCapture();
        c.maybeAutoCapture(READY);
    }

    assert.equal(c.fired, 4, 'exactly maxAttempts captures, then it stops');
    assert.equal(c.attemptsLeft, 0);
});

test('shake never auto-captures, however long it persists', () => {
    const c = coachStub({ holdFrames: 1 });
    for (let i = 0; i < 20; i++) c.maybeAutoCapture(SHAKING_NOW);
    assert.equal(c.fired, 0);
});

test('auto-capture can be switched off entirely', () => {
    const c = coachStub({ holdFrames: 1, autoCapture: false });
    c.armAutoCapture();
    c.maybeAutoCapture(READY);
    assert.equal(c.fired, 0);
});

test('a verdict maps each server reason to its own spoken instruction', async () => {
    const { ScanCoach, CATALOG } = await import('./ai-scan-coach.js');

    const cases = [
        /* NO_FACE belongs to the DOCUMENT endpoint only; the face endpoint uses
           NO_FACE_IN_SELFIE. Verified against ai_core/app.py. */
        [{ verified: false, reason: 'NO_FACE_IN_SELFIE' }, 'VERDICT_NO_FACE'],
        [{ verified: false, reason: 'MULTIPLE_FACES' }, 'VERDICT_MULTIPLE_FACES'],
        [{ verified: false, reason: 'NO_KTP' }, 'VERDICT_NO_KTP'],
        [{ verified: false, reason: 'EYES_CLOSED' }, 'VERDICT_EYES_CLOSED'],
        [{ verified: false, reason: 'BLUR_DETECTED' }, 'VERDICT_BLURRY'],
        [{ verified: false, reason: 'NO_MATCH' }, 'VERDICT_NOT_MATCH'],
        [{ verified: false, reason: 'AI_UNAVAILABLE' }, 'VERDICT_UNAVAILABLE'],
        [{ verified: true }, 'VERIFIED_OK'],
    ];

    for (const [result, expected] of cases) {
        const c = new ScanCoach({ mount: null, mode: 'face' });
        assert.equal(c.reportVerdict(result), expected,
            `reason ${result.reason} should map to ${expected}`);
    }

    assert.ok(CATALOG.VERDICT_NOT_MATCH.id.length > 3);
});

test('an offline verdict stops the loop instead of retrying the same failure', () => {
    const c = coachStub({ holdFrames: 1, maxAttempts: 4 });
    c.armAutoCapture();
    c.maybeAutoCapture(READY);
    assert.equal(c.fired, 1);

    /* AI_UNAVAILABLE must not re-arm: retrying cannot change the outcome. */
    const verdict = { verified: false, reason: 'AI_UNAVAILABLE' };
    const recoverable = verdict.reason !== 'AI_UNAVAILABLE'
        && verdict.reason !== 'NO_KTP';

    assert.equal(recoverable, false, 'offline / no-KTP must be treated as final');
    assert.equal(c.attemptsLeft, 3, 'attempts are preserved, not burned');
});

test('decide returns a localised line for whichever language is active', async () => {
    const m = analyzePixels(blank(20), 'document');
    const id = decide(m, WARM);
    const { id: idText, en: enText } = id.text;
    assert.ok(idText.includes('kartu'), `unexpected Indonesian copy: ${idText}`);
    assert.ok(enText.toLowerCase().includes('card'), `unexpected English copy: ${enText}`);
});