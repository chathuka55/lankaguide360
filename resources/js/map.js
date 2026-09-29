// Leaflet bundle, loaded only on pages that show a map:
//   @vite(['resources/js/map.js'])
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Vite rewrites asset URLs, so point Leaflet's default marker at the bundled images.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

export const SRI_LANKA_CENTER = [7.8731, 80.7718];

export const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

export function createMap(element, options = {}) {
    const map = L.map(element, { scrollWheelZoom: false, ...options })
        .setView(options.center ?? SRI_LANKA_CENTER, options.zoom ?? 7);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: OSM_ATTRIBUTION,
    }).addTo(map);

    return map;
}

/** Route colours per day (brand teal first, then distinct hues). */
const DAY_COLOURS = ['#0F766E', '#F59E0B', '#2563EB', '#DB2777', '#7C3AED', '#16A34A', '#EA580C', '#0891B2', '#B91C1C', '#4D7C0F'];

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/** Popup card for a place marker. */
export function placePopup(place) {
    const image = place.image ? `<img src="${escapeHtml(place.image)}" alt="" width="200" height="130" style="width:100%;height:110px;object-fit:cover;border-radius:6px;margin-bottom:6px">` : '';
    return `${image}<strong>${escapeHtml(place.name)}</strong><br><span style="color:#64748b">${escapeHtml(place.district)}</span><br><a href="${escapeHtml(place.url)}">View place →</a>`;
}

window.L = L;
window.LankaMap = { createMap, SRI_LANKA_CENTER, OSM_ATTRIBUTION, placePopup };

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /** Many places with clustered markers (destinations map view). */
    Alpine.data('placesMap', (places) => ({
        init() {
            const map = createMap(this.$el);
            const cluster = L.markerClusterGroup({ showCoverageOnHover: false });
            places.forEach((place) => {
                cluster.addLayer(L.marker([place.lat, place.lng], { title: place.name }).bindPopup(placePopup(place), { maxWidth: 220 }));
            });
            map.addLayer(cluster);
            if (places.length) {
                map.fitBounds(cluster.getBounds(), { padding: [30, 30], maxZoom: 12 });
            }
        },
    }));

    /** One pin (place page mini map, contact office map). */
    Alpine.data('pinMap', ({ lat, lng, label = '', zoom = 13 }) => ({
        init() {
            const map = createMap(this.$el, { center: [lat, lng], zoom });
            const marker = L.marker([lat, lng]).addTo(map);
            if (label) marker.bindPopup(escapeHtml(label));
        },
    }));

    /** District outline from /geo/lk-districts.geojson plus its places. */
    Alpine.data('districtMap', ({ slug, places = [], geojsonUrl }) => ({
        async init() {
            const map = createMap(this.$el);
            const markers = places.map((place) => L.marker([place.lat, place.lng], { title: place.name }).bindPopup(placePopup(place), { maxWidth: 220 }));
            markers.forEach((m) => m.addTo(map));

            try {
                const data = await (await fetch(geojsonUrl)).json();
                const feature = data.features.find((f) => f.properties.slug === slug);
                if (feature) {
                    const layer = L.geoJSON(feature, { style: { color: '#0F766E', weight: 2, fillOpacity: 0.08 } }).addTo(map);
                    map.fitBounds(layer.getBounds(), { padding: [10, 10] });
                    return;
                }
            } catch {
                // Boundary file missing: fall back to the markers.
            }

            if (markers.length) {
                map.fitBounds(L.featureGroup(markers).getBounds(), { padding: [30, 30], maxZoom: 12 });
            }
        },
    }));

    /**
     * Itinerary map: numbered stop markers and one coloured route per day.
     * days = [{ number, title, stops: [{ lat, lng, name, n }], path: [[lat, lng], …] }]
     * Re-renders on the `itinerary-updated` window event ({ detail: { days } }) from the trip builder.
     */
    Alpine.data('tripMap', ({ days = [], start = null }) => ({
        map: null,
        layer: null,
        all: days,
        only: null,
        init() {
            this.map = createMap(this.$el);
            this.layer = L.featureGroup().addTo(this.map);
            this.draw(days);
            window.addEventListener('itinerary-updated', (event) => {
                this.all = event.detail?.days ?? [];
                this.draw(this.all);
            });
            // Day filter on the builder review step: { detail: { day: 3 } } or { day: null } for all.
            window.addEventListener('trip-map-filter', (event) => {
                this.only = event.detail?.day ?? null;
                this.draw(this.all);
            });
        },
        draw(list) {
            this.layer.clearLayers();

            if (start && this.only === null) {
                L.circleMarker([start.lat, start.lng], { radius: 7, color: '#0f172a', fillColor: '#fff', fillOpacity: 1, weight: 3 })
                    .bindTooltip(escapeHtml(start.name)).addTo(this.layer);
            }

            list.forEach((day, index) => {
                if (this.only !== null && day.number !== this.only) return;
                const color = DAY_COLOURS[index % DAY_COLOURS.length];
                if (day.path && day.path.length > 1) {
                    L.polyline(day.path, { color, weight: 4, opacity: 0.85 })
                        .bindTooltip(`Day ${escapeHtml(day.number)}${day.title ? ': ' + escapeHtml(day.title) : ''}`, { sticky: true })
                        .addTo(this.layer);
                }
                (day.stops ?? []).forEach((stop) => {
                    const icon = L.divIcon({
                        className: '',
                        html: `<span style="display:grid;place-items:center;width:26px;height:26px;border-radius:9999px;background:${color};color:#fff;font:600 12px/1 Inter,system-ui,sans-serif;border:2px solid #fff;box-shadow:0 1px 3px rgb(0 0 0/.4)">${escapeHtml(stop.n)}</span>`,
                        iconSize: [26, 26],
                        iconAnchor: [13, 13],
                    });
                    L.marker([stop.lat, stop.lng], { icon, title: stop.name })
                        .bindPopup(`<strong>${escapeHtml(stop.name)}</strong><br><span style="color:#64748b">Day ${escapeHtml(day.number)}${stop.time ? ' · ' + escapeHtml(stop.time) : ''}</span>`)
                        .addTo(this.layer);
                });
            });

            if (this.layer.getLayers().length) {
                this.map.fitBounds(this.layer.getBounds(), { padding: [30, 30], maxZoom: 11 });
            }
        },
    }));
});

