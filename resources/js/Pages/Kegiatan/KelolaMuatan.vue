<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import * as XLSX from 'xlsx';

const flash = computed(() => usePage().props.flash);

const props = defineProps({
    kegiatan: Object,
    rows: Object,    // paginator: { data, links, meta }
    filters: Object, // { q }
    summary: Object, // { total, terisi, total_muatan }
});

const tab = ref('seragam'); // seragam | import | manual

// ---------- Tab Seragam ----------
const seragamForm = useForm({ nilai: 1 });
function terapkanSeragam() {
    seragamForm.post(route('kegiatan.muatan.seragam', props.kegiatan.id), {
        preserveScroll: true,
    });
}

// ---------- Tab Import Excel/CSV ----------
const importError = ref('');
const importRows = ref([]);     // hasil parse: array of objek baris
const importHeaders = ref([]);  // nama kolom
const idCol = ref('');          // kolom idsubsls
const muatanCol = ref('');      // kolom muatan
const namaFile = ref('');
const importForm = useForm({ nama_file: '', map: {} });

function onImportFile(e) {
    const file = e.target.files[0];
    importError.value = '';
    importRows.value = [];
    importHeaders.value = [];
    if (!file) return;

    namaFile.value = file.name;
    const reader = new FileReader();
    reader.onload = (ev) => {
        try {
            const wb = XLSX.read(ev.target.result, { type: 'array' });
            const ws = wb.Sheets[wb.SheetNames[0]];
            const json = XLSX.utils.sheet_to_json(ws, { defval: null });
            if (!json.length) {
                importError.value = 'File kosong atau tidak terbaca.';
                return;
            }
            importRows.value = json;
            importHeaders.value = Object.keys(json[0]);

            // Auto-deteksi kolom idsubsls & muatan
            idCol.value = importHeaders.value.find(h => /idsubsls/i.test(h)) ?? '';
            const bukanId = (h) => !/^(idsubsls|idsls|kd|nm|kode)/i.test(h);
            muatanCol.value = importHeaders.value.find(h => /muatan/i.test(h))
                ?? importHeaders.value.find(h => bukanId(h) && typeof json[0][h] === 'number')
                ?? '';
        } catch {
            importError.value = 'Gagal membaca file. Pastikan .xlsx atau .csv yang valid.';
        }
    };
    reader.readAsArrayBuffer(file);
}

const importMap = computed(() => {
    const map = {};
    if (!idCol.value || !muatanCol.value) return map;
    for (const row of importRows.value) {
        const id = row[idCol.value];
        if (id === null || id === undefined || id === '') continue;
        map[String(id)] = row[muatanCol.value];
    }
    return map;
});

const importCount = computed(() => Object.keys(importMap.value).length);

function kirimImport() {
    importForm.nama_file = namaFile.value;
    importForm.map = importMap.value;
    importForm.post(route('kegiatan.muatan.import', props.kegiatan.id), {
        preserveScroll: true,
        onSuccess: () => {
            importRows.value = [];
            importHeaders.value = [];
            namaFile.value = '';
        },
    });
}

// ---------- Tab Manual ----------
const edits = reactive({}); // { subsls_id: muatan|null }
const manualForm = useForm({ rows: [] });

function nilaiBaris(row) {
    return edits[row.subsls_id] !== undefined ? edits[row.subsls_id] : row.muatan;
}
function setEdit(subslsId, val) {
    edits[subslsId] = val === '' ? null : Number(val);
}
const jumlahEdit = computed(() => Object.keys(edits).length);

function simpanManual() {
    manualForm.rows = Object.entries(edits).map(([subsls_id, muatan]) => ({
        subsls_id: Number(subsls_id),
        muatan,
    }));
    manualForm.patch(route('kegiatan.muatan.manual', props.kegiatan.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            for (const k of Object.keys(edits)) delete edits[k];
        },
    });
}

// Pencarian + pagination (preserveState agar edits tidak hilang)
const cari = ref(props.filters.q ?? '');
function submitCari() {
    router.get(route('kegiatan.muatan.index', props.kegiatan.id), { q: cari.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function gotoPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head :title="`Kelola Muatan — ${kegiatan.nama}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Kelola Muatan</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">

                <Link :href="route('kegiatan.show', kegiatan.id)" class="text-sm text-gray-500 hover:text-gray-700">
                    ← {{ kegiatan.nama }}
                </Link>

                <div v-if="flash.success" class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    {{ flash.success }}
                </div>
                <div v-if="flash.error" class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    {{ flash.error }}
                </div>

                <!-- Ringkasan -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-lg bg-white shadow-sm p-4">
                        <p class="text-xs text-gray-500">Total SubSLS</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ summary.total.toLocaleString('id-ID') }}</p>
                    </div>
                    <div class="rounded-lg bg-white shadow-sm p-4">
                        <p class="text-xs text-gray-500">Muatan Terisi</p>
                        <p class="text-2xl font-semibold" :class="summary.terisi === summary.total ? 'text-green-600' : 'text-amber-600'">
                            {{ summary.terisi.toLocaleString('id-ID') }} / {{ summary.total.toLocaleString('id-ID') }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-white shadow-sm p-4">
                        <p class="text-xs text-gray-500">Total Muatan</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ summary.total_muatan.toLocaleString('id-ID') }}</p>
                    </div>
                </div>

                <!-- Tab -->
                <div class="flex gap-1 border-b border-gray-200">
                    <button v-for="t in [['seragam','Seragam'],['import','Import Excel/CSV'],['manual','Edit Manual']]"
                        :key="t[0]" @click="tab = t[0]"
                        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                        :class="tab === t[0] ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        {{ t[1] }}
                    </button>
                </div>

                <!-- Panel: Seragam -->
                <div v-show="tab === 'seragam'" class="rounded-lg bg-white shadow-sm p-6 space-y-4">
                    <p class="text-sm text-gray-600">Set nilai muatan yang sama untuk <strong>semua</strong> SubSLS. Cocok kalau setiap SubSLS dianggap setara (partisi bagi rata jumlah SubSLS).</p>
                    <div class="flex items-end gap-3">
                        <div>
                            <InputLabel for="nilai" value="Nilai muatan" />
                            <input id="nilai" type="number" min="0" v-model.number="seragamForm.nilai"
                                class="mt-1 w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        </div>
                        <PrimaryButton :disabled="seragamForm.processing" @click="terapkanSeragam">
                            Terapkan ke semua
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Panel: Import -->
                <div v-show="tab === 'import'" class="rounded-lg bg-white shadow-sm p-6 space-y-4">
                    <p class="text-sm text-gray-600">Upload Excel/CSV berisi kolom <code>idsubsls</code> dan kolom muatan. Baris dicocokkan berdasarkan <code>idsubsls</code>.</p>

                    <div>
                        <InputLabel for="impfile" value="File Excel / CSV" />
                        <input id="impfile" type="file" accept=".xlsx,.xls,.csv" @change="onImportFile"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-sm file:font-medium hover:file:bg-indigo-100 cursor-pointer" />
                        <p v-if="importError" class="mt-1 text-sm text-red-600">{{ importError }}</p>
                    </div>

                    <div v-if="importHeaders.length" class="space-y-4 rounded-md bg-gray-50 border border-gray-200 p-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="idcol" value="Kolom idsubsls" />
                                <select id="idcol" v-model="idCol" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="" disabled>-- Pilih --</option>
                                    <option v-for="h in importHeaders" :key="h" :value="h">{{ h }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="mcol" value="Kolom muatan" />
                                <select id="mcol" v-model="muatanCol" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="" disabled>-- Pilih --</option>
                                    <option v-for="h in importHeaders" :key="h" :value="h">{{ h }}</option>
                                </select>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600">
                            <span class="font-semibold">{{ importCount.toLocaleString('id-ID') }}</span> baris siap diimport.
                            <span class="text-gray-400">(SubSLS yang tidak ada di kegiatan ini akan dilewati.)</span>
                        </p>
                        <PrimaryButton :disabled="!importCount || importForm.processing" @click="kirimImport">
                            Import muatan
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Panel: Manual -->
                <div v-show="tab === 'manual'" class="rounded-lg bg-white shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <form @submit.prevent="submitCari" class="flex items-center gap-2">
                            <input v-model="cari" type="text" placeholder="Cari kecamatan / desa / idsubsls"
                                class="w-72 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            <SecondaryButton type="submit">Cari</SecondaryButton>
                        </form>
                        <PrimaryButton :disabled="!jumlahEdit || manualForm.processing" @click="simpanManual">
                            Simpan perubahan<span v-if="jumlahEdit"> ({{ jumlahEdit }})</span>
                        </PrimaryButton>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-md">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">idsubsls</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Kecamatan</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Desa</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">SLS</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 w-32">Muatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in rows.data" :key="row.subsls_id"
                                    :class="edits[row.subsls_id] !== undefined ? 'bg-amber-50' : ''">
                                    <td class="px-3 py-1.5 font-mono text-xs text-gray-700">{{ row.idsubsls }}</td>
                                    <td class="px-3 py-1.5 text-gray-700">{{ row.nmkec }}</td>
                                    <td class="px-3 py-1.5 text-gray-700">{{ row.nmdesa }}</td>
                                    <td class="px-3 py-1.5 text-gray-700">{{ row.nmsls }}</td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" min="0"
                                            :value="nilaiBaris(row)"
                                            @input="setEdit(row.subsls_id, $event.target.value)"
                                            class="w-24 rounded border-gray-300 shadow-sm text-sm py-1" />
                                    </td>
                                </tr>
                                <tr v-if="!rows.data.length">
                                    <td colspan="5" class="px-3 py-6 text-center text-gray-400">Tidak ada data.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="rows.links.length > 3" class="flex flex-wrap gap-1">
                        <button v-for="(link, i) in rows.links" :key="i"
                            @click="gotoPage(link.url)" :disabled="!link.url"
                            v-html="link.label"
                            class="px-3 py-1 text-sm rounded border"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50 disabled:opacity-40'" />
                    </div>
                    <p v-if="jumlahEdit" class="text-xs text-amber-600">
                        {{ jumlahEdit }} baris diubah (di semua halaman) — klik "Simpan perubahan".
                    </p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
