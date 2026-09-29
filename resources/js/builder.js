// Trip Builder (Livewire) helpers: the district picker map (step 3) and drag-and-drop stops (step 8).
// Loads map.js too, which registers the tripMap component used on the review step.
import Sortable from 'sortablejs';
import { createMap } from './map.js';

const L = window.L;

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /**
     * District map for step 3. Districts linked to the chosen interests are clickable; clicks call
     * the Livewire action, which answers with a `districts-changed` event to restyle the map.
     */
    Alpine.data('districtPicker', ({ geojsonUrl, districts, selected = [] }) => ({
        selected: selected.map(Number),
        layers: [],
        async init() {
            const map = createMap(this.$refs.map);
            const bySlug = Object.fromEntries(districts.map((d) => [d.slug, d]));

            try {
                const data = await (await fetch(geojsonUrl)).json();
                const layer = L.geoJSON(data, {
                    style: (feature) => this.style(bySlug[feature.properties.slug]),
                    onEachFeature: (feature, shape) => {
                        const district = bySlug[feature.properties.slug];
                        if (!district) return;
                        this.layers.push({ district, shape });
                        shape.bindTooltip(district.name + (district.allowed ? '' : ' (not a match)'), { sticky: true });
                        if (district.allowed) {
                            shape.on('click', () => this.$wire.toggleDistrict(district.id));
                        }
                    },
                }).addTo(map);
                map.fitBounds(layer.getBounds(), { padding: [10, 10] });
            } catch {
                // Boundary file missing: the list next to the map still works.
            }
        },
        style(district) {
            if (!district || !district.allowed) {
                return { color: '#94a3b8', weight: 1, fillColor: '#e2e8f0', fillOpacity: 0.35 };
            }
            return this.selected.includes(district.id)
                ? { color: '#115e59', weight: 2, fillColor: '#0F766E', fillOpacity: 0.65 }
                : { color: '#0F766E', weight: 1.5, fillColor: '#FCD34D', fillOpacity: 0.45 };
        },
        restyle(ids) {
            this.selected = (ids ?? []).map(Number);
            this.layers.forEach(({ district, shape }) => shape.setStyle(this.style(district)));
        },
    }));

    /**
     * Drag stops within a day or to another day on the review step. The DOM move is undone and
     * Livewire re-renders the re-timed itinerary from the server.
     */
    Alpine.data('itinerarySort', () => ({
        init() {
            this.$el.querySelectorAll('[data-day]').forEach((list) => {
                Sortable.create(list, {
                    group: 'trip-stops',
                    animation: 150,
                    ghostClass: 'opacity-40',
                    onEnd: (event) => {
                        if (event.from === event.to && event.oldIndex === event.newIndex) return;

                        const days = [...this.$el.querySelectorAll('[data-day]')].map((day) =>
                            [...day.querySelectorAll('[data-place-id]')].map((stop) => Number(stop.dataset.placeId)));

                        // Put the element back so Livewire's DOM diff starts from its own markup.
                        event.to.removeChild(event.item);
                        event.from.insertBefore(event.item, event.from.children[event.oldIndex] ?? null);

                        this.$wire.reorder(days);
                    },
                });
            });
        },
    }));
});
