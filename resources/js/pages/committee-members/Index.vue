<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Search } from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    members: {
        data: Array<{ id: number; name: string; position: string; agency: string }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    filters?: {
        search?: string;
    };
    flash?: {
        success?: string;
        error?: string;
    };
}>();

// Search state
const searchTerm = ref(props.filters?.search || '');
const isSearching = ref(false);

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>;

const performSearch = () => {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        isSearching.value = true;

        router.get(
            route('committee-members.index'),
            { search: searchTerm.value },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    isSearching.value = false;
                },
            },
        );
    }, 300);
};

watch(searchTerm, performSearch);

const clearSearch = () => {
    searchTerm.value = '';
    router.get(route('committee-members.index'));
};

const goToPage = (url: string) => {
    if (!url) return;
    router.get(
        url,
        { search: searchTerm.value },
        { preserveState: true, preserveScroll: false },
    );
};

// Notifications
const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'top' } });

watch(
    () => props.flash,
    (flash) => {
        if (flash?.success) notyf.success(flash.success);
        if (flash?.error) notyf.error(flash.error);
    },
    { immediate: true, deep: true },
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Committee Registry',
        href: route('committee-members.index'),
    },
];

// Modal state
const showDialog = ref(false);
const editingMember = ref<any>(null);

const form = useForm({
    name: '',
    position: '',
    agency: '',
});

function openEditDialog(member: any) {
    editingMember.value = member;
    form.name = member.name;
    form.position = member.position || '';
    form.agency = member.agency || '';
    form.clearErrors();
    showDialog.value = true;
}

function submitForm() {
    form.put(route('committee-members.update', editingMember.value.id), {
        onSuccess: () => {
            showDialog.value = false;
            form.reset();
        },
        onError: (errors) => {
            console.error(errors);
            notyf.error('Failed to update. Please check your inputs.');
        }
    });
}
</script>

<template>
    <Head title="Committee Registry" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-white p-4 md:p-8">
            <div class="flex flex-col items-center justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm md:flex-row">
                <div class="relative max-w-md flex-1 w-full md:w-auto">
                    <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchTerm"
                        type="text"
                        placeholder="Search registry by name or position..."
                        class="block w-full rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-10 text-sm placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                    <button
                        v-if="searchTerm"
                        @click="clearSearch"
                        class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                        ×
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
                            <tr>
                                <th class="px-6 py-3 font-medium tracking-wide">Name</th>
                                <th class="px-6 py-3 font-medium tracking-wide">Position</th>
                                <th class="px-6 py-3 font-medium tracking-wide">Agency</th>
                                <th class="px-6 py-3 text-center font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="member in members.data"
                                :key="member.id"
                                class="transition-colors duration-150 hover:bg-gray-50"
                            >
                                <td class="px-6 py-3 font-medium text-gray-900">{{ member.name }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ member.position || '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ member.agency || '—' }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex justify-center gap-2">
                                        <button
                                            class="rounded-lg bg-blue-50 p-2 text-blue-600 transition hover:bg-blue-100 focus:ring-2 focus:ring-blue-400"
                                                @click="openEditDialog(member)"
                                            title="Edit"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="members.data.length === 0" class="py-12 text-center">
                        <div class="flex flex-col items-center">
                            <Search class="mb-4 h-12 w-12 text-gray-300" />
                            <h3 class="text-lg font-semibold text-gray-900">No members found</h3>
                            <p class="mt-1 mb-4 text-sm text-gray-500">
                                {{ searchTerm ? 'Try adjusting your search terms.' : 'The registry is empty. Members will auto-register when documents are encoded.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="members.last_page > 1" class="flex items-center justify-between border-t bg-gray-50 px-4 py-3">
                    <p class="text-sm text-gray-600">
                        Showing <span class="font-medium">{{ members.from }}</span>–<span class="font-medium">{{ members.to }}</span> of
                        <span class="font-medium">{{ members.total }}</span>
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="(link, index) in members.links.slice(1, -1)"
                            :key="index"
                            @click="goToPage(String(link.url))"
                            :disabled="!link.url"
                            class="rounded-md px-3 py-1 text-sm transition"
                            :class="[
                                link.active ? 'bg-blue-600 font-medium text-white shadow-sm' : 'border bg-white text-gray-600 hover:bg-gray-100',
                            ]"
                        >
                            <span v-html="link.label"></span>
                        </button>
                    </div>
                </div>
            </div>

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h2 class="mb-6 text-lg font-semibold text-gray-900">Edit Member Details</h2>
                        
                        <form @submit.prevent="submitForm" class="space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700" for="name">Name</label>
                                <input
                                    v-model="form.name"
                                    id="name"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 font-medium focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none"
                                    required
                                    placeholder="Full Name"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700" for="position">Position</label>
                                <input
                                    v-model="form.position"
                                    id="position"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 font-medium focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none"
                                    placeholder="e.g., Department Head I"
                                />
                                <p v-if="form.errors.position" class="mt-1 text-sm text-red-500">{{ form.errors.position }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700" for="agency">Agency</label>
                                <input
                                    v-model="form.agency"
                                    id="agency"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 font-medium focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none"
                                    placeholder="e.g., City Information Office"
                                />
                                <p v-if="form.errors.agency" class="mt-1 text-sm text-red-500">{{ form.errors.agency }}</p>
                            </div>

                            <div class="flex justify-end gap-3 pt-4">
                                <button
                                    type="button"
                                    class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 transition hover:bg-gray-200"
                                    @click="showDialog = false"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="rounded-lg bg-blue-600 px-4 py-2 text-white transition hover:bg-blue-700 disabled:opacity-50"
                                    :disabled="form.processing"
                                >
                                    {{ form.processing ? 'Saving...' : 'Update Member' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.fade-enter-to,
.fade-leave-from {
    opacity: 1;
}
</style>