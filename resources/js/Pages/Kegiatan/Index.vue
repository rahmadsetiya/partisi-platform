<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    kegiatan: Array,
});

const flash = computed(() => usePage().props.flash);

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


                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div v-if="kegiatan.length === 0" class="p-8 text-center text-gray-500">
                        Belum ada kegiatan. <Link :href="route('kegiatan.create')" class="text-indigo-600 hover:underline">Buat kegiatan pertama.</Link>
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nama Kegiatan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Jenis</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tahun</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Gelombang</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tanggal Mulai</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="k in kegiatan" :key="k.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <Link :href="route('kegiatan.show', k.id)" class="font-medium text-gray-900 hover:text-indigo-600">
                                        {{ k.nama }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm capitalize text-gray-600">{{ k.jenis }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ k.tahun }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ k.gelombang ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize', statusStyle[k.status]]">
                                        {{ k.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ formatTanggal(k.tanggal_mulai) }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <Link :href="route('kegiatan.edit', k.id)" class="mr-3 text-indigo-600 hover:text-indigo-800">Edit</Link>
                                    <button @click="hapus(k)" class="text-red-600 hover:text-red-800">Hapus</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
