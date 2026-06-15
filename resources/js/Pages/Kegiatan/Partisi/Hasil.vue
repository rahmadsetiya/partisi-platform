<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PetaWilayah from '@/Components/PetaWilayah.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import * as XLSX from 'xlsx';

const props = defineProps({
    kegiatan: Object,
    sesi: Object,
    rows: Array,
    ringkasan: Array,
    ringkasanPml: { type: Array, default: () => [] },
    totalMuatan: Number,
    geojsonUrl: String,
    pplBySubsls: { type: Object, default: () => ({}) },
    pmlBySubsls: { type: Object, default: () => ({}) },
});

const isFinal = computed(() => props.sesi.status === 'final');

// ---------- Peta hasil (warna per PPL / per PML) ----------
const PALET = [
    '#ef4444', '#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#ec4899',
    '#14b8a6', '#f97316', '#6366f1', '#84cc16', '#06b6d4', '#d946ef',
    '#eab308', '#10b981', '#8b5cf6', '#f43f5e', '#0ea5e9', '#65a30d',
];
const warna = (i) => PALET[i % PALET.length];
const modePeta = ref('ppl'); // 'ppl' | 'pml'

const colorMap = computed(() => {
    const src = modePeta.value === 'pml' ? props.pmlBySubsls : props.pplBySubsls;
    const m = {};
    for (const [sid, idx] of Object.entries(src)) m[sid] = warna(idx);
    return m;
});

const legenda = computed(() =>
    (modePeta.value === 'pml' ? props.ringkasanPml : props.ringkasan).filter((g) => g.jumlah > 0),
);

// Ringkasan kualitas keseimbangan beban antar PPL.
const statBeban = computed(() => {
    if (!props.ringkasan.length) return null;
    const muatan = props.ringkasan.map((g) => g.muatan);
    return {
        min: Math.min(...muatan),
        max: Math.max(...muatan),
        gap: Math.max(...muatan) - Math.min(...muatan),
        kosong: props.ringkasan.filter((g) => g.jumlah === 0).length,
    };
});

function cvLabel(cv) {
    return cv === null || cv === undefined ? '—' : (cv * 100).toFixed(1) + '%';
}

function namaFile(ext) {
    const slug = `${props.kegiatan.nama}-${props.sesi.nama}`
        .replace(/[^a-z0-9]+/gi, '-')
        .replace(/^-+|-+$/g, '')
        .toLowerCase();
    return `partisi-${slug}.${ext}`;
}

function unduhExcel() {
    // Sheet 1: detail per SubSLS
    const detail = props.rows.map((r) => ({
        idsubsls: r.idsubsls,
        Kecamatan: r.nmkec,
        Desa: r.nmdesa,
        SLS: r.nmsls,
        Muatan: r.muatan,
        PPL: `${r.ppl_label} - ${r.ppl_nama}`,
        NIP_PPL: r.ppl_nip,
        PML: r.pml_label ? `${r.pml_label} - ${r.pml_nama}` : '',
    }));

    // Sheet 2: ringkasan beban per PPL
    const ringkasan = props.ringkasan.map((g) => ({
        PPL: g.label,
        Nama: g.nama,
        PML: g.pml ?? '',
        'Jumlah SubSLS': g.jumlah,
        'Total Muatan': g.muatan,
    }));

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(detail), 'Detail SubSLS');
    XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(ringkasan), 'Ringkasan per PPL');
    XLSX.writeFile(wb, namaFile('xlsx'));
}

function cetak() {
    window.print();
}

// Detail dikelompokkan per PPL untuk tampilan & cetak
const grup = computed(() => {
    const map = new Map();
    for (const r of props.rows) {
        if (!map.has(r.ppl_label)) {
            map.set(r.ppl_label, { label: r.ppl_label, nama: r.ppl_nama, pml: r.pml_label, items: [] });
        }
        map.get(r.ppl_label).items.push(r);
    }
    return [...map.values()];
});
</script>

<template>
    <Head :title="`Hasil · ${sesi.nama}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('kegiatan.partisi.index', kegiatan.id)" class="text-gray-400 hover:text-gray-600">← Sesi Partisi</Link>
                    <span class="text-gray-300">/</span>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Hasil: {{ sesi.nama }}</h2>
                </div>
                <div class="flex items-center gap-2 no-print">
                    <Link :href="route('kegiatan.partisi.monitoring', { kegiatan: kegiatan.id, sesi: sesi.id })">
                        <SecondaryButton>Monitoring</SecondaryButton>
                    </Link>
                    <Link :href="route('kegiatan.partisi.suratTugas', { kegiatan: kegiatan.id, sesi: sesi.id })">
                        <SecondaryButton>Surat Tugas</SecondaryButton>
                    </Link>
                    <SecondaryButton @click="cetak">Cetak / PDF</SecondaryButton>
                    <PrimaryButton @click="unduhExcel">Unduh Excel</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-8 print:py-0">
            <div id="cetak-area" class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6 print:max-w-none print:px-0">

                <!-- Kop / info -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6 print:shadow-none print:p-0">
                    <h3 class="text-lg font-semibold text-gray-900">Hasil Pembagian Wilayah Kerja</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ kegiatan.nama }} · {{ kegiatan.jenis === 'berkala' ? 'Berkala' : 'Insidentil' }} {{ kegiatan.tahun }}
                        <span v-if="kegiatan.gelombang"> · {{ kegiatan.gelombang }}</span>
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        Sesi: {{ sesi.nama }} ({{ isFinal ? 'Final' : 'Draft' }}) ·
                        {{ rows.length.toLocaleString('id-ID') }} SubSLS ·
                        total muatan {{ totalMuatan.toLocaleString('id-ID') }} ·
                        CV {{ cvLabel(sesi.cv) }}
                    </p>
                    <p v-if="statBeban" class="mt-1 text-xs text-gray-500">
                        Beban per PPL: min {{ statBeban.min.toLocaleString('id-ID') }} · max {{ statBeban.max.toLocaleString('id-ID') }} ·
                        selisih (gap) {{ statBeban.gap.toLocaleString('id-ID') }}
                        <span v-if="statBeban.kosong" class="text-amber-600"> · {{ statBeban.kosong }} PPL tanpa wilayah</span>
                    </p>
                    <p v-if="!isFinal" class="mt-2 text-xs text-amber-600 no-print">Catatan: sesi masih draft — hasil bisa berubah.</p>
                </div>

                <!-- Peta hasil -->
                <div class="bg-white shadow-sm sm:rounded-lg p-4 no-print">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-semibold text-gray-700">Peta Hasil</h4>
                        <div class="inline-flex rounded-md border border-gray-200 overflow-hidden text-xs">
                            <button @click="modePeta = 'ppl'" :class="['px-3 py-1', modePeta === 'ppl' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600']">Per PPL</button>
                            <button @click="modePeta = 'pml'" :disabled="!ringkasanPml.length"
                                :class="['px-3 py-1', modePeta === 'pml' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600', !ringkasanPml.length ? 'opacity-40 cursor-not-allowed' : '']">Per PML</button>
                        </div>
                    </div>
                    <PetaWilayah :geojson-url="geojsonUrl" :color-map="colorMap" height="520px" />
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1">
                        <span v-for="g in legenda" :key="g.label" class="flex items-center gap-1.5 text-xs text-gray-600">
                            <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: warna(g.group_id) }"></span>
                            {{ g.label }} <span class="text-gray-400">· {{ g.jumlah }} SubSLS · {{ g.muatan.toLocaleString('id-ID') }}</span>
                        </span>
                    </div>
                </div>

                <!-- Ringkasan per PPL -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6 print:shadow-none print:p-0">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Ringkasan Beban per PPL</h4>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="py-2 pr-3">PPL</th>
                                <th class="py-2 pr-3">Nama</th>
                                <th class="py-2 pr-3">PML</th>
                                <th class="py-2 pr-3 text-right">SubSLS</th>
                                <th class="py-2 text-right">Muatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="g in ringkasan" :key="g.label" class="border-b border-gray-50">
                                <td class="py-1.5 pr-3 font-medium text-gray-800">{{ g.label }}</td>
                                <td class="py-1.5 pr-3 text-gray-700">{{ g.nama }}</td>
                                <td class="py-1.5 pr-3 text-gray-500">{{ g.pml ?? '—' }}</td>
                                <td class="py-1.5 pr-3 text-right text-gray-700">{{ g.jumlah.toLocaleString('id-ID') }}</td>
                                <td class="py-1.5 text-right text-gray-700">{{ g.muatan.toLocaleString('id-ID') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Detail per PPL -->
                <div v-for="g in grup" :key="g.label" class="bg-white shadow-sm sm:rounded-lg p-6 print:shadow-none print:p-0 print:break-inside-avoid">
                    <h4 class="text-sm font-semibold text-gray-800 mb-2">
                        {{ g.label }} — {{ g.nama }}
                        <span v-if="g.pml" class="font-normal text-gray-500">· PML: {{ g.pml }}</span>
                        <span class="font-normal text-gray-400"> · {{ g.items.length }} SubSLS</span>
                    </h4>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="py-1.5 pr-3">idsubsls</th>
                                <th class="py-1.5 pr-3">Kecamatan</th>
                                <th class="py-1.5 pr-3">Desa</th>
                                <th class="py-1.5 pr-3">SLS</th>
                                <th class="py-1.5 text-right">Muatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in g.items" :key="r.idsubsls" class="border-b border-gray-50">
                                <td class="py-1 pr-3 font-mono text-xs text-gray-600">{{ r.idsubsls }}</td>
                                <td class="py-1 pr-3 text-gray-700">{{ r.nmkec }}</td>
                                <td class="py-1 pr-3 text-gray-700">{{ r.nmdesa }}</td>
                                <td class="py-1 pr-3 text-gray-700">{{ r.nmsls }}</td>
                                <td class="py-1 text-right text-gray-700">{{ r.muatan ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@media print {
    nav,
    .no-print {
        display: none !important;
    }
    body {
        background: #fff !important;
    }
}
</style>
