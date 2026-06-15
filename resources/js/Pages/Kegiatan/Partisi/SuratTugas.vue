<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    kegiatan: Object,
    sesi: Object,
    perPpl: Array,
});

function cetak() {
    window.print();
}

function tgl(str) {
    if (!str) return '-';
    return new Date(str).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
}
</script>

<template>
    <Head :title="`Surat Tugas · ${sesi.nama}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('kegiatan.partisi.hasil', { kegiatan: kegiatan.id, sesi: sesi.id })" class="text-gray-400 hover:text-gray-600">← Hasil</Link>
                    <span class="text-gray-300">/</span>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Surat Tugas</h2>
                </div>
                <div class="flex items-center gap-2 no-print">
                    <span class="text-sm text-gray-500">{{ perPpl.length }} surat (per PPL)</span>
                    <PrimaryButton @click="cetak">Cetak / PDF</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-8 print:py-0">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8 space-y-6 print:max-w-none print:px-0 print:space-y-0">

                <div v-if="!perPpl.length" class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-500 no-print">
                    Belum ada PPL yang mendapat wilayah pada sesi ini.
                </div>

                <section v-for="(p, i) in perPpl" :key="p.ppl_label"
                    :class="['bg-white shadow-sm sm:rounded-lg p-8 print:shadow-none print:rounded-none print:p-0', i > 0 ? 'surat-break' : '']">
                    <!-- Kop -->
                    <div class="text-center border-b-2 border-gray-800 pb-3 mb-4">
                        <h3 class="text-base font-bold uppercase tracking-wide text-gray-900">Surat Tugas Pencacahan</h3>
                        <p class="text-sm text-gray-700">{{ kegiatan.nama }}</p>
                        <p class="text-xs text-gray-500">
                            {{ kegiatan.jenis === 'berkala' ? 'Berkala' : 'Insidentil' }} · {{ kegiatan.tahun }}<span v-if="kegiatan.gelombang"> · {{ kegiatan.gelombang }}</span>
                        </p>
                    </div>

                    <!-- Penugasan -->
                    <p class="text-sm text-gray-800 leading-relaxed">
                        Dengan ini menugaskan:
                    </p>
                    <table class="text-sm text-gray-800 my-2">
                        <tbody>
                            <tr><td class="pr-3 align-top text-gray-500">Nama</td><td class="pr-2">:</td><td class="font-medium">{{ p.ppl_nama }}</td></tr>
                            <tr v-if="p.ppl_nip"><td class="pr-3 align-top text-gray-500">NIP</td><td>:</td><td>{{ p.ppl_nip }}</td></tr>
                            <tr><td class="pr-3 align-top text-gray-500">Sebagai</td><td>:</td><td>PPL ({{ p.ppl_label }})</td></tr>
                            <tr v-if="p.pml_nama"><td class="pr-3 align-top text-gray-500">Pengawas (PML)</td><td>:</td><td>{{ p.pml_nama }}</td></tr>
                        </tbody>
                    </table>
                    <p class="text-sm text-gray-800 leading-relaxed">
                        untuk melaksanakan pencacahan pada <b>{{ p.jumlah }}</b> SubSLS (total muatan
                        <b>{{ p.muatan.toLocaleString('id-ID') }}</b>) periode
                        {{ tgl(kegiatan.tanggal_mulai) }} s.d. {{ tgl(kegiatan.tanggal_selesai) }}, dengan rincian:
                    </p>

                    <!-- Daftar wilayah -->
                    <table class="w-full text-xs mt-3 border-collapse">
                        <thead>
                            <tr class="border-y border-gray-300 text-left text-gray-600">
                                <th class="py-1 pr-2">No</th>
                                <th class="py-1 pr-2">idsubsls</th>
                                <th class="py-1 pr-2">Kecamatan</th>
                                <th class="py-1 pr-2">Desa</th>
                                <th class="py-1 pr-2">SLS</th>
                                <th class="py-1 text-right">Muatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(it, idx) in p.items" :key="it.idsubsls" class="border-b border-gray-100">
                                <td class="py-0.5 pr-2 text-gray-500">{{ idx + 1 }}</td>
                                <td class="py-0.5 pr-2 font-mono">{{ it.idsubsls }}</td>
                                <td class="py-0.5 pr-2">{{ it.nmkec }}</td>
                                <td class="py-0.5 pr-2">{{ it.nmdesa }}</td>
                                <td class="py-0.5 pr-2">{{ it.nmsls }}</td>
                                <td class="py-0.5 text-right">{{ it.muatan ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Tanda tangan -->
                    <div class="mt-8 flex justify-end">
                        <div class="text-center text-sm text-gray-700">
                            <p>........................, {{ tgl(kegiatan.tanggal_mulai) }}</p>
                            <p class="mt-1">Mengetahui,</p>
                            <p>Koordinator</p>
                            <div class="h-16"></div>
                            <p class="border-t border-gray-400 pt-1">( ............................ )</p>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@media print {
    nav,
    .no-print {
        display: none !important;
    }
    body {
        background: #fff !important;
    }
    .surat-break {
        break-before: page;
    }
}
</style>
