<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DeleteConfirmationModal from '@/components/DeleteConfirmationModal.vue'; // Import your modal
import { Head, router, useForm } from '@inertiajs/vue3';
import { 
    Users, Plus, Search, Building2, Briefcase, 
    CheckCircle2, XCircle, Pencil, Trash2 
} from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    employees: {
        data: Array<{
            pmis_id: string; // PK
            full_name: string;
            position: string;
            dept_id: number;
            state: number; // 1 or 0
            department?: { name: string };
        }>;
        links: Array<any>;
        from: number; to: number; total: number;
        last_page: number;
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
        router.get(route('employees.index'), { search: searchTerm.value }, { preserveState: true, preserveScroll: true });
    }, 300);
};
watch(searchTerm, performSearch);

const clearSearch = () => {
    searchTerm.value = '';
    router.get(route('employees.index'));
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

const breadcrumbs = [
    { title: 'Employee Management', href: route('employees.index') },
];

// --- Form & Modal State ---
const showDialog = ref(false);
const isEdit = ref(false);
const editingId = ref<string | null>(null);

// --- Delete Modal State ---
const showDeleteModal = ref(false);
const itemToDeleteUrl = ref('');
const itemToDeleteName = ref('');

const form = useForm({
    pmis_id: '',
    full_name: '',
    position: '',
    dept_id: '' as string | number,
    state: true, // UI Toggle (true/false) -> Backend (1/0)
});

function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    form.reset();
    form.state = true;
    form.clearErrors();
    showDialog.value = true;
}

function openEditDialog(emp: any) {
    isEdit.value = true;
    editingId.value = emp.pmis_id;
    
    form.clearErrors();
    form.pmis_id = emp.pmis_id;
    form.full_name = emp.full_name;
    form.position = emp.position || '';
    form.dept_id = emp.dept_id;
    form.state = emp.state === 1; 
    
    showDialog.value = true;
}

function openDeleteModal(emp: any) {
    // Set the URL and Name for the modal props
    itemToDeleteUrl.value = route('employees.destroy', emp.pmis_id);
    itemToDeleteName.value = emp.full_name;
    showDeleteModal.value = true;
}

function submitForm() {
    if (isEdit.value && editingId.value) {
        form.put(route('employees.update', editingId.value), {
            onSuccess: () => showDialog.value = false,
        });
    } else {
        form.post(route('employees.store'), {
            onSuccess: () => showDialog.value = false,
        });
    }
}

// Helper for status badge style
const getStatusBadge = (isActive: boolean) => {
    return isActive 
        ? 'bg-green-100 text-green-700 ring-green-600/20' 
        : 'bg-red-50 text-red-700 ring-red-600/10';
};
</script>

<template>
    <Head title="Employee Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-white p-4 md:p-8">
            
            <div class="flex flex-col items-center justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm md:flex-row">
                <div class="relative max-w-md flex-1 w-full md:w-auto">
                    <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input v-model="searchTerm" type="text" placeholder="Search by name, ID, or position..." class="block w-full rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-10 text-sm placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none" />
                    <button v-if="searchTerm" @click="clearSearch" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600">×</button>
                </div>
                <button class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-blue-700 w-full md:w-auto justify-center" @click="openAddDialog">
                    <Plus class="h-4 w-4" /> Add Employee
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
                            <tr>
                                <th class="px-6 py-3 font-medium">PMIS ID</th>
                                <th class="px-6 py-3 font-medium">Employee Name</th>
                                <th class="px-6 py-3 font-medium">Position</th>
                                <th class="px-6 py-3 font-medium">Department</th>
                                <th class="px-6 py-3 text-center font-medium">Status</th>
                                <th class="px-6 py-3 text-center font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="emp in employees.data" :key="emp.pmis_id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-3 font-mono text-gray-500 font-bold">
                                    {{ emp.pmis_id }}
                                </td>
                                <td class="px-6 py-3">
                                    <div class="font-medium text-gray-900">{{ emp.full_name }}</div>
                                </td>
                                <td class="px-6 py-3 text-gray-600">
                                    <div class="flex items-center gap-1.5">
                                        <Briefcase class="w-3.5 h-3.5 text-gray-400" />
                                        {{ emp.position || '—' }}
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-gray-600">
                                    <div class="flex items-center gap-1.5">
                                        <Building2 class="w-3.5 h-3.5 text-gray-400" />
                                        {{ emp.department?.name || '—' }}
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset', getStatusBadge(emp.state === 1)]">
                                        <component :is="emp.state === 1 ? CheckCircle2 : XCircle" class="w-3 h-3" />
                                        {{ emp.state === 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button class="rounded-lg bg-blue-50 p-2 text-blue-600 hover:bg-blue-100 transition" @click="openEditDialog(emp)" title="Edit">
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button class="rounded-lg bg-red-50 p-2 text-red-600 hover:bg-red-100 transition" @click="openDeleteModal(emp)" title="Delete">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="employees.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    No employees found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div v-if="employees.last_page > 1" class="flex items-center justify-between border-t bg-gray-50 px-4 py-3">
                    <p class="text-sm text-gray-600">Showing <span class="font-medium">{{ employees.from }}</span>–<span class="font-medium">{{ employees.to }}</span> of <span class="font-medium">{{ employees.total }}</span></p>
                    <div class="flex gap-1">
                        <button v-for="(link, index) in employees.links.slice(1, -1)" :key="index" @click="goToPage(String(link.url))" :disabled="!link.url" class="rounded-md px-3 py-1 text-sm transition" :class="[link.active ? 'bg-blue-600 font-medium text-white' : 'border bg-white text-gray-600 hover:bg-gray-100']">
                            <span v-html="link.label"></span>
                        </button>
                    </div>
                </div>
            </div>

            <DeleteConfirmationModal 
                v-model:show="showDeleteModal" 
                :deleteUrl="itemToDeleteUrl" 
                :itemName="itemToDeleteName" 
                title="Remove Employee" 
            />

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                        
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <Users class="w-5 h-5 text-blue-600" />
                                {{ isEdit ? 'Edit Employee' : 'Add Employee' }}
                            </h2>
                            <button @click="showDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-full p-1.5 transition">×</button>
                        </div>

                        <form @submit.prevent="submitForm" class="space-y-4">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">PMIS ID (Unique)</label>
                                    <input v-model="form.pmis_id" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" :disabled="isEdit" :class="{'bg-gray-100 cursor-not-allowed': isEdit}" required />
                                    <p v-if="form.errors.pmis_id" class="text-xs text-red-500 mt-1">{{ form.errors.pmis_id }}</p>
                                </div>
                                
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                    <div class="flex items-center justify-between bg-gray-50 p-2 rounded-lg border border-gray-200">
                                        <span class="text-sm text-gray-600">Currently Active?</span>
                                        <button 
                                            type="button" 
                                            @click="form.state = !form.state"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                            :class="form.state ? 'bg-green-500' : 'bg-gray-300'"
                                        >
                                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" :class="form.state ? 'translate-x-6' : 'translate-x-1'" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Full Name</label>
                                <input v-model="form.full_name" type="text" placeholder="e.g. Juan Dela Cruz" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" required />
                                <p v-if="form.errors.full_name" class="text-xs text-red-500 mt-1">{{ form.errors.full_name }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Position</label>
                                    <input v-model="form.position" type="text" placeholder="e.g. Admin Officer" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                                    <select v-model="form.dept_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 bg-white" required>
                                        <option value="" disabled>Select Department</option>
                                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                    </select>
                                    <p v-if="form.errors.dept_id" class="text-xs text-red-500 mt-1">{{ form.errors.dept_id }}</p>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t mt-4">
                                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 transition" @click="showDialog = false">Cancel</button>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50 transition" :disabled="form.processing">
                                    {{ form.processing ? 'Saving...' : isEdit ? 'Update Employee' : 'Add Employee' }}
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