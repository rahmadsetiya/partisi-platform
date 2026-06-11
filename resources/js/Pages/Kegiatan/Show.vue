<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    kegiatan: Object,
    jumlahWilayah: Number,
    muatanTerisi: Number,
    totalMuatan: Number,
});

const flash = computed(() => usePage().props.flash);

const statusStyle = {
    draft:   'bg-gray-100 text-gray-700',
    aktif:   'bg-green-100 text-green-700',
    selesai: 'bg-blue-100 text-blue-700',
};

const statusLabel = {
    draft:   'Draft',
    aktif:   'Aktif',
    selesai: 'Selesai',
};

const transisiStatus = {
    draft:   [{ value: 'aktif', label: 'Tandai Aktif' }],
    aktif:   [{ value: 'selesai', label: 'Tandai Selesai' }, { value: 'draft', label: 'Kembalikan ke Draft' }],
    selesai: [{ value: 'aktif', label: 'Buka Kembali' }],
};

const statusForm = useForm({ status: '' });

function ubahStatus(status) {
    statusForm.status = status;
    statusForm.patch(route('kegiatan.updateStatus', props.kegiatan.id));
}

function hapus() {
    if (confirm(`Hapus kegiatan "${props.kegiatan.nama}"? Tindakan ini tidak dapat dibatalkan.`)) {
        router.delete(route('kegiatan.destroy', props.kegiatan.id));
    }
}

function formatTanggal(str) {
    if (!str) return '-';
    return new Date(str).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
}
</script>

<template>
    <Head :title="kegiatan.nama" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('kegiatan.index')" class="text-gray-400 hover:text-gray-600">
                        ← Kegiatan
                    </Link>
                    <span class="text-gray-300">/</span>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ kegiatan.nama }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('kegiatan.edit', kegiatan.id)">
                        <SecondaryButton>Edit</SecondaryButton>
                    </Link>
                    <DangerButton @click="hapus">Hapus</DangerButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">

                <div v-if="flash.success" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 border border-green-200">
                    {{ flash.success }}
                </div>
                <div v-if="flash.error" class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 border border-red-200">
                    {{ flash.error }}
                </div>

                <!-- Info utama -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ kegiatan.nama }}</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ kegiatan.jenis === 'berkala' ? 'Berkala' : 'Insidentil' }}
                                    · {{ kegiatan.tahun }}
                                    <span v-if="kegiatan.gelombang"> · {{ kegiatan.gelombang }}</span>
                                </p>
                            </div>
                            <span :class="['inline-flex rounded-full px-3 py-1 text-sm font-semibold', statusStyle[kegiatan.status]]">
                                {{ statusLabel[kegiatan.status] }}
                            </span>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                            <div>
                                <dt class="font-medium text-gray-500">Tanggal Mulai</dt>
                                <dd class="mt-1 text-gray-900">{{ formatTanggal(kegiatan.tanggal_mulai) }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500">Tanggal Selesai</dt>
                                <dd class="mt-1 text-gray-900">{{ formatTanggal(kegiatan.tanggal_selesai) }}</dd>
                            </div>
                            <div v-if="kegiatan.creator">
                                <dt class="font-medium text-gray-500">Dibuat oleh</dt>
                                <dd class="mt-1 text-gray-900">{{ kegiatan.creator.name }}</dd>
                            </div>
                            <div v-if="kegiatan.deskripsi" class="col-span-2">
                                <dt class="font-medium text-gray-500">Deskripsi</dt>
                                <dd class="mt-1 text-gray-900 whitespace-pre-line">{{ kegiatan.deskripsi }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Wilayah kerja -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700">Wilayah Kerja</h4>
                            <p class="mt-1 text-sm text-gray-500">
                                <template v-if="jumlahWilayah">
                                    <span class="font-medium text-gray-900">{{ jumlahWilayah.toLocaleString('id-ID') }}</span> SubSLS dimuat
                                    <template v-if="kegiatan.geojson_uploads?.[0]">
                                        dari <span class="font-medium">{{ kegiatan.geojson_uploads[0].nama_file }}</span>
                                    </template>
                                </template>
                                <span v-else class="text-gray-400">Belum ada data wilayah.</span>
                            </p>
                            <p v-if="jumlahWilayah" class="mt-1 text-sm">
                                <template v-if="muatanTerisi === jumlahWilayah">
                                    <span class="text-green-600 font-medium">Muatan lengkap</span>
                                    <span class="text-gray-500"> · total {{ totalMuatan.toLocaleString('id-ID') }}</span>
                                </template>
                                <template v-else>
                                    <span class="text-amber-600 font-medium">Muatan {{ muatanTerisi.toLocaleString('id-ID') }}/{{ jumlahWilayah.toLocaleString('id-ID') }} terisi</span>
                                    <span class="text-gray-400"> · lengkapi lewat Kelola Muatan</span>
                                </template>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link v-if="jumlahWilayah" :href="route('kegiatan.muatan.index', kegiatan.id)">
                                <SecondaryButton>Kelola Muatan</SecondaryButton>
                            </Link>
                            <Link :href="route('kegiatan.geojson.create', kegiatan.id)">
                                <SecondaryButton>
                                    {{ jumlahWilayah ? 'Kelola GeoJSON' : 'Upload GeoJSON' }}
                                </SecondaryButton>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Ubah status -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Ubah Status</h4>
                        <div class="flex items-center gap-3">
                            <button
                                v-for="t in transisiStatus[kegiatan.status]"
                                :key="t.value"
                                @click="ubahStatus(t.value)"
                                :disabled="statusForm.processing"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            >
                                {{ t.label }}
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
