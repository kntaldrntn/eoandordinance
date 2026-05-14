<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { 
    FileText, Plus, Search, Calendar, Building2, Link as LinkIcon, 
    Download, AlertCircle, Clock, Trash2, CheckCircle2, XCircle, Info, Users,
    Pencil, Eye
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
    classifications: Array<{ id: number; name: string }>;
    existing_eos: Array<{ id: number; eo_number: string; title: string }>;
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
const activeCouncilTab = ref('committee'); 
const selectedRecord = ref<any>(null); 

const selectableStatuses = computed(() => {
    const allowed = ['New', 'Amendment', 'Suspended'];
    return props.statuses.filter(s => allowed.includes(s.name));
});

const isAmendmentMode = computed(() => {
    const status = selectableStatuses.value.find(s => s.id === form.status_id);
    return status?.name === 'Amendment';
});

// --- PARENT EO SUGGESTIVE SEARCH ---
const parentSearchQuery = ref('');
const showParentDropdown = ref(false);

const filteredParents = computed(() => {
    let list = props.existing_eos || [];
    if (isEdit.value && editingId.value) list = list.filter(e => e.id !== editingId.value);
    
    if (parentSearchQuery.value && !parentSearchQuery.value.startsWith('AMENDING:')) {
        const q = parentSearchQuery.value.toLowerCase();
        list = list.filter(e => e.eo_number.toLowerCase().includes(q) || (e.title && e.title.toLowerCase().includes(q)));
    }
    return list.slice(0, 15);
});

const selectParent = (parent: any) => {
    form.amends_eo_id = parent.id;
    parentSearchQuery.value = `AMENDING: ${parent.eo_number}`;
    showParentDropdown.value = false;
    const amendStatus = selectableStatuses.value.find(s => s.name === 'Amendment');
    if (amendStatus) form.status_id = amendStatus.id;
};

const clearParent = () => {
    form.amends_eo_id = '';
    parentSearchQuery.value = '';
};

// --- DYNAMIC ARRAY HELPERS (MINIMUM 5, ALLOWS UNLIMITED) ---
const parseMembers = (val: any) => {
    let parsed: string[] = [];
    if (Array.isArray(val)) {
        parsed = [...val];
    } else if (val && typeof val === 'string') {
        parsed = val.split(/[\n,]+/).map(s => s.trim()).filter(s => s !== '');
    }
    
    // 🚀 FIXED: Pad the array to a MINIMUM of 5 inputs, but don't slice it!
    while (parsed.length < 5) parsed.push('');
    return parsed;
};

// 🚀 RESTORED: Add and Remove member functions
const addMember = (field: string) => {
    if (field === 'council_internal') form.committee_details.council.internal_members.push('');
    else if (field === 'council_external') form.committee_details.council.external_members.push('');
    else if (field === 'secretariat_members') form.committee_details.council.secretariat_members.push('');
    else if (field === 'twg_internal') form.committee_details.council.twg_internal_members.push('');
    else if (field === 'twg_external') form.committee_details.council.twg_external_members.push('');
    else if (field === 'program_internal') form.committee_details.program.internal_members.push('');
    else if (field === 'program_external') form.committee_details.program.external_members.push('');
};

const removeMember = (field: string, index: number) => {
    if (field === 'council_internal') form.committee_details.council.internal_members.splice(index, 1);
    else if (field === 'council_external') form.committee_details.council.external_members.splice(index, 1);
    else if (field === 'secretariat_members') form.committee_details.council.secretariat_members.splice(index, 1);
    else if (field === 'twg_internal') form.committee_details.council.twg_internal_members.splice(index, 1);
    else if (field === 'twg_external') form.committee_details.council.twg_external_members.splice(index, 1);
    else if (field === 'program_internal') form.committee_details.program.internal_members.splice(index, 1);
    else if (field === 'program_external') form.committee_details.program.external_members.splice(index, 1);
};

// --- SUGGESTIVE INPUT LOGIC (PEOPLE & DEPARTMENTS) ---
const activeSuggestion = ref<string | null>(null);

const getSuggestions = (query: any, typeFilter: string | null = null) => {
    if (!query || typeof query !== 'string') {
        let list = props.peopleRegistry ? props.peopleRegistry : [];
        if (typeFilter) list = list.filter(p => p.type === typeFilter);
        return list.slice(0, 10);
    }

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

const selectPerson = (field: string, name: string, index?: number) => {
    if (field === 'chairman') form.committee_details.council.chairman = name;
    else if (field === 'vice_chairman') form.committee_details.council.vice_chairman = name;
    else if (field === 'lead_secretariat') form.committee_details.council.lead_secretariat = name;
    else if (field === 'twg_head') form.committee_details.council.twg_head = name;
    else if (field === 'co_chairman') {
        let current = form.committee_details.council.co_chairmans;
        form.committee_details.council.co_chairmans = current ? current + ', ' + name : name;
    }
    else if (index !== undefined) {
        if (field === 'council_internal') form.committee_details.council.internal_members[index] = name;
        else if (field === 'council_external') form.committee_details.council.external_members[index] = name;
        else if (field === 'secretariat_members') form.committee_details.council.secretariat_members[index] = name;
        else if (field === 'twg_internal') form.committee_details.council.twg_internal_members[index] = name;
        else if (field === 'twg_external') form.committee_details.council.twg_external_members[index] = name;
        else if (field === 'program_internal') form.committee_details.program.internal_members[index] = name;
        else if (field === 'program_external') form.committee_details.program.external_members[index] = name;
    }
    activeSuggestion.value = null;
};

const leadOfficeSearch = ref('');
const filteredLeadOffices = computed(() => {
    if (!leadOfficeSearch.value) return props.departments.slice(0, 10);
    return props.departments.filter(d => d.name.toLowerCase().includes(leadOfficeSearch.value.toLowerCase())).slice(0, 10);
});

const selectLeadOffice = (dept: any) => {
    form.lead_office_id = dept.id;
    leadOfficeSearch.value = dept.name;
    activeSuggestion.value = null;
};

const clearLeadOffice = () => {
    form.lead_office_id = '';
    leadOfficeSearch.value = '';
};

const defaultCommitteeDetails = () => ({
    type: 'none', 
    council: {
        chairman: '', co_chairmans: '', vice_chairman: '',
        internal_members: ['', '', '', '', ''], 
        external_members: ['', '', '', '', ''],
        lead_secretariat: '',
        secretariat_members: ['', '', '', '', ''],
        twg_head: '', 
        twg_internal_members: ['', '', '', '', ''], 
        twg_external_members: ['', '', '', '', '']
    },
    program: {
        co_lead_office_id: '' as string | number,
        internal_members: ['', '', '', '', ''], 
        external_members: ['', '', '', '', '']
    }
});

const form = useForm({
    amends_eo_id: '' as string | number,
    eo_number: '',
    title: '',
    classification_id: '' as string | number,
    status_id: '' as string | number,
    date_issued: '',
    effectivity_date: '',
    legal_basis: '',
    declaration: '', 
    lead_office_id: '' as string | number,
    is_active: true,
    file: null as File | null,
    committee_details: defaultCommitteeDetails(), 
});

function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    selectedRecord.value = null;
    activeModalTab.value = 'details';
    activeCouncilTab.value = 'committee'; // Reset inner tab
    parentSearchQuery.value = ''; 
    leadOfficeSearch.value = '';

    form.reset();
    form.clearErrors();
    form.committee_details = defaultCommitteeDetails();
    
    const newStatus = selectableStatuses.value.find(s => s.name === 'New');
    if (newStatus) form.status_id = newStatus.id;

    showDialog.value = true;
}

function openEditDialog(eo: any) {
    isEdit.value = true;
    editingId.value = eo.id;
    selectedRecord.value = eo; 
    activeModalTab.value = 'details';
    activeCouncilTab.value = 'committee'; // Reset inner tab
    
    form.clearErrors();
    
    form.eo_number = eo.eo_number;
    form.title = eo.title;
    form.classification_id = eo.classification_id || ''; 
    form.status_id = eo.status_id || '';
    form.date_issued = eo.date_issued ? eo.date_issued.split('T')[0] : '';
    form.effectivity_date = eo.effectivity_date ? eo.effectivity_date.split('T')[0] : '';
    form.legal_basis = eo.legal_basis || '';
    form.declaration = eo.declaration || '';
    form.is_active = Boolean(eo.is_active);
    form.amends_eo_id = eo.amends_eo_id || '';

    if (eo.amends_eo_id) {
        const parent = props.existing_eos.find(e => e.id === eo.amends_eo_id);
        parentSearchQuery.value = parent ? `AMENDING: ${parent.eo_number}` : '';
    } else {
        parentSearchQuery.value = '';
    }

    const c = eo.committee_details || defaultCommitteeDetails();
    if (c.type === 'council') {
        c.council.internal_members = parseMembers(c.council.internal_members);
        c.council.external_members = parseMembers(c.council.external_members);
        c.council.lead_secretariat = c.council.lead_secretariat || '';
        c.council.secretariat_members = parseMembers(c.council.secretariat_members);
        c.council.twg_internal_members = parseMembers(c.council.twg_internal_members);
        c.council.twg_external_members = parseMembers(c.council.twg_external_members);
    } else if (c.type === 'program') {
        c.program.internal_members = parseMembers(c.program.internal_members);
        c.program.external_members = parseMembers(c.program.external_members);
    }
    form.committee_details = c;

    const lead = eo.departments.find((d: any) => d.pivot.role === 'lead');
    form.lead_office_id = lead ? lead.id : '';
    leadOfficeSearch.value = lead ? lead.name : '';
    
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

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) form.file = target.files[0];
}

function submitForm() {
    const joinMembers = (arr: any[]) => Array.isArray(arr) ? arr.filter(v => typeof v === 'string' && v.trim() !== '').join(',\n') : '';

    if (isEdit.value && editingId.value) {
        form.transform((data) => {
            const transformed = JSON.parse(JSON.stringify(data));
            transformed.file = data.file;

            if (transformed.committee_details.type === 'council') {
                transformed.committee_details.council.internal_members = joinMembers(transformed.committee_details.council.internal_members);
                transformed.committee_details.council.external_members = joinMembers(transformed.committee_details.council.external_members);
                transformed.committee_details.council.secretariat_members = joinMembers(transformed.committee_details.council.secretariat_members);
                transformed.committee_details.council.twg_internal_members = joinMembers(transformed.committee_details.council.twg_internal_members);
                transformed.committee_details.council.twg_external_members = joinMembers(transformed.committee_details.council.twg_external_members);
            } else if (transformed.committee_details.type === 'program') {
                transformed.committee_details.program.internal_members = joinMembers(transformed.committee_details.program.internal_members);
                transformed.committee_details.program.external_members = joinMembers(transformed.committee_details.program.external_members);
            }
            return { ...transformed, _method: 'PUT' };
        }).post(route('eo.update', editingId.value), {
            onSuccess: () => { showDialog.value = false; form.reset(); },
            onError: () => { notyf.error('Please check the form for missing or invalid fields.'); }
        });
    } else {
        form.transform((data) => {
            const transformed = JSON.parse(JSON.stringify(data));
            transformed.file = data.file;
            
            if (transformed.committee_details.type === 'council') {
                transformed.committee_details.council.internal_members = joinMembers(transformed.committee_details.council.internal_members);
                transformed.committee_details.council.external_members = joinMembers(transformed.committee_details.council.external_members);
                transformed.committee_details.council.secretariat_members = joinMembers(transformed.committee_details.council.secretariat_members);
                transformed.committee_details.council.twg_internal_members = joinMembers(transformed.committee_details.council.twg_internal_members);
                transformed.committee_details.council.twg_external_members = joinMembers(transformed.committee_details.council.twg_external_members);
            } else if (transformed.committee_details.type === 'program') {
                transformed.committee_details.program.internal_members = joinMembers(transformed.committee_details.program.internal_members);
                transformed.committee_details.program.external_members = joinMembers(transformed.committee_details.program.external_members);
            }
            return transformed;
        }).post(route('eo.store'), {
            onSuccess: () => { showDialog.value = false; form.reset(); },
            onError: () => { notyf.error('Please check the form for missing or invalid fields.'); }
        });
    }
}

const getLeadOffice = (depts: any[]) => {
    const lead = depts.find(d => d.pivot.role === 'lead');
    return lead ? lead.name : '—';
};

const breadcrumbs = [{ title: 'Executive Orders', href: '/eo' }];
</script>

<template>
    <Head title="EO Profiling" />

    <AppLayout :breadcrumbs="breadcrumbs">
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
                                            Amends: {{ eo.parent_e_o.eo_number }}
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
                                            <a v-if="eo.file_url" :href="eo.file_url" target="_blank" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View PDF Document">
                                                <Eye class="w-4 h-4" />
                                            </a>
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

                <div v-if="eos.last_page > 1" class="flex items-center justify-between border-t bg-gray-50 px-6 py-3">
                    <p class="text-sm text-gray-600">Showing <span class="font-medium">{{ eos.from }}</span>–<span class="font-medium">{{ eos.to }}</span> of <span class="font-medium">{{ eos.total }}</span></p>
                    <div class="flex gap-1">
                        <button v-for="(link, index) in eos.links.slice(1, -1)" :key="index" @click="goToPage(String(link.url))" :disabled="!link.url" class="rounded-md px-3 py-1 text-sm transition" :class="[link.active ? 'bg-blue-600 font-medium text-white shadow' : 'border bg-white text-gray-600 hover:bg-gray-100']"><span v-html="link.label"></span></button>
                    </div>
                </div>
            </div>

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto custom-scrollbar" @click="activeSuggestion = null; showParentDropdown = false">
                        
                        <div class="flex items-center justify-between mb-6 sticky top-0 bg-white/95 backdrop-blur py-2 z-20 border-b border-gray-100 pb-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <FileText class="w-5 h-5 text-blue-600" />
                                    {{ isEdit ? 'Edit Executive Order' : 'Encode Executive Order' }}
                                </h2>
                                <p class="text-xs text-gray-500 mt-1">{{ isEdit ? 'Update details.' : 'Create a new continuous record.' }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <button v-if="isEdit" @click="activeModalTab = activeModalTab === 'history' ? 'details' : 'history'" class="text-xs font-bold text-blue-600 hover:underline">
                                    {{ activeModalTab === 'history' ? 'Back to Edit' : 'View Audit History' }}
                                </button>
                                <button @click="showDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center transition">×</button>
                            </div>
                        </div>

                        <div v-if="activeModalTab === 'history'" class="py-4">
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
                            <div v-else class="text-center py-10">
                                <Clock class="w-10 h-10 text-gray-200 mx-auto mb-2" />
                                <p class="text-sm text-gray-400">No history found.</p>
                            </div>
                        </div>
                        
                        <form v-else @submit.prevent="submitForm" class="space-y-8">
                            
                            <div class="bg-gray-50/50 p-5 rounded-xl border border-gray-100 space-y-5 relative">
                                <div class="absolute -top-3 left-4 bg-white px-2 text-[10px] font-bold text-blue-600 uppercase tracking-widest border border-blue-100 rounded-full">1. Metadata & Classification</div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-5 pt-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">EO Number <span class="text-red-500">*</span></label>
                                        <input v-model="form.eo_number" type="text" placeholder="e.g. EO No. 1, s. 2026" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" required />
                                    </div>
                                    
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-blue-600 uppercase">Structure Type</label>
                                        <select v-model="form.committee_details.type" class="w-full rounded-lg border border-blue-300 text-sm px-3 py-2 bg-blue-50/50 text-blue-800 font-bold outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                                            <option value="none">Standard EO</option>
                                            <option value="council">Council / Committee / TWG</option>
                                            <option value="program">Program-Based Initiative</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Status <span class="text-red-500">*</span></label>
                                        <select v-model="form.status_id" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" required>
                                            <option value="" disabled>Select Status</option>
                                            <option v-for="status in selectableStatuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Classification</label>
                                        <select v-model="form.classification_id" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">Select Classification</option>
                                            <option v-for="cls in classifications" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-4 pt-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Executive Order Title <span class="text-red-500">*</span></label>
                                        <textarea v-model="form.title" rows="2" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Full title including subject matter..." required></textarea>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Legal Basis</label>
                                        <input v-model="form.legal_basis" type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. LGC Section 455" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Date Issued <span class="text-red-500">*</span></label>
                                            <input v-model="form.date_issued" type="date" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" required />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Effectivity</label>
                                            <input v-model="form.effectivity_date" type="date" :min="form.date_issued" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" />
                                        </div>
                                    </div>
                                </div>

                                <Transition name="fade">
                                    <div v-if="isAmendmentMode" class="p-4 bg-amber-50/50 rounded-xl border border-amber-100 space-y-4 mt-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2 text-[10px] font-bold text-amber-800 uppercase tracking-widest">
                                                <Info class="w-3.5 h-3.5" /> Historical Tracker: Amendment Linking
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] text-gray-600 font-bold uppercase tracking-wider">Active State</span>
                                                <button type="button" @click="form.is_active = !form.is_active" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:ring-2 focus:ring-blue-500" :class="form.is_active ? 'bg-green-500' : 'bg-gray-300'">
                                                    <span class="inline-block h-3 w-3 transform rounded-full bg-white transition-transform" :class="form.is_active ? 'translate-x-5' : 'translate-x-1'" />
                                                </button>
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <p class="text-[10px] text-amber-700 italic mb-2">Search for a Parent EO. If selected, the parent will automatically be marked as "Amended".</p>
                                            
                                            <div class="relative">
                                                <input 
                                                    type="text" 
                                                    v-model="parentSearchQuery"
                                                    @focus.stop="showParentDropdown = true"
                                                    @click.stop="showParentDropdown = true"
                                                    class="w-full rounded-lg border border-amber-200 text-sm px-3 py-2 pr-8 bg-white outline-none focus:ring-2 focus:ring-amber-500"
                                                    placeholder="Search Parent EO Number or Title to link..."
                                                />
                                                <button v-if="form.amends_eo_id || parentSearchQuery" @click="clearParent" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-400 hover:text-amber-600 transition">
                                                    <XCircle class="w-4 h-4" />
                                                </button>
                                            </div>

                                            <div v-if="showParentDropdown && filteredParents.length > 0" class="absolute z-20 w-full mt-1 bg-white border border-amber-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="ex in filteredParents" :key="ex.id"
                                                     @mousedown.prevent="selectParent(ex)"
                                                     class="px-3 py-2 hover:bg-amber-50 cursor-pointer border-b border-amber-50 last:border-0 text-sm transition-colors">
                                                    <div class="font-bold text-amber-900">{{ ex.eo_number }}</div>
                                                    <div class="text-xs text-gray-500 truncate">{{ ex.title }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Transition>
                            </div>

                            <div class="bg-blue-50/30 p-5 rounded-xl border border-blue-100 relative">
                                <div class="absolute -top-3 left-4 bg-white px-2 text-[10px] font-bold text-blue-600 uppercase tracking-widest border border-blue-100 rounded-full">2. Signed Document</div>
                                <div class="pt-2">
                                    <label class="mb-2 block text-xs font-bold text-gray-600 uppercase">Upload PDF File</label>
                                    <input type="file" @change="handleFileChange" accept="application/pdf" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-600 file:text-white file:font-medium hover:file:bg-blue-700 cursor-pointer transition-colors"/>
                                    <p class="text-[10px] text-gray-400 mt-2 italic">Max size 20MB. Ensure the document is fully signed before uploading.</p>
                                </div>
                            </div>

                            <div class="bg-gray-50/50 p-5 rounded-xl border border-gray-100 space-y-6 relative animate-in fade-in slide-in-from-bottom-2 duration-300">
                                
                                <div class="absolute -top-3 left-4 bg-white px-2 text-[10px] font-bold text-blue-600 uppercase tracking-widest border border-blue-100 rounded-full">
                                    3. {{ form.committee_details.type === 'none' ? 'Standard EO Details' : (form.committee_details.type === 'council' ? 'Council / Committee Details' : 'Program Implementation Details') }}
                                </div>

                                <div class="pt-2" :class="form.committee_details.type !== 'none' ? 'border-b border-gray-200 pb-6' : ''">
                                    <div class="relative w-full">
                                        <label class="mb-1 block text-xs font-bold text-blue-600 uppercase">Lead Implementing Office</label>
                                        <input 
                                            type="text" 
                                            v-model="leadOfficeSearch"
                                            @focus.stop="activeSuggestion = 'lead_office'"
                                            @click.stop="activeSuggestion = 'lead_office'"
                                            @input="activeSuggestion = 'lead_office'; form.lead_office_id = ''"
                                            class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 pr-8 outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                            placeholder="Search and select lead department..."
                                        />
                                        <button v-if="form.lead_office_id || leadOfficeSearch" @click.prevent="clearLeadOffice" type="button" class="absolute right-2 top-[28px] text-gray-400 hover:text-gray-600 transition">
                                            <XCircle class="w-4 h-4" />
                                        </button>

                                        <div v-if="activeSuggestion === 'lead_office' && filteredLeadOffices.length > 0" 
                                             class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                            <div v-for="dept in filteredLeadOffices" :key="dept.id"
                                                 @mousedown.prevent="selectLeadOffice(dept)"
                                                 class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                <div class="text-sm font-medium text-gray-800">{{ dept.name }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="form.committee_details.type === 'none'" class="animate-in fade-in duration-300">
                                    <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Declaration / Directive</label>
                                    <textarea 
                                        v-model="form.declaration" 
                                        rows="4" 
                                        class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" 
                                        placeholder="Enter the main declaration, directive, or order..."></textarea>
                                </div>

                                <div v-if="form.committee_details.type === 'council'" class="space-y-6 animate-in fade-in duration-300">
                                    
                                    <div class="flex items-center gap-6 border-b border-gray-200">
                                        <button type="button" @click="activeCouncilTab = 'committee'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeCouncilTab === 'committee' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Council / Committee</button>
                                        <button type="button" @click="activeCouncilTab = 'secretariat'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeCouncilTab === 'secretariat' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Secretariat</button>
                                        <button type="button" @click="activeCouncilTab = 'twg'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeCouncilTab === 'twg' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Technical Working Group</button>
                                    </div>

                                    <div v-show="activeCouncilTab === 'committee'" class="space-y-6 animate-in fade-in duration-300">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div class="relative">
                                                <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Chairman</label>
                                                <input 
                                                    v-model="form.committee_details.council.chairman" 
                                                    @focus.stop="activeSuggestion = 'chairman'"
                                                    @click.stop="activeSuggestion = 'chairman'"
                                                    @input="activeSuggestion = 'chairman'"
                                                    type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                
                                                <div v-if="activeSuggestion === 'chairman' && getSuggestions(form.committee_details.council.chairman).length > 0" 
                                                     class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
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
                                                    type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                
                                                <div v-if="activeSuggestion === 'co_chairman' && getSuggestions(form.committee_details.council.co_chairmans).length > 0" 
                                                     class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
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
                                                    type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                
                                                <div v-if="activeSuggestion === 'vice_chairman' && getSuggestions(form.committee_details.council.vice_chairman).length > 0" 
                                                     class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(form.committee_details.council.vice_chairman)" :key="idx"
                                                         @mousedown.prevent="selectPerson('vice_chairman', person.name)"
                                                         class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                        <div class="text-[10px] text-gray-500">{{ person.title }} • <span :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                            <div class="space-y-2">
                                                <label class="mb-2 block text-xs font-bold text-gray-500 uppercase">Internal Members</label>
                                                <div v-for="(member, index) in form.committee_details.council.internal_members" :key="'ci_'+index" class="relative flex items-center gap-2">
                                                    <span class="text-[10px] font-bold text-gray-400 w-3 text-right">{{ index + 1 }}.</span>
                                                    <div class="relative flex-1">
                                                        <input 
                                                            v-model="form.committee_details.council.internal_members[index]" 
                                                            @focus.stop="activeSuggestion = 'council_internal_' + index"
                                                            @click.stop="activeSuggestion = 'council_internal_' + index"
                                                            @input="activeSuggestion = 'council_internal_' + index"
                                                            type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                        
                                                        <div v-if="activeSuggestion === 'council_internal_' + index && getSuggestions(form.committee_details.council.internal_members[index], 'Internal').length > 0" 
                                                             class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                            <div v-for="(person, idx) in getSuggestions(form.committee_details.council.internal_members[index], 'Internal')" :key="idx"
                                                                 @mousedown.prevent="selectPerson('council_internal', person.name, index)"
                                                                 class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                                <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                                <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-blue-600">{{ person.type }}</span></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button @click="removeMember('council_internal', index)" type="button" class="text-red-300 hover:text-red-500 transition p-1" v-if="form.committee_details.council.internal_members.length > 1">
                                                        <XCircle class="w-4 h-4" />
                                                    </button>
                                                </div>
                                                <button type="button" @click="addMember('council_internal')" class="mt-1 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-blue-600 hover:text-blue-800 transition ml-5">
                                                    <Plus class="w-3.5 h-3.5" /> Add Row
                                                </button>
                                            </div>

                                            <div class="space-y-2">
                                                <label class="mb-2 block text-xs font-bold text-gray-500 uppercase">External Members</label>
                                                <div v-for="(member, index) in form.committee_details.council.external_members" :key="'ce_'+index" class="relative flex items-center gap-2">
                                                    <span class="text-[10px] font-bold text-gray-400 w-3 text-right">{{ index + 1 }}.</span>
                                                    <div class="relative flex-1">
                                                        <input 
                                                            v-model="form.committee_details.council.external_members[index]" 
                                                            @focus.stop="activeSuggestion = 'council_external_' + index"
                                                            @click.stop="activeSuggestion = 'council_external_' + index"
                                                            @input="activeSuggestion = 'council_external_' + index"
                                                            type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                        
                                                        <div v-if="activeSuggestion === 'council_external_' + index && getSuggestions(form.committee_details.council.external_members[index], 'External').length > 0" 
                                                             class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                            <div v-for="(person, idx) in getSuggestions(form.committee_details.council.external_members[index], 'External')" :key="idx"
                                                                 @mousedown.prevent="selectPerson('council_external', person.name, index)"
                                                                 class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                                <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                                <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-green-600">{{ person.type }}</span></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button @click="removeMember('council_external', index)" type="button" class="text-red-300 hover:text-red-500 transition p-1" v-if="form.committee_details.council.external_members.length > 1">
                                                        <XCircle class="w-4 h-4" />
                                                    </button>
                                                </div>
                                                <button type="button" @click="addMember('council_external')" class="mt-1 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-blue-600 hover:text-blue-800 transition ml-5">
                                                    <Plus class="w-3.5 h-3.5" /> Add Row
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-show="activeCouncilTab === 'secretariat'" class="space-y-6 animate-in fade-in duration-300">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                            <div class="space-y-6">
                                                <div class="relative">
                                                    <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Lead Secretariat</label>
                                                    <input 
                                                        v-model="form.committee_details.council.lead_secretariat" 
                                                        @focus.stop="activeSuggestion = 'lead_secretariat'"
                                                        @click.stop="activeSuggestion = 'lead_secretariat'"
                                                        @input="activeSuggestion = 'lead_secretariat'"
                                                        type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                    
                                                    <div v-if="activeSuggestion === 'lead_secretariat' && getSuggestions(form.committee_details.council.lead_secretariat).length > 0" 
                                                         class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                        <div v-for="(person, idx) in getSuggestions(form.committee_details.council.lead_secretariat)" :key="idx"
                                                             @mousedown.prevent="selectPerson('lead_secretariat', person.name)"
                                                             class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                            <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                            <div class="text-[10px] text-gray-500">{{ person.title }} • <span :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</span></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="space-y-2">
                                                <label class="mb-2 block text-xs font-bold text-gray-500 uppercase">Secretariat Members</label>
                                                <div v-for="(member, index) in form.committee_details.council.secretariat_members" :key="'sm_'+index" class="relative flex items-center gap-2">
                                                    <span class="text-[10px] font-bold text-gray-400 w-3 text-right">{{ index + 1 }}.</span>
                                                    <div class="relative flex-1">
                                                        <input 
                                                            v-model="form.committee_details.council.secretariat_members[index]" 
                                                            @focus.stop="activeSuggestion = 'secretariat_members_' + index"
                                                            @click.stop="activeSuggestion = 'secretariat_members_' + index"
                                                            @input="activeSuggestion = 'secretariat_members_' + index"
                                                            type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                        
                                                        <div v-if="activeSuggestion === 'secretariat_members_' + index && getSuggestions(form.committee_details.council.secretariat_members[index]).length > 0" 
                                                             class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                            <div v-for="(person, idx) in getSuggestions(form.committee_details.council.secretariat_members[index])" :key="idx"
                                                                 @mousedown.prevent="selectPerson('secretariat_members', person.name, index)"
                                                                 class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                                <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                                <div class="text-[10px] text-gray-500">{{ person.title }} • <span :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</span></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button @click="removeMember('secretariat_members', index)" type="button" class="text-red-300 hover:text-red-500 transition p-1" v-if="form.committee_details.council.secretariat_members.length > 1">
                                                        <XCircle class="w-4 h-4" />
                                                    </button>
                                                </div>
                                                <button type="button" @click="addMember('secretariat_members')" class="mt-1 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-blue-600 hover:text-blue-800 transition ml-5">
                                                    <Plus class="w-3.5 h-3.5" /> Add Row
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-show="activeCouncilTab === 'twg'" class="space-y-6 animate-in fade-in duration-300">
                                        <div class="w-full md:w-1/2 pr-4">
                                            <div class="relative">
                                                <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">TWG Head</label>
                                                <input 
                                                    v-model="form.committee_details.council.twg_head" 
                                                    @focus.stop="activeSuggestion = 'twg_head'"
                                                    @click.stop="activeSuggestion = 'twg_head'"
                                                    @input="activeSuggestion = 'twg_head'"
                                                    type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                
                                                <div v-if="activeSuggestion === 'twg_head' && getSuggestions(form.committee_details.council.twg_head).length > 0" 
                                                     class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(form.committee_details.council.twg_head)" :key="idx"
                                                         @mousedown.prevent="selectPerson('twg_head', person.name)"
                                                         class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                        <div class="text-[10px] text-gray-500">{{ person.title }} • <span :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                            <div class="space-y-2">
                                                <label class="mb-2 block text-xs font-bold text-gray-500 uppercase">TWG Internal Members</label>
                                                <div v-for="(member, index) in form.committee_details.council.twg_internal_members" :key="'ti_'+index" class="relative flex items-center gap-2">
                                                    <span class="text-[10px] font-bold text-gray-400 w-3 text-right">{{ index + 1 }}.</span>
                                                    <div class="relative flex-1">
                                                        <input 
                                                            v-model="form.committee_details.council.twg_internal_members[index]" 
                                                            @focus.stop="activeSuggestion = 'twg_internal_' + index"
                                                            @click.stop="activeSuggestion = 'twg_internal_' + index"
                                                            @input="activeSuggestion = 'twg_internal_' + index"
                                                            type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                        
                                                        <div v-if="activeSuggestion === 'twg_internal_' + index && getSuggestions(form.committee_details.council.twg_internal_members[index], 'Internal').length > 0" 
                                                             class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                            <div v-for="(person, idx) in getSuggestions(form.committee_details.council.twg_internal_members[index], 'Internal')" :key="idx"
                                                                 @mousedown.prevent="selectPerson('twg_internal', person.name, index)"
                                                                 class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                                <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                                <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-blue-600">{{ person.type }}</span></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button @click="removeMember('twg_internal', index)" type="button" class="text-red-300 hover:text-red-500 transition p-1" v-if="form.committee_details.council.twg_internal_members.length > 1">
                                                        <XCircle class="w-4 h-4" />
                                                    </button>
                                                </div>
                                                <button type="button" @click="addMember('twg_internal')" class="mt-1 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-blue-600 hover:text-blue-800 transition ml-5">
                                                    <Plus class="w-3.5 h-3.5" /> Add Row
                                                </button>
                                            </div>

                                            <div class="space-y-2">
                                                <label class="mb-2 block text-xs font-bold text-gray-500 uppercase">TWG External Members</label>
                                                <div v-for="(member, index) in form.committee_details.council.twg_external_members" :key="'te_'+index" class="relative flex items-center gap-2">
                                                    <span class="text-[10px] font-bold text-gray-400 w-3 text-right">{{ index + 1 }}.</span>
                                                    <div class="relative flex-1">
                                                        <input 
                                                            v-model="form.committee_details.council.twg_external_members[index]" 
                                                            @focus.stop="activeSuggestion = 'twg_external_' + index"
                                                            @click.stop="activeSuggestion = 'twg_external_' + index"
                                                            @input="activeSuggestion = 'twg_external_' + index"
                                                            type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                        
                                                        <div v-if="activeSuggestion === 'twg_external_' + index && getSuggestions(form.committee_details.council.twg_external_members[index], 'External').length > 0" 
                                                             class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                            <div v-for="(person, idx) in getSuggestions(form.committee_details.council.twg_external_members[index], 'External')" :key="idx"
                                                                 @mousedown.prevent="selectPerson('twg_external', person.name, index)"
                                                                 class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                                <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                                <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-green-600">{{ person.type }}</span></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button @click="removeMember('twg_external', index)" type="button" class="text-red-300 hover:text-red-500 transition p-1" v-if="form.committee_details.council.twg_external_members.length > 1">
                                                        <XCircle class="w-4 h-4" />
                                                    </button>
                                                </div>
                                                <button type="button" @click="addMember('twg_external')" class="mt-1 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-blue-600 hover:text-blue-800 transition ml-5">
                                                    <Plus class="w-3.5 h-3.5" /> Add Row
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="form.committee_details.type === 'program'" class="space-y-6 animate-in fade-in duration-300">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Co-Lead Office <span class="lowercase text-[10px] font-normal italic text-gray-400">(Optional)</span></label>
                                        <select v-model="form.committee_details.program.co_lead_office_id" class="w-full md:w-1/2 rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">Select Co-Lead Office...</option>
                                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <div class="space-y-2">
                                            <label class="mb-2 block text-xs font-bold text-gray-500 uppercase">Internal Team Members</label>
                                            <div v-for="(member, index) in form.committee_details.program.internal_members" :key="'pi_'+index" class="relative flex items-center gap-2">
                                                <span class="text-[10px] font-bold text-gray-400 w-3 text-right">{{ index + 1 }}.</span>
                                                <div class="relative flex-1">
                                                    <input 
                                                        v-model="form.committee_details.program.internal_members[index]" 
                                                        @focus.stop="activeSuggestion = 'program_internal_' + index"
                                                        @click.stop="activeSuggestion = 'program_internal_' + index"
                                                        @input="activeSuggestion = 'program_internal_' + index"
                                                        type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                    
                                                    <div v-if="activeSuggestion === 'program_internal_' + index && getSuggestions(form.committee_details.program.internal_members[index], 'Internal').length > 0" 
                                                         class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                        <div v-for="(person, idx) in getSuggestions(form.committee_details.program.internal_members[index], 'Internal')" :key="idx"
                                                             @mousedown.prevent="selectPerson('program_internal', person.name, index)"
                                                             class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                            <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                            <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-blue-600">{{ person.type }}</span></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button @click="removeMember('program_internal', index)" type="button" class="text-red-300 hover:text-red-500 transition p-1" v-if="form.committee_details.program.internal_members.length > 1">
                                                    <XCircle class="w-4 h-4" />
                                                </button>
                                            </div>
                                            <button type="button" @click="addMember('program_internal')" class="mt-1 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-blue-600 hover:text-blue-800 transition ml-5">
                                                <Plus class="w-3.5 h-3.5" /> Add Row
                                            </button>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="mb-2 block text-xs font-bold text-gray-500 uppercase">External Members / Partners</label>
                                            <div v-for="(member, index) in form.committee_details.program.external_members" :key="'pe_'+index" class="relative flex items-center gap-2">
                                                <span class="text-[10px] font-bold text-gray-400 w-3 text-right">{{ index + 1 }}.</span>
                                                <div class="relative flex-1">
                                                    <input 
                                                        v-model="form.committee_details.program.external_members[index]" 
                                                        @focus.stop="activeSuggestion = 'program_external_' + index"
                                                        @click.stop="activeSuggestion = 'program_external_' + index"
                                                        @input="activeSuggestion = 'program_external_' + index"
                                                        type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />
                                                    
                                                    <div v-if="activeSuggestion === 'program_external_' + index && getSuggestions(form.committee_details.program.external_members[index], 'External').length > 0" 
                                                         class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                        <div v-for="(person, idx) in getSuggestions(form.committee_details.program.external_members[index], 'External')" :key="idx"
                                                             @mousedown.prevent="selectPerson('program_external', person.name, index)"
                                                             class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                            <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                            <div class="text-[10px] text-gray-500">{{ person.title }} • <span class="text-green-600">{{ person.type }}</span></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button @click="removeMember('program_external', index)" type="button" class="text-red-300 hover:text-red-500 transition p-1" v-if="form.committee_details.program.external_members.length > 1">
                                                    <XCircle class="w-4 h-4" />
                                                </button>
                                            </div>
                                            <button type="button" @click="addMember('program_external')" class="mt-1 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-blue-600 hover:text-blue-800 transition ml-5">
                                                <Plus class="w-3.5 h-3.5" /> Add Row
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100 mt-8">
                                <button type="button" @click="showDialog = false" class="rounded-lg bg-gray-100 px-5 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-200 transition-colors">Cancel</button>
                                <button type="submit" :disabled="form.processing" class="flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-bold text-white shadow hover:bg-blue-700 transition-colors disabled:opacity-70">
                                    <CheckCircle2 v-if="!form.processing" class="w-4 h-4" />
                                    {{ form.processing ? 'Saving Record...' : 'Save & Publish EO' }}
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
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 8px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>