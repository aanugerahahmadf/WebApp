/**
 * Free checkout address picker.
 * Leaflet + OpenStreetMap tiles + Nominatim geocoding; no Google/API key.
 */
let leafletPromise = null;

function loadLeaflet() {
    if (leafletPromise) return leafletPromise;

    leafletPromise = new Promise((resolve, reject) => {
        if (window.L) return resolve(window.L);

        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(css);

        // Use two free public CDNs. This avoids leaving the map blank when a
        // particular CDN is unavailable on the customer's network.
        const sources = [
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
        ];
        let index = 0;
        const load = () => {
            const script = document.createElement('script');
            script.src = sources[index++];
            script.async = true;
            script.onload = () => window.L ? resolve(window.L) : (index < sources.length ? load() : reject(new Error('leaflet-missing')));
            script.onerror = () => index < sources.length ? load() : reject(new Error('leaflet-missing'));
            document.head.appendChild(script);
        };
        load();
    });

    return leafletPromise;
}

function addressParts(address = {}) {
    const area = [
        address.city || address.town || address.village || address.municipality,
        address.county || address.state_district,
        address.state || address.province,
        address.postcode,
    ].filter(Boolean).join(', ');

    const street = [address.house_number, address.road || address.pedestrian || address.residential]
        .filter(Boolean).join(' ');

    return { area, street };
}

async function nominatim(url) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'Accept-Language': document.documentElement.lang || 'id' },
    });

    if (!response.ok) throw new Error('nominatim-failed');
    return response.json();
}

function checkoutAddress(config) {
    return {
        config,
        draft: {
            administrative_area: config.initial?.administrative_area ?? null,
            street: config.initial?.street ?? null,
            detail: config.initial?.detail ?? null,
            label: config.initial?.label ?? 'home',
            save_for_user: config.initial?.save_for_user ?? false,
            place_id: config.initial?.place_id ?? null,
            latitude: config.initial?.latitude ?? null,
            longitude: config.initial?.longitude ?? null,
        },
        mapReady: false,
        mapFailed: false,
        locating: false,
        status: '',
        suggestions: { area: [], street: [] },
        map: null,
        marker: null,
        searchToken: 0,
        autoLocateAttempted: false,
        fullscreen: false,
        defaultCenter: [-6.2, 106.816666],

        get livewire() {
            return window.Livewire?.find(this.config.componentId) ?? null;
        },

        set(key, value) {
            this.livewire?.set(`${this.config.statePath}.${key}`, value);
        },

        openAddressModal() {
            window.dispatchEvent(new CustomEvent('open-modal', {
                detail: { id: this.config.modalId },
                bubbles: true,
            }));
        },

        closeAddressModal() {
            window.dispatchEvent(new CustomEvent('close-modal', {
                detail: { id: this.config.modalId },
                bubbles: true,
            }));
        },

        init() {
            this.draft = {
                administrative_area: this.config.initial?.administrative_area ?? null,
                street: this.config.initial?.street ?? null,
                detail: this.config.initial?.detail ?? null,
                label: this.config.initial?.label ?? 'home',
                save_for_user: this.config.initial?.save_for_user ?? false,
                place_id: this.config.initial?.place_id ?? null,
                latitude: this.config.initial?.latitude ?? null,
                longitude: this.config.initial?.longitude ?? null,
            };
            this.status = '';
            this.suggestions = { area: [], street: [] };

            // The first Edit action also starts the free browser GPS flow when
            // this address has no coordinates yet. The browser will ask for
            // permission; if it is denied, the manual fields and map remain
            // fully usable.
            if (!this.autoLocateAttempted && this.draft.latitude == null && this.draft.longitude == null) {
                this.autoLocateAttempted = true;
                this.locate();
            }

            loadLeaflet().then((L) => {
                const container = this.$el.querySelector('[data-checkout-address-map]');
                if (!container) return;

                // Never let Leaflet resolve its default marker image relative
                // to the current Laravel URL. That is what produces the
                // broken-image icon and the visible alt text "Mark".
                const pinSvg = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="0 0 28 36"%3E%3Cpath d="M14 1C7.4 1 2 6.2 2 12.6 2 21.1 14 35 14 35s12-13.9 12-22.4C26 6.2 20.6 1 14 1Z" fill="%23d97706" stroke="white" stroke-width="2"/%3E%3Ccircle cx="14" cy="12" r="4" fill="white"/%3E%3C/svg%3E';
                if (L.Icon?.Default) {
                    L.Icon.Default.mergeOptions({
                        iconRetinaUrl: pinSvg,
                        iconUrl: pinSvg,
                        shadowUrl: '',
                    });
                }

                if (!this.map) {
                    const lat = Number(this.draft.latitude) || this.defaultCenter[0];
                    const lng = Number(this.draft.longitude) || this.defaultCenter[1];
                    this.map = L.map(container).setView([lat, lng], this.draft.latitude ? 16 : 11);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(this.map);
                    this.map.on('click', (event) => this.setPosition(event.latlng.lat, event.latlng.lng, true));
                }

                this.mapReady = true;
                this.$nextTick(() => {
                    this.map.invalidateSize();
                    this.syncMarker();
                    // Filament opens the modal with a transition. Leaflet may
                    // measure a hidden container as 0x0, so recalculate once
                    // the transition has finished as well.
                    window.setTimeout(() => this.map?.invalidateSize(), 350);
                });
            }).catch(() => {
                this.mapFailed = true;
                this.status = this.config.labels?.mapsMissing ?? 'Peta gratis tidak dapat dimuat.';
            });
        },

        syncMarker() {
            if (this.draft.latitude != null && this.draft.longitude != null) {
                this.setPosition(Number(this.draft.latitude), Number(this.draft.longitude), false);
            }
        },

        setPosition(latitude, longitude, reverse = true) {
            this.draft.latitude = latitude;
            this.draft.longitude = longitude;

            if (this.map && window.L) {
                const point = [latitude, longitude];
                if (!this.marker) {
                    const icon = window.L.divIcon({
                        className: 'checkout-address-marker',
                        html: '<span aria-hidden="true"></span>',
                        iconSize: [28, 36],
                        iconAnchor: [14, 34],
                        popupAnchor: [0, -34],
                    });
                    this.marker = window.L.marker(point, { draggable: true, icon }).addTo(this.map);
                    this.marker.on('dragend', () => {
                        const position = this.marker.getLatLng();
                        this.setPosition(position.lat, position.lng, true);
                    });
                } else {
                    this.marker.setLatLng(point);
                }
                this.map.setView(point, Math.max(this.map.getZoom(), 16));
            }

            if (reverse) this.reverseGeocode(latitude, longitude);
        },

        async reverseGeocode(latitude, longitude) {
            this.status = 'Mencari alamat…';
            try {
                const result = await nominatim(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(latitude)}&lon=${encodeURIComponent(longitude)}`);
                const parts = addressParts(result.address);
                if (parts.area) this.draft.administrative_area = parts.area.toUpperCase();
                if (parts.street) this.draft.street = parts.street;
                this.draft.place_id = String(result.place_id ?? '');
                this.status = '';
            } catch {
                this.status = this.config.labels?.gpsFailed ?? 'Alamat tidak ditemukan; silakan isi manual.';
            }
        },

        async searchPlaces(kind) {
            const query = String(kind === 'area' ? this.draft.administrative_area : this.draft.street || '').trim();
            if (query.length < 3) {
                this.suggestions[kind] = [];
                return;
            }

            const token = ++this.searchToken;
            try {
                const results = await nominatim(`https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&countrycodes=id&q=${encodeURIComponent(query)}`);
                if (token === this.searchToken) this.suggestions[kind] = results;
            } catch {
                this.suggestions[kind] = [];
            }
        },

        selectSuggestion(place) {
            const parts = addressParts(place.address);
            this.draft.administrative_area = parts.area ? parts.area.toUpperCase() : this.draft.administrative_area;
            this.draft.street = parts.street || place.display_name || this.draft.street;
            this.draft.latitude = Number(place.lat);
            this.draft.longitude = Number(place.lon);
            this.draft.place_id = String(place.place_id ?? '');
            this.suggestions = { area: [], street: [] };
            this.setPosition(this.draft.latitude, this.draft.longitude, false);
        },

        clearArea() { this.suggestions.area = []; },
        clearStreet() { this.suggestions.street = []; },

        resetMap() {
            this.draft.latitude = null;
            this.draft.longitude = null;
            this.draft.place_id = null;
            this.draft.administrative_area = null;
            this.draft.street = null;
            if (this.marker) {
                this.map.removeLayer(this.marker);
                this.marker = null;
            }
            this.map?.setView(this.defaultCenter, 11);
            this.status = 'Pilih lokasi pada peta atau gunakan GPS.';
        },

        toggleFullscreen() {
            const element = this.$refs.mapShell;
            if (!element) return;

            if (!document.fullscreenElement) {
                element.requestFullscreen?.();
                this.fullscreen = true;
            } else {
                document.exitFullscreen?.();
                this.fullscreen = false;
            }

            window.setTimeout(() => this.map?.invalidateSize(), 300);
        },

        locate() {
            if (!navigator.geolocation) {
                this.status = this.config.labels?.gpsUnsupported ?? '';
                return;
            }

            this.locating = true;
            this.status = 'Mengambil lokasi GPS…';
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.locating = false;
                    this.setPosition(position.coords.latitude, position.coords.longitude, true);
                },
                (error) => {
                    this.locating = false;
                    this.status = error.code === error.PERMISSION_DENIED
                        ? (this.config.labels?.gpsDenied ?? '')
                        : (this.config.labels?.gpsFailed ?? '');
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
            );
        },

        async apply() {
            const livewire = this.livewire;
            const state = {
                administrative_area: this.draft.administrative_area,
                street: this.draft.street,
                detail: this.draft.detail,
                label: this.draft.label,
                save_for_user: Boolean(this.draft.save_for_user),
                place_id: this.draft.place_id,
                latitude: this.draft.latitude,
                longitude: this.draft.longitude,
            };

            // Send one complete object. Sending each nested key separately can
            // make Livewire rerender between calls, causing the later fields
            // to be discarded and the summary to remain "Not set yet".
            // Entanglement keeps the parent Filament form in sync even when
            // the modal is closed before a Livewire response arrives.
            this.config.stateBinding = state;
            if (livewire) {
                await Promise.resolve(livewire.set(this.config.statePath, state));
            }

            const summary = [this.draft.detail, this.draft.street, this.draft.administrative_area]
                .filter((line) => String(line ?? '').trim() !== '').join('\n');
            const target = this.$el.querySelector('[data-checkout-address-summary]');
            const mirror = this.$el.querySelector('[data-checkout-address-mirror]');
            if (target) target.textContent = summary;
            if (mirror) mirror.value = summary;

            this.$dispatch('checkout-address:applied', { modalId: this.config.modalId });
        },
    };
}

// Also expose the factory globally. Alpine evaluates x-data expressions while
// the page is booting; this fallback keeps the component functional even when
// Filament initialized Alpine just before this Vite entry finished loading.
window.checkoutAddress = checkoutAddress;

// This entry is loaded with @vite inside a Blade component. Depending on
// where Filament has already booted Alpine, `alpine:init` may have fired
// before this module executes. Register in both cases so x-data is never left
// inert (which would make the Edit button appear but do nothing).
function registerCheckoutAddress() {
    if (window.Alpine && typeof window.Alpine.data === 'function') {
        window.Alpine.data('checkoutAddress', checkoutAddress);
        return true;
    }

    return false;
}

if (!registerCheckoutAddress()) {
    document.addEventListener('alpine:init', registerCheckoutAddress, { once: true });
}
