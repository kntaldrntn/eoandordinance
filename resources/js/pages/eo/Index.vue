<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { 
    FileText, Plus, Search, Calendar, Building2, Link as LinkIcon, 
    BookOpen, Download, AlertCircle, Clock, Trash2, CheckCircle2, XCircle, Info 
} from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch, computed } from 'vue';
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
            is_active: boolean; 
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
            amendments?: Array<{ id: number; eo_number: string }>;
            status: { name: string };
            departments: Array<{ id: number; name: string; pivot: { role: string } }>;
            implementing_rules: Array<{
                id: number;
                status: string;
                file_url: string;
                lead_office: { name: string };
                support_offices?: Array<{ name: string }>; // Transparency: Show who helps
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
        router.get(route('eo.index'), { search: searchTerm.value }, { 
            preserveState: true, 
            preserveScroll: true,
            onFinish: () => isLoading.value = false 
        });
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
const activeModalTab = ref('details'); 
const selectedRecord = ref<any>(null); 
const officeSearchQuery = ref('');

const form = useForm({
    amends_eo_id: '' as string | number,
    relationship_type: 'Amends', 
    remarks: '',
    eo_number: '',
    title: '',
    date_issued: '',
    effectivity_date: '',
    legal_basis: '',
    lead_office_id: '' as string | number,
    support_office_ids: [] as number[],
    status_id: '' as string | number,
    is_active: true,
    file: null as File | null,
});

// --- IRR MODAL STATE ---
const showIRRDialog = ref(false);
const selectedEO = ref<any>(null);
const irrOfficeSearchQuery = ref(''); // Separate search for IRR modal

const irrForm = useForm({
    executive_order_id: '' as string | number,
    lead_office_id: '' as string | number,
    support_office_ids: [] as number[], // Added support offices
    status: 'Drafting', 
    file: null as File | null,
});

const irrStatuses = ['Drafting', 'Pending Approval', 'Approved', 'Implemented', 'Delayed'];

// --- Computed Filters ---
const filteredDepartments = computed(() => {
    if (!officeSearchQuery.value) return props.departments;
    return props.departments.filter(dept => dept.name.toLowerCase().includes(officeSearchQuery.value.toLowerCase()));
});

const filteredIRRDepartments = computed(() => {
    if (!irrOfficeSearchQuery.value) return props.departments;
    return props.departments.filter(dept => dept.name.toLowerCase().includes(irrOfficeSearchQuery.value.toLowerCase()));
});

// --- Functions ---
function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    selectedRecord.value = null;
    activeModalTab.value = 'details';
    officeSearchQuery.value = '';
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEditDialog(eo: any) {
    isEdit.value = true;
    editingId.value = eo.id;
    selectedRecord.value = eo; 
    activeModalTab.value = 'details';
    officeSearchQuery.value = '';
    form.clearErrors();
    
    // Core Data
    form.eo_number = eo.eo_number;
    form.title = eo.title;
    form.date_issued = eo.date_issued ? eo.date_issued.split('T')[0] : '';
    form.effectivity_date = eo.effectivity_date ? eo.effectivity_date.split('T')[0] : '';
    form.legal_basis = eo.legal_basis || '';
    form.status_id = eo.status_id;
    form.is_active = Boolean(eo.is_active);
    
    // Relationship / Transparency Data
    form.amends_eo_id = eo.amends_eo_id || '';
    form.relationship_type = eo.relationship_type || 'Amends'; 
    form.remarks = eo.remarks || '';

    // Departments
    const lead = eo.departments.find((d: any) => d.pivot.role === 'lead');
    form.lead_office_id = lead ? lead.id : '';
    form.support_office_ids = eo.departments.filter((d: any) => d.pivot.role === 'support').map((d: any) => d.id);
    
    form.file = null; 
    showDialog.value = true;
}

const formatDate = (dateString: string) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatAuditDate = (date: string) => {
    return new Date(date).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const getChangedFields = (audit: any) => {
    if (audit.action === 'Created') return 'Initial Record Created';
    let newVals = audit.new_values;
    if (typeof newVals === 'string') try { newVals = JSON.parse(newVals); } catch (e) {}

    if (newVals) {
        const changes = Object.keys(newVals)
            .filter(key => key !== 'updated_at')
            .map(key => key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
        return changes.length > 0 ? `Modified: ${changes.join(', ')}` : 'Updated metadata';
    }
    return 'Updated record';
};

function openIRRDialog(eo: any) {
    selectedEO.value = eo;
    irrForm.reset();
    irrForm.clearErrors();
    const lead = eo.departments.find((d: any) => d.pivot.role === 'lead');
    irrForm.lead_office_id = lead ? lead.id : '';
    irrForm.executive_order_id = eo.id;
    irrOfficeSearchQuery.value = ''; // Reset search
    showIRRDialog.value = true;
}

function handleIRRFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) irrForm.file = target.files[0];
}

function submitIRR() {
    irrForm.post(route('irr.store'), {
        onSuccess: () => { notyf.success('IRR Added Successfully'); showIRRDialog.value = false; },
    });
}

function deleteIRR(irrId: number) {
    if (confirm('Are you sure you want to delete this rule?')) {
        router.delete(route('irr.destroy', irrId), {
            onSuccess: () => { notyf.success('IRR Deleted'); showIRRDialog.value = false; }
        });
    }
}

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) form.file = target.files[0];
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
                    <input v-model="searchTerm" type="text" placeholder="Search EO Number, Title or Keywords..." class="block w-full rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-10 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                    <button v-if="searchTerm" @click="clearSearch" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600">×</button>
                </div>
                <button v-if="$page.props.auth.user.role !== 'user'" class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-blue-700 transition" @click="openAddDialog">
                    <Plus class="h-4 w-4" /> Encode EO
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
                            <tr>
                                <th class="px-6 py-3 font-medium">EO Tracking</th>
                                <th class="px-6 py-3 font-medium w-1/2">Subject & Legal Context</th>
                                <th class="px-6 py-3 text-center font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-if="isLoading">
                                <tr v-for="n in 5" :key="n" class="animate-pulse">
                                    <td colspan="3" class="px-6 py-4"><div class="h-4 bg-gray-100 rounded w-full"></div></td>
                                </tr>
                            </template>
                            <template v-else-if="eos.data.length > 0">
                                <tr v-for="eo in eos.data" :key="eo.id" class="transition-colors border-l-4" :class="eo.is_active ? 'hover:bg-gray-50 border-transparent' : 'bg-gray-50/60 border-red-400'">
                                    
                                    <td class="px-6 py-4 align-top">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-mono font-bold text-blue-600 text-base">{{ eo.eo_number }}</span>
                                            
                                            <div class="text-xs text-gray-600 mt-1 flex flex-col gap-0.5">
                                                <span><span class="text-gray-400 font-medium">Issued:</span> {{ formatDate(eo.date_issued) }}</span>
                                                <span><span class="text-gray-400 font-medium">Effective:</span> {{ formatDate(eo.effectivity_date) }}</span>
                                            </div>

                                            <div v-if="!eo.is_active" class="inline-flex items-center gap-1 w-fit px-2 py-0.5 rounded bg-red-100 text-red-600 text-[10px] font-bold uppercase border border-red-200 mt-1">
                                                <XCircle class="w-3 h-3" /> Inactive
                                            </div>
                                            <div class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                                                <Building2 class="w-3 h-3" /> {{ getLeadOffice(eo.departments) }}
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 align-top">
                                        <div class="text-gray-900 font-semibold mb-2 leading-snug">{{ eo.title }}</div>
                                        
                                        <div v-if="eo.parent_e_o" class="p-2 bg-amber-50 rounded-lg border border-amber-100 text-xs mb-2">
                                            <div class="flex items-center gap-1 text-amber-800 font-bold uppercase">
                                                <AlertCircle class="w-3.5 h-3.5" /> 
                                                {{ eo.relationship_type }}: {{ eo.parent_e_o.eo_number }}
                                            </div>
                                            <p v-if="eo.remarks" class="text-amber-700 mt-1 italic pl-5">"{{ eo.remarks }}"</p>
                                        </div>

                                        <div v-if="eo.implementing_rules?.length > 0" class="flex flex-wrap gap-2 mt-2">
                                            <div v-for="rule in eo.implementing_rules" :key="rule.id" class="inline-flex items-center gap-1 px-2 py-1 bg-green-50 text-green-700 text-[10px] font-medium rounded border border-green-200">
                                                <CheckCircle2 class="w-3 h-3" /> IRR: {{ rule.status }}
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center align-middle">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="openIRRDialog(eo)" class="p-2 text-green-600 hover:bg-green-100 rounded-lg transition relative group" title="Manage IRR">
                                                <BookOpen class="w-4 h-4" />
                                                <span v-if="eo.implementing_rules?.length > 0" class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500 border border-white"></span>
                                            </button>
                                            <div class="h-4 w-px bg-gray-200 mx-1"></div>
                                            <a v-if="eo.file_url" :href="eo.file_url" target="_blank" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View PDF"><Download class="w-4 h-4" /></a>
                                            <button v-if="$page.props.auth.user.role !== 'user'" @click="openEditDialog(eo)" class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition" title="Edit"><Plus class="w-4 h-4 rotate-45" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                        
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <FileText class="w-5 h-5 text-blue-600" />
                                    {{ isEdit ? 'Edit Executive Order' : 'Encode Executive Order' }}
                                </h2>
                                <p class="text-xs text-gray-500 mt-1">{{ isEdit ? 'Update details.' : 'Create a new record.' }}</p>
                            </div>
                            <button @click="showDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-100 rounded-full p-1.5 transition">×</button>
                        </div>

                        <div v-if="isEdit" class="flex items-center gap-6 border-b border-gray-100 mb-6">
                            <button @click="activeModalTab = 'details'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeModalTab === 'details' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Details</button>
                            <button @click="activeModalTab = 'history'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeModalTab === 'history' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Audit Log</button>
                        </div>

                        <div v-show="activeModalTab === 'details'">
                            <form @submit.prevent="submitForm" class="space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">EO Number</label>
                                        <input v-model="form.eo_number" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" required />
                                        <p v-if="form.errors.eo_number" class="text-[10px] text-red-500 mt-1">{{ form.errors.eo_number }}</p>
                                    </div>
                                    <div class="space-y-3">
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Legal Status</label>
                                        <select v-model="form.status_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500">
                                            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                        </select>
                                        <div class="flex items-center justify-between bg-gray-50 p-2 rounded-lg border border-gray-200 px-3">
                                            <span class="text-xs text-gray-600 font-bold uppercase tracking-wider">Active Status</span>
                                            <button type="button" @click="form.is_active = !form.is_active" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-blue-500" :class="form.is_active ? 'bg-green-500' : 'bg-gray-300'">
                                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" :class="form.is_active ? 'translate-x-6' : 'translate-x-1'" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Title / Subject</label>
                                    <textarea v-model="form.title" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Full title..."></textarea>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Legal Basis</label>
                                    <input v-model="form.legal_basis" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. LGC Section 455" />
                                </div>

                                <div class="p-4 bg-amber-50/50 rounded-xl border border-amber-100 space-y-4">
                                    <div class="flex items-center gap-2 text-xs font-bold text-amber-800 uppercase mb-1">
                                        <Info class="w-4 h-4" /> Legal Context (Transparency)
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-amber-900">Target EO (Parent)</label>
                                            <select v-model="form.amends_eo_id" class="w-full rounded-lg border border-amber-200 px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-amber-500">
                                                <option value="">None (New Parent)</option>
                                                <option v-for="ex in existing_eos" :key="ex.id" :value="ex.id">{{ ex.eo_number }} - {{ ex.title }}</option>
                                            </select>
                                        </div>
                                        <div v-if="form.amends_eo_id">
                                            <label class="mb-1 block text-xs font-medium text-amber-900">Action Type</label>
                                            <select v-model="form.relationship_type" class="w-full rounded-lg border border-amber-200 px-3 py-2 bg-white text-xs font-bold uppercase focus:ring-2 focus:ring-amber-500">
                                                <option value="Amends">Amends</option>
                                                <option value="Repeals">Repeals</option>
                                                <option value="Supplements">Supplements</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div><label class="mb-1 block text-sm font-medium text-gray-700">Date Issued</label><input v-model="form.date_issued" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none" /></div>
                                    <div><label class="mb-1 block text-sm font-medium text-gray-700">Effectivity</label><input v-model="form.effectivity_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none" /></div>
                                </div>

                                <div class="bg-blue-50/50 p-5 rounded-xl border border-blue-100/80 space-y-4">
                                    <div><label class="mb-2 block text-sm font-semibold text-blue-900">Lead Office</label>
                                        <select v-model="form.lead_office_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 bg-white outline-none">
                                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-2"><label class="text-sm font-semibold text-blue-900">Support Offices</label><span class="text-[10px] font-bold text-blue-500 bg-blue-100 px-2 py-0.5 rounded-full">{{ form.support_office_ids.length }} Selected</span></div>
                                        <div class="relative mb-2"><Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" /><input v-model="officeSearchQuery" type="text" placeholder="Filter offices..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-gray-200 outline-none" /></div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto p-3 bg-white rounded-lg border border-blue-100">
                                            <label v-for="dept in filteredDepartments" :key="dept.id" class="flex items-center gap-2 cursor-pointer hover:bg-blue-50 p-1.5 rounded transition"><input type="checkbox" :value="dept.id" v-model="form.support_office_ids" class="rounded text-blue-600 focus:ring-blue-500 h-4 w-4 border-gray-300" /><span class="text-xs text-gray-700">{{ dept.name }}</span></label>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Remarks</label>
                                    <textarea v-model="form.remarks" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Add remarks about the EO..."></textarea>
                                </div>

                                <div class="border-t pt-5"><label class="mb-1 block text-sm font-medium text-gray-700">Document (PDF)</label><input type="file" @change="handleFileChange" accept="application/pdf" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-700 cursor-pointer"/></div>

                                <div class="flex justify-end gap-3 pt-4 border-t mt-4">
                                    <button type="button" @click="showDialog = false" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancel</button>
                                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white shadow hover:bg-blue-700 transition">{{ form.processing ? 'Saving...' : 'Save Record' }}</button>
                                </div>
                            </form>
                        </div>

                        <div v-show="activeModalTab === 'history'" class="py-4">
                            <div v-if="selectedRecord?.audits?.length > 0" class="space-y-6 relative border-l-2 border-gray-100 ml-4 pl-6">
                                <div v-for="audit in selectedRecord.audits" :key="audit.id" class="relative">
                                    <div class="absolute -left-[33px] top-1.5 h-3.5 w-3.5 rounded-full border-2 border-white" :class="audit.action === 'Created' ? 'bg-green-500' : 'bg-blue-500'"></div>
                                    <div class="flex flex-col gap-1">
                                        <p class="text-sm font-bold text-gray-900">{{ audit.action }} by <span class="text-blue-600">{{ audit.user?.name }}</span></p>
                                        <p class="text-xs text-gray-500">{{ getChangedFields(audit) }}</p>
                                        <span class="text-[10px] text-gray-400 font-mono">{{ formatAuditDate(audit.created_at) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-10"><Clock class="w-10 h-10 text-gray-200 mx-auto mb-2" /><p class="text-sm text-gray-400">No history found.</p></div>
                        </div>
                    </div>
                </div>
            </Transition>

            <Transition name="fade">
                <div v-if="showIRRDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                            <div class="flex items-center gap-3"><div class="bg-green-100 p-2 rounded-lg"><BookOpen class="w-6 h-6 text-green-600" /></div><div><h2 class="text-lg font-bold text-gray-900 leading-tight">Manage IRR</h2><p class="text-xs text-gray-500">For: <span class="font-semibold text-blue-600">{{ selectedEO?.eo_number }}</span></p></div></div>
                            <button @click="showIRRDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-100 rounded-full p-1.5 transition">×</button>
                        </div>

                        <div class="mb-8">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Implementing Rules List</h3>
                            <div v-if="selectedEO?.implementing_rules?.length > 0" class="space-y-2">
                                <div v-for="rule in selectedEO.implementing_rules" :key="rule.id" class="flex items-center justify-between p-3 rounded-xl border border-gray-100 bg-gray-50 transition-all hover:bg-white hover:shadow-sm">
                                    <div class="flex items-center gap-3"><FileText class="w-5 h-5 text-gray-400" /><div><p class="text-sm font-semibold text-gray-900">{{ rule.status }}</p><p class="text-[10px] text-gray-500 uppercase font-medium">Lead: {{ rule.lead_office?.name || 'Unassigned' }}</p></div></div>
                                    <div class="flex items-center gap-2"><a :href="rule.file_url" target="_blank" class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded">PDF</a><button @click="deleteIRR(rule.id)" class="text-gray-300 hover:text-red-600 p-1"><Trash2 class="w-4 h-4" /></button></div>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200"><p class="text-sm text-gray-400">No IRR records found.</p></div>
                        </div>

                        <div class="bg-green-50/50 rounded-2xl border border-green-100 p-5 shadow-inner">
                            <h3 class="text-xs font-bold text-green-800 uppercase tracking-widest mb-4 flex items-center gap-2"><Plus class="w-4 h-4" /> Add New Rule</h3>
                            <form @submit.prevent="submitIRR" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div><label class="mb-1 block text-sm font-medium text-gray-700">Implementation Status</label><select v-model="irrForm.status" class="w-full rounded-lg border border-gray-300 px-3 py-2 bg-white text-sm outline-none focus:ring-2 focus:ring-green-500"><option v-for="s in irrStatuses" :key="s" :value="s">{{ s }}</option></select></div>
                                    <div><label class="mb-1 block text-sm font-medium text-gray-700">Lead Office</label><select v-model="irrForm.lead_office_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 bg-white text-sm outline-none focus:ring-2 focus:ring-green-500"><option value="" disabled>Select Office</option><option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option></select></div>
                                </div>
                                
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Support Offices (IRR)</label>
                                    <div class="relative mb-2"><Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" /><input v-model="irrOfficeSearchQuery" type="text" placeholder="Search offices..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-gray-200 outline-none bg-white" /></div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-32 overflow-y-auto p-3 bg-white rounded-lg border border-green-100">
                                        <label v-for="dept in filteredIRRDepartments" :key="dept.id" class="flex items-center gap-2 cursor-pointer hover:bg-green-50 p-1.5 rounded transition"><input type="checkbox" :value="dept.id" v-model="irrForm.support_office_ids" class="rounded text-green-600 focus:ring-green-500 h-4 w-4 border-gray-300" /><span class="text-xs text-gray-700">{{ dept.name }}</span></label>
                                    </div>
                                </div>

                                <div><label class="mb-1 block text-sm font-medium text-gray-700">Upload PDF</label><input type="file" @change="handleIRRFileChange" accept="application/pdf" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-white file:text-green-700 cursor-pointer"/></div>
                                <div class="flex justify-end pt-2"><button type="submit" :disabled="irrForm.processing" class="bg-green-600 px-6 py-2 text-white rounded-lg hover:bg-green-700 shadow text-sm font-bold transition-all disabled:opacity-50">Save Rule</button></div>
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
</style>