<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    petugas: Object,
});

const form = useForm({
    nama: props.petugas.nama,
    nip: props.petugas.nip ?? '',
    telepon: props.petugas.telepon ?? '',
    satker: props.petugas.satker ?? '',
});

function submit() {
    form.patch(route('petugas.update', props.petugas.id));
}
</script>

<template>
    <Head title="Edit Petugas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit Petugas</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">

                        <div>
                            <InputLabel for="nama" value="Nama Petugas" />
                            <TextInput id="nama" v-model="form.nama" type="text" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.nama" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="nip" value="NIP (opsional)" />
                            <TextInput id="nip" v-model="form.nip" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors.nip" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="telepon" value="Telepon (opsional)" />
                                <TextInput id="telepon" v-model="form.telepon" type="text" class="mt-1 block w-full" />
                                <InputError :message="form.errors.telepon" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="satker" value="Satuan Kerja (opsional)" />
                                <TextInput id="satker" v-model="form.satker" type="text" class="mt-1 block w-full" />
                                <InputError :message="form.errors.satker" class="mt-1" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <Link :href="route('petugas.index')">
                                <SecondaryButton type="button">Batal</SecondaryButton>
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </PrimaryButton>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
