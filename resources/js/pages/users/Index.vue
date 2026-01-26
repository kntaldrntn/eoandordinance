<script setup lang="ts">
import DeleteConfirmationModal from '@/components/DeleteConfirmationModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, User as UserIcon } from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    users: {
        data: Array<{
            id: number;
            name: string;
            email: string;
            role: string;
            department_id: number | null;
            department?: { name: string };
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<any>;
    };
    departments: Array<{ id: number; name: string }>;
    filters?: { search?: string };
    flash?: { success?: string; error?: string };
}>();

// --- Search Logic ---
const searchTerm = ref(props.filters?.search || '');
let searchTimeout: ReturnType<typeof setTimeout>;

const performSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('users.index'), { search: searchTerm.value }, { preserveState: true, preserveScroll: true });
    }, 300);
};
watch(searchTerm, performSearch);

const clearSearch = () => {
    searchTerm.value = '';
    router.get(route('users.index'));
};

const goToPage = (url: string) => {
    if (!url) return;
    router.get(url, { search: searchTerm.value }, { preserveState: true, preserveScroll: false });
};

// --- Notifications ---
const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'top' } });
watch(() => props.flash, (flash) => {
    if (flash?.success) notyf.success(flash.success);
    if (flash?.error) notyf.error(flash.error);
}, { immediate: true, deep: true });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'User Management', href: route('users.index') },
];

// --- Modal State ---
const showDialog = ref(false);
const isEdit = ref(false);
const editingId = ref<number | null>(null);
const showDeleteModal = ref(false);
const itemToDeleteUrl = ref('');
const itemToDeleteName = ref('');

// --- Form ---
const form = useForm({
    name: '',
    email: '',
    role: 'focal_person',
    department_id: '' as string | number,
    password: '',
    password_confirmation: '',
});

function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEditDialog(user: any) {
    isEdit.value = true;
    editingId.value = user.id;
    form.reset();
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    form.department_id = user.department_id || '';
    form.clearErrors();
    showDialog.value = true;
}

function openDeleteModal(user: any) {
    itemToDeleteUrl.value = route('users.destroy', user.id);
    itemToDeleteName.value = user.name;
    showDeleteModal.value = true;
}

function submitForm() {
    if (isEdit.value && editingId.value) {
        form.put(route('users.update', editingId.value), {
            onSuccess: () => showDialog.value = false,
        });
    } else {
        form.post(route('users.store'), {
            onSuccess: () => showDialog.value = false,
        });
    }
}

const getRoleBadgeColor = (role: string) => {
    switch(role) {
        case 'system_admin': return 'bg-purple-100 text-purple-700 ring-purple-600/20';
        case 'supervisor': return 'bg-blue-100 text-blue-700 ring-blue-600/20';
        case 'focal_person': return 'bg-green-100 text-green-700 ring-green-600/20';
        default: return 'bg-gray-100 text-gray-700 ring-gray-600/20';
    }
};
</script>

<template>
    <Head title="User Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-white p-4 md:p-8">
            <div class="flex flex-col items-center justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm md:flex-row">
                <div class="relative max-w-md flex-1 w-full md:w-auto">
                    <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input v-model="searchTerm" type="text" placeholder="Search users..." class="block w-full rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-10 text-sm placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none" />
                    <button v-if="searchTerm" @click="clearSearch" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600">×</button>
                </div>
                <button class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-blue-700 w-full md:w-auto justify-center" @click="openAddDialog">
                    <Plus class="h-4 w-4" /> Create User
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
                            <tr>
                                <th class="px-6 py-3 font-medium">User</th>
                                <th class="px-6 py-3 font-medium">Role</th>
                                <th class="px-6 py-3 font-medium">Department</th>
                                <th class="px-6 py-3 text-center font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-3">
                                    <div class="font-medium text-gray-900">{{ user.name }}</div>
                                    <div class="text-xs text-gray-500">{{ user.email }}</div>
                                </td>
                                <td class="px-6 py-3">
                                    <span :class="['inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset capitalize', getRoleBadgeColor(user.role)]">
                                        {{ user.role.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-gray-600">
                                    {{ user.department?.name || '—' }}
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button class="rounded-lg bg-blue-50 p-2 text-blue-600 hover:bg-blue-100" @click="openEditDialog(user)">
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button class="rounded-lg bg-red-50 p-2 text-red-600 hover:bg-red-100" @click="openDeleteModal(user)">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="users.last_page > 1" class="flex items-center justify-between border-t bg-gray-50 px-4 py-3">
                    <p class="text-sm text-gray-600">Showing <span class="font-medium">{{ users.from }}</span>–<span class="font-medium">{{ users.to }}</span> of <span class="font-medium">{{ users.total }}</span></p>
                    <div class="flex gap-1">
                        <button v-for="(link, index) in users.links.slice(1, -1)" :key="index" @click="goToPage(String(link.url))" :disabled="!link.url" class="rounded-md px-3 py-1 text-sm transition" :class="[link.active ? 'bg-blue-600 font-medium text-white' : 'border bg-white text-gray-600 hover:bg-gray-100']"><span v-html="link.label"></span></button>
                    </div>
                </div>
            </div>

            <DeleteConfirmationModal v-model:show="showDeleteModal" :deleteUrl="itemToDeleteUrl" :item-name="itemToDeleteName" title="Delete User" />

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                        <h2 class="mb-6 text-lg font-semibold text-gray-900">{{ isEdit ? 'Edit User' : 'Create New User' }}</h2>
                        <form @submit.prevent="submitForm" class="space-y-4">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Full Name</label>
                                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" required />
                                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                                    <input v-model="form.email" type="email" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" required />
                                    <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Role</label>
                                    <select v-model="form.role" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                        <option value="system_admin">System Admin</option>
                                        <option value="supervisor">Supervisor</option>
                                        <option value="focal_person">Focal Person</option>
                                        <option value="monitoring_committee">Monitoring Committee</option>
                                        <option value="read_only">Read Only</option>
                                    </select>
                                    <p v-if="form.errors.role" class="text-xs text-red-500 mt-1">{{ form.errors.role }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                                    <select v-model="form.department_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                        <option value="">None</option>
                                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="border-t pt-4 mt-2">
                                <div v-if="isEdit" class="text-xs text-gray-500 mb-2 italic">Leave blank to keep current password.</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Password</label>
                                        <input v-model="form.password" type="password" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" :required="!isEdit" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Confirm</label>
                                        <input v-model="form.password_confirmation" type="password" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" :required="!isEdit" />
                                    </div>
                                </div>
                                <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200" @click="showDialog = false">Cancel</button>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50" :disabled="form.processing">
                                    {{ form.processing ? 'Saving...' : isEdit ? 'Update User' : 'Create User' }}
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
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.fade-enter-to, .fade-leave-from { opacity: 1; }
</style>