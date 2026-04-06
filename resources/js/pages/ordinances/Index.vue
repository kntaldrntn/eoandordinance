<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { 
    FileText, Plus, Search, Calendar, Building2, Link as LinkIcon, 
    BookOpen, Download, AlertCircle, UserCheck, Clock, Trash2, XCircle, Info, CheckCircle2, Pencil
} from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch, computed } from 'vue';
import { route } from 'ziggy-js';

// --- Props ---
const props = defineProps<{
    ordinances: {
        data: Array<any>;
        links: Array<any>;
        from: number; to: number; total: number;
        current_page: number; last_page: number;
    };
    departments: Array<{ id: number; name: string }>;
    statuses: Array<{ id: number; name: string }>;
    existing_ordinances: Array<{ id: number; ordinance_number: string; title: string }>;
    peopleRegistry: Array<{ name: string; title: string; type: string }>; // <--- Added for Author Search
    filters?: { search?: string };
    flash?: { success?: string; error?: string };
}>();

// --- Search & Loading ---
const searchTerm = ref(props.filters?.search || '');
const isLoading = ref(false);
let searchTimeout: ReturnType<typeof setTimeout>;

const performSearch = () => {
    clearTimeout(searchTimeout);
    isLoading.value = true;
    searchTimeout = setTimeout(() => {
        router.get(route('ordinances.index'), { search: searchTerm.value }, { 
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
    router.get(route('ordinances.index'), {}, { onFinish: () => isLoading.value = false });
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

// --- ORDINANCE MODAL STATE ---
const showDialog = ref(false);
const isEdit = ref(false);
const editingId = ref<number | null>(null);
const activeModalTab = ref('details'); 
const selectedRecord = ref<any>(null); 

const implementingSearchQuery = ref('');

// --- PARENT ORDINANCE SUGGESTIVE SEARCH ---
const parentSearchQuery = ref('');
const showParentDropdown = ref(false);

const filteredParents = computed(() => {
    let list = props.existing_ordinances || [];
    
    if (isEdit.value && editingId.value) {
        list = list.filter(o => o.id !== editingId.value);
    }
    
    if (parentSearchQuery.value) {
        const q = parentSearchQuery.value.toLowerCase();
        list = list.filter(o => 
            o.ordinance_number.toLowerCase().includes(q) || 
            (o.title && o.title.toLowerCase().includes(q))
        );
    }
    
    return list.slice(0, 15);
});

const selectParent = (parent: any) => {
    form.amends_ordinance_id = parent.id;
    parentSearchQuery.value = `${parent.ordinance_number} - ${parent.title}`;
    showParentDropdown.value = false;
};

const clearParent = () => {
    form.amends_ordinance_id = '';
    parentSearchQuery.value = '';
    form.relationship_type = 'Amends'; 
};

// --- SUGGESTIVE INPUT LOGIC (AUTHORS) ---
const activeSuggestion = ref<string | null>(null);

const getSuggestions = (query: any) => {
    if (!query || typeof query !== 'string') return props.peopleRegistry ? props.peopleRegistry.slice(0, 10) : [];
    const parts = query.split(/[\n,]+/).map(s => s.trim());
    const currentSearch = parts[parts.length - 1].toLowerCase();
    if (!currentSearch) return props.peopleRegistry.slice(0, 10);

    return props.peopleRegistry.filter(p => {
        const safeName = p.name ? String(p.name).toLowerCase() : '';
        return safeName.includes(currentSearch);
    }).slice(0, 10);
};

const selectPerson = (field: string, name: string) => {
    let current = '';
    if (field === 'primary') current = form.author_details.primary_author;
    else if (field === 'co_authors') current = form.author_details.co_authors;

    let newValue = '';
    if (current && current.trim() !== '') {
        const lastDelimiter = Math.max(current.lastIndexOf(','), current.lastIndexOf('\n'));
        if (lastDelimiter !== -1) newValue = current.substring(0, lastDelimiter + 1) + ' ' + name;
        else newValue = name;
    } else {
        newValue = name;
    }

    if (field === 'primary') form.author_details.primary_author = newValue;
    else if (field === 'co_authors') form.author_details.co_authors = newValue + ',\n';
    
    activeSuggestion.value = null;
};

const form = useForm({
    ordinance_number: '',
    title: '',
    subject_matter: '', // <--- NEW
    date_enacted: '',
    date_approved: '',
    effectivity_date: '',
    attested_by: '',
    approved_by: '',
    status_id: '' as string | number,
    is_active: true,
    amends_ordinance_id: '' as string | number,
    relationship_type: 'Amends',
    remarks: '',
    author_details: { // <--- REPLACED sponsor_department_ids
        primary_author: '',
        co_authors: '',
        committee_chairmanship: ''
    },
    implementing_department_ids: [] as number[],
    file: null as File | null,
});

const filteredImplementing = computed(() => {
    if (!implementingSearchQuery.value) return props.departments;
    return props.departments.filter(dept => dept.name.toLowerCase().includes(implementingSearchQuery.value.toLowerCase()));
});

// --- IRR MODAL STATE ---
const showIRRDialog = ref(false);
const selectedOrd = ref<any>(null);

const irrForm = useForm({
    ordinance_id: '' as string | number, // (Note: this says eo_id in your EO file)
    lead_office_id: '' as string | number,
    support_office_ids: [] as number[], // <--- NEW
    status: 'Active', // <--- CHANGED
    file: null as File | null,
});

// Update the available statuses:
const irrStatuses = ['Active', 'On-hold', 'Dropped']; // <--- CHANGED

// Add the search filter logic for the new support offices box:
const irrSupportSearchQuery = ref('');
const filteredIrrSupport = computed(() => {
    if (!irrSupportSearchQuery.value) return props.departments;
    return props.departments.filter(dept => dept.name.toLowerCase().includes(irrSupportSearchQuery.value.toLowerCase()));
});

// --- Functions: Ordinance CRUD ---
function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    selectedRecord.value = null;
    activeModalTab.value = 'details';
    implementingSearchQuery.value = '';
    parentSearchQuery.value = ''; 
    
    form.reset();
    form.clearErrors();
    form.relationship_type = 'Amends';
    form.is_active = true;
    showDialog.value = true;
}

function openEditDialog(ord: any) {
    isEdit.value = true;
    editingId.value = ord.id;
    selectedRecord.value = ord; 
    activeModalTab.value = 'details'; 
    implementingSearchQuery.value = '';
    
    form.clearErrors();
    
    // Basic Info
    form.ordinance_number = ord.ordinance_number;
    form.title = ord.title;
    form.subject_matter = ord.subject_matter || ''; // <--- NEW
    form.date_enacted = ord.date_enacted ? ord.date_enacted.split('T')[0] : '';
    form.date_approved = ord.date_approved ? ord.date_approved.split('T')[0] : '';
    form.effectivity_date = ord.effectivity_date ? ord.effectivity_date.split('T')[0] : '';
    form.attested_by = ord.attested_by || '';
    form.approved_by = ord.approved_by || '';
    form.status_id = ord.status_id;
    form.is_active = Boolean(ord.is_active);
    
    // Relationship
    form.amends_ordinance_id = ord.amends_ordinance_id || '';
    form.relationship_type = ord.relationship_type || 'Amends';
    form.remarks = ord.remarks || '';
    
    if (ord.amends_ordinance_id) {
        const parent = props.existing_ordinances.find(o => o.id === ord.amends_ordinance_id);
        parentSearchQuery.value = parent ? `${parent.ordinance_number} - ${parent.title}` : '';
    } else {
        parentSearchQuery.value = '';
    }

    // Load JSON Authors
    form.author_details = ord.author_details || { primary_author: '', co_authors: '', committee_chairmanship: '' };
        
    form.implementing_department_ids = ord.departments
        .filter((d: any) => d.pivot.role === 'implementing')
        .map((d: any) => d.id);

    form.file = null; 
    showDialog.value = true;
}

const formatDate = (dateString: string | null) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) form.file = target.files[0];
}

function submitForm() {
    if (isEdit.value && editingId.value) {
        form.transform((data) => ({ ...data, _method: 'PUT' })).post(route('ordinances.update', editingId.value), {
            onSuccess: () => { showDialog.value = false; form.reset(); },
            onError: () => { notyf.error('Please check the form for missing or invalid fields.'); }
        });
    } else {
        form.transform((data) => data).post(route('ordinances.store'), {
            onSuccess: () => { showDialog.value = false; form.reset(); },
            onError: () => { notyf.error('Please check the form for missing or invalid fields.'); }
        });
    }
}

const formatAuditDate = (date: string) => {
    return new Date(date).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const getChangedFields = (audit: any) => {
    if (audit.action === 'Created') return 'Initial Record Created';
    if (audit.action === 'Deleted') return 'Record Deleted';
    
    let newVals = audit.new_values;
    let oldVals = audit.old_values;

    if (typeof newVals === 'string') { try { newVals = JSON.parse(newVals); } catch (e) { return 'Updated details'; } }
    if (typeof oldVals === 'string') { try { oldVals = JSON.parse(oldVals); } catch (e) { oldVals = {}; } }
    
    if (newVals) {
        const changes = Object.keys(newVals)
            .filter(key => key !== 'updated_at')
            .map(key => key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
            
        if (changes.length === 0) return 'Updated metadata';
        return 'Modified: ' + changes.join(', ');
    }
    return 'Updated record';
};

// --- Functions: IRR Management ---
function openIRRDialog(record: any) {
    selectedOrd.value = record; 
    irrForm.reset();
    irrForm.clearErrors();
    irrSupportSearchQuery.value = ''; 
    
    const imp = record.departments.find((d: any) => d.pivot.role === 'implementing');
    irrForm.lead_office_id = imp ? imp.id : '';
    irrForm.support_office_ids = []; 
    irrForm.ordinance_id = record.id; 
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
            notyf.success('IRR Added Successfully');
            showIRRDialog.value = false;
        },
    });
}

function deleteIRR(irrId: number) {
    if (confirm('Are you sure you want to delete this IRR file?')) {
        router.delete(route('irr.destroy', irrId), {
            onSuccess: () => {
                notyf.success('IRR Deleted Successfully');
                showIRRDialog.value = false; 
            },
            onError: () => {
                notyf.error('Failed to delete IRR');
            }
        });
    }
}

// Helpers
const getAuthorLabel = (ord: any) => {
    if (ord.author_details && ord.author_details.primary_author) {
        return ord.author_details.primary_author;
    }
    return 'City Council';
};
</script>

<template>
    <Head title="Ordinances" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-white p-4 md:p-8">
            
            <div class="flex flex-col items-center justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm md:flex-row">
                <div class="relative max-w-md flex-1 w-full md:w-auto">
                    <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input v-model="searchTerm" type="text" placeholder="Search Ord. No. or Title..." class="block w-full rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-10 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none" />
                    <button v-if="searchTerm" @click="clearSearch" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600">×</button>
                </div>
                <button v-if="$page.props.auth.user.role === 'system_admin' || $page.props.auth.user.role === 'supervisor'" class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow hover:bg-blue-700 transition" @click="openAddDialog">
                    <Plus class="h-4 w-4" /> Encode Ordinance
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 font-semibold">Ord. Tracking</th>
                                <th class="px-6 py-4 font-semibold w-1/3">Subject Title</th>
                                <th class="px-6 py-4 font-semibold">Primary Author</th>
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

                            <template v-else-if="ordinances.data.length > 0">
                                <tr v-for="ord in ordinances.data" :key="ord.id" 
                                    class="transition-colors border-l-4"
                                    :class="ord.is_active ? 'hover:bg-gray-50 border-transparent' : 'bg-gray-50/60 opacity-80 border-red-400'"
                                >
                                    <td class="px-6 py-4 align-top">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-mono font-bold text-blue-600 text-base">{{ ord.ordinance_number }}</span>
                                            <div class="text-xs text-gray-600 mt-1 flex flex-col gap-0.5">
                                                <span><span class="text-gray-400 font-medium">Enacted:</span> {{ formatDate(ord.date_enacted) }}</span>
                                                <span><span class="text-gray-400 font-medium">Effective:</span> {{ formatDate(ord.effectivity_date) }}</span>
                                            </div>
                                            <div v-if="!ord.is_active" class="mt-1 inline-flex items-center gap-1 w-fit px-2 py-0.5 rounded bg-red-100 text-red-600 text-[10px] font-bold uppercase border border-red-200">
                                                <XCircle class="w-3 h-3" /> Inactive
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <div class="text-gray-900 font-semibold mb-2 leading-snug">{{ ord.title }}</div>
                                        
                                        <div v-if="ord.parent_ordinance" class="mb-2 flex items-center gap-1 text-xs text-amber-700 font-bold bg-amber-50 px-2 py-1 rounded w-fit border border-amber-100 uppercase">
                                            <AlertCircle class="w-3.5 h-3.5" />
                                            {{ ord.relationship_type }}: {{ ord.parent_ordinance.ordinance_number }}
                                        </div>

                                        <div v-if="ord.remarks" class="mt-2 pl-3 border-l-2 border-gray-300 bg-gray-50 py-1.5 pr-3 rounded-r">
                                            <p class="text-xs text-gray-600 italic">"{{ ord.remarks }}"</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-600 align-top">
                                        <span class="flex items-center gap-1 mt-1">
                                            <UserCheck class="w-4 h-4 text-gray-400" />
                                            {{ getAuthorLabel(ord) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 border border-gray-200 mt-1">
                                            {{ ord.status?.name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center align-middle">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="openIRRDialog(ord)" class="group relative flex items-center justify-center rounded-lg bg-green-50 p-2 text-green-600 hover:bg-green-100 transition-colors" title="Manage Implementing Rules">
                                                <BookOpen class="w-4 h-4" />
                                                <span v-if="ord.implementing_rules?.length > 0" class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full bg-red-500 border border-white"></span>
                                            </button>
                                            <div class="h-4 w-px bg-gray-200 mx-1"></div>
                                            <a v-if="ord.file_url" :href="ord.file_url" target="_blank" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View PDF"><Download class="w-4 h-4" /></a>
                                            <button v-if="$page.props.auth.user.role === 'system_admin' || $page.props.auth.user.role === 'supervisor'"class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" @click="openEditDialog(ord)" title="Edit">
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="bg-gray-50 p-4 rounded-full mb-3"><Search class="h-6 w-6 text-gray-400" /></div>
                                        <p class="text-base font-medium text-gray-900">No Ordinances found</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                 <div v-if="ordinances.last_page > 1" class="flex items-center justify-between border-t bg-gray-50 px-6 py-3">
                    <p class="text-sm text-gray-600">Showing <span class="font-medium">{{ ordinances.from }}</span>–<span class="font-medium">{{ ordinances.to }}</span> of <span class="font-medium">{{ ordinances.total }}</span></p>
                    <div class="flex gap-1">
                        <button v-for="(link, index) in ordinances.links.slice(1, -1)" :key="index" @click="goToPage(String(link.url))" :disabled="!link.url" class="rounded-md px-3 py-1 text-sm transition" :class="[link.active ? 'bg-blue-600 font-medium text-white shadow' : 'border bg-white text-gray-600 hover:bg-gray-100']"><span v-html="link.label"></span></button>
                    </div>
                </div>
            </div>

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                        
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <FileText class="w-5 h-5 text-blue-600" />
                                    {{ isEdit ? 'Edit Ordinance' : 'Encode Ordinance' }}
                                </h2>
                                <p class="text-xs text-gray-500 mt-1">{{ isEdit ? 'Update details.' : 'Create a new record.' }}</p>
                            </div>
                            <button @click="showDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center transition">×</button>
                        </div>

                        <div v-if="isEdit" class="flex items-center gap-6 border-b border-gray-100 mb-6">
                            <button @click="activeModalTab = 'details'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeModalTab === 'details' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Details</button>
                            <button @click="activeModalTab = 'history'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeModalTab === 'history' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Audit Log</button>
                        </div>

                        <div v-show="activeModalTab === 'details'">
                            <form @submit.prevent="submitForm" class="space-y-5">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-5 mb-5">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Ordinance Number</label>
                                        <input v-model="form.ordinance_number" type="text" placeholder="e.g., Ord. No. 2026-05" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" required />
                                        <p v-if="form.errors.ordinance_number" class="text-[10px] text-red-500 mt-1">{{ form.errors.ordinance_number }}</p>
                                    </div>
                                    
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Legal Status</label>
                                        <select v-model="form.status_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500">
                                            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-transparent select-none" aria-hidden="true">Spacer</label>
                                        <div class="flex items-center justify-between bg-gray-50 px-3 py-2 rounded-lg border border-gray-200 h-[42px]">
                                            <span class="text-xs text-gray-600 font-bold uppercase tracking-wider">Active Status</span>
                                            <button type="button" @click="form.is_active = !form.is_active" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:ring-2 focus:ring-blue-500" :class="form.is_active ? 'bg-green-500' : 'bg-gray-300'">
                                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" :class="form.is_active ? 'translate-x-6' : 'translate-x-1'" />
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="hidden md:block"></div>
                                </div>

                                <div class="space-y-4 mb-5">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Subject Title</label>
                                        <textarea v-model="form.title" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Describe the ordinance..." required></textarea>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Subject Matter</label>
                                        <textarea v-model="form.subject_matter" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Brief summary of the subject matter..."></textarea>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Remarks</label>
                                    <textarea v-model="form.remarks" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Optional context or notes..."></textarea>
                                </div>

                                <div class="p-4 bg-amber-50/50 rounded-xl border border-amber-100 space-y-4">
                                    <div class="flex items-center gap-2 text-xs font-bold text-amber-800 uppercase mb-1">
                                        <Info class="w-4 h-4" /> Legal Context (Transparency)
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        
                                        <div class="relative">
                                            <label class="mb-1 block text-xs font-medium text-amber-900">Previous Ordinance Amended?</label>
                                            
                                            <div class="relative">
                                                <input 
                                                    type="text" 
                                                    v-model="parentSearchQuery"
                                                    @focus="showParentDropdown = true"
                                                    @input="form.amends_ordinance_id = ''; showParentDropdown = true"
                                                    @blur="setTimeout(() => showParentDropdown = false, 200)"
                                                    class="w-full rounded-lg border border-amber-200 px-3 py-2 pr-8 bg-white outline-none focus:ring-2 focus:ring-amber-500 text-sm"
                                                    placeholder="Search Ord. Number or Title..."
                                                />
                                                <button v-if="form.amends_ordinance_id || parentSearchQuery" @click="clearParent" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-400 hover:text-amber-600 transition">
                                                    <XCircle class="w-4 h-4" />
                                                </button>
                                            </div>

                                            <div v-if="showParentDropdown && filteredParents.length > 0" class="absolute z-50 w-full mt-1 bg-white border border-amber-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="ex in filteredParents" :key="ex.id"
                                                     @mousedown.prevent="selectParent(ex)"
                                                     class="px-3 py-2 hover:bg-amber-50 cursor-pointer border-b border-amber-50 last:border-0 text-sm transition-colors">
                                                    <div class="font-bold text-amber-900">{{ ex.ordinance_number }}</div>
                                                    <div class="text-xs text-gray-500 truncate">{{ ex.title }}</div>
                                                </div>
                                            </div>
                                            
                                            <div v-else-if="showParentDropdown && parentSearchQuery" class="absolute z-50 w-full mt-1 bg-white border border-amber-200 rounded-lg shadow-lg p-3 text-xs text-gray-500 text-center">
                                                No matching records found.
                                            </div>
                                        </div>

                                        <div v-if="form.amends_ordinance_id">
                                            <label class="mb-1 block text-xs font-medium text-amber-900">Action Type</label>
                                            <select v-model="form.relationship_type" class="w-full rounded-lg border border-amber-200 px-3 py-2 bg-white text-xs font-bold uppercase focus:ring-2 focus:ring-amber-500">
                                                <option value="Amends">Amends</option>
                                                <option value="Repeals">Repeals</option>
                                                <option value="Supersedes">Supersedes</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div><label class="mb-1 block text-sm font-medium text-gray-700">Date Enacted</label><input v-model="form.date_enacted" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" /></div>
                                    <div><label class="mb-1 block text-sm font-medium text-gray-700">Date Approved</label><input v-model="form.date_approved" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" /></div>
                                    <div><label class="mb-1 block text-sm font-medium text-gray-700">Effectivity</label><input v-model="form.effectivity_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" /></div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div><label class="mb-1 block text-sm font-medium text-gray-700">Attested By</label><input v-model="form.attested_by" type="text" placeholder="e.g., SP Secretary" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" /></div>
                                    <div><label class="mb-1 block text-sm font-medium text-gray-700">Approved By</label><input v-model="form.approved_by" type="text" placeholder="e.g., City Mayor" class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" /></div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-5 bg-blue-50/50 rounded-xl border border-blue-100/80">
                                    
                                    <div class="col-span-1 md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 border-b border-blue-200 pb-4 mb-2">
                                        <div class="relative">
                                            <label class="mb-1 block text-sm font-bold text-gray-700">Primary Sponsor / Author</label>
                                            <input 
                                                v-model="form.author_details.primary_author" 
                                                @focus.stop="activeSuggestion = 'primary'"
                                                @click.stop="activeSuggestion = 'primary'"
                                                @input="activeSuggestion = 'primary'"
                                                type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type..." />
                                            
                                            <div v-if="activeSuggestion === 'primary' && getSuggestions(form.author_details.primary_author).length > 0" 
                                                 class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="(person, idx) in getSuggestions(form.author_details.primary_author)" :key="idx"
                                                     @mousedown.prevent="selectPerson('primary', person.name)"
                                                     class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                    <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                    <div class="text-[10px] text-gray-500">{{ person.title }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="relative">
                                            <label class="mb-1 block text-sm font-bold text-gray-700">Committee Chairmanship(s)</label>
                                            <textarea 
                                                v-model="form.author_details.committee_chairmanship" 
                                                rows="1" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. Committee on Health..."></textarea>
                                        </div>

                                        <div class="relative md:col-span-2">
                                            <label class="mb-1 block text-sm font-bold text-gray-700">Co-Authors / Committee Members</label>
                                            <textarea 
                                                v-model="form.author_details.co_authors" 
                                                @focus.stop="activeSuggestion = 'co_authors'"
                                                @click.stop="activeSuggestion = 'co_authors'"
                                                @input="activeSuggestion = 'co_authors'"
                                                rows="3" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or list co-authors..."></textarea>
                                            
                                            <div v-if="activeSuggestion === 'co_authors' && getSuggestions(form.author_details.co_authors).length > 0" 
                                                 class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="(person, idx) in getSuggestions(form.author_details.co_authors)" :key="idx"
                                                     @mousedown.prevent="selectPerson('co_authors', person.name)"
                                                     class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                    <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                    <div class="text-[10px] text-gray-500">{{ person.title }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2">
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="text-sm font-semibold text-blue-900">Implementing Office</label>
                                            <span class="text-[10px] font-bold text-white bg-blue-500 px-2 py-0.5 rounded-full shadow-sm">{{ form.implementing_department_ids.length }} Selected</span>
                                        </div>
                                        <div class="relative mb-2">
                                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                                            <input v-model="implementingSearchQuery" type="text" placeholder="Filter offices..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500 bg-white" />
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-40 overflow-y-auto p-3 bg-white rounded-lg border border-blue-100">
                                            <label v-for="dept in filteredImplementing" :key="dept.id" class="flex items-center gap-2 cursor-pointer hover:bg-blue-50 p-1.5 rounded transition">
                                                <input type="checkbox" :value="dept.id" v-model="form.implementing_department_ids" class="rounded text-blue-600 focus:ring-blue-500 h-4 w-4 border-gray-300" />
                                                <span class="text-xs text-gray-700 leading-tight">{{ dept.name }}</span>
                                            </label>
                                            <div v-if="filteredImplementing.length === 0" class="text-[10px] text-gray-400 text-center py-2">No offices match your search.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t pt-5">
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Document (PDF)</label>
                                    <input type="file" @change="handleFileChange" accept="application/pdf" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"/>
                                    <p v-if="isEdit" class="mt-1 text-[10px] text-gray-400 italic">Leave empty to keep the existing PDF document.</p>
                                </div>

                                <div class="flex justify-end gap-3 pt-4 border-t mt-4">
                                    <button type="button" @click="showDialog = false" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 transition">Cancel</button>
                                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white shadow hover:bg-blue-700 transition">
                                        {{ form.processing ? 'Saving...' : 'Save Record' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div v-show="activeModalTab === 'history'" class="py-4">
                            <div v-if="selectedRecord?.audits && selectedRecord.audits.length > 0" class="relative border-l-2 border-gray-100 ml-4 pl-6 space-y-6">
                                <div v-for="audit in selectedRecord.audits" :key="audit.id" class="relative">
                                    <div class="absolute -left-[33px] top-1.5 h-3.5 w-3.5 rounded-full border-2 border-white" :class="audit.action === 'Created' ? 'bg-green-500' : 'bg-blue-500'"></div>
                                    <div class="flex flex-col gap-1">
                                        <p class="text-sm font-bold text-gray-900">{{ audit.action }} by <span class="text-blue-600">{{ audit.user?.name || 'Unknown' }}</span></p>
                                        <p class="text-xs text-gray-500 leading-relaxed">{{ getChangedFields(audit) }}</p>
                                        <span class="text-[10px] text-gray-400 font-mono flex items-center gap-1"><Clock class="w-3 h-3" /> {{ formatAuditDate(audit.created_at) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-10">
                                <Clock class="w-10 h-10 text-gray-200 mx-auto mb-2" />
                                <p class="text-sm text-gray-400 font-medium">No history found</p>
                                <p class="text-xs text-gray-400 mt-1">Changes will appear here once edits are made.</p>
                            </div>
                        </div>

                    </div>
                </div>
            </Transition>

            <Transition name="fade">
                <div v-if="showIRRDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="bg-green-100 p-2 rounded-lg"><BookOpen class="w-6 h-6 text-green-600" /></div>
                                <div>
                                    <h2 class="text-lg font-bold text-gray-900 leading-tight">Manage IRR</h2>
                                    <p class="text-xs text-gray-500 mt-0.5">For: <span class="font-bold text-blue-600">{{ selectedOrd?.ordinance_number }}</span></p>
                                </div>
                            </div>
                            <button @click="showIRRDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-100 rounded-full p-1.5 transition">×</button>
                        </div>

                        <div class="mb-8">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Implementing Rules List</h3>
                            <div v-if="selectedOrd?.implementing_rules?.length > 0" class="space-y-3">
                                <div v-for="rule in selectedOrd.implementing_rules" :key="rule.id" class="flex items-center justify-between p-3 rounded-xl border border-gray-100 bg-gray-50 hover:bg-white hover:shadow-sm transition-all">
                                    <div class="flex items-center gap-3">
                                        <FileText class="w-5 h-5 text-gray-400" />
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ rule.status }}</p>
                                            <p class="text-[10px] text-gray-500 uppercase font-medium mt-0.5">Lead: {{ rule.lead_office?.name || 'Unassigned' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <a :href="rule.file_url" target="_blank" class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded hover:bg-blue-100 transition flex items-center gap-1">PDF</a>
                                        <button @click="deleteIRR(rule.id)" class="text-gray-400 hover:text-red-600 p-1 rounded hover:bg-red-50 transition" title="Delete IRR"><Trash2 class="w-4 h-4" /></button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                                <p class="text-sm text-gray-400 font-medium">No IRR records found.</p>
                            </div>
                        </div>

                        <div class="bg-green-50/50 rounded-2xl border border-green-100 p-5 shadow-inner">
                            <h3 class="text-xs font-bold text-green-800 uppercase tracking-widest mb-4 flex items-center gap-2"><Plus class="w-4 h-4" /> Add New Regulation</h3>
                            <form @submit.prevent="submitIRR" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">IRR Status</label>
                                        <select v-model="irrForm.status" class="w-full rounded-lg border border-gray-300 px-3 py-2 bg-white text-sm focus:ring-2 focus:ring-green-500 outline-none">
                                            <option v-for="s in irrStatuses" :key="s" :value="s">{{ s }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Lead Office</label>
                                        <select v-model="irrForm.lead_office_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 bg-white text-sm focus:ring-2 focus:ring-green-500 outline-none">
                                            <option value="" disabled>Select Office</option>
                                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                        </select>
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="text-sm font-medium text-gray-700">Support Offices</label>
                                            <span class="text-[10px] font-bold text-green-700 bg-green-100 px-2 py-0.5 rounded-full">{{ irrForm.support_office_ids.length }} Selected</span>
                                        </div>
                                        <div class="relative mb-2">
                                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                                            <input v-model="irrSupportSearchQuery" type="text" placeholder="Filter offices..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-gray-200 outline-none focus:ring-2 focus:ring-green-500 bg-white" />
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-32 overflow-y-auto p-3 bg-white rounded-lg border border-gray-200">
                                            <label v-for="dept in filteredIrrSupport" :key="dept.id" class="flex items-center gap-2 cursor-pointer hover:bg-green-50 p-1.5 rounded transition">
                                                <input type="checkbox" :value="dept.id" v-model="irrForm.support_office_ids" class="rounded text-green-600 focus:ring-green-500 h-4 w-4 border-gray-300" />
                                                <span class="text-xs text-gray-700 leading-tight">{{ dept.name }}</span>
                                            </label>
                                            <div v-if="filteredIrrSupport.length === 0" class="text-[10px] text-gray-400 text-center py-2 col-span-full">No offices match your search.</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Upload PDF</label>
                                    <input type="file" @change="handleIRRFileChange" accept="application/pdf" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-white file:text-green-700 cursor-pointer" required/>
                                </div>
                                <div class="flex justify-end pt-2">
                                    <button type="submit" :disabled="irrForm.processing" class="bg-green-600 px-6 py-2 text-white rounded-lg shadow-sm hover:bg-green-700 text-sm font-bold transition-all disabled:opacity-50">
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
</style>