<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    kegiatan: Object,
    uploads: Array,
});

const flash = computed(() => usePage().props.flash);

const form = useForm({
    file: null,
    muatan_col: '',
    level: 'subsls',
});

const preview = ref(null);
const fileError = ref('');

function onFileChange(e) {
    const file = e.target.files[0];
    if (!file) {
        preview.value = null;
        form.file = null;
        return;
    }

    form.file = file;
    form.muatan_col = '';
    preview.value = null;
    fileError.value = '';

    const reader = new FileReader();
    reader.onload = (ev) => {
        try {
            const geo = JSON.parse(ev.target.result);
            if (geo.type !== 'FeatureCollection' || !geo.features?.length) {
                fileError.value = 'File bukan FeatureCollection GeoJSON yang valid.';
                return;
            }
            const firstProps = geo.features[0].properties ?? {};
            const kolom = Object.entries(firstProps).map(([nama, val]) => ({ nama, contoh: val }));

            const defaultCol = kolom.find(k => k.nama === 'Perkiraan_Jumlah_Muatan')?.nama
                ?? kolom.find(k => typeof k.contoh === 'number' || /^\d+(\.\d+)?$/.test(String(k.contoh ?? '')))?.nama
                ?? '';
            form.muatan_col = defaultCol;

            preview.value = { jumlah: geo.features.length, kolom };
        } catch {
            fileError.value = 'Gagal membaca file. Pastikan file adalah JSON yang valid.';
        }
    };
    reader.readAsText(file);
}

function submit() {
    form.post(route('kegiatan.geojson.store', props.kegiatan.id), {
        forceFormData: true,
    });
}

function hapus(uploadId) {
    if (!confirm('Hapus upload ini? Data wilayah terkait mungkin ikut terhapus.')) return;
    router.delete(route('kegiatan.geojson.destroy', { kegiatan: props.kegiatan.id, upload: uploadId }));
}

function formatTanggal(str) {
    if (!str) return '-';
    return new Date(str).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="`Upload GeoJSON — ${kegiatan.nama}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('kegiatan.show', kegiatan.id)" class="text-gray-400 hover:text-gray-600">
                    ← {{ kegiatan.nama }}
                </Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-xl font-semibold text-gray-800">Upload GeoJSON</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8 space-y-6">

                <div v-if="flash.success" class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    {{ flash.success }}
                </div>
                <div v-if="flash.error" class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    {{ flash.error }}
                </div>

                <!-- Riwayat upload -->
                <div v-if="uploads.length" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Riwayat Upload</h3>
                        <ul class="divide-y divide-gray-100">
                            <li v-for="u in uploads" :key="u.id" class="flex items-start justify-between py-3 text-sm">
                                <div>
                                    <p class="font-medium text-gray-900">{{ u.nama_file }}</p>
                                    <p class="text-gray-500 mt-0.5">
                                        {{ u.jumlah_fitur?.toLocaleString('id-ID') }} fitur
                                        · kolom muatan: <code class="bg-gray-100 px-1 rounded text-xs">{{ u.muatan_col }}</code>
                                        · {{ u.level }}
                                    </p>
                                    <p class="text-gray-400 text-xs mt-0.5">
                                        {{ formatTanggal(u.uploaded_at) }} oleh {{ u.uploader?.name ?? '-' }}
                                    </p>
                                </div>
                                <button
                                    @click="hapus(u.id)"
                                    class="ml-4 mt-0.5 text-xs text-red-500 hover:text-red-700 whitespace-nowrap"
                                >
                                    Hapus
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Form upload -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-5">
                        <h3 class="text-sm font-semibold text-gray-700">Upload File GeoJSON Baru</h3>

                        <div v-if="uploads.length" class="rounded-md bg-yellow-50 border border-yellow-200 px-4 py-3 text-sm text-yellow-800">
                            Upload baru akan menggantikan seluruh data wilayah yang sudah ada untuk kegiatan ini.
                        </div>

                        <div>
                            <InputLabel for="level" value="Level GeoJSON" />
                            <select
                                id="level"
                                v-model="form.level"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                <option value="subsls">SubSLS</option>
                                <option value="desa">Desa</option>
                            </select>
                        </div>

                        <div>
                            <InputLabel for="file" value="File GeoJSON" />
                            <input
                                id="file"
                                type="file"
                                accept=".geojson,.json"
                                @change="onFileChange"
                                class="mt-1 block w-full text-sm text-gray-500
                                       file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                       file:bg-indigo-50 file:text-indigo-700 file:text-sm file:font-medium
                                       hover:file:bg-indigo-100 cursor-pointer"
                            />
                            <p class="mt-1 text-xs text-gray-400">Format: .geojson atau .json · Maks. 30 MB</p>
                            <p v-if="fileError" class="mt-1 text-sm text-red-600">{{ fileError }}</p>
                            <InputError :message="form.errors.file" class="mt-1" />
                        </div>

                        <!-- Preview & pilih kolom muatan -->
                        <div v-if="preview" class="rounded-md bg-gray-50 border border-gray-200 p-4 space-y-4">
                            <p class="text-sm text-gray-700">
                                <span class="font-semibold">{{ preview.jumlah.toLocaleString('id-ID') }}</span> fitur terdeteksi.
                            </p>

                            <div>
                                <InputLabel for="muatan_col" value="Kolom Muatan" />
                                <select
                                    id="muatan_col"
                                    v-model="form.muatan_col"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    required
                                >
                                    <option value="" disabled>-- Pilih kolom --</option>
                                    <option v-for="k in preview.kolom" :key="k.nama" :value="k.nama">
                                        {{ k.nama }}
                                        <template v-if="k.contoh !== null && k.contoh !== undefined">
                                            (contoh: {{ k.contoh }})
                                        </template>
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-gray-400">Pilih kolom yang mewakili beban kerja (jumlah KK, RT, dll).</p>
                                <InputError :message="form.errors.muatan_col" class="mt-1" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-1">
                            <Link :href="route('kegiatan.show', kegiatan.id)">
                                <SecondaryButton type="button">Batal</SecondaryButton>
                            </Link>
                            <PrimaryButton :disabled="form.processing || !preview || !form.muatan_col">
                                {{ form.processing ? 'Memproses...' : 'Upload & Proses' }}
                            </PrimaryButton>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
