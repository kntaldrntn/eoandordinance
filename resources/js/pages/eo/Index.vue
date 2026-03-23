<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { 
    FileText, Plus, Search, Calendar, Building2, Link as LinkIcon, 
    BookOpen, Download, AlertCircle, Clock, Trash2, CheckCircle2, XCircle, Info, Users,
    Pencil
} from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch, computed } from 'vue';
import { route } from 'ziggy-js';

// --- Props ---
const props = defineProps<{
    eos: {
        data: Array<any>;
        links: Array<any>;
        from: number; to: number; total: number;
        current_page: number; last_page: number;
    };
    departments: Array<{ id: number; name: string }>;
    statuses: Array<{ id: number; name: string }>;
    existing_eos: Array<{ id: number; eo_number: string; title: string }>;
    // ADDED: People Registry for Autocomplete
    peopleRegistry: Array<{ name: string; title: string; type: string }>;
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

// --- SUGGESTIVE INPUT LOGIC ---
const activeSuggestion = ref<string | null>(null);

// UPDATED: Now supports filtering by type ('Internal' | 'External') and splits by newlines
const getSuggestions = (query: any, typeFilter: string | null = null) => {
    if (!query || typeof query !== 'string') {
        let list = props.peopleRegistry ? props.peopleRegistry : [];
        if (typeFilter) list = list.filter(p => p.type === typeFilter);
        return list.slice(0, 10);
    }

    // Split by commas OR newlines so textareas parse properly
    const parts = query.split(/[\n,]+/).map(s => s.trim());
    const currentSearch = parts[parts.length - 1].toLowerCase();

    if (!currentSearch) {
        let list = props.peopleRegistry ? props.peopleRegistry : [];
        if (typeFilter) list = list.filter(p => p.type === typeFilter);
        return list.slice(0, 10);
    }

    return props.peopleRegistry.filter(p => {
        if (typeFilter && p.type !== typeFilter) return false;

        const safeName = p.name ? String(p.name).toLowerCase() : '';
        const safeTitle = p.title ? String(p.title).toLowerCase() : '';
        
        return safeName.includes(currentSearch) || safeTitle.includes(currentSearch);
    }).slice(0, 10);
};

// UPDATED: Smarter formatting for Textareas
const selectPerson = (field: string, name: string) => {
    if (field === 'chairman') form.committee_details.council.chairman = name;
    else if (field === 'vice_chairman') form.committee_details.council.vice_chairman = name;
    else if (field === 'twg_head') form.committee_details.council.twg_head = name;
    else {
        let current = '';
        if (field === 'co_chairman') current = form.committee_details.council.co_chairmans;
        else if (field === 'council_internal') current = form.committee_details.council.internal_members;
        else if (field === 'council_external') current = form.committee_details.council.external_members;
        else if (field === 'secretariat') current = form.committee_details.council.secretariat;
        else if (field === 'twg_internal') current = form.committee_details.council.twg_internal_members;
        else if (field === 'twg_external') current = form.committee_details.council.twg_external_members;
        else if (field === 'program_internal') current = form.committee_details.program.internal_members;
        else if (field === 'program_external') current = form.committee_details.program.external_members;

        let newValue = '';
        if (current && current.trim() !== '') {
            // Find the last place the user pressed 'Enter' or typed a ','
            const lastDelimiter = Math.max(current.lastIndexOf(','), current.lastIndexOf('\n'));
            if (lastDelimiter !== -1) {
                newValue = current.substring(0, lastDelimiter + 1) + ' ' + name;
            } else {
                 newValue = name;
            }
        } else {
            newValue = name;
        }

        // Apply specific formatting based on the field
        if (field === 'co_chairman') {
            newValue += ', '; // Inline inputs get commas
            form.committee_details.council.co_chairmans = newValue;
        } else {
            newValue += ',\n'; // Textareas get a newline to stay visually clean
            if (field === 'council_internal') form.committee_details.council.internal_members = newValue;
            else if (field === 'council_external') form.committee_details.council.external_members = newValue;
            else if (field === 'secretariat') form.committee_details.council.secretariat = newValue;
            else if (field === 'twg_internal') form.committee_details.council.twg_internal_members = newValue;
            else if (field === 'twg_external') form.committee_details.council.twg_external_members = newValue;
            else if (field === 'program_internal') form.committee_details.program.internal_members = newValue;
            else if (field === 'program_external') form.committee_details.program.external_members = newValue;
        }
    }
    
    activeSuggestion.value = null;
};

// Helper for default committee details
const defaultCommitteeDetails = () => ({
    type: 'none', 
    council: {
        chairman: '', co_chairmans: '', vice_chairman: '',
        internal_members: '', 
        external_members: '',
        secretariat: '',
        twg_head: '', twg_internal_members: '', twg_external_members: ''
    },
    program: {
        lead_office_id: '' as string | number, co_lead_office_id: '' as string | number,
        internal_members: '', 
        external_members: ''
    }
});

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
    committee_details: defaultCommitteeDetails(), 
});

// --- IRR MODAL STATE ---
const showIRRDialog = ref(false);
const selectedEO = ref<any>(null);
const irrOfficeSearchQuery = ref(''); 

const irrForm = useForm({
    executive_order_id: '' as string | number,
    lead_office_id: '' as string | number,
    support_office_ids: [] as number[], 
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
    form.committee_details = defaultCommitteeDetails();
    showDialog.value = true;
}

function openEditDialog(eo: any) {
    isEdit.value = true;
    editingId.value = eo.id;
    selectedRecord.value = eo; 
    activeModalTab.value = 'details';
    officeSearchQuery.value = '';
    
    form.clearErrors();
    
    form.eo_number = eo.eo_number;
    form.title = eo.title;
    form.date_issued = eo.date_issued ? eo.date_issued.split('T')[0] : '';
    form.effectivity_date = eo.effectivity_date ? eo.effectivity_date.split('T')[0] : '';
    form.legal_basis = eo.legal_basis || '';
    form.status_id = eo.status_id;
    form.is_active = Boolean(eo.is_active);
    
    form.amends_eo_id = eo.amends_eo_id || '';
    form.relationship_type = eo.relationship_type || 'Amends'; 
    form.remarks = eo.remarks || '';

    // Load JSON details (or default if null)
    form.committee_details = eo.committee_details || defaultCommitteeDetails();

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
    irrOfficeSearchQuery.value = ''; 
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
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 font-semibold">EO Tracking</th>
                                <th class="px-6 py-4 font-semibold w-1/3">Subject Title</th>
                                <th class="px-6 py-4 font-semibold">Lead Office</th>
                                <th class="px-6 py-4 font-semibold">Status</th>
                                <th class="px-6 py-4 text-center font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-if="isLoading">
                                <tr v-for="n in 5" :key="n" class="animate-pulse">
                                    <td colspan="5" class="px-6 py-4"><div class="h-4 bg-gray-100 rounded w-full"></div></td>
                                </tr>
                            </template>
                            
                            <template v-else-if="eos.data.length > 0">
                                <tr v-for="eo in eos.data" :key="eo.id" 
                                    class="transition-colors border-l-4" 
                                    :class="eo.is_active ? 'hover:bg-gray-50 border-transparent' : 'bg-gray-50/60 opacity-80 border-red-400'">
                                    
                                    <td class="px-6 py-4 align-top">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-mono font-bold text-blue-600 text-base">{{ eo.eo_number }}</span>
                                            <div class="text-xs text-gray-600 mt-1 flex flex-col gap-0.5">
                                                <span><span class="text-gray-400 font-medium">Issued:</span> {{ formatDate(eo.date_issued) }}</span>
                                                <span><span class="text-gray-400 font-medium">Effective:</span> {{ formatDate(eo.effectivity_date) }}</span>
                                            </div>
                                            <div v-if="!eo.is_active" class="mt-1 inline-flex items-center gap-1 w-fit px-2 py-0.5 rounded bg-red-100 text-red-600 text-[10px] font-bold uppercase border border-red-200">
                                                <XCircle class="w-3 h-3" /> Inactive
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 align-top">
                                        <div class="text-gray-900 font-semibold mb-2 leading-snug">{{ eo.title }}</div>
                                        
                                        <div v-if="eo.parent_e_o" class="mb-2 flex items-center gap-1 text-xs text-amber-700 font-bold bg-amber-50 px-2 py-1 rounded w-fit border border-amber-100 uppercase">
                                            <AlertCircle class="w-3.5 h-3.5" /> 
                                            {{ eo.relationship_type }}: {{ eo.parent_e_o.eo_number }}
                                        </div>

                                        <div v-if="eo.remarks" class="mt-2 pl-3 border-l-2 border-gray-300 bg-gray-50 py-1.5 pr-3 rounded-r w-fit">
                                            <p class="text-xs text-gray-600 italic">"{{ eo.remarks }}"</p>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-xs text-gray-600 align-top">
                                        <span class="flex items-center gap-1 mt-1">
                                            <Building2 class="w-4 h-4 text-gray-400" />
                                            {{ getLeadOffice(eo.departments) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 align-top">
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 border border-gray-200 mt-1">
                                            {{ eo.status?.name }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-center align-middle">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="openIRRDialog(eo)" class="group relative flex items-center justify-center rounded-lg bg-green-50 p-2 text-green-600 hover:bg-green-100 transition-colors" title="Manage Implementing Rules">
                                                <BookOpen class="w-4 h-4" />
                                                <span v-if="eo.implementing_rules?.length > 0" class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full bg-red-500 border border-white"></span>
                                            </button>
                                            <div class="h-4 w-px bg-gray-200 mx-1"></div>
                                            <a v-if="eo.file_url" :href="eo.file_url" target="_blank" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View PDF"><Download class="w-4 h-4" /></a>
                                            <button v-if="$page.props.auth.user.role !== 'user'" @click="openEditDialog(eo)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                                <Pencil class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            
                            <tr v-else>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="bg-gray-50 p-4 rounded-full mb-3"><Search class="h-6 w-6 text-gray-400" /></div>
                                        <p class="text-base font-medium text-gray-900">No Executive Orders found</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto" @click="activeSuggestion = null">
                        
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
                            <button @click="activeModalTab = 'committee'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all flex items-center gap-1.5" :class="activeModalTab === 'committee' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'"><Users class="w-3.5 h-3.5"/> Committees / Programs</button>
                            <button @click="activeModalTab = 'history'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeModalTab === 'history' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Audit Log</button>
                        </div>
                        <div v-else class="flex items-center gap-6 border-b border-gray-100 mb-6">
                            <button @click="activeModalTab = 'details'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeModalTab === 'details' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Details</button>
                            <button @click="activeModalTab = 'committee'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all flex items-center gap-1.5" :class="activeModalTab === 'committee' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'"><Users class="w-3.5 h-3.5"/> Committees / Programs</button>
                        </div>

                        <form @submit.prevent="submitForm" class="space-y-5">
                            
                            <div v-show="activeModalTab === 'details'" class="space-y-5">
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
                            </div>

                            <div v-show="activeModalTab === 'committee'" class="space-y-6">
                                
                                <div class="flex items-center gap-4 bg-gray-50 p-2 rounded-lg border border-gray-200">
                                    <span class="text-sm font-semibold text-gray-700 ml-2">Type of Structure:</span>
                                    <select v-model="form.committee_details.type" class="rounded-lg border-gray-300 text-sm font-medium text-blue-700 bg-white">
                                        <option value="none">Standard EO (None)</option>
                                        <option value="council">Council / Committee / TWG</option>
                                        <option value="program">Program-Based Initiative</option>
                                    </select>
                                </div>

                                <div v-if="form.committee_details.type === 'council'" class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="relative">
                                            <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Chairman</label>
                                            <input 
                                                v-model="form.committee_details.council.chairman" 
                                                @focus.stop="activeSuggestion = 'chairman'"
                                                @click.stop="activeSuggestion = 'chairman'"
                                                @input="activeSuggestion = 'chairman'"
                                                type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type..." />
                                            
                                            <div v-if="activeSuggestion === 'chairman' && getSuggestions(form.committee_details.council.chairman).length > 0" 
                                                 class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="(person, idx) in getSuggestions(form.committee_details.council.chairman)" :key="idx"
                                                     @mousedown.prevent="selectPerson('chairman', person.name)"
                                                     class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                    <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                    <div class="text-[10px] text-gray-500">{{ person.title }} • <span :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</span></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="relative">
                                            <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Co-Chairman(s)</label>
                                            <input 
                                                v-model="form.committee_details.council.co_chairmans" 
                                                @focus.stop="activeSuggestion = 'co_chairman'"
                                                @click.stop="activeSuggestion = 'co_chairman'"
                                                @input="activeSuggestion = 'co_chairman'"
                                                type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type..." />
                                            
                                            <div v-if="activeSuggestion === 'co_chairman' && getSuggestions(form.committee_details.council.co_chairmans).length > 0" 
                                                 class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="(person, idx) in getSuggestions(form.committee_details.council.co_chairmans)" :key="idx"
                                                     @mousedown.prevent="selectPerson('co_chairman', person.name)"
                                                     class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                    <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                    <div class="text-[10px] text-gray-500">{{ person.title }} • <span :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</span></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="relative">
                                            <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Vice Chairman</label>
                                            <input 
                                                v-model="form.committee_details.council.vice_chairman" 
                                                @focus.stop="activeSuggestion = 'vice_chairman'"
                                                @click.stop="activeSuggestion = 'vice_chairman'"
                                                @input="activeSuggestion = 'vice_chairman'"
                                                type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type..." />
                                            
                                            <div v-if="activeSuggestion === 'vice_chairman' && getSuggestions(form.committee_details.council.vice_chairman).length > 0" 
                                                 class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="(person, idx) in getSuggestions(form.committee_details.council.vice_chairman)" :key="idx"
                                                     @mousedown.prevent="selectPerson('vice_chairman', person.name)"
                                                     class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                    <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                    <div class="text-[10px] text-gray-500">{{ person.title }} • <span :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="relative">
                                            <label class="mb-1 block text-sm font-bold text-gray-700">Internal Members</label>
                                            <textarea 
                                                v-model="form.committee_details.council.internal_members" 
                                                @focus.stop="activeSuggestion = 'council_internal'"
                                                @click.stop="activeSuggestion = 'council_internal'"
                                                @input="activeSuggestion = 'council_internal'"
                                                rows="4" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="List internal members..."></textarea>
                                            
                                            <div v-if="activeSuggestion === 'council_internal' && getSuggestions(form.committee_details.council.internal_members, 'Internal').length > 0" 
                                                 class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="(person, idx) in getSuggestions(form.committee_details.council.internal_members, 'Internal')" :key="idx"
                                                     @mousedown.prevent="selectPerson('council_internal', person.name)"
                                                     class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                    <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                    <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-blue-600">{{ person.type }}</span></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-4">
                                            <div class="relative">
                                                <label class="mb-1 block text-sm font-bold text-gray-700">External Members</label>
                                                <textarea 
                                                    v-model="form.committee_details.council.external_members" 
                                                    @focus.stop="activeSuggestion = 'council_external'"
                                                    @click.stop="activeSuggestion = 'council_external'"
                                                    @input="activeSuggestion = 'council_external'"
                                                    rows="2" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="List external representatives..."></textarea>
                                                
                                                <div v-if="activeSuggestion === 'council_external' && getSuggestions(form.committee_details.council.external_members, 'External').length > 0" 
                                                     class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(form.committee_details.council.external_members, 'External')" :key="idx"
                                                         @mousedown.prevent="selectPerson('council_external', person.name)"
                                                         class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                        <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-green-600">{{ person.type }}</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="relative">
                                                <label class="mb-1 block text-sm font-bold text-gray-700">Secretariat Members</label>
                                                <textarea 
                                                    v-model="form.committee_details.council.secretariat" 
                                                    @focus.stop="activeSuggestion = 'secretariat'"
                                                    @click.stop="activeSuggestion = 'secretariat'"
                                                    @input="activeSuggestion = 'secretariat'"
                                                    rows="1" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Who handles the secretariat?"></textarea>

                                                <div v-if="activeSuggestion === 'secretariat' && getSuggestions(form.committee_details.council.secretariat).length > 0" 
                                                     class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(form.committee_details.council.secretariat)" :key="idx"
                                                         @mousedown.prevent="selectPerson('secretariat', person.name)"
                                                         class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                        <div class="text-[10px] text-gray-500">{{ person.title }} • <span :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-t border-dashed border-gray-200 pt-5">
                                        <h3 class="text-sm font-bold text-gray-900 mb-4">Technical Working Group (TWG)</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="md:col-span-1 space-y-4">
                                                <div class="relative">
                                                    <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">TWG Head</label>
                                                    <input 
                                                        v-model="form.committee_details.council.twg_head" 
                                                        @focus.stop="activeSuggestion = 'twg_head'"
                                                        @click.stop="activeSuggestion = 'twg_head'"
                                                        @input="activeSuggestion = 'twg_head'"
                                                        type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type..." />
                                                    
                                                    <div v-if="activeSuggestion === 'twg_head' && getSuggestions(form.committee_details.council.twg_head).length > 0" 
                                                         class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                        <div v-for="(person, idx) in getSuggestions(form.committee_details.council.twg_head)" :key="idx"
                                                             @mousedown.prevent="selectPerson('twg_head', person.name)"
                                                             class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                            <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                            <div class="text-[10px] text-gray-500">{{ person.title }} • <span :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</span></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="relative">
                                                    <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">TWG External Members</label>
                                                    <textarea 
                                                        v-model="form.committee_details.council.twg_external_members" 
                                                        @focus.stop="activeSuggestion = 'twg_external'"
                                                        @click.stop="activeSuggestion = 'twg_external'"
                                                        @input="activeSuggestion = 'twg_external'"
                                                        rows="2" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="External experts..."></textarea>
                                                        
                                                    <div v-if="activeSuggestion === 'twg_external' && getSuggestions(form.committee_details.council.twg_external_members, 'External').length > 0" 
                                                         class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                        <div v-for="(person, idx) in getSuggestions(form.committee_details.council.twg_external_members, 'External')" :key="idx"
                                                             @mousedown.prevent="selectPerson('twg_external', person.name)"
                                                             class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                            <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                            <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-green-600">{{ person.type }}</span></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="md:col-span-2 relative">
                                                <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">TWG Internal Members</label>
                                                <textarea 
                                                    v-model="form.committee_details.council.twg_internal_members" 
                                                    @focus.stop="activeSuggestion = 'twg_internal'"
                                                    @click.stop="activeSuggestion = 'twg_internal'"
                                                    @input="activeSuggestion = 'twg_internal'"
                                                    rows="4" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="List TWG internal members..."></textarea>
                                                    
                                                <div v-if="activeSuggestion === 'twg_internal' && getSuggestions(form.committee_details.council.twg_internal_members, 'Internal').length > 0" 
                                                     class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(form.committee_details.council.twg_internal_members, 'Internal')" :key="idx"
                                                         @mousedown.prevent="selectPerson('twg_internal', person.name)"
                                                         class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                        <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-blue-600">{{ person.type }}</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="form.committee_details.type === 'program'" class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Lead Implementing Office</label>
                                            <select v-model="form.committee_details.program.lead_office_id" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white">
                                                <option value="">Select Lead Office...</option>
                                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Co-Lead Office</label>
                                            <select v-model="form.committee_details.program.co_lead_office_id" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white">
                                                <option value="">Select Co-Lead Office...</option>
                                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="relative">
                                            <label class="mb-1 block text-sm font-bold text-gray-700">Internal Members</label>
                                            <textarea 
                                                v-model="form.committee_details.program.internal_members" 
                                                @focus.stop="activeSuggestion = 'program_internal'"
                                                @click.stop="activeSuggestion = 'program_internal'"
                                                @input="activeSuggestion = 'program_internal'"
                                                rows="5" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="List internal members..."></textarea>
                                                
                                            <div v-if="activeSuggestion === 'program_internal' && getSuggestions(form.committee_details.program.internal_members, 'Internal').length > 0" 
                                                 class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="(person, idx) in getSuggestions(form.committee_details.program.internal_members, 'Internal')" :key="idx"
                                                     @mousedown.prevent="selectPerson('program_internal', person.name)"
                                                     class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                    <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                    <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-blue-600">{{ person.type }}</span></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="relative">
                                            <label class="mb-1 block text-sm font-bold text-gray-700">External Members / Partners</label>
                                            <textarea 
                                                v-model="form.committee_details.program.external_members" 
                                                @focus.stop="activeSuggestion = 'program_external'"
                                                @click.stop="activeSuggestion = 'program_external'"
                                                @input="activeSuggestion = 'program_external'"
                                                rows="5" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="List external partners, NGOs, or representatives..."></textarea>
                                                
                                            <div v-if="activeSuggestion === 'program_external' && getSuggestions(form.committee_details.program.external_members, 'External').length > 0" 
                                                 class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="(person, idx) in getSuggestions(form.committee_details.program.external_members, 'External')" :key="idx"
                                                     @mousedown.prevent="selectPerson('program_external', person.name)"
                                                     class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                    <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                    <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-green-600">{{ person.type }}</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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

                            <div v-show="activeModalTab !== 'history'">
                                <div v-if="activeModalTab === 'details'" class="border-t pt-5"><label class="mb-1 block text-sm font-medium text-gray-700">Document (PDF)</label><input type="file" @change="handleFileChange" accept="application/pdf" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-700 cursor-pointer"/></div>
                                <div class="flex justify-end gap-3 pt-4 mt-4" :class="activeModalTab === 'details' ? '' : 'border-t'">
                                    <button type="button" @click="showDialog = false" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancel</button>
                                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white shadow hover:bg-blue-700 transition">{{ form.processing ? 'Saving...' : 'Save Record' }}</button>
                                </div>
                            </div>
                        </form>
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