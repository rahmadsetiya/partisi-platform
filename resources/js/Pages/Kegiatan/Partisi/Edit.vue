<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PetaWilayah from '@/Components/PetaWilayah.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    kegiatan: Object,
    sesi: Object,
    ppl: Array,
    pml: Array,
    assignments: Object, // { subsls_id: { ppl_id, pml_id } }
    jumlahWilayah: Number,
    geojsonUrl: String,
});

const flash = computed(() => usePage().props.flash);
const isFinal = computed(() => props.sesi.status === 'final');

// Palet warna per PPL (siklus bila PPL > palet).
const PALET = [
    '#ef4444', '#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#ec4899',
    '#14b8a6', '#f97316', '#6366f1', '#84cc16', '#06b6d4', '#d946ef',
    '#eab308', '#10b981', '#8b5cf6', '#f43f5e', '#0ea5e9', '#65a30d',
];
function warnaPpl(pplId) {
    const idx = props.ppl.findIndex((p) => p.id === pplId);
    return idx === -1 ? '#9ca3af' : PALET[idx % PALET.length];
}

// State assignment: subsls_id -> ppl_id ; dan PML per PPL group.
const assign = reactive({}); // subsls_id -> ppl_id
const pplPml = reactive({}); // ppl_id -> pml_id (atau null)
for (const [subslsId, a] of Object.entries(props.assignments)) {
    assign[subslsId] = a.ppl_id;
    if (a.pml_id) pplPml[a.ppl_id] = a.pml_id;
}

const selectedPpl = ref(props.ppl[0]?.id ?? null);
const dirty = ref(false);
const muatanById = ref({}); // subsls_id -> muatan

// Peta colorMap: subsls_id -> warna PPL
const colorMap = computed(() => {
    const m = {};
    for (const [subslsId, pplId] of Object.entries(assign)) {
        m[subslsId] = warnaPpl(pplId);
    }
    return m;
});

function onSelect(subslsId) {
    if (isFinal.value || !selectedPpl.value) return;
    if (assign[subslsId] === selectedPpl.value) {
        delete assign[subslsId]; // klik ulang PPL yang sama = lepas
    } else {
        assign[subslsId] = selectedPpl.value;
    }
    dirty.value = true;
}

function onLoaded({ features }) {
    const m = {};
    for (const f of features) {
        m[f.properties.id] = f.properties.muatan ?? 0;
    }
    muatanById.value = m;
}

// Ringkasan beban per PPL (live)
const beban = computed(() => {
    const out = props.ppl.map((p) => ({ ppl: p, jumlah: 0, muatan: 0 }));
    const byId = Object.fromEntries(out.map((o) => [o.ppl.id, o]));
    for (const [subslsId, pplId] of Object.entries(assign)) {
        const o = byId[pplId];
        if (!o) continue;
        o.jumlah += 1;
        o.muatan += muatanById.value[subslsId] ?? 0;
    }
    return out;
});

const totalTerassign = computed(() => Object.keys(assign).length);
const sisa = computed(() => props.jumlahWilayah - totalTerassign.value);
const maxMuatan = computed(() => Math.max(1, ...beban.value.map((b) => b.muatan)));

// CV live = stddev/mean dari muatan antar PPL (semua PPL, kosong = 0)
const cvLive = computed(() => {
    const loads = beban.value.map((b) => b.muatan);
    if (!loads.length) return null;
    const mean = loads.reduce((a, b) => a + b, 0) / loads.length;
    if (mean <= 0) return null;
    const varian = loads.reduce((a, x) => a + (x - mean) ** 2, 0) / loads.length;
    return Math.sqrt(varian) / mean;
});
function cvLabel(cv) {
    return cv === null || cv === undefined ? '—' : (cv * 100).toFixed(1) + '%';
}

// ----- Simpan & finalkan -----
const saveForm = useForm({ assignments: [] });

function buildPayload() {
    return Object.entries(assign).map(([subslsId, pplId]) => ({
        subsls_id: Number(subslsId),
        ppl_id: pplId,
        pml_id: pplPml[pplId] ?? null,
    }));
}

function simpan(onDone) {
    saveForm.assignments = buildPayload();
    saveForm.patch(route('kegiatan.partisi.assign', { kegiatan: props.kegiatan.id, sesi: props.sesi.id }), {
        preserveScroll: true,
        onSuccess: () => {
            dirty.value = false;
            if (onDone) onDone();
        },
    });
}

function finalkan() {
    if (sisa.value > 0) return;
    const lanjut = () => {
        router.patch(route('kegiatan.partisi.finalize', { kegiatan: props.kegiatan.id, sesi: props.sesi.id }), {}, { preserveScroll: true });
    };
    if (dirty.value) {
        if (!confirm('Ada perubahan belum tersimpan. Simpan lalu finalkan?')) return;
        simpan(lanjut);
    } else {
        lanjut();
    }
}

function reopen() {
    if (confirm('Kembalikan sesi ini ke draft agar bisa diedit?')) {
        router.patch(route('kegiatan.partisi.reopen', { kegiatan: props.kegiatan.id, sesi: props.sesi.id }), {}, { preserveScroll: true });
    }
}

// ----- Jalankan ulang auto -----
const ulangPrioritasDesa = ref(props.sesi.config?.prioritas_desa ?? false);

function jalankanUlang() {
    if (!confirm('Jalankan ulang partisi auto? Pembagian saat ini (termasuk poles manual) akan ditimpa.')) return;
    router.post(route('kegiatan.partisi.regenerate', { kegiatan: props.kegiatan.id, sesi: props.sesi.id }), {
        prioritas_desa: ulangPrioritasDesa.value,
    });
}
</script>

<template>
    <Head :title="`${sesi.nama} · ${kegiatan.nama}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('kegiatan.partisi.index', kegiatan.id)" class="text-gray-400 hover:text-gray-600">← Sesi Partisi</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ sesi.nama }}</h2>
                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', isFinal ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700']">
                    {{ isFinal ? 'Final' : 'Draft' }}
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">

                <div v-if="flash.success" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 border border-green-200">{{ flash.success }}</div>
                <div v-if="flash.error" class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 border border-red-200">{{ flash.error }}</div>

                <div v-if="!isFinal" class="rounded-md bg-indigo-50 border border-indigo-200 px-4 py-2 text-sm text-indigo-800">
                    Pilih PPL di panel kanan, lalu klik polygon di peta untuk membaginya. Klik ulang dengan PPL yang sama untuk melepas.
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Peta -->
                    <div class="lg:col-span-2 bg-white shadow-sm sm:rounded-lg p-3">
                        <PetaWilayah
                            :geojson-url="geojsonUrl"
                            :color-map="colorMap"
                            :selectable="!isFinal"
                            height="640px"
                            @select="onSelect"
                            @loaded="onLoaded"
                        />
                    </div>

                    <!-- Panel -->
                    <div class="space-y-4">
                        <!-- Ringkasan -->
                        <div class="bg-white shadow-sm sm:rounded-lg p-4">
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p class="text-xs text-gray-500">Terbagi</p>
                                    <p class="text-lg font-semibold text-gray-900">{{ totalTerassign }}/{{ jumlahWilayah }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Sisa</p>
                                    <p :class="['text-lg font-semibold', sisa > 0 ? 'text-amber-600' : 'text-green-600']">{{ sisa }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">CV beban</p>
                                    <p class="text-lg font-semibold text-gray-900">{{ cvLabel(cvLive) }}</p>
                                </div>
                            </div>
                            <p class="mt-2 text-[11px] text-gray-400">CV makin kecil = beban antar PPL makin seimbang.</p>
                        </div>

                        <!-- Daftar PPL + beban -->
                        <div class="bg-white shadow-sm sm:rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">PPL — pilih untuk membagi</h4>
                            <ul class="space-y-2 max-h-[420px] overflow-y-auto pr-1">
                                <li v-for="b in beban" :key="b.ppl.id">
                                    <button
                                        type="button"
                                        :disabled="isFinal"
                                        @click="selectedPpl = b.ppl.id"
                                        :class="['w-full text-left rounded-md border px-3 py-2 transition',
                                            selectedPpl === b.ppl.id && !isFinal ? 'border-indigo-400 ring-1 ring-indigo-300 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50',
                                            isFinal ? 'cursor-default' : '']"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="flex items-center gap-2 text-sm font-medium text-gray-800">
                                                <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: warnaPpl(b.ppl.id) }"></span>
                                                {{ b.ppl.label }}
                                            </span>
                                            <span class="text-xs text-gray-500">{{ b.jumlah }} SubSLS · {{ b.muatan.toLocaleString('id-ID') }}</span>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500 truncate">{{ b.ppl.petugas?.nama }}</div>
                                        <div class="mt-1.5 h-1.5 w-full rounded bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded" :style="{ width: (b.muatan / maxMuatan * 100) + '%', backgroundColor: warnaPpl(b.ppl.id) }"></div>
                                        </div>
                                        <div v-if="pml.length" class="mt-2 flex items-center gap-1" @click.stop>
                                            <span class="text-[11px] text-gray-400">PML:</span>
                                            <select v-model="pplPml[b.ppl.id]" :disabled="isFinal"
                                                @change="dirty = true"
                                                class="flex-1 rounded border-gray-300 text-xs py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                                <option :value="undefined">—</option>
                                                <option v-for="m in pml" :key="m.id" :value="m.id">{{ m.label }} · {{ m.petugas?.nama }}</option>
                                            </select>
                                        </div>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Aksi -->
                        <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-2">
                            <template v-if="!isFinal">
                                <PrimaryButton class="w-full justify-center" :disabled="saveForm.processing || !dirty" @click="simpan()">
                                    {{ dirty ? 'Simpan Draft' : 'Tersimpan' }}
                                </PrimaryButton>
                                <SecondaryButton class="w-full justify-center" :disabled="sisa > 0" @click="finalkan">
                                    Finalkan Sesi
                                </SecondaryButton>
                                <p v-if="sisa > 0" class="text-[11px] text-amber-600 text-center">Bagi semua SubSLS dulu untuk finalkan.</p>

                                <!-- Jalankan ulang (khusus sesi auto) -->
                                <div v-if="sesi.tipe === 'auto'" class="mt-2 pt-2 border-t border-gray-100">
                                    <label class="flex items-center gap-2 text-xs text-gray-600 mb-2">
                                        <input v-model="ulangPrioritasDesa" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        Prioritaskan 1 desa per PPL
                                    </label>
                                    <SecondaryButton class="w-full justify-center" @click="jalankanUlang">🔄 Jalankan Ulang Auto</SecondaryButton>
                                    <p class="text-[11px] text-gray-400 text-center mt-1">Menimpa pembagian saat ini dengan hasil algoritma baru.</p>
                                </div>
                            </template>
                            <template v-else>
                                <p class="text-sm text-green-700 text-center">Sesi sudah final.</p>
                                <Link :href="route('kegiatan.partisi.hasil', { kegiatan: kegiatan.id, sesi: sesi.id })" class="block">
                                    <PrimaryButton class="w-full justify-center">Lihat Hasil / Export</PrimaryButton>
                                </Link>
                                <SecondaryButton class="w-full justify-center" @click="reopen">Kembalikan ke Draft</SecondaryButton>
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
