// Alpine components for the admin panel. Loaded only by layouts/admin.blade.php.
// Heavy libraries (Leaflet, SortableJS) are imported on demand.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /**
     * Click or drag on a Leaflet map to set lat/lng inputs; typing in the inputs moves the marker.
     * <div x-data="mapPicker({ lat: '7.29', lng: '80.63', readonly: false })">
     */
    Alpine.data('mapPicker', ({ lat = '', lng = '', readonly = false } = {}) => ({
        lat,
        lng,
        map: null,
        marker: null,

        async init() {
            const { createMap, SRI_LANKA_CENTER } = await import('./map.js');
            const hasPoint = this.lat !== '' && this.lng !== '';
            this.map = createMap(this.$refs.map, {
                center: hasPoint ? [Number(this.lat), Number(this.lng)] : SRI_LANKA_CENTER,
                zoom: hasPoint ? 13 : 7,
            });

            if (hasPoint) {
                this.placeMarker(Number(this.lat), Number(this.lng));
            }

            if (!readonly) {
                this.map.on('click', (e) => this.set(e.latlng.lat, e.latlng.lng));
            }

            this.$watch('lat', () => this.syncFromInputs());
            this.$watch('lng', () => this.syncFromInputs());
        },

        set(lat, lng) {
            this.lat = lat.toFixed(6);
            this.lng = lng.toFixed(6);
            this.placeMarker(lat, lng);
        },

        placeMarker(lat, lng) {
            if (this.marker) {
                this.marker.setLatLng([lat, lng]);
                return;
            }
            this.marker = window.L.marker([lat, lng], { draggable: !readonly }).addTo(this.map);
            this.marker.on('dragend', () => {
                const point = this.marker.getLatLng();
                this.set(point.lat, point.lng);
            });
        },

        syncFromInputs() {
            const lat = Number(this.lat);
            const lng = Number(this.lng);
            if (this.lat !== '' && this.lng !== '' && !Number.isNaN(lat) && !Number.isNaN(lng)) {
                this.placeMarker(lat, lng);
            }
        },
    }));

    /**
     * Tag chips with suggestions, submitted as name[] hidden inputs.
     */
    Alpine.data('tagInput', ({ tags = [], suggestions = [] } = {}) => ({
        tags: [...tags],
        suggestions,
        draft: '',

        add(value = this.draft) {
            const tag = String(value).trim().toLowerCase().replace(/\s+/g, '_');
            if (tag !== '' && !this.tags.includes(tag)) {
                this.tags.push(tag);
            }
            this.draft = '';
        },

        remove(tag) {
            this.tags = this.tags.filter((t) => t !== tag);
        },

        label(tag) {
            return tag.replace(/_/g, ' ');
        },
    }));

    /**
     * Drag-and-drop photo order, saved with a JSON POST.
     */
    Alpine.data('sortableGallery', ({ url }) => ({
        saving: false,
        message: '',

        async init() {
            if (!url) {
                return;
            }
            const { default: Sortable } = await import('sortablejs');
            Sortable.create(this.$refs.grid, {
                handle: '[data-drag-handle]',
                animation: 150,
                onEnd: () => this.save(),
            });
        },

        async save() {
            const ids = [...this.$refs.grid.querySelectorAll('[data-media-id]')].map((el) => el.dataset.mediaId);
            this.saving = true;
            this.message = 'Saving order…';
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ ids }),
                });
                this.message = response.ok ? 'Order saved.' : 'Could not save the order.';
            } catch {
                this.message = 'Could not save the order.';
            } finally {
                this.saving = false;
            }
        },
    }));

    /**
     * Wikimedia Commons search modal: results come from our backend (never from the browser
     * directly), the admin ticks photos and submits the import form.
     */
    Alpine.data('commonsSearch', ({ url, query = '' }) => ({
        open: false,
        query,
        loading: false,
        error: '',
        results: [],
        selected: [],

        async search() {
            if (this.query.trim() === '') {
                return;
            }
            this.loading = true;
            this.error = '';
            this.results = [];
            this.selected = [];
            try {
                const response = await fetch(`${url}?q=${encodeURIComponent(this.query)}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message ?? 'Search failed.');
                }
                this.results = data.results;
                if (this.results.length === 0) {
                    this.error = 'No photos found. Try a different search.';
                }
            } catch (e) {
                this.error = e.message || 'Search failed.';
            } finally {
                this.loading = false;
            }
        },

        toggle(title) {
            this.selected = this.selected.includes(title)
                ? this.selected.filter((t) => t !== title)
                : [...this.selected, title];
        },
    }));

    /**
     * "Select all" for bulk-action tables. Checkboxes carry data-bulk-item.
     */
    Alpine.data('bulkSelect', () => ({
        count: 0,

        init() {
            this.$el.addEventListener('change', () => this.recount());
        },

        toggleAll(checked) {
            this.$el.querySelectorAll('[data-bulk-item]').forEach((box) => { box.checked = checked; });
            this.recount();
        },

        recount() {
            this.count = this.$el.querySelectorAll('[data-bulk-item]:checked').length;
        },
    }));
});
