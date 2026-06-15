<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PetaWilayah from '@/Components/PetaWilayah.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    kegiatan: Object,
    sesi: Object,
    rows: Array,
    overall: Object,
    perPpl: Array,
    geojsonUrl: String,
    statusBySubsls: Object,
});

const WARNA = { belum: '#9ca3af', proses: '#f59e0b', selesai: '#22c55e' };
const LABEL = { belum: 'Belum', proses: 'Proses', selesai: 'Selesai' };

const colorMap = computed(() => {
    const m = {};
    for (const [sid, st] of Object.entries(props.statusBySubsls)) m[sid] = WARNA[st] ?? WARNA.belum;
    return m;
});

const pct = (n, total) => (total ? Math.round((n / total) * 100) : 0);

const filter = ref('semua');
const rowsTampil = computed(() =>
    filter.value === 'semua' ? props.rows : props.rows.filter((r) => r.status_lapangan === filter.value),
);

function ubahStatus(row, status) {
    router.patch(route('kegiatan.partisi.monitoringUpdate', { kegiatan: props.kegiatan.id, sesi: props.sesi.id }), {
        subsls_id: row.subsls_id,
        status_lapangan: status,
    }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Monitoring · ${sesi.nama}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('kegiatan.partisi.hasil', { kegiatan: kegiatan.id, sesi: sesi.id })" class="text-gray-400 hover:text-gray-600">← Hasil</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Monitoring: {{ sesi.nama }}</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">

                <!-- Progres keseluruhan -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-sm font-semibold text-gray-700">Progres Pencacahan</h4>
                        <span class="text-sm font-semibold text-green-600">{{ pct(overall.selesai, overall.total) }}% selesai</span>
                    </div>
                    <div class="h-3 w-full rounded-full bg-gray-100 overflow-hidden flex">
                        <div class="h-full bg-green-500" :style="{ width: pct(overall.selesai, overall.total) + '%' }"></div>
                        <div class="h-full bg-amber-400" :style="{ width: pct(overall.proses, overall.total) + '%' }"></div>
                    </div>
                    <div class="mt-2 flex gap-4 text-xs text-gray-500">
                        <span>Total {{ overall.total }}</span>
                        <span class="text-green-600">Selesai {{ overall.selesai }}</span>
                        <span class="text-amber-600">Proses {{ overall.proses }}</span>
                        <span>Belum {{ overall.belum }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Peta status -->
                    <div class="lg:col-span-2 bg-white shadow-sm sm:rounded-lg p-3">
                        <PetaWilayah :geojson-url="geojsonUrl" :color-map="colorMap" height="560px" />
                        <div class="mt-2 flex gap-4 text-xs text-gray-500">
                            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full" :style="{ background: WARNA.belum }"></span> Belum</span>
                            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full" :style="{ background: WARNA.proses }"></span> Proses</span>
                            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full" :style="{ background: WARNA.selesai }"></span> Selesai</span>
                        </div>
                    </div>

                    <!-- Progres per PPL -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Progres per PPL</h4>
                        <ul class="space-y-2 max-h-[520px] overflow-y-auto pr-1">
                            <li v-for="g in perPpl" :key="g.label">
                                <div class="flex items-center justify-between text-xs text-gray-600">
                                    <span class="font-medium">{{ g.label }}</span>
                                    <span>{{ g.selesai }}/{{ g.total }}</span>
                                </div>
                                <div class="mt-1 h-1.5 w-full rounded bg-gray-100 overflow-hidden flex">
                                    <div class="h-full bg-green-500" :style="{ width: pct(g.selesai, g.total) + '%' }"></div>
                                    <div class="h-full bg-amber-400" :style="{ width: pct(g.proses, g.total) + '%' }"></div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Tabel update status -->
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-semibold text-gray-700">Status per SubSLS</h4>
                        <div class="inline-flex rounded-md border border-gray-200 overflow-hidden text-xs">
                            <button v-for="f in ['semua','belum','proses','selesai']" :key="f" @click="filter = f"
                                :class="['px-3 py-1 capitalize', filter === f ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600']">{{ f }}</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto max-h-[480px]">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white">
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="py-2 pr-3">idsubsls</th>
                                    <th class="py-2 pr-3">Desa / SLS</th>
                                    <th class="py-2 pr-3">PPL</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in rowsTampil" :key="r.subsls_id" class="border-b border-gray-50">
                                    <td class="py-1.5 pr-3 font-mono text-xs text-gray-600">{{ r.idsubsls }}</td>
                                    <td class="py-1.5 pr-3 text-gray-700">{{ r.nmdesa }} · {{ r.nmsls }}</td>
                                    <td class="py-1.5 pr-3 text-gray-600">{{ r.ppl_label }}</td>
                                    <td class="py-1.5">
                                        <select :value="r.status_lapangan" @change="ubahStatus(r, $event.target.value)"
                                            class="rounded border-gray-300 text-xs py-1 focus:border-indigo-500 focus:ring-indigo-500"
                                            :style="{ color: WARNA[r.status_lapangan] }">
                                            <option value="belum">Belum</option>
                                            <option value="proses">Proses</option>
                                            <option value="selesai">Selesai</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
