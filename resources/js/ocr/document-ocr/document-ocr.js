/**
 * Client-side OCR for identity documents (KTP, NPWP, SIM, Passport).
 *
 * Mirrors the on-device behaviour already implemented in the mobile app
 * (google_mlkit_text_recognition + Dart regex parsers in
 * mobile_app/lib/core/utils/*_utils.dart). On the web we use Tesseract.js
 * (WASM) so the parsing stays fully client-side — no network round-trip and
 * no external API key required.
 *
 * Note on "TEXT JANGAN MIRING": any user-visible copy rendered by this module
 * is plain text (labels/status in Bahasa Indonesia).
 */

import { createWorker } from 'tesseract.js';

/**
 * Base URL where the Tesseract.js static assets live (worker, WASM core and
 * the traineddata language files, copied into public/tesseract).
 */
const TESSERACT_BASE = '/tesseract';

/** Languages loaded into the worker. Indonesian + English cover KTP/NPWP/SIM labels. */
const TESSERACT_LANGS = ['ind', 'eng'];

let workerPromise = null;
let busy = false;

function ensureTesseractReady() {
    if (typeof window.Tesseract === 'undefined' && !('tesseract_promise' in window)) {
        // createWorker is imported above; nothing extra to expose.
    }
    return Promise.resolve();
}

/**
 * Returns a lazily-created, persistent Tesseract worker (created once and
 * reused for all scans on the page). Falls back gracefully if creation fails.
 */
function getWorker() {
    if (!workerPromise) {
        workerPromise = createWorker(TESSERACT_LANGS, 1, {
            langPath: `${TESSERACT_BASE}/lang`,
            workerPath: `${TESSERACT_BASE}/worker.min.js`,
            corePath: `${TESSERACT_BASE}/core`,
            gzip: true,
            logger: () => {},
        }).catch((error) => {
            // Prevent a permanently rejected promise from being reused.
            workerPromise = null;
            throw error;
        });
    }
    return workerPromise;
}

/**
 * Runs OCR on an image source (File / Blob / dataURL / canvas / URL).
 *
 * @param {File|Blob|string|HTMLCanvasElement} image
 * @param {{ onProgress?: (status: string, progress: number) => void }} [opts]
 * @returns {Promise<string>} recognized plain text
 */
export async function recognizeText(image, { onProgress } = {}) {
    if (busy) {
        throw new Error('OCR sedang berjalan. Tunggu sebentar lalu coba lagi.');
    }
    busy = true;
    try {
        await ensureTesseractReady();
        const worker = await getWorker();
        const { data } = await worker.recognize(image, {}, {
            text: true,
            blocks: false,
            hocr: false,
            tsv: false,
        }, {
            logger: (m) => {
                if (onProgress && typeof m?.progress === 'number') {
                    onProgress(String(m.status || ''), m.progress);
                }
            },
        });
        return String(data?.text || '');
    } finally {
        busy = false;
    }
}

/**
 * Splits a full name into first / middle / last, mirroring splitKtpName().
 *
 * @param {string} fullName
 * @returns {{ first_name: string, mid_name: string, last_name: string }}
 */
function splitName(fullName) {
    const parts = fullName.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 1) {
        return { first_name: parts[0] || '', mid_name: '', last_name: '' };
    }
    if (parts.length === 2) {
        return { first_name: parts[0], mid_name: '', last_name: parts[1] };
    }
    return {
        first_name: parts[0],
        mid_name: parts.slice(1, -1).join(' '),
        last_name: parts[parts.length - 1],
    };
}

/**
 * Converts "dd/mm/yyyy" (as read from KTP / SIM) into the "Y-m-d" format
 * expected by the CalendarPicker form field. Returns '' when unparseable.
 */
function toIsoDate(ddmmyyyy, fallback = '') {
    const m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(String(ddmmyyyy || '').trim());
    if (!m) return fallback;
    const [, d, mo, y] = m;
    const day = parseInt(d, 10);
    const month = parseInt(mo, 10);
    if (day > 31 || month > 12 || day < 1 || month < 1) return fallback;
    return `${y}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

/**
 * Normalizes OCR gender (LAKI-LAKI / PEREMPUAN) to the form's options (Pria/Wanita).
 */
function normalizeGender(raw) {
    const value = String(raw || '').toUpperCase().replace(/[^A-Z]/g, '');
    if (value.includes('LAKI')) return 'Pria';
    if (value.includes('PEREMPUAN') || value.includes('WANITA')) return 'Wanita';
    return '';
}

/**
 * Best-effort match of OCR values against the fixed option lists used by the
 * KYC Select fields. Returns '' when no match is found.
 */
function matchOption(value, options) {
    const haystack = String(value || '').trim().toUpperCase().replace(/\./g, '');
    if (!haystack) return '';
    const result = options.find((opt) => {
        const o = opt.toUpperCase();
        if (haystack === o) return true;
        if (haystack.includes(o)) return true;
        const hayLetters = haystack.replace(/[^A-Z]/g, '');
        const oLetters = o.replace(/[^A-Z]/g, '');
        return oLetters.length >= 3 && hayLetters.includes(oLetters);
    });
    return result || '';
}

const RELIGION_OPTIONS = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
const MARITAL_OPTIONS = ['Belum Menikah', 'Menikah', 'Cerai'];
const OCCUPATION_OPTIONS = ['Karyawan', 'Wiraswasta', 'Pelajar/Mahasiswa', 'Ibu Rumah Tangga', 'Profesional', 'Lainnya'];

/**
 * Shared parsing helpers (mirrors ktp_utils.dart / npwp_utils.dart / sim_utils.dart).
 */
function cleanLines(text) {
    return String(text || '')
        .split('\n')
        .map((l) => l.trim())
        .filter((l) => l.length > 0);
}

function indexOfLine(lines, regex) {
    for (let i = 0; i < lines.length; i++) {
        if (regex.test(lines[i])) return i;
    }
    return -1;
}

function valueAfter(lines, idx) {
    if (idx < 0 || idx >= lines.length) return '';
    const line = lines[idx];
    const sep = line.search(/[:|]/);
    if (sep >= 0) {
        const rest = line.slice(sep + 1).trim();
        if (rest) return rest;
    }
    if (idx + 1 < lines.length) return lines[idx + 1].trim();
    return '';
}

function findFirst(text, regex) {
    const m = regex.exec(String(text || ''));
    return m ? m[0] : '';
}

/**
 * Parses KTP OCR text into form fields (port of _parseKtpText).
 *
 * @param {string} text
 * @returns {import('../types/types').OcrParsedData}
 */
export function parseKtpFields(text) {
    const lines = cleanLines(text);

    // NIK
    let number = '';
    const nikVal = valueAfter(lines, indexOfLine(lines, /^NIK/));
    const nikHit = /\d{16}/.exec(nikVal);
    if (nikHit) number = nikHit[0];
    if (!number) number = findFirst(text, /\b\d{16}\b/);

    // Nama
    const name = valueAfter(lines, indexOfLine(lines, /^Nama/i));

    // Tempat / Tanggal Lahir
    let birthPlace = '';
    let birthDate = '';
    const ttlIdx = indexOfLine(lines, /Tempat.*Lahir|TTL/i);
    const ttl = valueAfter(lines, ttlIdx);
    if (ttl) {
        const m = /(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})/.exec(ttl);
        if (m) {
            const day = parseInt(m[1], 10);
            const month = parseInt(m[2], 10);
            const year = m[3];
            if (day > 0 && month > 0 && day <= 31 && month <= 12) {
                birthDate = `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${year}`;
                birthPlace = ttl.slice(0, m.index).replace(/[,\s]+$/, '').trim();
            } else {
                birthPlace = ttl;
            }
        } else {
            birthPlace = ttl;
        }
    }

    const genderRaw = valueAfter(lines, indexOfLine(lines, /Jenis\s*Kelamin/i));
    const gender = normalizeGender(genderRaw);

    const genderOption = matchOption(genderRaw, ['Pria', 'Wanita']);

    const bloodType = valueAfter(lines, indexOfLine(lines, /Gol\s*\.?\s*Darah/i));

    const address = valueAfter(lines, indexOfLine(lines, /^Alamat/i));
    const rtRw = valueAfter(lines, indexOfLine(lines, /^RT\s*\/\s*RW/i));
    const village = valueAfter(lines, indexOfLine(lines, /Kel\s*\/\s*Desa/i));
    const district = valueAfter(lines, indexOfLine(lines, /Kecamatan/i));

    const religionRaw = valueAfter(lines, indexOfLine(lines, /^Agama/i));
    const maritalRaw = valueAfter(lines, indexOfLine(lines, /Status\s*Perkawinan/i));
    const occupationRaw = valueAfter(lines, indexOfLine(lines, /Pekerjaan/i));

    const nationality = valueAfter(lines, indexOfLine(lines, /Kewarganegaraan/i));
    const validUntil = valueAfter(lines, indexOfLine(lines, /Berlaku/i));

    return {
        identity_type: 'ktp',
        number,
        name: name.trim(),
        first_name: '',
        mid_name: '',
        last_name: '',
        birthPlace,
        birthDate,
        gender,
        genderOption,
        bloodType,
        address,
        rtRw,
        village,
        district,
        religion: matchOption(religionRaw, RELIGION_OPTIONS),
        maritalStatus: matchOption(maritalRaw, MARITAL_OPTIONS),
        occupation: matchOption(occupationRaw, OCCUPATION_OPTIONS),
        nationality,
        validUntil,
        raw: text,
    };
}

/**
 * Parses NPWP OCR text (port of _parseNpwpText).
 */
export function parseNpwpFields(text) {
    const lines = cleanLines(text);

    let number = '';
    const numVal = valueAfter(lines, indexOfLine(lines, /^NPWP/i));
    const numDigits = numVal.replace(/[^0-9]/g, '');
    if (numDigits.length === 15) number = numDigits;
    if (!number) number = findFirst(text, /\b\d{15}\b/);

    const name = valueAfter(lines, indexOfLine(lines, /^Nama/i));
    const address = valueAfter(lines, indexOfLine(lines, /^Alamat/i));

    return {
        identity_type: 'npwp',
        number,
        name: name.trim(),
        first_name: '',
        mid_name: '',
        last_name: '',
        birthPlace: '',
        birthDate: '',
        gender: '',
        genderOption: '',
        bloodType: '',
        address,
        rtRw: '',
        village: '',
        district: '',
        religion: '',
        maritalStatus: '',
        occupation: '',
        nationality: '',
        validUntil: '',
        raw: text,
    };
}

/**
 * Parses SIM OCR text (port of _parseSimText).
 */
export function parseSimFields(text) {
    const lines = cleanLines(text);

    let number = '';
    const numVal = valueAfter(lines, indexOfLine(lines, /^Nomor/i));
    const numDigits = numVal.replace(/[^0-9]/g, '');
    if (numDigits.length >= 6 && numDigits.length <= 12) number = numDigits;
    if (!number) number = findFirst(text, /\b\d{6,12}\b/);

    const name = valueAfter(lines, indexOfLine(lines, /^Nama/i));

    let birthPlace = '';
    let birthDate = '';
    const ttlIdx = indexOfLine(lines, /Tempat.*Lahir|TTL/i);
    const ttl = valueAfter(lines, ttlIdx);
    if (ttl) {
        const m = /(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})/.exec(ttl);
        if (m) {
            const day = parseInt(m[1], 10);
            const month = parseInt(m[2], 10);
            const year = m[3];
            if (day > 0 && month > 0 && day <= 31 && month <= 12) {
                birthDate = `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${year}`;
                birthPlace = ttl.slice(0, m.index).replace(/[,\s]+$/, '').trim();
            } else {
                birthPlace = ttl;
            }
        } else {
            birthPlace = ttl;
        }
    }

    const address = valueAfter(lines, indexOfLine(lines, /^Alamat/i));

    return {
        identity_type: 'sim',
        number,
        name: name.trim(),
        first_name: '',
        mid_name: '',
        last_name: '',
        birthPlace,
        birthDate,
        gender: '',
        genderOption: '',
        bloodType: '',
        address,
        rtRw: '',
        village: '',
        district: '',
        religion: '',
        maritalStatus: '',
        occupation: '',
        nationality: '',
        validUntil: '',
        raw: text,
    };
}

/**
 * Parses Passport OCR text (port of _parsePassportText) — reads MRZ line 2.
 */
export function parsePassportFields(text) {
    const lines = cleanLines(text);

    let number = '';
    for (let i = 0; i < lines.length; i++) {
        if (/^P<[A-Z]{3}</.test(lines[i])) {
            if (i + 1 < lines.length) {
                const mrzLine2 = lines[i + 1].replace(/\s+/g, '');
                const m = /^([A-Z0-9]{6,9})/.exec(mrzLine2);
                if (m) number = m[1];
            }
            break;
        }
    }
    if (!number) {
        for (const line of lines) {
            const clean = line.replace(/\s+/g, '');
            if (clean.length >= 6 && clean.length <= 9 && /^[A-Z0-9]+$/.test(clean)) {
                number = clean;
                break;
            }
        }
    }

    let name = '';
    const mrzMatch = /P<[A-Z]{3}<([A-Z<]+)<<([A-Z<]+)/.exec(String(text || ''));
    if (mrzMatch) {
        const surname = mrzMatch[1].replace(/</g, ' ').trim();
        const givenNames = mrzMatch[2].replace(/</g, ' ').trim();
        const fullName = `${givenNames} ${surname}`.trim();
        if (fullName.length > 2) name = fullName.toUpperCase();
    }

    let birthDate = '';
    let mrzLine = null;
    for (const line of lines) {
        const clean = line.replace(/\s+/g, '');
        if (clean.length > 20) {
            mrzLine = clean;
            break;
        }
    }
    const dobMatch = mrzLine === null ? null : /[A-Z]{3}(\d{6})/.exec(mrzLine);
    if (dobMatch) {
        const raw = dobMatch[1];
        const y = parseInt(raw.slice(0, 2), 10);
        const m = parseInt(raw.slice(2, 4), 10);
        const d = parseInt(raw.slice(4, 6), 10);
        if (!Number.isNaN(y) && !Number.isNaN(m) && !Number.isNaN(d) && m > 0 && m <= 12 && d > 0 && d <= 31) {
            const year = y >= 70 ? 1900 + y : 2000 + y;
            birthDate = `${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}/${year}`;
        }
    }

    return {
        identity_type: 'passport',
        number,
        name: name.trim(),
        first_name: '',
        mid_name: '',
        last_name: '',
        birthPlace: '',
        birthDate,
        gender: '',
        genderOption: '',
        bloodType: '',
        address: '',
        rtRw: '',
        village: '',
        district: '',
        religion: '',
        maritalStatus: '',
        occupation: '',
        nationality: '',
        validUntil: '',
        raw: text,
    };
}

/**
 * Picks the right parser based on the form's identity_type.
 */
export function parseDocument(text, identity_type) {
    switch (identity_type) {
        case 'npwp':
            return parseNpwpFields(text);
        case 'sim':
            return parseSimFields(text);
        case 'passport':
            return parsePassportFields(text);
        case 'ktp':
        default:
            return parseKtpFields(text);
    }
}

/**
 * Maps parsed OCR data into the Flat array of state-path keys the Filament form
 * understands (statePath = "data"), e.g. { 'data.ktp_number': '...' }.
 *
 * @param {import('../types/types').OcrParsedData} parsed
 * @returns {Record<string, string>}
 */
export function mapOcrToFormState(parsed) {
    const values = {};

    const nameSplit = splitName(parsed.name || '');

    switch (parsed.identity_type) {
        case 'ktp':
            if (parsed.number) values['data.ktp_number'] = parsed.number;
            break;
        case 'npwp':
            if (parsed.number) values['data.npwp_number'] = parsed.number;
            break;
        case 'sim':
            if (parsed.number) values['data.sim_number'] = parsed.number;
            break;
        case 'passport':
            if (parsed.number) values['data.passport_number'] = parsed.number;
            break;
    }

    if (nameSplit.first_name) values['data.first_name'] = nameSplit.first_name;
    if (nameSplit.mid_name) values['data.mid_name'] = nameSplit.mid_name;
    if (nameSplit.last_name) values['data.last_name'] = nameSplit.last_name;

    if (parsed.birthPlace) values['data.birth_place'] = parsed.birthPlace;
    if (parsed.birthDate) values['data.birth_date'] = toIsoDate(parsed.birthDate);
    if (parsed.gender) values['data.gender'] = parsed.gender;
    if (parsed.religion) values['data.religion'] = parsed.religion;
    if (parsed.maritalStatus) values['data.marital_status'] = parsed.maritalStatus;
    if (parsed.occupation) values['data.occupation'] = parsed.occupation;
    if (parsed.address) values['data.address'] = parsed.address;
    // KTP sub-locality fields (kept as free text; the cascading dropdown is not touched).
    if (parsed.village && !values['data.village_name']) values['data.village_name'] = parsed.village;
    if (parsed.district && !values['data.district_name']) values['data.district_name'] = parsed.district;

    return values;
}

export { splitName, toIsoDate, normalizeGender, matchOption };