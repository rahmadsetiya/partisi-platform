<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    users: Array,
});

const page = usePage();
const flash = computed(() => page.props.flash);
const currentUserId = computed(() => page.props.auth.user.id);

const roleStyle = {
    admin: 'bg-purple-100 text-purple-700',
    koordinator: 'bg-gray-100 text-gray-700',
};
const roleLabel = { admin: 'Admin', koordinator: 'Koordinator' };

// ---------- Modal Tambah/Edit ----------
const showForm = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', email: '', role: 'koordinator', satker: '', password: '' });

function bukaTambah() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function bukaEdit(u) {
    editingId.value = u.id;
    form.clearErrors();
    form.name = u.name;
    form.email = u.email;
    form.role = u.role;
    form.satker = u.satker ?? '';
    form.password = '';
    showForm.value = true;
}

function simpan() {
    const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
    if (editingId.value) {
        form.put(route('admin.users.update', editingId.value), opts);
    } else {
        form.post(route('admin.users.store'), opts);
    }
}

// ---------- Modal Hapus ----------
const confirmingDelete = ref(null);
const deleteForm = useForm({});

function hapus() {
    deleteForm.delete(route('admin.users.destroy', confirmingDelete.value.id), {
        preserveScroll: true,
        onFinish: () => { confirmingDelete.value = null; },
    });
}
</script>

<template>
    <Head title="Pengguna" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Manajemen Pengguna</h2>
                <PrimaryButton @click="bukaTambah">+ Tambah Pengguna</PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">

                <div v-if="flash.success" class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 border border-green-200">{{ flash.success }}</div>
                <div v-if="flash.error" class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 border border-red-200">{{ flash.error }}</div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="px-6 py-3 font-medium">Nama</th>
                                <th class="px-6 py-3 font-medium">Surel</th>
                                <th class="px-6 py-3 font-medium">Peran</th>
                                <th class="px-6 py-3 font-medium">Satker</th>
                                <th class="px-6 py-3 font-medium text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-medium text-gray-800">
                                    {{ u.name }}
                                    <span v-if="u.id === currentUserId" class="text-xs text-gray-400">(Anda)</span>
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ u.email }}</td>
                                <td class="px-6 py-3">
                                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', roleStyle[u.role]]">{{ roleLabel[u.role] }}</span>
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ u.satker ?? '—' }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">
                                    <button @click="bukaEdit(u)" class="text-indigo-600 hover:text-indigo-800 text-xs mr-3">Edit</button>
                                    <button v-if="u.id !== currentUserId" @click="confirmingDelete = u" class="text-red-500 hover:text-red-700 text-xs">Hapus</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- Modal Tambah/Edit -->
        <Modal :show="showForm" @close="showForm = false">
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ editingId ? 'Edit Pengguna' : 'Tambah Pengguna' }}</h3>

                <div>
                    <InputLabel for="u_name" value="Nama" />
                    <TextInput id="u_name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="u_email" value="Surel" />
                    <TextInput id="u_email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="u_role" value="Peran" />
                        <select id="u_role" v-model="form.role"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="koordinator">Koordinator</option>
                            <option value="admin">Admin</option>
                        </select>
                        <InputError :message="form.errors.role" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="u_satker" value="Satuan Kerja (opsional)" />
                        <TextInput id="u_satker" v-model="form.satker" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.satker" class="mt-1" />
                    </div>
                </div>

                <div>
                    <InputLabel for="u_password" :value="editingId ? 'Kata Sandi (kosongkan jika tak diubah)' : 'Kata Sandi'" />
                    <TextInput id="u_password" v-model="form.password" type="password" class="mt-1 block w-full" :required="!editingId" autocomplete="new-password" />
                    <InputError :message="form.errors.password" class="mt-1" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <SecondaryButton @click="showForm = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="form.processing" @click="simpan">
                        {{ form.processing ? 'Menyimpan...' : (editingId ? 'Simpan Perubahan' : 'Simpan Pengguna') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Modal Hapus -->
        <Modal :show="!!confirmingDelete" @close="confirmingDelete = null">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900">Hapus Pengguna</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Yakin menghapus <span class="font-medium">{{ confirmingDelete?.name }}</span>? Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <SecondaryButton @click="confirmingDelete = null">Batal</SecondaryButton>
                    <DangerButton :disabled="deleteForm.processing" @click="hapus">Hapus</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
