<script setup>
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const page = usePage();
const toasts = ref([]);
let seq = 0;

function tambah(type, msg) {
    if (!msg) return;
    const id = ++seq;
    toasts.value.push({ id, type, msg });
    setTimeout(() => tutup(id), 4500);
}

function tutup(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

watch(
    () => page.props.flash,
    (f) => {
        if (!f) return;
        tambah('success', f.success);
        tambah('error', f.error);
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <div class="fixed top-4 right-4 z-[2000] w-80 max-w-[90vw] space-y-2">
        <transition-group name="toast">
            <div
                v-for="t in toasts"
                :key="t.id"
                :class="[
                    'flex items-start gap-2 rounded-md border px-4 py-3 text-sm shadow-lg',
                    t.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800',
                ]"
            >
                <span class="mt-0.5">{{ t.type === 'success' ? '✅' : '⚠️' }}</span>
                <span class="flex-1">{{ t.msg }}</span>
                <button @click="tutup(t.id)" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(20px);
}
</style>
