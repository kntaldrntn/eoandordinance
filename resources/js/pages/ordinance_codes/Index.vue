<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DeleteConfirmationModal from '@/components/DeleteConfirmationModal.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { FolderPlus, BookOpen, Trash2, Pencil, CheckCircle2, Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { watch } from 'vue';

const props = defineProps<{
    ordinance_codes: Array<{
        id: number;
        name: string;
        description: string | null;
        ordinances_count?: number; // Tells you how many laws are grouped here
    }>;
    flash?: { success?: string; delete?: string };
}>();

// Notifications
const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'top' } });

watch(
    () => props.flash,
    (flash) => {
        if (flash?.success) notyf.success(flash.success);
        if (flash?.error) notyf.error(flash.error);
        if (flash?.delete) notyf.success(flash.delete); 
    },
    { immediate: true, deep: true },
);

// --- MODAL & FORM STATE ---
const showDialog = ref(false);
const isEdit = ref(false);
const editingId = ref<number | null>(null);
const searchQuery = ref('');

// --- DELETE MODAL STATE ---
const showDeleteModal = ref(false);
const itemToDeleteUrl = ref('');
const itemToDeleteName = ref('');

const form = useForm({
    name: '',
    description: '',
});

// --- FILTERING ---
const filteredCodes = computed(() => {
    if (!searchQuery.value) return props.ordinance_codes;
    const q = searchQuery.value.toLowerCase();
    return props.ordinance_codes.filter(code => 
        code.name.toLowerCase().includes(q) || 
        (code.description && code.description.toLowerCase().includes(q))
    );
});

// --- ACTIONS ---
function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEditDialog(code: any) {
    isEdit.value = true;
    editingId.value = code.id;
    form.clearErrors();
    form.name = code.name;
    form.description = code.description || '';
    showDialog.value = true;
}

function openDeleteModal(code: { id: number; name: string }) {
    itemToDeleteUrl.value = route('ordinance-codes.destroy', code.id);
    itemToDeleteName.value = code.name;
    showDeleteModal.value = true;
}

function submitForm() {
    if (isEdit.value && editingId.value) {
        form.put(route('ordinance-codes.update', editingId.value), {
            onSuccess: () => {
                showDialog.value = false;
                notyf.success('Subject code updated successfully.');
            }
        });
    } else {
        form.post(route('ordinance-codes.store'), {
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
                notyf.success('New Subject code created.');
            }
        });
    }
}

const breadcrumbs = [
    { title: 'Ordinances', href: '/ordinances' },
    { title: 'Subject Codes', href: '/ordinance-codes' }
];
</script>

<template>
    <Head title="Ordinance Subject Codes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 bg-gray-50/50 p-4 md:p-8 rounded-xl">
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm">
                <div class="relative w-full sm:max-w-xs">
                    <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input v-model="searchQuery" type="text" placeholder="Search codes..." class="block w-full rounded-lg border border-gray-300 bg-gray-50 py-1.5 pl-9 text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none" />
                </div>

                <button @click="openAddDialog" class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 transition w-full sm:w-auto justify-center">
                    <FolderPlus class="h-4 w-4" /> Add Subject Code
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <table class="w-full text-left text-sm text-gray-700">
                    <thead class="bg-gray-50 text-xs text-gray-600 uppercase border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Code Group Name</th>
                            <th class="px-6 py-4 font-semibold">Description</th>
                            <th class="px-6 py-4 font-semibold text-center">Linked Laws</th>
                            <th class="px-6 py-4 font-semibold text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="code in filteredCodes" :key="code.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-bold text-gray-900 flex items-center gap-2">
                                <BookOpen class="w-4 h-4 text-blue-500 shrink-0" />
                                {{ code.name }}
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500 max-w-sm truncate">
                                {{ code.description || 'No description provided.' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 border border-blue-100">
                                    {{ code.ordinances_count ?? 0 }} Ordinances
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="openEditDialog(code)" class="rounded-lg bg-blue-50 p-2 text-blue-600 transition hover:bg-blue-100 focus:ring-2 focus:ring-blue-400" title="Edit Name/Desc">
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                    <button @click="openDeleteModal(code)" class="rounded-lg bg-red-50 p-2 text-red-600 transition hover:bg-red-100 focus:ring-2 focus:ring-red-400" title="Delete Group">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredCodes.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center text-gray-400 italic">No Subject Code records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <DeleteConfirmationModal
                v-model:show="showDeleteModal"
                :deleteUrl="itemToDeleteUrl"
                :item-name="itemToDeleteName"
                title="Delete Subject Code"
                message="Are you sure you want to delete this Subject Code? Any ordinances currently linked to this group will return to being 'Standalone' ordinances. This action cannot be undone."
            />

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <div class="flex items-center justify-between mb-4 border-b pb-3">
                            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                <FolderPlus class="w-5 h-5 text-blue-600" /> {{ isEdit ? 'Edit Group Identity' : 'Create Code Group' }}
                            </h2>
                            <button @click="showDialog = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">×</button>
                        </div>

                        <form @submit.prevent="submitForm" class="space-y-4">
                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-500 uppercase tracking-wider">Group Code / Title <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" placeholder="e.g., The Traffic Management Code" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" required />
                                <div v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-500 uppercase tracking-wider">Scope / Description</label>
                                <textarea v-model="form.description" rows="3" placeholder="Brief summary of what laws this code encompasses..." class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                <button type="button" @click="showDialog = false" class="rounded-lg bg-gray-100 px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-200">Cancel</button>
                                <button type="submit" :disabled="form.processing" class="flex items-center gap-1 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-70">
                                    <CheckCircle2 class="w-3.5 h-3.5" /> Save Group
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
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>