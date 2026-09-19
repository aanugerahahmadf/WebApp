/**
 * UI helpers for the scan modals (document + face).
 *
 * Reuses the same FilePond injection technique as the avatar browse modal and
 * exposes a small API for writing OCR results into the Filament form state via
 * Livewire.
 */

/**
 * Browser / OS detection (mirrors avatar-browse-modal blade helpers).
 */
export function detectPlatform() {
    const ua = navigator.userAgent;
    return {
        isMobile: /Android|iPhone|iPad|iPod/i.test(ua),
        isIOS: /iPhone|iPad|iPod/i.test(ua),
        isAndroid: /Android/i.test(ua),
        isMac: /Macintosh|Mac OS X/i.test(ua) && !/iPhone|iPad|iPod/i.test(ua),
    };
}

/**
 * Finds the nearest Livewire component for a given DOM element.
 *
 * When `el` is null we fall back to the first `[wire:id]` node on the page.
 * Note: this fallback can target the wrong component on pages that contain
 * more than one Livewire component (e.g. CompleteProfile = Filament page root
 * + `complete-profile-component`), so callers SHOULD pass an element that lives
 * inside the intended component (a form wrapper works well).
 *
 * @param {HTMLElement|null} [el]
 * @returns {object|null} Livewire `$wire` proxy (or null).
 */
export function getLivewireComponent(el = null) {
    const base = el && el.closest ? el.closest('[wire\\:id]') : null;
    const node = base || document.querySelector('[wire\\:id]');
    if (!node) return null;
    const id = node.getAttribute('wire:id');
    if (!id) return null;
    return window.Livewire?.find(id) || null;
}

/**
 * Reads a single state value from the Filament form ("data.*").
 *
 * @param {string} key
 * @param {HTMLElement|null} [el]
 */
export function getFormState(key, el = null) {
    const component = getLivewireComponent(el);
    if (!component) return null;
    try {
        return component.get(key) ?? null;
    } catch {
        return null;
    }
}

/**
 * Writes one state value into the Filament form.
 *
 * @param {string} key e.g. "data.ktp_number"
 * @param {*} value
 * @param {HTMLElement|null} [el]
 */
export function setFormState(key, value, el = null) {
    const component = getLivewireComponent(el);
    if (!component) return false;
    try {
        component.set(key, value);
        return true;
    } catch {
        return false;
    }
}

/**
 * Writes many form values at once.
 *
 * @param {Record<string, string>} values map of data.* keys → values
 * @param {HTMLElement|null} [el]
 * @returns {number} number of fields successfully written
 */
export function setFormStateMany(values, el = null) {
    let written = 0;
    for (const [key, value] of Object.entries(values)) {
        if ((typeof value === 'string' && value !== '') || typeof value === 'number') {
            if (setFormState(key, value, el)) written += 1;
        }
    }
    return written;
}

/**
 * Injects a File object into the FilePond instance scoped to the given wrapper
 * selector (e.g. ".document-photo-wrapper"). Falls back to the hidden
 * `<input type="file">` inside the scope (mirrors avatar-browse-modal).
 *
 * @param {string} wrapperSelector CSS selector for the field wrapper
 * @param {File|Blob} file
 * @param {string} [filename]
 * @returns {boolean} true when injected successfully
 */
export function injectFile(wrapperSelector, file, filename = '') {
    const wrapper = document.querySelector(wrapperSelector);
    if (!wrapper || !file) return false;

    const pondEl = wrapper.querySelector('.filepond--root');
    if (pondEl && window.FilePond) {
        const inst = window.FilePond.find(pondEl);
        if (inst) {
            inst.addFile(file);
            return true;
        }
    }

    const fp = wrapper.querySelector('input[type=file].scan-photo-input')
        || wrapper.querySelector('input[type=file]');
    if (fp) {
        const dt = new DataTransfer();
        dt.items.add(file);
        fp.files = dt.files;
        fp.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    return false;
}

/**
 * Invokes a Livewire action on the nearest component (e.g. "verifyFaceAi")
 * and returns a Promise resolving to the action's return value (if any).
 *
 * `getLivewireComponent()` already returns the `$wire` proxy, so we can access
 * the action directly: `component[name]` routes through Livewire's fallback
 * (`requestCall`) and resolves with the server-side return value.
 *
 * @param {string} name
 * @param {Array} [args]
 * @param {HTMLElement|null} [el]
 * @returns {Promise<*>}
 */
export function callAction(name, args = [], el = null) {
    const component = getLivewireComponent(el);
    if (!component) return Promise.resolve(null);
    try {
        if (typeof component[name] === 'function') {
            return Promise.resolve(component[name](...args));
        }
        if (typeof component.$call === 'function') {
            return Promise.resolve(component.$call(name, ...args));
        }
    } catch (e) {
        return Promise.reject(e);
    }
    return Promise.resolve(null);
}

/**
 * Reads the raw original filename/extension from a File.
 */
export function fileExtension(file) {
    const name = file.name || '';
    const dot = name.lastIndexOf('.');
    if (dot < 0) return 'jpg';
    return name.slice(dot + 1).toLowerCase();
}