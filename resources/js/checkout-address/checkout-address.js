/**
 * Alpine component untuk field alamat checkout (App\Forms\Components\CheckoutAddress).
 *
 * Tanggung jawabnya sempit dan sengaja tidak masuk ke PHP:
 *
 *   1. Memuat Google Maps JavaScript API (Places + Geocoder) -- sekali per halaman,
 *      hanya kalau `mapsKey` tidak kosong.
 *   2. Dua Autocomplete: satu untuk wilayah (administrative area), satu untuk
 *      nama jalan. Keduanya dibatasi jenisnya supaya saran tidak bercampur.
 *   3. Peta dengan satu pin. Pin bisa digeser; saat digeser, koordinat
 *      dibalik jadi alamat (Geocoder) supaya isianIkut sinkron dengan lokasi
 *      yang benar-benar dipilih.
 *   4. `locate()`: navigator.geolocation. Ini jalur yang tetap jalan tanpa API
 *      key apa pun, dan tetap jalan di app shell Android/iOS.
 *
 * Semua penulisan ke form dilakukan lewat `Livewire.find(componentId).set(...)`
 * dengan path state yang diberikan Blade. Bukan `$wire` langsung: field ini
 * bisa dipakai di Livewire component mana pun, dan `$wire` hanya benar kalau
 * component-nya sedang berada dalam scope Alpine milik Livewire.
 *
 * Kenapa address components diterjemahkan manual, bukan memakai `place.formatted_address`:
 * format `formatted_address` berubah antar negara dan tidak punya baris terpisah,
 * sedangkan tim dekorasi butuh pecahan wilayah + jalan untuk memetakan area.
 */

let googleMapsPromise = null;

/**
 * Muat Google Maps JS API satu kali per halaman.
 *
 * Promise disimpan di luar component supaya membuka modal kedua kali (setelah
 * "Edit") tidak memuat ulang script, dan tidak mendaftarkan ulang listener
 * Autocomplete yang sudah terpasang.
 */
function loadGoogleMaps(key) {
    if (googleMapsPromise) {
        return googleMapsPromise;
    }

    googleMapsPromise = new Promise((resolve, reject) => {
        if (window.google?.maps?.places) {
            resolve(window.google.maps);

            return;
        }

        const existing = document.querySelector('script[data-checkout-address]');

        if (existing) {
            existing.addEventListener('load', () => resolve(window.google.maps));
            existing.addEventListener('error', reject);

            return;
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&libraries=places&v=weekly&loading=async`;
        script.async = true;
        script.defer = true;
        script.dataset.checkoutAddress = 'true';
        script.addEventListener('load', () => resolve(window.google.maps));
        script.addEventListener('error', () => reject(new Error('google-maps-load-failed')));

        document.head.appendChild(script);
    });

    return googleMapsPromise;
}

/**
 * Ambil satu bagian dari `address_components` Google.
 *
 * Daftar type yang dianggap sama: `administrative_area_level_1` dilewati di
 * sebagian negara, dan `locality` kadang berisi kode pos.
 */
function componentOf(components, types) {
    const wanted = Array.isArray(types) ? types : [types];

    return components.find((component) =>
        wanted.includes(component.types[0]),
    )?.long_name ?? null;
}

/**
 * Component Alpine untuk satu field alamat checkout.
 *
 * `config` diisi dari Blade: `{ mapsKey, componentId, statePath, initial, labels }`.
 * `componentId` + `statePath` dipakai untuk menulis balik ke form lewat
 * `Livewire.find(...)`, bukan `$wire`, supaya field ini tidak bergantung pada
 * component tempat ia dirender.
 */
function checkoutAddress(config) {
    return {
        config,

        // Draft = isi modal. Dipisah dari state form supaya "Nanti Saja"
        // benar-benar membatalkan: state form hanya ditulis saat apply().
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

        placesReady: false,
        locating: false,
        status: '',

        map: null,
        marker: null,
        streetAutocomplete: null,
        areaAutocomplete: null,
        geocoder: null,

        get livewire() {
            return window.Livewire?.find(this.config.componentId) ?? null;
        },

        set(key, value) {
            this.livewire?.set(`${this.config.statePath}.${key}`, value);
        },

        /**
         * Dipanggil saat modal dibuka. Idempoten: peta tidak boleh dibuat ulang
         * setiap kali modal dibuka, karena Google Maps akan menumpuk marker.
         */
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

            if (!this.config.mapsKey) {
                return;
            }

            if (this.placesReady) {
                this.syncPinToDraft();

                return;
            }

            loadGoogleMaps(this.config.mapsKey)
                .then((maps) => {
                    const container = this.$el.querySelector('[data-checkout-address-map]');

                    if (!container || container.dataset.ready === 'true') {
                        return;
                    }

                    container.dataset.ready = 'true';

                    this.map = new maps.Map(container, {
                        center: { lat: -6.2, lng: 106.816666 },
                        zoom: 11,
                        disableDefaultUI: true,
                        zoomControl: true,
                    });

                    this.geocoder = new maps.Geocoder();
                    this.attachAutocompletes(maps);
                    this.placesReady = true;

                    this.syncPinToDraft();
                })
                .catch(() => {
                    this.status = this.config.labels?.mapsMissing ?? '';
                });
        },

        attachAutocompletes(maps) {
            const areaInput = this.$el.querySelector('[data-checkout-address-area]');
            const streetInput = this.$el.querySelector('[data-checkout-address-street]');

            if (areaInput) {
                // (regions) = daftar wilayah: provinsi/kota/kabupaten/kecamatan.
                this.areaAutocomplete = new maps.places.Autocomplete(areaInput, {
                    types: ['(regions)'],
                    fields: ['address_components', 'geometry', 'formatted_address', 'place_id'],
                });

                this.areaAutocomplete.addListener('place_changed', () => {
                    this.applyPlace(this.areaAutocomplete.getPlace(), 'area');
                });
            }

            if (streetInput) {
                this.streetAutocomplete = new maps.places.Autocomplete(streetInput, {
                    types: ['address'],
                    componentRestrictions: { country: ['id'] },
                    fields: ['address_components', 'geometry', 'formatted_address', 'place_id'],
                });

                this.streetAutocomplete.addListener('place_changed', () => {
                    this.applyPlace(this.streetAutocomplete.getPlace(), 'street');
                });
            }
        },

        /**
         * Pecah satu Place Google menjadi dua baris form.
         *
         * `focus` menentukan bagian mana yang TIDAK ditimpa: memilih jalan tidak
         * boleh menghapus borough yang sudah dipilih di baris atas.
         */
        applyPlace(place, focus) {
            if (!place?.address_components) {
                return;
            }

            const components = place.address_components;

            this.draft.latitude = place.geometry?.location?.lat() ?? this.draft.latitude;
            this.draft.longitude = place.geometry?.location?.lng() ?? this.draft.longitude;
            this.draft.place_id = place.place_id ?? this.draft.place_id;

            if (focus === 'street') {
                this.draft.street = [
                    componentOf(components, 'street_number'),
                    componentOf(components, 'route'),
                ]
                    .filter(Boolean)
                    .join(' ');

                if (this.draft.administrative_area) {
                    this.draft.administrative_area = this.mergeArea(components);
                }
            } else {
                this.draft.administrative_area = this.mergeArea(components);
            }

            this.drawMarker();
        },

        /**
     * Baris wilayah: kota -> district -> provinsi -> kode pos,
         * semuanya_uppercase seperti pada screenshot acuan.
         */
        mergeArea(components) {
            return [
                componentOf(components, ['locality', 'postal_town', 'administrative_area_level_3']),
                componentOf(components, ['administrative_area_level_2', 'sublocality_level_1']),
                componentOf(components, 'administrative_area_level_1'),
                componentOf(components, 'postal_code'),
            ]
                .filter(Boolean)
                .join(', ')
                .toUpperCase();
        },

        clearArea() {
            this.areaAutocomplete?.setInputElement?.({});
        },

        clearStreet() {
            this.streetAutocomplete?.setInputElement?.({});
        },

        /**
         * Ambil lokasi perangkat lalu isi koordinat.
         *
         * Berjalan tanpa Google Maps: yang dipakai hanya `navigator.geolocation`.
         * Pembalikan koordinat -> alamat butuh Geocoder, jadi kalau Google belum
         * termuat, koordinat tetap tersimpan dan pengguna bisa melengkapi
         * alamatnya sendiri (tetap bisa klik OK).
         */
        locate() {
            if (!navigator.geolocation) {
                this.status = this.config.labels?.gpsUnsupported ?? '';

                return;
            }

            this.locating = true;
            this.status = '';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.locating = false;

                    const { latitude, longitude } = position.coords;

                    this.draft.latitude = latitude;
                    this.draft.longitude = longitude;

                    this.drawMarker();

                    if (!this.geocoder) {
                        this.status = this.config.labels?.gpsFailed ?? '';

                        return;
                    }

                    this.geocoder.geocode({ location: { lat: latitude, lng: longitude } }, (results, status) => {
                        const place = results?.[0];

                        if (status !== 'OK' || !place) {
                            this.status = this.config.labels?.gpsFailed ?? '';

                            return;
                        }

                        this.applyPlace(place, this.draft.street ? 'street' : 'area');
                        this.status = '';
                    });
                },
                (error) => {
                    this.locating = false;

                    this.status =
                        error?.code === error?.PERMISSION_DENIED
                            ? (this.config.labels?.gpsDenied ?? '')
                            : (this.config.labels?.gpsFailed ?? '');
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
            );
        },

        drawMarker() {
            if (!this.map || this.draft.latitude == null || this.draft.longitude == null) {
                return;
            }

            const position = { lat: this.draft.latitude, lng: this.draft.longitude };

            if (this.marker) {
                this.marker.setPosition(position);
            } else {
                this.marker = new window.google.maps.Marker({
                    map: this.map,
                    position,
                    draggable: true,
                });

                this.marker.addListener('dragend', (event) => {
                    const next = event.latLng;

                    this.draft.latitude = next.lat();
                    this.draft.longitude = next.lng();

                    // Pin digeser = koordinat berubah, jadi alamat wajib
                    // mengikuti, bukan menunggu diketik ulang oleh pengguna.
                    if (this.geocoder) {
                        this.geocoder.geocode({ location: next }, (results, status) => {
                            if (status === 'OK' && results?.[0]) {
                                this.applyPlace(results[0], this.draft.street ? 'street' : 'area');
                            }
                        });
                    }
                });
            }

            this.map.panTo(position);
            this.map.setZoom(this.map.getZoom() ?? 15);
        },

        /** Tampilkan pin pada koordinat yang sudah tersimpan (buka modal kedua). */
        syncPinToDraft() {
            this.$nextTick(() => this.drawMarker());
        },

        /** Terapkan draft ke form, lalu tutup modal. */
        apply() {
            [
                'administrative_area',
                'street',
                'detail',
                'label',
                'save_for_user',
                'place_id',
                'latitude',
                'longitude',
            ].forEach((key) => this.set(key, this.draft[key]));

            this.$dispatch('checkout-address:applied');

            const summary = [this.draft.detail, this.draft.street, this.draft.administrative_area]
                .filter((line) => (line ?? '').trim() !== '')
                .join('\n');

            const target = this.$el.querySelector('[data-checkout-address-summary]');

            if (target) {
                target.textContent = summary;
            }

            const mirror = this.$el.querySelector('[data-checkout-address-mirror]');

            if (mirror) {
                mirror.value = summary;
            }
        },
    };
}

/**
 * Pendaftaran ke Alpine.
 *
 * Proyek ini tidak memakai `Alpine.data()` di modul lain (semua memakai IIFE),
 * tapi `x-data="checkoutAddress({...})"` di view membutuhkan komponen yang sudah
 * terdaftar. `alpine:init` adalah event resmi yang dipancarkan Alpine SEBELUM
 * DOM di-scan, jadi pendaftaran di situ tidak akan kalah cepat dari render.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('checkoutAddress', checkoutAddress);
});

// Halaman yang Alpine-nya sudah diinisialisasi (navigasi SPA, hot reload) tidak
// akan menerima `alpine:init` lagi, jadi didaftarkan juga secara langsung.
if (window.Alpine) {
    window.Alpine.data('checkoutAddress', checkoutAddress);
}

export default checkoutAddress;
