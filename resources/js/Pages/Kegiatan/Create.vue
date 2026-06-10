<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    nama: '',
    jenis: 'berkala',
    tahun: new Date().getFullYear(),
    gelombang: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
    deskripsi: '',
});

function submit() {
    form.post(route('kegiatan.store'));
}
</script>

<template>
    <Head title="Buat Kegiatan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Buat Kegiatan Baru</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">

                        <div>
                            <InputLabel for="nama" value="Nama Kegiatan" />
                            <TextInput
                                id="nama"
                                v-model="form.nama"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Contoh: SUSENAS Maret 2025"
                                required
                            />
                            <InputError :message="form.errors.nama" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="jenis" value="Jenis Kegiatan" />
                                <select
                                    id="jenis"
                                    v-model="form.jenis"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="berkala">Berkala</option>
                                    <option value="insidentil">Insidentil</option>
                                </select>
                                <InputError :message="form.errors.jenis" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="tahun" value="Tahun" />
                                <TextInput
                                    id="tahun"
                                    v-model="form.tahun"
                                    type="number"
                                    class="mt-1 block w-full"
                                    min="2000"
                                    max="2100"
                                    required
                                />
                                <InputError :message="form.errors.tahun" class="mt-1" />
                            </div>
                        </div>

                        <div v-if="form.jenis === 'berkala'">
                            <InputLabel for="gelombang" value="Gelombang" />
                            <TextInput
                                id="gelombang"
                                v-model="form.gelombang"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Contoh: Maret, Triwulan I"
                            />
                            <InputError :message="form.errors.gelombang" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="tanggal_mulai" value="Tanggal Mulai" />
                                <TextInput
                                    id="tanggal_mulai"
                                    v-model="form.tanggal_mulai"
                                    type="date"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.tanggal_mulai" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="tanggal_selesai" value="Tanggal Selesai" />
                                <TextInput
                                    id="tanggal_selesai"
                                    v-model="form.tanggal_selesai"
                                    type="date"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.tanggal_selesai" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="deskripsi" value="Deskripsi (opsional)" />
                            <textarea
                                id="deskripsi"
                                v-model="form.deskripsi"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Catatan tambahan tentang kegiatan ini..."
                            ></textarea>
                            <InputError :message="form.errors.deskripsi" class="mt-1" />
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <Link :href="route('kegiatan.index')">
                                <SecondaryButton type="button">Batal</SecondaryButton>
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Kegiatan' }}
                            </PrimaryButton>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
