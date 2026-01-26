<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { FileText, Plus, Search, Save, Calendar, Building2 } from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

// --- Props ---
const props = defineProps<{
    eos: {
        data: Array<{
            id: number;
            eo_number: string;
            title: string;
            date_issued: string;
            effectivity_date: string;
            legal_basis: string;
            status_id: number;
            status: { name: string };
            departments: Array<{ id: number; name: string; pivot: { role: string } }>;
        }>;
        links: Array<any>;
        from: number; to: number; total: number;
        current_page: number; last_page: number;
    };
    departments: Array<{ id: number; name: string }>;
    statuses: Array<{ id: number; name: string }>;
    filters?: { search?: string };
    flash?: { success?: string; error?: string };
}>();

// --- Search & Loading State ---
const searchTerm = ref(props.filters?.search || '');
const isLoading = ref(false);
let searchTimeout: ReturnType<typeof setTimeout>;

const performSearch = () => {
    clearTimeout(searchTimeout);
    isLoading.value = true; // Show skeleton
    searchTimeout = setTimeout(() => {
        router.get(
            route('eo.index'), 
            { search: searchTerm.value }, 
            { 
                preserveState: true, 
                preserveScroll: true,
                onFinish: () => isLoading.value = false 
            }
        );
    }, 300);
};

watch(searchTerm, performSearch);

const clearSearch = () => {
    searchTerm.value = '';
    isLoading.value = true;
    router.get(route('eo.index'), {}, { onFinish: () => isLoading.value = false });
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

// --- Modal & Form State ---
const showDialog = ref(false);
const isEdit = ref(false); // <--- New: Track edit mode
const editingId = ref<number | null>(null); // <--- New: Track ID being edited

const form = useForm({
    eo_number: '',
    title: '',
    date_issued: '',
    effectivity_date: '',
    legal_basis: '',
    lead_office_id: '' as string | number,
    support_office_ids: [] as number[],
    status_id: '' as string | number,
    file: null as File | null,
    // Note: We don't send 'id' in the body usually, we put it in the URL for PUT
});

// Open "Create" Modal
function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

// Open "Edit" Modal
function openEditDialog(eo: any) {
    isEdit.value = true;
    editingId.value = eo.id;
    form.clearErrors();
    
    // 1. Populate Basic Fields
    form.eo_number = eo.eo_number;
    form.title = eo.title;
    form.date_issued = eo.date_issued;
    form.effectivity_date = eo.effectivity_date;
    form.legal_basis = eo.legal_basis || ''; // Handle null
    form.status_id = eo.status_id;
    
    // 2. Populate Lead Office
    // We look for the department where pivot.role is 'lead'
    const lead = eo.departments.find((d: any) => d.pivot.role === 'lead');
    form.lead_office_id = lead ? lead.id : '';

    // 3. Populate Support Offices
    // Filter for 'support' roles and extract just the IDs
    form.support_office_ids = eo.departments
        .filter((d: any) => d.pivot.role === 'support')
        .map((d: any) => d.id);
    
    // 4. File
    // We can't pre-fill file inputs for security, so we reset it. 
    // The backend should handle "if file is null, keep old file".
    form.file = null; 

    showDialog.value = true;
}

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.file = target.files[0];
    }
}

function submitForm() {
    if (isEdit.value && editingId.value) {
        // --- UPDATE MODE (PUT) ---
        // Note: Inertia often has trouble with PUT/PATCH and Files. 
        // A common workaround is using POST with _method: 'PUT'
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('eo.update', editingId.value), {
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
            },
        });
    } else {
        // --- CREATE MODE (POST) ---
        form.post(route('eo.store'), {
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
            },
        });
    }
}

// Helper
const getLeadOffice = (depts: any[]) => {
    const lead = depts.find(d => d.pivot.role === 'lead');
    return lead ? lead.name : '—';
};
</script>

<template>
    <Head title="EO Profiling" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-white p-4 md:p-8">
            
            <div class="flex flex-col items-center justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm md:flex-row">
                <div class="relative max-w-md flex-1 w-full md:w-auto">
                    <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input v-model="searchTerm" type="text" placeholder="Search EO Number or Title..." class="block w-full rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-10 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none" />
                    <button v-if="searchTerm" @click="clearSearch" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600">×</button>
                </div>
                <button class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-blue-700 transition" @click="openAddDialog">
                    <Plus class="h-4 w-4" /> Encode EO
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
                            <tr>
                                <th class="px-6 py-3 font-medium">EO Number</th>
                                <th class="px-6 py-3 font-medium w-1/3">Title</th>
                                <th class="px-6 py-3 font-medium">Date Issued</th>
                                <th class="px-6 py-3 font-medium">Lead Office</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 text-center font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            
                            <template v-if="isLoading">
                                <tr v-for="n in 5" :key="n" class="animate-pulse">
                                    <td class="px-6 py-4"><div class="h-4 w-24 bg-gray-200 rounded"></div></td>
                                    <td class="px-6 py-4">
                                        <div class="h-4 w-full bg-gray-200 rounded mb-2"></div>
                                        <div class="h-3 w-2/3 bg-gray-100 rounded"></div>
                                    </td>
                                    <td class="px-6 py-4"><div class="h-4 w-20 bg-gray-200 rounded"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-32 bg-gray-200 rounded"></div></td>
                                    <td class="px-6 py-4"><div class="h-6 w-16 bg-gray-200 rounded-full"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-8 bg-gray-200 rounded mx-auto"></div></td>
                                </tr>
                            </template>

                            <template v-else-if="eos.data.length > 0">
                                <tr v-for="eo in eos.data" :key="eo.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-3 font-mono font-medium text-blue-600">{{ eo.eo_number }}</td>
                                    <td class="px-6 py-3 line-clamp-2">
                                        <div class="text-gray-900">{{ eo.title }}</div>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-500">{{ eo.date_issued }}</td>
                                    <td class="px-6 py-3 text-xs text-gray-600">
                                        <span class="flex items-center gap-1">
                                            <Building2 class="w-3 h-3 text-gray-400" />
                                            {{ getLeadOffice(eo.departments) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">
                                            {{ eo.status?.name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <button 
                                            @click="openEditDialog(eo)"
                                            class="text-sm font-medium text-blue-600 hover:text-blue-800 transition-colors"
                                        >
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="bg-gray-50 p-4 rounded-full mb-3">
                                            <Search class="h-6 w-6 text-gray-400" />
                                        </div>
                                        <p class="text-base font-medium text-gray-900">No Executive Orders found</p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Try adjusting your search terms or create a new one.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            
                        </tbody>
                    </table>
                </div>
                <div v-if="eos.last_page > 1" class="flex items-center justify-between border-t bg-gray-50 px-4 py-3">
                    <p class="text-sm text-gray-600">Showing <span class="font-medium">{{ eos.from }}</span>–<span class="font-medium">{{ eos.to }}</span> of <span class="font-medium">{{ eos.total }}</span></p>
                    <div class="flex gap-1">
                        <button v-for="(link, index) in eos.links.slice(1, -1)" :key="index" @click="goToPage(String(link.url))" :disabled="!link.url" class="rounded-md px-3 py-1 text-sm transition" :class="[link.active ? 'bg-blue-600 font-medium text-white' : 'border bg-white text-gray-600 hover:bg-gray-100']"><span v-html="link.label"></span></button>
                    </div>
                </div>
            </div>

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl max-h-[95vh] overflow-y-auto">
                        
                        <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                    <FileText class="w-6 h-6 text-blue-600" />
                                    {{ isEdit ? 'Edit Executive Order' : 'Encode Executive Order' }}
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ isEdit ? 'Update details and assignments.' : 'Create a new record and assign implementing offices.' }}
                                </p>
                            </div>
                            <button @click="showDialog = false" class="text-gray-400 hover:text-gray-600 transition-colors bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                                ×
                            </button>
                        </div>

                        <form @submit.prevent="submitForm" class="space-y-6">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">EO Number</label>
                                    <input v-model="form.eo_number" type="text" placeholder="e.g., EO-2026-001" class="w-full rounded-lg border-gray-300 bg-white px-4 py-2.5 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all" />
                                    <p v-if="form.errors.eo_number" class="text-red-500 text-xs mt-1">{{ form.errors.eo_number }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                                    <select v-model="form.status_id" class="w-full rounded-lg border-gray-300 bg-white px-4 py-2.5 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all">
                                        <option value="" disabled>Select Status</option>
                                        <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                    </select>
                                    <p v-if="form.errors.status_id" class="text-red-500 text-xs mt-1">{{ form.errors.status_id }}</p>
                                </div>
                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Title / Subject</label>
                                    <textarea v-model="form.title" rows="3" class="w-full rounded-lg border-gray-300 bg-white px-4 py-2.5 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all" placeholder="An Order Creating the..."></textarea>
                                    <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-gray-100 pt-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date Issued</label>
                                    <div class="relative">
                                        <Calendar class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                        <input v-model="form.date_issued" type="date" class="w-full rounded-lg border-gray-300 bg-white pl-10 pr-4 py-2.5 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Effectivity Date</label>
                                    <div class="relative">
                                        <Calendar class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                        <input v-model="form.effectivity_date" type="date" class="w-full rounded-lg border-gray-300 bg-white pl-10 pr-4 py-2.5 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all" />
                                    </div>
                                </div>
                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Legal Basis (Optional)</label>
                                    <input v-model="form.legal_basis" type="text" placeholder="e.g., Section 455 of RA 7160" class="w-full rounded-lg border-gray-300 bg-white px-4 py-2.5 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all" />
                                </div>
                            </div>

                            <div class="bg-blue-50/50 p-6 rounded-xl border border-blue-100/80 space-y-6">
                                <h3 class="font-semibold text-blue-900 flex items-center gap-2">
                                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">Tagging</span>
                                    Implementing Offices
                                </h3>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Lead Implementing Office</label>
                                    <div class="relative">
                                        <Building2 class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                        <select v-model="form.lead_office_id" class="w-full rounded-lg border-gray-300 bg-white pl-10 pr-4 py-2.5 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all">
                                            <option value="" disabled>Select Lead Office...</option>
                                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Support Offices (Hold Ctrl/Cmd to select multiple)</label>
                                    <select v-model="form.support_office_ids" multiple class="w-full h-32 rounded-lg border-gray-300 bg-white px-4 py-2.5 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm transition-all">
                                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1.5">Selected: {{ form.support_office_ids.length }} offices</p>
                                </div>
                            </div>

                            <div class="border-t border-gray-100 pt-6">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Scanned EO (PDF)</label>
                                <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:bg-gray-50 hover:border-blue-400 transition-colors cursor-pointer relative bg-white">
                                    <input type="file" @change="handleFileChange" accept="application/pdf" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                                    <div class="space-y-2">
                                        <div class="bg-blue-50 rounded-full p-3 inline-flex">
                                            <FileText class="w-8 h-8 text-blue-500" />
                                        </div>
                                        <p class="text-sm text-gray-700">
                                            <span class="font-semibold text-blue-600 hover:underline">Click to upload</span> or drag and drop
                                        </p>
                                        <p class="text-xs text-gray-400">PDF up to 10MB</p>
                                    </div>
                                </div>
                                <div v-if="form.file" class="mt-3 text-sm text-green-700 font-medium flex items-center gap-2 bg-green-50 p-2 rounded-lg border border-green-100">
                                    <span>✓ Selected: {{ form.file.name }}</span>
                                </div>
                                <p v-if="form.errors.file" class="text-red-500 text-xs mt-1">{{ form.errors.file }}</p>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-gray-100">
                                <button type="button" class="mr-3 rounded-lg bg-white border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50 font-medium transition-colors" @click="showDialog = false">Cancel</button>
                                
                                <button type="submit" class="flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2 text-white hover:bg-blue-700 shadow-md transition-all disabled:opacity-50 font-medium" :disabled="form.processing">
                                    <Save class="w-4 h-4" />
                                    {{ form.processing ? 'Saving...' : (isEdit ? 'Update EO' : 'Save EO') }}
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