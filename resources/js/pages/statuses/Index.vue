<script setup lang="ts">
import DeleteConfirmationModal from '@/components/DeleteConfirmationModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch } from 'vue';

// 1. Updated Props to match StatusController
const props = defineProps<{
    statuses: {
        data: Array<{
            id: number;
            name: string; 
            created_at: string;
        }>;
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

        // 2. Updated Route to 'statuses.index'
        router.get(
            route('statuses.index'),
            {
                search: searchTerm.value,
            },
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
    router.get(route('statuses.index'));
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
        title: 'Status Management',
        href: route('statuses.index'),
    },
];

// Modal state
const showDialog = ref(false);
const isEdit = ref(false);
const editingId = ref<number | null>(null);

const showDeleteModal = ref(false);
const itemToDeleteUrl = ref('');
const itemToDeleteName = ref('');

// 3. Updated Form to use 'name'
const form = useForm({
    name: '',
});

function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEditDialog(item: any) {
    isEdit.value = true;
    editingId.value = item.id;
    form.name = item.name; // Load data
    form.clearErrors();
    showDialog.value = true;
}

function openDeleteModal(item: { id: number; name: string }) {
    // 4. Updated Delete Route
    itemToDeleteUrl.value = route('statuses.destroy', item.id);
    itemToDeleteName.value = item.name;
    showDeleteModal.value = true;
}

function submitForm() {
    if (isEdit.value && editingId.value !== null) {
        // 5. Updated Update Route
        form.put(route('statuses.update', editingId.value), {
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
            },
        });
    } else {
        // 6. Updated Store Route
        form.post(route('statuses.store'), {
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
            },
        });
    }
}
</script>

<template>
    <Head title="Status Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-white p-4 md:p-8">
            <div class="flex flex-col items-center justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm md:flex-row">
                <div class="relative max-w-md flex-1 w-full md:w-auto">
                    <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchTerm"
                        type="text"
                        placeholder="Search statuses..."
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

                <button
                    class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm transition-colors duration-200 hover:bg-blue-700 w-full md:w-auto justify-center"
                    @click="openAddDialog"
                >
                    <Plus class="h-4 w-4" />
                    Create Status
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
                            <tr>
                                <th class="px-6 py-3 font-medium tracking-wide">ID</th>
                                <th class="px-6 py-3 font-medium tracking-wide">Status Name</th>
                                <th class="px-6 py-3 text-center font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="status in statuses.data"
                                :key="status.id"
                                class="transition-colors duration-150 hover:bg-gray-50"
                            >
                                <td class="px-6 py-3 font-mono text-gray-900">{{ status.id }}</td>
                                <td class="px-6 py-3 font-medium text-gray-800">
                                    <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">
                                        {{ status.name }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex justify-center gap-2">
                                        <button
                                            class="rounded-lg bg-blue-50 p-2 text-blue-600 transition hover:bg-blue-100 focus:ring-2 focus:ring-blue-400"
                                            @click="openEditDialog(status)"
                                            title="Edit"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            class="rounded-lg bg-red-50 p-2 text-red-600 transition hover:bg-red-100 focus:ring-2 focus:ring-red-400"
                                            @click="openDeleteModal(status)"
                                            title="Delete"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="statuses.data.length === 0" class="py-12 text-center">
                        <div class="flex flex-col items-center">
                            <Search class="mb-4 h-12 w-12 text-gray-300" />
                            <h3 class="text-lg font-semibold text-gray-900">No statuses found</h3>
                            <p class="mt-1 mb-4 text-sm text-gray-500">
                                {{ searchTerm ? 'Try adjusting your search terms.' : 'Get started by creating your first status.' }}
                            </p>
                            <button
                                v-if="!searchTerm"
                                @click="openAddDialog"
                                class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-blue-700"
                            >
                                Create Status
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="statuses.last_page > 1" class="flex items-center justify-between border-t bg-gray-50 px-4 py-3">
                    <p class="text-sm text-gray-600">
                        Showing <span class="font-medium">{{ statuses.from }}</span>–<span class="font-medium">{{ statuses.to }}</span> of
                        <span class="font-medium">{{ statuses.total }}</span>
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="(link, index) in statuses.links.slice(1, -1)"
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

            <DeleteConfirmationModal
                v-model:show="showDeleteModal"
                :deleteUrl="itemToDeleteUrl"
                :item-name="itemToDeleteName"
                title="Delete Status"
                message="Are you sure you want to delete this status? This action cannot be undone."
            />

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h2 class="mb-6 text-lg font-semibold text-gray-900">
                            {{ isEdit ? 'Edit Status' : 'Create New Status' }}
                        </h2>
                        <form @submit.prevent="submitForm" class="space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700" for="name">Status Name</label>
                                <input
                                    v-model="form.name"
                                    id="name"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 font-medium focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none"
                                    required
                                    placeholder="e.g., Active, Repealed, Superseded"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
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
                                    {{ form.processing ? 'Saving...' : isEdit ? 'Update Status' : 'Create Status' }}
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