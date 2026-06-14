<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PetaWilayah from '@/Components/PetaWilayah.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    kegiatan: Object,
    geojsonUrl: String,
    edges: Array, // [[aId, bId], ...]
    overrides: Array, // [{id, a_id, b_id, tipe, catatan}]
    jumlahWilayah: Number,
});

const flash = computed(() => usePage().props.flash);

const nodeById = ref({}); // id -> { lat, lon, idsubsls, nmsls, nmdesa }
const selectedPair = ref([]); // maks 2 subsls_id

function onLoaded({ features }) {
    const m = {};
    for (const f of features) {
        const p = f.properties;
        if (p.centroid_lat == null || p.centroid_lon == null) continue;
        m[p.id] = {
            lat: p.centroid_lat,
            lon: p.centroid_lon,
            idsubsls: p.idsubsls,
            nmsls: p.nmsls,
            nmdesa: p.nmdesa,
        };
    }
    nodeById.value = m;
}

function namaNode(id) {
    const n = nodeById.value[id];
    if (!n) return '#' + id;
    return n.nmsls || n.nmdesa || n.idsubsls;
}

const pairKey = (a, b) => (a < b ? `${a}-${b}` : `${b}-${a}`);

// Lookup cepat
const edgeSet = computed(() => new Set(props.edges.map(([a, b]) => pairKey(a, b))));
const overrideByPair = computed(() => {
    const m = {};
    for (const o of props.overrides) m[pairKey(o.a_id, o.b_id)] = o;
    return m;
});

// Garis untuk peta: adjacency dasar (abu) + override (hijau/merah)
const garis = computed(() => {
    const nodes = nodeById.value;
    if (!Object.keys(nodes).length) return [];
    const out = [];

    for (const [a, b] of props.edges) {
        if (!nodes[a] || !nodes[b]) continue;
        out.push({
            from: [nodes[a].lat, nodes[a].lon],
            to: [nodes[b].lat, nodes[b].lon],
            color: '#9ca3af',
            weight: 1,
            opacity: 0.35,
            key: 'e' + pairKey(a, b),
        });
    }
    for (const o of props.overrides) {
        if (!nodes[o.a_id] || !nodes[o.b_id]) continue;
        const connect = o.tipe === 'force_connect';
        out.push({
            from: [nodes[o.a_id].lat, nodes[o.a_id].lon],
            to: [nodes[o.b_id].lat, nodes[o.b_id].lon],
            color: connect ? '#16a34a' : '#dc2626',
            weight: 3,
            opacity: 0.9,
            dashed: !connect,
            key: 'o' + o.id,
        });
    }
    return out;
});

// Sorot pasangan terpilih
const sorotPasangan = computed(() => {
    const m = {};
    for (const id of selectedPair.value) m[id] = '#f59e0b';
    return m;
});

function onSelect(id) {
    const cur = selectedPair.value;
    if (cur.includes(id)) {
        selectedPair.value = cur.filter((x) => x !== id);
    } else if (cur.length < 2) {
        selectedPair.value = [...cur, id];
    } else {
        selectedPair.value = [id];
    }
}

// Status relasi pasangan terpilih
const relasi = computed(() => {
    if (selectedPair.value.length !== 2) return null;
    const [a, b] = selectedPair.value;
    const key = pairKey(a, b);
    return {
        bertetangga: edgeSet.value.has(key),
        override: overrideByPair.value[key] ?? null,
    };
});

const form = useForm({ a_id: null, b_id: null, tipe: '', catatan: '' });

function simpan(tipe) {
    if (selectedPair.value.length !== 2) return;
    form.a_id = selectedPair.value[0];
    form.b_id = selectedPair.value[1];
    form.tipe = tipe;
    form.post(route('kegiatan.koneksi.store', props.kegiatan.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('catatan');
            selectedPair.value = [];
        },
    });
}

function hapusOverride(o) {
    if (confirm(`Hapus override koneksi ${namaNode(o.a_id)} ↔ ${namaNode(o.b_id)}?`)) {
        router.delete(route('kegiatan.koneksi.destroy', { kegiatan: props.kegiatan.id, override: o.id }), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="`Koneksi · ${kegiatan.nama}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('kegiatan.show', kegiatan.id)" class="text-gray-400 hover:text-gray-600">← {{ kegiatan.nama }}</Link>
                    <span class="text-gray-300">/</span>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit Koneksi</h2>
                </div>
                <Link :href="route('kegiatan.partisi.index', kegiatan.id)">
                    <SecondaryButton>Ke Partisi →</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">

                <div v-if="flash.success" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 border border-green-200">{{ flash.success }}</div>
                <div v-if="flash.error" class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 border border-red-200">{{ flash.error }}</div>

                <div class="rounded-md bg-indigo-50 border border-indigo-200 px-4 py-2 text-sm text-indigo-800">
                    Klik 2 SubSLS di peta, lalu pilih <b>Putuskan</b> (jika sebenarnya tak terhubung di lapangan)
                    atau <b>Sambungkan</b> (jika terhubung tapi polygon tak bersinggungan). Override dipakai saat partisi auto.
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Peta -->
                    <div class="lg:col-span-2 bg-white shadow-sm sm:rounded-lg p-3">
                        <PetaWilayah
                            :geojson-url="geojsonUrl"
                            :color-map="sorotPasangan"
                            :lines="garis"
                            selectable
                            height="640px"
                            @select="onSelect"
                            @loaded="onLoaded"
                        />
                        <div class="mt-2 flex items-center gap-4 text-xs text-gray-500">
                            <span class="flex items-center gap-1"><span class="inline-block w-4 border-t border-gray-400"></span> bertetangga</span>
                            <span class="flex items-center gap-1"><span class="inline-block w-4 border-t-2 border-green-600"></span> disambung (override)</span>
                            <span class="flex items-center gap-1"><span class="inline-block w-4 border-t-2 border-dashed border-red-600"></span> diputus (override)</span>
                        </div>
                    </div>

                    <!-- Panel -->
                    <div class="space-y-4">
                        <!-- Pasangan terpilih -->
                        <div class="bg-white shadow-sm sm:rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Pasangan Terpilih</h4>
                            <div v-if="selectedPair.length < 2" class="text-sm text-gray-400">
                                Pilih {{ 2 - selectedPair.length }} SubSLS lagi di peta.
                                <ul v-if="selectedPair.length" class="mt-2 text-gray-600">
                                    <li>• {{ namaNode(selectedPair[0]) }}</li>
                                </ul>
                            </div>
                            <div v-else>
                                <p class="text-sm text-gray-700">
                                    <span class="font-medium">{{ namaNode(selectedPair[0]) }}</span>
                                    ↔
                                    <span class="font-medium">{{ namaNode(selectedPair[1]) }}</span>
                                </p>
                                <p class="mt-1 text-xs" :class="relasi?.bertetangga ? 'text-gray-500' : 'text-amber-600'">
                                    {{ relasi?.bertetangga ? 'Saat ini bertetangga (ada koneksi).' : 'Saat ini TIDAK bertetangga.' }}
                                    <span v-if="relasi?.override" class="text-indigo-600">
                                        · sudah ada override: {{ relasi.override.tipe === 'force_connect' ? 'disambung' : 'diputus' }}
                                    </span>
                                </p>
                                <input v-model="form.catatan" type="text" placeholder="Catatan (opsional, mis. terhalang sungai)"
                                    class="mt-3 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <DangerButton class="justify-center" :disabled="form.processing" @click="simpan('force_disconnect')">Putuskan</DangerButton>
                                    <PrimaryButton class="justify-center" :disabled="form.processing" @click="simpan('force_connect')">Sambungkan</PrimaryButton>
                                </div>
                                <button class="mt-2 text-xs text-gray-400 hover:text-gray-600" @click="selectedPair = []">Batalkan pilihan</button>
                            </div>
                        </div>

                        <!-- Daftar override -->
                        <div class="bg-white shadow-sm sm:rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Override Koneksi ({{ overrides.length }})</h4>
                            <div v-if="!overrides.length" class="text-sm text-gray-400">Belum ada override. Koneksi murni dari ketetanggaan polygon.</div>
                            <ul v-else class="space-y-2">
                                <li v-for="o in overrides" :key="o.id" class="flex items-start justify-between gap-2 border-b border-gray-50 pb-2">
                                    <div class="text-sm">
                                        <span :class="['inline-flex rounded px-1.5 py-0.5 text-xs font-semibold mr-1',
                                            o.tipe === 'force_connect' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                                            {{ o.tipe === 'force_connect' ? 'Sambung' : 'Putus' }}
                                        </span>
                                        <span class="text-gray-700">{{ namaNode(o.a_id) }} ↔ {{ namaNode(o.b_id) }}</span>
                                        <p v-if="o.catatan" class="text-xs text-gray-400 mt-0.5">{{ o.catatan }}</p>
                                    </div>
                                    <button @click="hapusOverride(o)" class="text-red-500 hover:text-red-700 text-xs shrink-0">Hapus</button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
