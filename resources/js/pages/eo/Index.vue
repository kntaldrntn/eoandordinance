<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { FileText, Plus, Search, Save, Calendar, Building2, Link as LinkIcon, BookOpen, Download, AlertCircle, Clock } from 'lucide-vue-next';
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
            file_path: string; 
            file_url: string;  
            effectivity_date: string;
            legal_basis: string;
            status_id: number;
            
            // Audit History (ADDED)
            audits: Array<{
                id: number;
                user: { name: string };
                action: string;
                created_at: string;
                old_values: any;
                new_values: any;
            }>;

            amends_eo_id: number | null;
            relationship_type: string | null; 
            remarks: string | null;           
            parent_e_o: { eo_number: string } | null;
            amendments?: Array<{ 
                id: number; 
                eo_number: string; 
            }>;
            status: { name: string };
            departments: Array<{ id: number; name: string; pivot: { role: string } }>;
            implementing_rules: Array<{
                id: number;
                status: string;
                file_url: string;
                lead_office: { name: string };
                created_at: string;
            }>;
        }>;
        links: Array<any>;
        from: number; to: number; total: number;
        current_page: number; last_page: number;
    };
    departments: Array<{ id: number; name: string }>;
    statuses: Array<{ id: number; name: string }>;
    existing_eos: Array<{ id: number; eo_number: string; title: string }>;
    filters?: { search?: string };
    flash?: { success?: string; error?: string };
}>();

// --- Search & Loading State ---
const searchTerm = ref(props.filters?.search || '');
const isLoading = ref(false);
let searchTimeout: ReturnType<typeof setTimeout>;

const performSearch = () => {
    clearTimeout(searchTimeout);
    isLoading.value = true;
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

// --- EO MODAL STATE ---
const showDialog = ref(false);
const isEdit = ref(false);
const editingId = ref<number | null>(null);
const activeModalTab = ref('details'); // 'details' or 'history'
const selectedRecord = ref<any>(null); // For history view

const form = useForm({
    amends_eo_id: '' as string | number,
    relationship_type: 'Amends', // Default
    remarks: '',
    eo_number: '',
    title: '',
    date_issued: '',
    effectivity_date: '',
    legal_basis: '',
    lead_office_id: '' as string | number,
    support_office_ids: [] as number[],
    status_id: '' as string | number,
    file: null as File | null,
});

// --- IRR MODAL STATE ---
const showIRRDialog = ref(false);
const selectedEO = ref<any>(null);

const irrForm = useForm({
    executive_order_id: '' as string | number,
    lead_office_id: '' as string | number,
    status: 'Drafting', 
    file: null as File | null,
});

const irrStatuses = ['Drafting', 'Pending Approval', 'Approved', 'Implemented', 'Delayed'];

// --- Functions for EO Modal ---
function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    selectedRecord.value = null;
    activeModalTab.value = 'details';
    
    form.reset();
    form.clearErrors();
    form.relationship_type = 'Amends'; 
    showDialog.value = true;
}

function openEditDialog(eo: any) {
    isEdit.value = true;
    editingId.value = eo.id;
    selectedRecord.value = eo; // Store for Audit Log
    activeModalTab.value = 'details';
    
    form.clearErrors();
    
    form.eo_number = eo.eo_number;
    form.title = eo.title;
    form.date_issued = eo.date_issued;
    form.effectivity_date = eo.effectivity_date;
    form.legal_basis = eo.legal_basis || '';
    form.status_id = eo.status_id;
    
    // Load Relationship Data
    form.amends_eo_id = eo.amends_eo_id || '';
    form.relationship_type = eo.relationship_type || 'Amends'; 
    form.remarks = eo.remarks || '';

    const lead = eo.departments.find((d: any) => d.pivot.role === 'lead');
    form.lead_office_id = lead ? lead.id : '';
    form.support_office_ids = eo.departments.filter((d: any) => d.pivot.role === 'support').map((d: any) => d.id);
    form.file = null; 
    showDialog.value = true;
}

// --- History Helpers (Safety Checked) ---
const formatAuditDate = (date: string) => {
    return new Date(date).toLocaleString('en-US', { 
        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' 
    });
};

const getChangedFields = (audit: any) => {
    if (audit.action === 'Created') return 'Initial Record Created';
    if (audit.action === 'Deleted') return 'Record Deleted';
    
    let newVals = audit.new_values;
    let oldVals = audit.old_values;

    // Safety: Decode if they come as strings
    if (typeof newVals === 'string') { try { newVals = JSON.parse(newVals); } catch (e) {} }
    if (typeof oldVals === 'string') { try { oldVals = JSON.parse(oldVals); } catch (e) {} }

    if (newVals) {
        const changes = Object.keys(newVals)
            .filter(key => key !== 'updated_at')
            .map(key => {
                const field = key.replace(/_/g, ' '); // Clean field name
                const from = oldVals && oldVals[key] ? oldVals[key] : '(empty)';
                const to = newVals[key];
                return `${field}: "${from}" → "${to}"`;
            });
            
        if (changes.length === 0) return 'Updated metadata';
        return 'Updated: ' + changes.join(', ');
    }
    return 'Updated record';
};

// --- Functions for IRR Modal ---
function openIRRDialog(eo: any) {
    selectedEO.value = eo;
    irrForm.reset();
    irrForm.clearErrors();
    const lead = eo.departments.find((d: any) => d.pivot.role === 'lead');
    irrForm.lead_office_id = lead ? lead.id : '';
    irrForm.executive_order_id = eo.id;
    showIRRDialog.value = true;
}

function handleIRRFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        irrForm.file = target.files[0];
    }
}

function submitIRR() {
    irrForm.post(route('irr.store'), {
        onSuccess: () => {
            irrForm.reset();
            showIRRDialog.value = false;
            notyf.success('IRR Added Successfully');
        },
    });
}

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.file = target.files[0];
    }
}

function submitForm() {
    if (isEdit.value && editingId.value) {
        form.transform((data) => ({ ...data, _method: 'PUT' })).post(route('eo.update', editingId.value), {
            onSuccess: () => { showDialog.value = false; form.reset(); },
        });
    } else {
        form.post(route('eo.store'), {
            onSuccess: () => { showDialog.value = false; form.reset(); },
        });
    }
}

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
                <button v-if="$page.props.auth.user.role === 'system_admin' || $page.props.auth.user.role === 'supervisor'" class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-blue-700 transition" @click="openAddDialog">
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
                                <th class="px-6 py-3 font-medium">Effectivity Date</th>
                                <th class="px-6 py-3 font-medium">Lead Office</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 text-center font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            
                            <template v-if="isLoading">
                                <tr v-for="n in 5" :key="n" class="animate-pulse">
                                    <td class="px-6 py-4"><div class="h-4 w-24 bg-gray-200 rounded"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-full bg-gray-200 rounded mb-2"></div><div class="h-3 w-2/3 bg-gray-100 rounded"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-20 bg-gray-200 rounded"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-32 bg-gray-200 rounded"></div></td>
                                    <td class="px-6 py-4"><div class="h-6 w-16 bg-gray-200 rounded-full"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-8 bg-gray-200 rounded mx-auto"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-8 bg-gray-200 rounded mx-auto"></div></td>
                                </tr>
                            </template>

                            <template v-else-if="eos.data.length > 0">
                                <tr v-for="eo in eos.data" :key="eo.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-3 font-mono font-medium text-blue-600">{{ eo.eo_number }}</td>
                                    <td class="px-6 py-3">
                                        <div class="line-clamp-2 text-gray-900 font-medium">{{ eo.title }}</div>
                                        
                                        <div v-if="eo.parent_e_o" class="mt-1 flex items-center gap-1 text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded w-fit border border-amber-100">
                                            <LinkIcon class="w-3 h-3" />
                                            <span>Amends: {{ eo.parent_e_o.eo_number }}</span>
                                        </div>

                                        <div v-if="eo.amendments && eo.amendments.length > 0" class="mt-1 flex flex-col gap-1">
                                            <div v-for="child in eo.amendments" :key="child.id" class="flex items-center gap-1 text-xs text-red-600 bg-red-50 px-2 py-0.5 rounded w-fit border border-red-100 font-medium">
                                                <AlertCircle class="w-3 h-3" />
                                                <span>Amended by {{ child.eo_number }}</span>
                                            </div>
                                        </div>

                                        <div v-if="eo.remarks" class="mt-2 text-xs text-gray-500 bg-gray-50 p-2 rounded border border-gray-100 italic">
                                            <span class="font-semibold not-italic text-gray-600">Note:</span> {{ eo.remarks }}
                                        </div>

                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-500">{{ eo.date_issued }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-500">{{ eo.effectivity_date }}</td>
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
                                        <div class="flex items-center justify-center gap-3">
                                            <button 
                                                @click="openIRRDialog(eo)" 
                                                class="group relative flex items-center justify-center rounded-lg bg-green-50 p-2 text-green-600 hover:bg-green-100 transition-colors"
                                                title="Manage Implementing Rules"
                                            >
                                                <BookOpen class="w-4 h-4" />
                                                <span v-if="eo.implementing_rules?.length > 0" class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full bg-red-500 border border-white"></span>
                                            </button>

                                            <span class="text-gray-300">|</span>

                                            <a v-if="eo.file_url" :href="eo.file_url" target="_blank" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">View</a>
                                            <span v-else class="text-sm text-gray-400 cursor-not-allowed">—</span>
                                            
                                            <span v-if="$page.props.auth.user.role === 'system_admin' || $page.props.auth.user.role === 'supervisor'" class="text-gray-300">|</span>
                                            
                                            <button @click="openEditDialog(eo)" v-if="$page.props.auth.user.role === 'system_admin' || $page.props.auth.user.role === 'supervisor'" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">Edit</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="bg-gray-50 p-4 rounded-full mb-3"><Search class="h-6 w-6 text-gray-400" /></div>
                                        <p class="text-base font-medium text-gray-900">No Executive Orders found</p>
                                        <p class="text-sm text-gray-500 mt-1">Try adjusting your search terms or create a new one.</p>
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
                        
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                    <FileText class="w-6 h-6 text-blue-600" />
                                    {{ isEdit ? 'Edit Executive Order' : 'Encode Executive Order' }}
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">{{ isEdit ? 'Update details.' : 'Create a new record.' }}</p>
                            </div>
                            <button @click="showDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-full p-2">×</button>
                        </div>

                        <div v-if="isEdit" class="flex items-center gap-6 border-b border-gray-200 mb-6">
                            <button 
                                @click="activeModalTab = 'details'"
                                class="pb-3 px-1 text-sm font-bold uppercase tracking-wide border-b-2 transition-all"
                                :class="activeModalTab === 'details' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            >
                                Details
                            </button>
                            <button 
                                @click="activeModalTab = 'history'"
                                class="pb-3 px-1 text-sm font-bold uppercase tracking-wide border-b-2 transition-all"
                                :class="activeModalTab === 'history' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            >
                                Audit Log (History)
                            </button>
                        </div>
                        <div v-else class="mb-6 border-b border-gray-200"></div>

                        <div v-show="activeModalTab === 'details'">
                            <form @submit.prevent="submitForm" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">EO Number</label>
                                        <input v-model="form.eo_number" type="text" class="w-full rounded-lg border-gray-300" />
                                        <p v-if="form.errors.eo_number" class="text-red-500 text-xs mt-1">{{ form.errors.eo_number }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                                        <select v-model="form.status_id" class="w-full rounded-lg border-gray-300">
                                            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Title</label>
                                    <textarea v-model="form.title" rows="2" class="w-full rounded-lg border-gray-300"></textarea>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-5 bg-gray-50 rounded-xl border border-gray-200">
                                    <div class="col-span-1">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Target EO (Parent)</label>
                                        <select v-model="form.amends_eo_id" class="w-full rounded-lg border-gray-300 focus:ring-blue-500">
                                            <option value="">None (This is a new Parent)</option>
                                            <option v-for="ex in existing_eos" :key="ex.id" :value="ex.id">
                                                {{ ex.eo_number }} - {{ ex.title }}
                                            </option>
                                        </select>
                                    </div>

                                    <div v-if="form.amends_eo_id" class="col-span-1">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Action / Effect</label>
                                        <select v-model="form.relationship_type" class="w-full rounded-lg border-gray-300 font-medium text-blue-900 bg-blue-50 border-blue-200">
                                            <option value="Amends">Amends (Changes Status to Amended)</option>
                                            <option value="Repeals">Repeals (Changes Status to Repealed)</option>
                                            <option value="Supplements">Supplements (Keeps Status Active)</option>
                                        </select>
                                        <div v-if="form.errors.relationship_type" class="text-red-500 text-xs mt-1">{{ form.errors.relationship_type }}</div>
                                    </div>

                                    <div class="col-span-1 md:col-span-2">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Remarks</label>
                                        <textarea v-model="form.remarks" rows="2" class="w-full rounded-lg border-gray-300 placeholder-gray-400 text-sm" placeholder="Add specific details about the amendment, repeal, or supplement..."></textarea>
                                        <div v-if="form.errors.remarks" class="text-red-500 text-xs mt-1">{{ form.errors.remarks }}</div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t pt-6">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Date Issued</label>
                                        <input v-model="form.date_issued" type="date" class="w-full rounded-lg border-gray-300" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Effectivity</label>
                                        <input v-model="form.effectivity_date" type="date" class="w-full rounded-lg border-gray-300" />
                                    </div>
                                    <div class="col-span-1 md:col-span-2">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Legal Basis</label>
                                        <input v-model="form.legal_basis" type="text" class="w-full rounded-lg border-gray-300" />
                                    </div>
                                </div>
                                <div class="bg-blue-50/50 p-6 rounded-xl border border-blue-100/80 space-y-6">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Lead Office</label>
                                        <select v-model="form.lead_office_id" class="w-full rounded-lg border-gray-300">
                                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Support Offices</label>
                                        <select v-model="form.support_office_ids" multiple class="w-full h-32 rounded-lg border-gray-300">
                                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="border-t pt-6">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Upload PDF</label>
                                    <input type="file" @change="handleFileChange" accept="application/pdf" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                                </div>
                                <div class="flex justify-end pt-6 border-t">
                                    <button type="button" class="mr-3 px-4 py-2 text-gray-700 hover:bg-gray-50 rounded-lg" @click="showDialog = false">Cancel</button>
                                    <button type="submit" class="bg-blue-600 px-6 py-2 text-white rounded-lg hover:bg-blue-700" :disabled="form.processing">
                                        {{ form.processing ? 'Saving...' : 'Save' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div v-show="activeModalTab === 'history'" class="space-y-6">
                            <div v-if="selectedRecord?.audits && selectedRecord.audits.length > 0">
                                <div class="relative border-l-2 border-gray-200 ml-3 space-y-8">
                                    <div v-for="audit in selectedRecord.audits" :key="audit.id" class="relative pl-6">
                                        <div class="absolute -left-[9px] top-1 h-4 w-4 rounded-full border-2 border-white"
                                            :class="{
                                                'bg-green-500': audit.action === 'Created',
                                                'bg-blue-500': audit.action === 'Updated',
                                                'bg-red-500': audit.action === 'Deleted'
                                            }"
                                        ></div>

                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                            <div>
                                                <p class="text-sm font-bold text-gray-900">
                                                    {{ audit.action }} by <span class="text-blue-600">{{ audit.user?.name || 'Unknown' }}</span>
                                                </p>
                                                <p class="text-xs text-gray-500 font-medium">
                                                    {{ getChangedFields(audit) }}
                                                </p>
                                            </div>
                                            <span class="text-xs text-gray-400 font-mono bg-gray-50 px-2 py-1 rounded flex items-center gap-1">
                                                <Clock class="w-3 h-3" />
                                                {{ formatAuditDate(audit.created_at) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-12">
                                <div class="bg-gray-50 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <Clock class="w-6 h-6 text-gray-400" />
                                </div>
                                <p class="text-gray-900 font-medium">No history found</p>
                                <p class="text-xs text-gray-500">Changes will appear here once edits are made.</p>
                            </div>
                        </div>

                    </div>
                </div>
            </Transition>

            <Transition name="fade">
                <div v-if="showIRRDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl max-h-[95vh] overflow-y-auto">
                        <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                    <BookOpen class="w-6 h-6 text-green-600" />
                                    Manage IRR
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">
                                    For: <span class="font-medium text-gray-900">{{ selectedEO?.eo_number }}</span>
                                </p>
                            </div>
                            <button @click="showIRRDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-full p-2">×</button>
                        </div>

                        <div class="mb-8">
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Existing Implementing Rules</h3>
                            <div v-if="selectedEO?.implementing_rules?.length > 0" class="space-y-3">
                                <div v-for="rule in selectedEO.implementing_rules" :key="rule.id" class="flex items-center justify-between p-3 rounded-lg border border-gray-100 bg-gray-50 hover:border-blue-200 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-white p-2 rounded border border-gray-200">
                                            <FileText class="w-5 h-5 text-gray-500" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ rule.status }}</p>
                                            <p class="text-xs text-gray-500">Lead: {{ rule.lead_office?.name }}</p>
                                        </div>
                                    </div>
                                    <a :href="rule.file_url" target="_blank" class="text-xs font-medium text-blue-600 hover:underline flex items-center gap-1">
                                        <Download class="w-3 h-3" /> Download
                                    </a>
                                </div>
                            </div>
                            <div v-else class="text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                                <p class="text-sm text-gray-500">No Implementing Rules and Regulations encoded yet.</p>
                            </div>
                        </div>

                        <div class="bg-green-50/50 rounded-xl border border-green-100 p-5">
                            <h3 class="text-sm font-bold text-green-900 uppercase tracking-wide mb-4 flex items-center gap-2">
                                <Plus class="w-4 h-4" /> Add New Rule
                            </h3>
                            <form @submit.prevent="submitIRR" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Implementation Status</label>
                                    <select v-model="irrForm.status" class="w-full rounded-lg border-gray-300 bg-white">
                                        <option v-for="s in irrStatuses" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Office in Charge of IRR</label>
                                    <select v-model="irrForm.lead_office_id" class="w-full rounded-lg border-gray-300 bg-white">
                                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Upload IRR File (PDF)</label>
                                    <input type="file" @change="handleIRRFileChange" accept="application/pdf" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:bg-white file:text-green-700 hover:file:bg-green-50"/>
                                    <p v-if="irrForm.errors.file" class="text-red-500 text-xs mt-1">{{ irrForm.errors.file }}</p>
                                </div>
                                <div class="flex justify-end pt-2">
                                    <button type="submit" class="bg-green-600 px-4 py-2 text-white rounded-lg hover:bg-green-700 shadow-sm text-sm font-medium" :disabled="irrForm.processing">
                                        {{ irrForm.processing ? 'Uploading...' : 'Save Rule' }}
                                    </button>
                                </div>
                            </form>
                        </div>
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