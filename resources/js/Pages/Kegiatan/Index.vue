<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import { FilterMatchMode } from '@primevue/core/api';
import DuplikatKegiatanModal from '@/Components/DuplikatKegiatanModal.vue';

defineProps({
    kegiatan: Array,
});

const filters = ref({ global: { value: null, matchMode: FilterMatchMode.CONTAINS } });

const duplikatTarget = ref(null);

function hapus(kegiatan) {
    if (confirm(`Hapus kegiatan "${kegiatan.nama}"? Tindakan ini tidak dapat dibatalkan.`)) {
        router.delete(route('kegiatan.destroy', kegiatan.id));
    }
}

function formatTanggal(str) {
    if (!str) return '-';
    return new Date(str).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

const statusStyle = {
    draft:   'bg-gray-100 text-gray-700',
    aktif:   'bg-green-100 text-green-700',
    selesai: 'bg-blue-100 text-blue-700',
};
</script>

<template>
    <Head title="Kegiatan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Daftar Kegiatan</h2>
                <Link
                    :href="route('kegiatan.create')"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    + Buat Kegiatan
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">


                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <DataTable :value="kegiatan" paginator :rows="10" :rowsPerPageOptions="[10, 25, 50]"
                        v-model:filters="filters" :globalFilterFields="['nama', 'jenis', 'tahun', 'gelombang', 'status']"
                        dataKey="id" removableSort stripedRows class="text-sm">
                        <template #header>
                            <div class="flex justify-end">
                                <InputText v-model="filters['global'].value" placeholder="Cari kegiatan…" class="text-sm" />
                            </div>
                        </template>
                        <template #empty><div class="p-6 text-center text-gray-500">Belum ada kegiatan.</div></template>

                        <Column field="nama" header="Nama Kegiatan" sortable>
                            <template #body="{ data }">
                                <Link :href="route('kegiatan.show', data.id)" class="font-medium text-indigo-600 hover:underline">{{ data.nama }}</Link>
                            </template>
                        </Column>
                        <Column field="jenis" header="Jenis" sortable><template #body="{ data }"><span class="capitalize">{{ data.jenis }}</span></template></Column>
                        <Column field="tahun" header="Tahun" sortable />
                        <Column field="gelombang" header="Gelombang" sortable><template #body="{ data }">{{ data.gelombang ?? '-' }}</template></Column>
                        <Column field="status" header="Status" sortable>
                            <template #body="{ data }">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize', statusStyle[data.status]]">{{ data.status }}</span>
                            </template>
                        </Column>
                        <Column field="tanggal_mulai" header="Tanggal Mulai" sortable><template #body="{ data }">{{ formatTanggal(data.tanggal_mulai) }}</template></Column>
                        <Column header="Aksi">
                            <template #body="{ data }">
                                <Link :href="route('kegiatan.edit', data.id)" class="mr-3 text-indigo-600 hover:text-indigo-800">Edit</Link>
                                <button @click="duplikatTarget = data" class="mr-3 text-indigo-600 hover:text-indigo-800">Duplikat</button>
                                <button @click="hapus(data)" class="text-red-600 hover:text-red-800">Hapus</button>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <DuplikatKegiatanModal :show="!!duplikatTarget" :kegiatan="duplikatTarget" @close="duplikatTarget = null" />
    </AuthenticatedLayout>
</template>
