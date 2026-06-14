<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    stat: Object,
    kegiatanAktif: Array,
});

const user = computed(() => usePage().props.auth.user);

function progres(k) {
    if (k.final_count > 0) return { label: 'Partisi Final', cls: 'bg-green-100 text-green-700' };
    if (k.sesi_count > 0) return { label: `Draft (${k.sesi_count} sesi)`, cls: 'bg-amber-100 text-amber-700' };
    return { label: 'Belum dipartisi', cls: 'bg-gray-100 text-gray-600' };
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Sapaan + pintasan -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Halo, {{ user.name }} 👋</h3>
                        <p class="text-sm text-gray-500" v-if="user.satker">{{ user.satker }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link :href="route('petugas.index')"><SecondaryButton>Kelola Petugas</SecondaryButton></Link>
                        <Link :href="route('kegiatan.index')"><SecondaryButton>Lihat Kegiatan</SecondaryButton></Link>
                        <Link :href="route('kegiatan.create')"><PrimaryButton>+ Buat Kegiatan</PrimaryButton></Link>
                    </div>
                </div>

                <!-- Kartu statistik -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Kegiatan</p>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ stat.kegiatan_total.toLocaleString('id-ID') }}</p>
                        <div class="mt-3 flex flex-wrap gap-1.5 text-xs">
                            <span class="rounded-full bg-gray-100 text-gray-600 px-2 py-0.5">{{ stat.kegiatan_draft }} draft</span>
                            <span class="rounded-full bg-green-100 text-green-700 px-2 py-0.5">{{ stat.kegiatan_aktif }} aktif</span>
                            <span class="rounded-full bg-blue-100 text-blue-700 px-2 py-0.5">{{ stat.kegiatan_selesai }} selesai</span>
                        </div>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Petugas</p>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ stat.petugas_total.toLocaleString('id-ID') }}</p>
                        <p class="mt-3 text-xs text-gray-400">PPL & PML (master data)</p>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">SubSLS</p>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ stat.subsls_total.toLocaleString('id-ID') }}</p>
                        <p class="mt-3 text-xs text-gray-400">Total wilayah termuat</p>
                    </div>
                </div>

                <!-- Kegiatan aktif -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-semibold text-gray-700">Kegiatan Aktif</h4>
                        <Link :href="route('kegiatan.index')" class="text-sm text-indigo-600 hover:text-indigo-800">Semua kegiatan →</Link>
                    </div>

                    <div v-if="!kegiatanAktif.length" class="text-sm text-gray-400 py-4 text-center">
                        Belum ada kegiatan aktif.
                        <Link :href="route('kegiatan.create')" class="text-indigo-600 hover:underline">Buat kegiatan</Link>.
                    </div>

                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="k in kegiatanAktif" :key="k.id">
                            <Link :href="route('kegiatan.show', k.id)" class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded">
                                <div>
                                    <p class="font-medium text-gray-800">{{ k.nama }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        {{ k.jenis === 'berkala' ? 'Berkala' : 'Insidentil' }} · {{ k.tahun }}<span v-if="k.gelombang"> · {{ k.gelombang }}</span>
                                        · {{ k.wilayah_count.toLocaleString('id-ID') }} SubSLS · {{ k.ppl_count }} PPL
                                    </p>
                                </div>
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold shrink-0', progres(k).cls]">
                                    {{ progres(k).label }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
