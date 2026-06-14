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
    petugasPpl: Array,
    petugasPml: Array,
    petugasTersedia: Array,
    jumlahSesi: Number,
    sesiFinal: Object,
    jumlahOverride: Number,
});

const flash = computed(() => usePage().props.flash);

// ---------- Assign petugas ----------
const tambahPpl = useForm({ petugas_id: '', peran: 'ppl' });
const tambahPml = useForm({ petugas_id: '', peran: 'pml' });

function assign(form) {
    if (!form.petugas_id) return;
    form.post(route('kegiatan.petugas.store', props.kegiatan.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('petugas_id'),
    });
}

function lepasPetugas(kp) {
    if (confirm(`Lepas ${kp.label} (${kp.petugas?.nama}) dari kegiatan ini?`)) {
        router.delete(route('kegiatan.petugas.destroy', { kegiatan: props.kegiatan.id, kegiatanPetugas: kp.id }), {
            preserveScroll: true,
        });
    }
}

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

                <!-- Petugas Lapangan -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-4">Petugas Lapangan</h4>

                        <div v-if="!petugasTersedia.length && !petugasPpl.length && !petugasPml.length"
                            class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 mb-4">
                            Belum ada data petugas. Tambahkan dulu di menu
                            <Link :href="route('petugas.index')" class="font-medium underline">Petugas</Link>.
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- PPL -->
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">
                                    PPL — Pencacah ({{ petugasPpl.length }})
                                </p>
                                <ul class="space-y-1 mb-3">
                                    <li v-for="kp in petugasPpl" :key="kp.id"
                                        class="flex items-center justify-between rounded border border-gray-100 bg-gray-50 px-3 py-1.5 text-sm">
                                        <span>
                                            <span class="font-medium text-gray-700">{{ kp.label }}</span>
                                            <span class="text-gray-600"> · {{ kp.petugas?.nama }}</span>
                                            <span v-if="kp.petugas?.nip" class="text-gray-400"> ({{ kp.petugas.nip }})</span>
                                        </span>
                                        <button @click="lepasPetugas(kp)" class="text-red-500 hover:text-red-700 text-xs">Lepas</button>
                                    </li>
                                    <li v-if="!petugasPpl.length" class="text-sm text-gray-400 px-1">Belum ada PPL.</li>
                                </ul>
                                <div class="flex items-center gap-2">
                                    <select v-model="tambahPpl.petugas_id"
                                        class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Pilih petugas --</option>
                                        <option v-for="p in petugasTersedia" :key="p.id" :value="p.id">
                                            {{ p.nama }}{{ p.nip ? ' (' + p.nip + ')' : '' }}
                                        </option>
                                    </select>
                                    <SecondaryButton :disabled="!tambahPpl.petugas_id || tambahPpl.processing" @click="assign(tambahPpl)">
                                        + PPL
                                    </SecondaryButton>
                                </div>
                            </div>

                            <!-- PML -->
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">
                                    PML — Pengawas ({{ petugasPml.length }})
                                </p>
                                <ul class="space-y-1 mb-3">
                                    <li v-for="kp in petugasPml" :key="kp.id"
                                        class="flex items-center justify-between rounded border border-gray-100 bg-gray-50 px-3 py-1.5 text-sm">
                                        <span>
                                            <span class="font-medium text-gray-700">{{ kp.label }}</span>
                                            <span class="text-gray-600"> · {{ kp.petugas?.nama }}</span>
                                            <span v-if="kp.petugas?.nip" class="text-gray-400"> ({{ kp.petugas.nip }})</span>
                                        </span>
                                        <button @click="lepasPetugas(kp)" class="text-red-500 hover:text-red-700 text-xs">Lepas</button>
                                    </li>
                                    <li v-if="!petugasPml.length" class="text-sm text-gray-400 px-1">Belum ada PML.</li>
                                </ul>
                                <div class="flex items-center gap-2">
                                    <select v-model="tambahPml.petugas_id"
                                        class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Pilih petugas --</option>
                                        <option v-for="p in petugasTersedia" :key="p.id" :value="p.id">
                                            {{ p.nama }}{{ p.nip ? ' (' + p.nip + ')' : '' }}
                                        </option>
                                    </select>
                                    <SecondaryButton :disabled="!tambahPml.petugas_id || tambahPml.processing" @click="assign(tambahPml)">
                                        + PML
                                    </SecondaryButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sesi Partisi -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700">Sesi Partisi</h4>
                            <p class="mt-1 text-sm text-gray-500">
                                <template v-if="sesiFinal">
                                    <span class="text-green-600 font-medium">Final: {{ sesiFinal.nama }}</span>
                                    <span class="text-gray-500"> · {{ sesiFinal.detail_count.toLocaleString('id-ID') }} SubSLS dibagi</span>
                                    <span v-if="sesiFinal.cv !== null" class="text-gray-500"> · CV {{ (sesiFinal.cv * 100).toFixed(1) }}%</span>
                                </template>
                                <template v-else-if="jumlahSesi">
                                    <span class="text-amber-600 font-medium">{{ jumlahSesi }} sesi draft</span>
                                    <span class="text-gray-400"> · belum ada yang difinalkan</span>
                                </template>
                                <span v-else class="text-gray-400">Belum ada pembagian wilayah.</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link v-if="jumlahWilayah" :href="route('kegiatan.koneksi.index', kegiatan.id)">
                                <SecondaryButton>Edit Koneksi{{ jumlahOverride ? ` (${jumlahOverride})` : '' }}</SecondaryButton>
                            </Link>
                            <Link :href="route('kegiatan.partisi.index', kegiatan.id)">
                                <SecondaryButton>{{ jumlahSesi ? 'Kelola Partisi' : 'Mulai Partisi' }}</SecondaryButton>
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
