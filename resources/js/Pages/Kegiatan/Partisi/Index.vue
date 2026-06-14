<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    kegiatan: Object,
    sesiList: Array,
    jumlahPpl: Number,
    jumlahPml: Number,
    jumlahWilayah: Number,
    muatanLengkap: Boolean,
});

const flash = computed(() => usePage().props.flash);

const bisaBuat = computed(() => props.jumlahPpl >= 1 && props.jumlahWilayah >= 1);

const buatForm = useForm({ nama: '' });

function buatSesi() {
    buatForm.post(route('kegiatan.partisi.store', props.kegiatan.id), {
        onSuccess: () => buatForm.reset('nama'),
    });
}

function hapusSesi(sesi) {
    if (confirm(`Hapus sesi "${sesi.nama}"? Semua assignment di sesi ini akan ikut terhapus.`)) {
        router.delete(route('kegiatan.partisi.destroy', { kegiatan: props.kegiatan.id, sesi: sesi.id }), {
            preserveScroll: true,
        });
    }
}

const statusStyle = {
    draft: 'bg-gray-100 text-gray-700',
    final: 'bg-green-100 text-green-700',
};

function formatTanggal(str) {
    if (!str) return '-';
    return new Date(str).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function cvLabel(cv) {
    if (cv === null || cv === undefined) return '—';
    return (cv * 100).toFixed(1) + '%';
}
</script>

<template>
    <Head :title="`Partisi · ${kegiatan.nama}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('kegiatan.show', kegiatan.id)" class="text-gray-400 hover:text-gray-600">← {{ kegiatan.nama }}</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Sesi Partisi</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">

                <div v-if="flash.success" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 border border-green-200">{{ flash.success }}</div>
                <div v-if="flash.error" class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 border border-red-200">{{ flash.error }}</div>

                <!-- Prasyarat -->
                <div v-if="!bisaBuat" class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    Sebelum membuat sesi partisi, pastikan kegiatan punya
                    <span v-if="jumlahWilayah < 1">wilayah kerja (SubSLS)</span>
                    <span v-if="jumlahWilayah < 1 && jumlahPpl < 1"> dan </span>
                    <span v-if="jumlahPpl < 1">minimal satu PPL</span>.
                </div>

                <!-- Buat sesi -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-1">Buat Sesi Partisi Manual</h4>
                        <p class="text-sm text-gray-500 mb-4">
                            {{ jumlahWilayah.toLocaleString('id-ID') }} SubSLS · {{ jumlahPpl }} PPL · {{ jumlahPml }} PML
                            <span v-if="jumlahWilayah && !muatanLengkap" class="text-amber-600"> · muatan belum lengkap (CV bisa kurang akurat)</span>
                        </p>
                        <div class="flex items-center gap-2">
                            <input v-model="buatForm.nama" type="text" placeholder="Nama sesi (opsional)"
                                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            <PrimaryButton :disabled="!bisaBuat || buatForm.processing" @click="buatSesi">+ Buat Sesi</PrimaryButton>
                        </div>
                    </div>
                </div>

                <!-- Daftar sesi -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-4">Daftar Sesi ({{ sesiList.length }})</h4>

                        <div v-if="!sesiList.length" class="text-sm text-gray-400">Belum ada sesi partisi.</div>

                        <ul v-else class="divide-y divide-gray-100">
                            <li v-for="s in sesiList" :key="s.id" class="flex items-center justify-between py-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-800">{{ s.nama }}</span>
                                        <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', statusStyle[s.status]]">
                                            {{ s.status === 'final' ? 'Final' : 'Draft' }}
                                        </span>
                                        <span class="text-xs text-gray-400 uppercase">{{ s.tipe }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ s.detail_count }} SubSLS dibagi · CV {{ cvLabel(s.cv) }}
                                        · dibuat {{ formatTanggal(s.created_at) }}
                                        <span v-if="s.creator"> oleh {{ s.creator.name }}</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Link :href="route('kegiatan.partisi.show', { kegiatan: kegiatan.id, sesi: s.id })">
                                        <SecondaryButton>{{ s.status === 'final' ? 'Lihat' : 'Bagi Wilayah' }}</SecondaryButton>
                                    </Link>
                                    <button @click="hapusSesi(s)" class="text-red-500 hover:text-red-700 text-xs">Hapus</button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
