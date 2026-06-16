<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import * as XLSX from 'xlsx';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import { FilterMatchMode } from '@primevue/core/api';

defineProps({
    petugas: Array,
});

const filters = ref({ global: { value: null, matchMode: FilterMatchMode.CONTAINS } });

// ---------- Modal Tambah/Edit ----------
const showForm = ref(false);
const editingId = ref(null);
const form = useForm({ nama: '', jenis: 'mitra', nip: '', telepon: '', satker: '' });

function bukaTambah() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function bukaEdit(p) {
    editingId.value = p.id;
    form.clearErrors();
    form.nama = p.nama;
    form.jenis = p.jenis ?? 'mitra';
    form.nip = p.nip ?? '';
    form.telepon = p.telepon ?? '';
    form.satker = p.satker ?? '';
    showForm.value = true;
}

function simpan() {
    const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
    if (editingId.value) {
        form.put(route('petugas.update', editingId.value), opts);
    } else {
        form.post(route('petugas.store'), opts);
    }
}

// ---------- Modal Hapus ----------
const confirmingDelete = ref(null);
const deleteForm = useForm({});

function hapus() {
    deleteForm.delete(route('petugas.destroy', confirmingDelete.value.id), {
        preserveScroll: true,
        onFinish: () => { confirmingDelete.value = null; },
    });
}

// ---------- Import Excel/CSV ----------
const showImport = ref(false);
const importError = ref('');
const importRows = ref([]);
const namaFile = ref('');
const importForm = useForm({ rows: [] });

function onImportFile(e) {
    const file = e.target.files[0];
    importError.value = '';
    importRows.value = [];
    if (!file) return;
    namaFile.value = file.name;

    const reader = new FileReader();
    reader.onload = (ev) => {
        try {
            const wb = XLSX.read(ev.target.result, { type: 'array' });
            const json = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { defval: null });
            if (!json.length) {
                importError.value = 'File kosong atau tidak terbaca.';
                return;
            }
            const headers = Object.keys(json[0]);
            const cari = (re) => headers.find(h => re.test(h));
            const colNama = cari(/nama/i);
            const colJenis = cari(/jenis|organik|mitra/i);
            const colNip = cari(/nip/i);
            const colTelp = cari(/telp|telepon|hp|phone/i);
            const colSatker = cari(/satker|satuan/i);

            if (!colNama) {
                importError.value = 'Kolom "nama" tidak ditemukan di file.';
                return;
            }

            importRows.value = json
                .map(r => ({
                    nama: r[colNama] != null ? String(r[colNama]).trim() : '',
                    jenis: colJenis && /organik/i.test(String(r[colJenis] ?? '')) ? 'organik' : 'mitra',
                    nip: colNip && r[colNip] != null ? String(r[colNip]).trim() : null,
                    telepon: colTelp && r[colTelp] != null ? String(r[colTelp]).trim() : null,
                    satker: colSatker && r[colSatker] != null ? String(r[colSatker]).trim() : null,
                }))
                .filter(r => r.nama !== '');
        } catch {
            importError.value = 'Gagal membaca file. Pastikan .xlsx atau .csv yang valid.';
        }
    };
    reader.readAsArrayBuffer(file);
}

const importCount = computed(() => importRows.value.length);

function kirimImport() {
    importForm.rows = importRows.value;
    importForm.post(route('petugas.import'), {
        onSuccess: () => {
            showImport.value = false;
            importRows.value = [];
            namaFile.value = '';
        },
    });
}
</script>

<template>
    <Head title="Petugas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Daftar Petugas</h2>
                <div class="flex items-center gap-2">
                    <SecondaryButton @click="showImport = !showImport">Import Excel/CSV</SecondaryButton>
                    <button type="button" @click="bukaTambah"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        + Tambah Petugas
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">

                <!-- Panel Import -->
                <div v-if="showImport" class="rounded-lg bg-white shadow-sm p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700">Import Petugas dari Excel/CSV</h3>
                    <p class="text-xs text-gray-500">Kolom dikenali otomatis: <code>nama</code> (wajib), <code>jenis</code> (organik/mitra, default mitra), <code>nip</code>, <code>telepon</code>, <code>satker</code>. NIP duplikat akan dilewati.</p>
                    <input type="file" accept=".xlsx,.xls,.csv" @change="onImportFile"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-sm file:font-medium hover:file:bg-indigo-100 cursor-pointer" />
                    <p v-if="importError" class="text-sm text-red-600">{{ importError }}</p>
                    <div v-if="importCount" class="flex items-center gap-3">
                        <span class="text-sm text-gray-600"><span class="font-semibold">{{ importCount.toLocaleString('id-ID') }}</span> petugas siap diimport.</span>
                        <PrimaryButton :disabled="importForm.processing" @click="kirimImport">Import sekarang</PrimaryButton>
                    </div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <DataTable :value="petugas" paginator :rows="10" :rowsPerPageOptions="[10, 25, 50]"
                        v-model:filters="filters" :globalFilterFields="['nama', 'nip', 'satker', 'jenis']"
                        dataKey="id" removableSort stripedRows class="text-sm">
                        <template #header>
                            <div class="flex justify-end">
                                <InputText v-model="filters['global'].value" placeholder="Cari nama / NIP / satker…" class="text-sm" />
                            </div>
                        </template>
                        <template #empty>
                            <div class="p-6 text-center text-gray-500">Belum ada petugas. <button type="button" @click="bukaTambah" class="text-indigo-600 hover:underline">Tambah petugas pertama.</button></div>
                        </template>

                        <Column field="nama" header="Nama" sortable />
                        <Column field="jenis" header="Jenis" sortable>
                            <template #body="{ data }">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', data.jenis === 'organik' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600']">
                                    {{ data.jenis === 'organik' ? 'Organik' : 'Mitra' }}
                                </span>
                            </template>
                        </Column>
                        <Column field="nip" header="NIP" sortable><template #body="{ data }">{{ data.nip ?? '-' }}</template></Column>
                        <Column field="telepon" header="Telepon"><template #body="{ data }">{{ data.telepon ?? '-' }}</template></Column>
                        <Column field="satker" header="Satuan Kerja" sortable><template #body="{ data }">{{ data.satker ?? '-' }}</template></Column>
                        <Column field="kegiatan_petugas_count" header="Penugasan" sortable />
                        <Column field="aktif_count" header="Kegiatan Aktif" sortable>
                            <template #body="{ data }">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', data.aktif_count >= 3 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600']">
                                    {{ data.aktif_count }}<span v-if="data.aktif_count >= 3"> · padat</span>
                                </span>
                            </template>
                        </Column>
                        <Column field="muatan_final" header="Beban (final)" sortable>
                            <template #body="{ data }">{{ (data.muatan_final ?? 0).toLocaleString('id-ID') }}</template>
                        </Column>
                        <Column header="Aksi">
                            <template #body="{ data }">
                                <Link :href="route('petugas.show', data.id)" class="mr-3 text-gray-600 hover:text-gray-800">Riwayat</Link>
                                <button type="button" @click="bukaEdit(data)" class="mr-3 text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button type="button" @click="confirmingDelete = data" class="text-red-600 hover:text-red-800">Hapus</button>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Modal Tambah/Edit -->
        <Modal :show="showForm" max-width="lg" @close="showForm = false">
            <form @submit.prevent="simpan" class="p-6 space-y-5">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editingId ? 'Edit Petugas' : 'Tambah Petugas' }}
                </h2>

                <div>
                    <InputLabel for="m_nama" value="Nama Petugas" />
                    <TextInput id="m_nama" v-model="form.nama" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.nama" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="m_jenis" value="Jenis" />
                    <select id="m_jenis" v-model="form.jenis"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="mitra">Mitra (bisa PPL/PML)</option>
                        <option value="organik">Organik BPS (hanya PML)</option>
                    </select>
                    <InputError :message="form.errors.jenis" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="m_nip" value="NIP (opsional)" />
                    <TextInput id="m_nip" v-model="form.nip" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.nip" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="m_telepon" value="Telepon (opsional)" />
                        <TextInput id="m_telepon" v-model="form.telepon" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.telepon" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="m_satker" value="Satuan Kerja (opsional)" />
                        <TextInput id="m_satker" v-model="form.satker" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.satker" class="mt-1" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-1">
                    <SecondaryButton type="button" @click="showForm = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : (editingId ? 'Simpan Perubahan' : 'Simpan Petugas') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal Hapus -->
        <Modal :show="confirmingDelete !== null" max-width="md" @close="confirmingDelete = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Hapus petugas?</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Yakin menghapus <span class="font-medium">{{ confirmingDelete?.nama }}</span>?
                    Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <SecondaryButton type="button" @click="confirmingDelete = null">Batal</SecondaryButton>
                    <DangerButton :disabled="deleteForm.processing" @click="hapus">
                        {{ deleteForm.processing ? 'Menghapus...' : 'Hapus' }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
