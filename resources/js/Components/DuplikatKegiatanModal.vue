<script setup>
import Modal from '@/Components/Modal.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    kegiatan: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
    nama: '',
    salin_wilayah: true,
    salin_petugas: true,
    salin_koneksi: true,
});

// Setiap modal dibuka untuk kegiatan tertentu, set nama default "… (Salinan)".
watch(
    () => props.show,
    (open) => {
        if (open && props.kegiatan) {
            form.reset();
            form.clearErrors();
            form.nama = `${props.kegiatan.nama} (Salinan)`;
        }
    },
);

function tutup() {
    emit('close');
}

function submit() {
    if (!props.kegiatan) return;
    form.post(route('kegiatan.duplicate', props.kegiatan.id), {
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :show="show" @close="tutup">
        <form @submit.prevent="submit" class="p-6">
            <h3 class="text-lg font-semibold text-gray-800">Duplikasi Kegiatan</h3>
            <p class="mt-1 text-sm text-gray-500">
                Membuat <b>draft baru</b> dari kegiatan ini. Hasil partisi (sesi &amp; pembagian) tidak ikut disalin.
            </p>

            <div class="mt-4">
                <InputLabel for="dup-nama" value="Nama kegiatan baru" />
                <TextInput id="dup-nama" v-model="form.nama" type="text" class="mt-1 block w-full" required />
                <InputError :message="form.errors.nama" class="mt-1" />
            </div>

            <div class="mt-4">
                <p class="text-sm font-medium text-gray-700 mb-2">Salin juga:</p>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <Checkbox v-model:checked="form.salin_wilayah" />
                        Wilayah kerja (SubSLS + muatan)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <Checkbox v-model:checked="form.salin_petugas" />
                        Penugasan petugas (PPL / PML)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <Checkbox v-model:checked="form.salin_koneksi" />
                        Override koneksi antar SubSLS
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <SecondaryButton type="button" @click="tutup">Batal</SecondaryButton>
                <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                    Duplikat
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
