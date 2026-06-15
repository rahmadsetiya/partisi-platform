<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    petugas: Object,
    riwayat: Array,
});

const statusStyle = {
    draft: 'bg-gray-100 text-gray-700',
    aktif: 'bg-green-100 text-green-700',
    selesai: 'bg-blue-100 text-blue-700',
};

const totalAktif = computed(() => props.riwayat.filter((r) => r.status === 'aktif').length);
const totalMuatan = computed(() => props.riwayat.reduce((s, r) => s + (r.muatan || 0), 0));
</script>

<template>
    <Head :title="`Riwayat · ${petugas.nama}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('petugas.index')" class="text-gray-400 hover:text-gray-600">← Petugas</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ petugas.nama }}</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">

                <!-- Identitas + ringkasan -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900">{{ petugas.nama }}</h3>
                    <p class="text-sm text-gray-500">
                        <span v-if="petugas.nip">NIP {{ petugas.nip }} · </span>
                        <span v-if="petugas.telepon">{{ petugas.telepon }} · </span>
                        {{ petugas.satker ?? '-' }}
                    </p>
                    <div class="mt-4 flex gap-6 text-sm">
                        <div><span class="text-gray-500">Total penugasan:</span> <b>{{ riwayat.length }}</b></div>
                        <div><span class="text-gray-500">Kegiatan aktif:</span> <b :class="totalAktif >= 3 ? 'text-amber-600' : ''">{{ totalAktif }}</b></div>
                        <div><span class="text-gray-500">Total beban (final):</span> <b>{{ totalMuatan.toLocaleString('id-ID') }}</b></div>
                    </div>
                    <p v-if="totalAktif >= 3" class="mt-2 text-xs text-amber-600">⚠ Petugas ini cukup padat — ditugaskan di {{ totalAktif }} kegiatan aktif.</p>
                </div>

                <!-- Riwayat penugasan -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Riwayat Penugasan</h4>
                    <div v-if="!riwayat.length" class="text-sm text-gray-400">Belum pernah ditugaskan di kegiatan mana pun.</div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="py-2 pr-3">Kegiatan</th>
                                <th class="py-2 pr-3">Peran</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 pr-3 text-right">SubSLS</th>
                                <th class="py-2 text-right">Beban</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in riwayat" :key="r.kegiatan_id + '-' + r.label" class="border-b border-gray-50">
                                <td class="py-2 pr-3">
                                    <Link :href="route('kegiatan.show', r.kegiatan_id)" class="text-indigo-600 hover:underline">{{ r.nama }}</Link>
                                    <span class="text-gray-400 text-xs"> · {{ r.tahun }}<span v-if="r.gelombang"> · {{ r.gelombang }}</span></span>
                                </td>
                                <td class="py-2 pr-3">{{ r.label }}</td>
                                <td class="py-2 pr-3">
                                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', statusStyle[r.status]]">{{ r.status }}</span>
                                </td>
                                <td class="py-2 pr-3 text-right text-gray-700">{{ r.jml_subsls ? r.jml_subsls.toLocaleString('id-ID') : '—' }}</td>
                                <td class="py-2 text-right text-gray-700">{{ r.muatan ? r.muatan.toLocaleString('id-ID') : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-gray-400">SubSLS & beban dihitung dari sesi partisi yang sudah final.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
