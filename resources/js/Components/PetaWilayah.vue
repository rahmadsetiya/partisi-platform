<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    // URL endpoint GeoJSON (FeatureCollection). properties wajib: id (subsls_id).
    geojsonUrl: { type: String, required: true },
    // Map warna: { [subsls_id]: '#rrggbb' }. Yang tidak ada di map → abu-abu.
    colorMap: { type: Object, default: () => ({}) },
    // Jika true, klik polygon meng-emit 'select'.
    selectable: { type: Boolean, default: false },
    // Garis tambahan: [{ from:[lat,lon], to:[lat,lon], color?, dashed?, weight?, key? }]
    lines: { type: Array, default: () => [] },
    height: { type: String, default: '600px' },
});

const emit = defineEmits(['select', 'loaded']);

const el = ref(null);
let map = null;
let lineLayer = null; // L.layerGroup untuk garis koneksi
const layerById = new Map(); // subsls_id -> L.Layer
let loadedFeatures = [];

const WARNA_DEFAULT = '#e5e7eb'; // gray-200
const BORDER_DEFAULT = '#9ca3af'; // gray-400

function styleFor(id) {
    const fill = props.colorMap[id];
    return {
        color: fill ? '#374151' : BORDER_DEFAULT,
        weight: 1,
        fillColor: fill || WARNA_DEFAULT,
        fillOpacity: fill ? 0.65 : 0.25,
    };
}

function restyle() {
    layerById.forEach((layer, id) => layer.setStyle(styleFor(id)));
}

function drawLines() {
    if (!map) return;
    if (!lineLayer) {
        lineLayer = L.layerGroup().addTo(map);
    }
    lineLayer.clearLayers();
    for (const ln of props.lines) {
        if (!ln.from || !ln.to) continue;
        L.polyline([ln.from, ln.to], {
            color: ln.color || '#9ca3af',
            weight: ln.weight || 1,
            opacity: ln.opacity ?? 0.7,
            dashArray: ln.dashed ? '6 6' : undefined,
            interactive: false,
        }).addTo(lineLayer);
    }
}

async function muatGeojson() {
    const res = await fetch(props.geojsonUrl, {
        headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    loadedFeatures = data.features || [];

    const geoLayer = L.geoJSON(data, {
        style: (feature) => styleFor(feature.properties.id),
        onEachFeature: (feature, layer) => {
            const p = feature.properties;
            layerById.set(p.id, layer);

            const muatanTxt = p.muatan !== null && p.muatan !== undefined ? p.muatan : '—';
            layer.bindTooltip(
                `<strong>${p.nmsls ?? '-'}</strong><br>` +
                    `${p.nmdesa ?? ''} · ${p.nmkec ?? ''}<br>` +
                    `Muatan: ${muatanTxt}`,
                { sticky: true },
            );

            if (props.selectable) {
                layer.on('click', () => emit('select', p.id));
            }
        },
    }).addTo(map);

    if (loadedFeatures.length) {
        map.fitBounds(geoLayer.getBounds(), { padding: [20, 20] });
    }

    emit('loaded', {
        count: loadedFeatures.length,
        features: loadedFeatures,
    });

    drawLines();
}

onMounted(async () => {
    map = L.map(el.value, { zoomControl: true });
    map.setView([-3.5, 119.9], 11); // fallback: Kab Enrekang
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    await muatGeojson();
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
    }
});

watch(() => props.colorMap, restyle, { deep: true });
watch(() => props.lines, drawLines, { deep: true });
</script>

<template>
    <div ref="el" :style="{ height, width: '100%' }" class="rounded-md border border-gray-200 z-0"></div>
</template>
