<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { 
    FileText, Plus, Search, Calendar, Building2, Link as LinkIcon, 
    BookOpen, Download, AlertCircle, UserCheck, Clock, Trash2 
} from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

// --- Props ---
const props = defineProps<{
    ordinances: {
        data: Array<{
            id: number;
            ordinance_number: string;
            title: string;
            date_enacted: string;
            date_approved: string | null;
            effectivity_date: string | null;
            file_url: string;
            attested_by: string | null;
            approved_by: string | null;
            is_active: boolean; // <--- Added is_active

            // Audit History
            audits: Array<{
                id: number;
                user: { name: string };
                action: string;
                created_at: string;
                old_values: any;
                new_values: any;
            }>;
            
            // Relationships
            status: { name: string };
            status_id: number;
            
            // Logic
            amends_ordinance_id: number | null;
            relationship_type: string | null;
            remarks: string | null;
            parent_ordinance: { ordinance_number: string } | null;
            amendments?: Array<{ id: number; ordinance_number: string }>;
            
            // Departments (Sponsors/Implementing)
            departments: Array<{ id: number; name: string; pivot: { role: string } }>;
            
            // IRR Data
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
    existing_ordinances: Array<{ id: number; ordinance_number: string; title: string }>;
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

const form = useForm({
    ordinance_number: '',
    title: '',
    date_enacted: '',
    date_approved: '',
    effectivity_date: '',
    attested_by: '',
    approved_by: '',
    status_id: '' as string | number,
    is_active: true, // <--- Default Active
    
    // Logic
    amends_ordinance_id: '' as string | number,
    relationship_type: 'Amends',
    remarks: '',
    
    // Offices
    sponsor_department_ids: [] as number[],
    implementing_department_ids: [] as number[],
    
    file: null as File | null,
});

// --- IRR MODAL STATE ---
const showIRRDialog = ref(false);
const selectedOrd = ref<any>(null);

const irrForm = useForm({
    ordinance_id: '' as string | number,
    lead_office_id: '' as string | number,
    status: 'Drafting', 
    file: null as File | null,
});

const irrStatuses = ['Drafting', 'Pending Approval', 'Approved', 'Implemented', 'Delayed'];

// --- Functions: Ordinance CRUD ---
function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    selectedRecord.value = null;
    activeModalTab.value = 'details';
    
    form.reset();
    form.clearErrors();
    form.relationship_type = 'Amends';
    form.is_active = true; // Default active
    showDialog.value = true;
}

function openEditDialog(ord: any) {
    isEdit.value = true;
    editingId.value = ord.id;
    selectedRecord.value = ord; 
    activeModalTab.value = 'details'; 
    
    form.clearErrors();
    
    // Basic Info
    form.ordinance_number = ord.ordinance_number;
    form.title = ord.title;
    form.date_enacted = ord.date_enacted ? ord.date_enacted.split('T')[0] : '';
    form.date_approved = ord.date_approved ? ord.date_approved.split('T')[0] : '';
    form.effectivity_date = ord.effectivity_date ? ord.effectivity_date.split('T')[0] : '';
    form.attested_by = ord.attested_by || '';
    form.approved_by = ord.approved_by || '';
    form.status_id = ord.status_id;
    form.is_active = Boolean(ord.is_active); // <--- Load Active Status
    
    // Relationship
    form.amends_ordinance_id = ord.amends_ordinance_id || '';
    form.relationship_type = ord.relationship_type || 'Amends';
    form.remarks = ord.remarks || '';
    
    // Roles
    form.sponsor_department_ids = ord.departments
        .filter((d: any) => d.pivot.role === 'sponsor')
        .map((d: any) => d.id);
        
    form.implementing_department_ids = ord.departments
        .filter((d: any) => d.pivot.role === 'implementing')
        .map((d: any) => d.id);

    form.file = null; 
    showDialog.value = true;
}

// --- Helper: Format Date ---
const formatDate = (dateString: string) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.file = target.files[0];
    }
}

function submitForm() {
    if (isEdit.value && editingId.value) {
        form.transform((data) => ({ ...data, _method: 'PUT' })).post(route('ordinances.update', editingId.value), {
            onSuccess: () => { showDialog.value = false; form.reset(); },
        });
    } else {
        form.post(route('ordinances.store'), {
            onSuccess: () => { showDialog.value = false; form.reset(); },
        });
    }
}

// --- History Helpers ---
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

    if (typeof newVals === 'string') { try { newVals = JSON.parse(newVals); } catch (e) { return 'Updated details'; } }
    if (typeof oldVals === 'string') { try { oldVals = JSON.parse(oldVals); } catch (e) { oldVals = {}; } }
    
    if (newVals) {
        const changes = Object.keys(newVals)
            .filter(key => key !== 'updated_at')
            .map(key => {
                const fieldName = key.replace(/_/g, ' '); 
                const from = oldVals && oldVals[key] ? oldVals[key] : '(empty)';
                const to = newVals[key] ? newVals[key] : '(empty)';
                return `${fieldName}: "${from}" → "${to}"`;
            });
            
        if (changes.length === 0) return 'Updated metadata';
        return 'Updated: ' + changes.join(', ');
    }
    return 'Updated record';
};

// --- Functions: IRR Management ---
function openIRRDialog(ord: any) {
    selectedOrd.value = ord;
    irrForm.reset();
    irrForm.clearErrors();
    
    const imp = ord.departments.find((d: any) => d.pivot.role === 'implementing');
    irrForm.lead_office_id = imp ? imp.id : '';
    
    irrForm.ordinance_id = ord.id;
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

// --- Delete IRR ---
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
const getSponsors = (depts: any[]) => {
    const sponsors = depts.filter(d => d.pivot.role === 'sponsor');
    if (sponsors.length === 0) return '—';
    if (sponsors.length === 1) return sponsors[0].name;
    return `${sponsors[0].name} +${sponsors.length - 1} others`;
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
                <button v-if="$page.props.auth.user.role === 'system_admin' || $page.props.auth.user.role === 'supervisor'" class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-indigo-700 transition" @click="openAddDialog">
                    <Plus class="h-4 w-4" /> Encode Ordinance
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
                            <tr>
                                <th class="px-6 py-3 font-medium">Ord. No.</th>
                                <th class="px-6 py-3 font-medium w-1/3">Title</th>
                                <th class="px-6 py-3 font-medium">Date Enacted</th>
                                <th class="px-6 py-3 font-medium">Sponsor / Author</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 text-center font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-if="isLoading">
                                <tr v-for="n in 5" :key="n" class="animate-pulse">
                                    <td class="px-6 py-4"><div class="h-4 w-24 bg-gray-200 rounded"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-full bg-gray-200 rounded mb-2"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-20 bg-gray-200 rounded"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-32 bg-gray-200 rounded"></div></td>
                                    <td class="px-6 py-4"><div class="h-6 w-16 bg-gray-200 rounded-full"></div></td>
                                    <td class="px-6 py-4"><div class="h-4 w-8 bg-gray-200 rounded mx-auto"></div></td>
                                </tr>
                            </template>

                            <template v-else-if="ordinances.data.length > 0">
                                <tr v-for="ord in ordinances.data" :key="ord.id" 
                                    class="transition-colors border-l-4"
                                    :class="ord.is_active ? 'hover:bg-gray-50 border-transparent' : 'bg-gray-50/80 opacity-75 border-red-300'"
                                >
                                    <td class="px-6 py-3 font-mono font-medium text-indigo-600">
                                        {{ ord.ordinance_number }}
                                        <div v-if="!ord.is_active" class="text-[10px] text-red-500 font-bold uppercase mt-1">Inactive</div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="line-clamp-2 text-gray-900 font-medium">{{ ord.title }}</div>
                                        <div v-if="ord.parent_ordinance" class="mt-1 flex items-center gap-1 text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded w-fit border border-amber-100">
                                            <LinkIcon class="w-3 h-3" />
                                            <span>Amends: {{ ord.parent_ordinance.ordinance_number }}</span>
                                        </div>
                                        <div v-if="ord.remarks" class="mt-2 text-xs text-gray-500 italic bg-gray-50 p-1.5 rounded border border-gray-100">
                                            <span class="font-semibold not-italic">Note:</span> {{ ord.remarks }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-500">{{ formatDate(ord.date_enacted) }}</td>
                                    <td class="px-6 py-3 text-xs text-gray-600">
                                        <span class="flex items-center gap-1">
                                            <UserCheck class="w-3 h-3 text-gray-400" />
                                            {{ getSponsors(ord.departments) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">
                                            {{ ord.status?.name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <div class="flex items-center justify-center gap-3">
                                            <button 
                                                @click="openIRRDialog(ord)" 
                                                class="group relative flex items-center justify-center rounded-lg bg-green-50 p-2 text-green-600 hover:bg-green-100 transition-colors"
                                                title="Manage Implementing Rules"
                                            >
                                                <BookOpen class="w-4 h-4" />
                                                <span v-if="ord.implementing_rules?.length > 0" class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full bg-red-500 border border-white"></span>
                                            </button>
                                            <span class="text-gray-300">|</span>
                                            <a v-if="ord.file_url" :href="ord.file_url" target="_blank" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">View</a>
                                            <span v-else class="text-sm text-gray-400 cursor-not-allowed">—</span>
                                            <span v-if="$page.props.auth.user.role === 'system_admin' || $page.props.auth.user.role === 'supervisor'" class="text-gray-300">|</span>
                                            <button @click="openEditDialog(ord)" v-if="$page.props.auth.user.role === 'system_admin' || $page.props.auth.user.role === 'supervisor'" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">Edit</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="bg-gray-50 p-4 rounded-full mb-3"><Search class="h-6 w-6 text-gray-400" /></div>
                                        <p class="text-base font-medium text-gray-900">No Ordinances found</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                 <div v-if="ordinances.last_page > 1" class="flex items-center justify-between border-t bg-gray-50 px-4 py-3">
                    <p class="text-sm text-gray-600">Showing <span class="font-medium">{{ ordinances.from }}</span>–<span class="font-medium">{{ ordinances.to }}</span> of <span class="font-medium">{{ ordinances.total }}</span></p>
                    <div class="flex gap-1">
                        <button v-for="(link, index) in ordinances.links.slice(1, -1)" :key="index" @click="goToPage(String(link.url))" :disabled="!link.url" class="rounded-md px-3 py-1 text-sm transition" :class="[link.active ? 'bg-indigo-600 font-medium text-white' : 'border bg-white text-gray-600 hover:bg-gray-100']"><span v-html="link.label"></span></button>
                    </div>
                </div>
            </div>

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl max-h-[95vh] overflow-y-auto">
                        
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                    <FileText class="w-6 h-6 text-indigo-600" />
                                    {{ isEdit ? 'Edit Ordinance' : 'Encode Ordinance' }}
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">{{ isEdit ? 'Update details.' : 'Create a new record.' }}</p>
                            </div>
                            <button @click="showDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-full p-2">×</button>
                        </div>

                        <div v-if="isEdit" class="flex items-center gap-6 border-b border-gray-200 mb-6">
                            <button 
                                @click="activeModalTab = 'details'"
                                class="pb-3 px-1 text-sm font-bold uppercase tracking-wide border-b-2 transition-all"
                                :class="activeModalTab === 'details' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            >
                                Details
                            </button>
                            <button 
                                @click="activeModalTab = 'history'"
                                class="pb-3 px-1 text-sm font-bold uppercase tracking-wide border-b-2 transition-all"
                                :class="activeModalTab === 'history' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            >
                                Audit Log (History)
                            </button>
                        </div>
                        <div v-else class="mb-6 border-b border-gray-200"></div>

                        <div v-show="activeModalTab === 'details'">
                            <form @submit.prevent="submitForm" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Ordinance Number</label>
                                        <input v-model="form.ordinance_number" type="text" placeholder="e.g., Ord. No. 2026-05" class="w-full rounded-lg border-gray-300" />
                                        <p v-if="form.errors.ordinance_number" class="text-red-500 text-xs mt-1">{{ form.errors.ordinance_number }}</p>
                                    </div>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                                            <select v-model="form.status_id" class="w-full rounded-lg border-gray-300">
                                                <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                            </select>
                                        </div>
                                        <div class="flex items-center justify-between bg-gray-50 p-2 rounded-lg border border-gray-200">
                                            <span class="text-xs font-medium text-gray-700">Currently in Effect?</span>
                                            <button 
                                                type="button" 
                                                @click="form.is_active = !form.is_active"
                                                class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                :class="form.is_active ? 'bg-green-500' : 'bg-gray-300'"
                                            >
                                                <span 
                                                    class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"
                                                    :class="form.is_active ? 'translate-x-4.5' : 'translate-x-0.5'"
                                                />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Title</label>
                                    <textarea v-model="form.title" rows="2" class="w-full rounded-lg border-gray-300"></textarea>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-5 bg-gray-50 rounded-xl border border-gray-200">
                                    <div class="col-span-1">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Target Ordinance (Parent)</label>
                                        <select v-model="form.amends_ordinance_id" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500">
                                            <option value="">None (This is a new Parent)</option>
                                            <option v-for="ex in existing_ordinances" :key="ex.id" :value="ex.id">
                                                {{ ex.ordinance_number }} - {{ ex.title }}
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="form.amends_ordinance_id" class="col-span-1">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Action / Effect</label>
                                        <select v-model="form.relationship_type" class="w-full rounded-lg border-gray-300 font-medium text-indigo-900 bg-indigo-50 border-indigo-200">
                                            <option value="Amends">Amends (Status -> Amended)</option>
                                            <option value="Repeals">Repeals (Status -> Repealed)</option>
                                            <option value="Supersedes">Supersedes</option>
                                        </select>
                                        <div v-if="form.errors.relationship_type" class="text-red-500 text-xs mt-1">{{ form.errors.relationship_type }}</div>
                                    </div>
                                    <div class="col-span-1 md:col-span-2">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Remarks</label>
                                        <textarea v-model="form.remarks" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Additional notes..."></textarea>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 border-t pt-6">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Date Enacted</label>
                                        <input v-model="form.date_enacted" type="date" class="w-full rounded-lg border-gray-300" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Date Approved</label>
                                        <input v-model="form.date_approved" type="date" class="w-full rounded-lg border-gray-300" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Effectivity</label>
                                        <input v-model="form.effectivity_date" type="date" class="w-full rounded-lg border-gray-300" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Attested By</label>
                                        <input v-model="form.attested_by" type="text" placeholder="e.g., SP Secretary" class="w-full rounded-lg border-gray-300" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Approved By</label>
                                        <input v-model="form.approved_by" type="text" placeholder="e.g., City Mayor" class="w-full rounded-lg border-gray-300" />
                                    </div>
                                </div>

                                <div class="bg-indigo-50/50 p-6 rounded-xl border border-indigo-100/80 space-y-6">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sponsors / Authors</label>
                                        <select v-model="form.sponsor_department_ids" multiple class="w-full h-24 rounded-lg border-gray-300">
                                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Hold Ctrl to select multiple sponsors.</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Implementing Office (Optional)</label>
                                        <select v-model="form.implementing_department_ids" multiple class="w-full h-24 rounded-lg border-gray-300">
                                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="border-t pt-6">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Upload PDF</label>
                                    <input type="file" @change="handleFileChange" accept="application/pdf" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                </div>

                                <div class="flex justify-end pt-6 border-t">
                                    <button type="button" class="mr-3 px-4 py-2 text-gray-700 hover:bg-gray-50 rounded-lg" @click="showDialog = false">Cancel</button>
                                    <button type="submit" class="bg-indigo-600 px-6 py-2 text-white rounded-lg hover:bg-indigo-700" :disabled="form.processing">
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
                                    For: <span class="font-medium text-gray-900">{{ selectedOrd?.ordinance_number }}</span>
                                </p>
                            </div>
                            <button @click="showIRRDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-full p-2">×</button>
                        </div>

                        <div class="mb-8">
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Existing Implementing Rules</h3>
                            <div v-if="selectedOrd?.implementing_rules?.length > 0" class="space-y-3">
                                <div v-for="rule in selectedOrd.implementing_rules" :key="rule.id" class="flex items-center justify-between p-3 rounded-lg border border-gray-100 bg-gray-50 hover:border-blue-200 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-white p-2 rounded border border-gray-200">
                                            <FileText class="w-5 h-5 text-gray-500" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ rule.status }}</p>
                                            <p class="text-xs text-gray-500">Lead: {{ rule.lead_office?.name }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a :href="rule.file_url" target="_blank" class="text-xs font-medium text-blue-600 hover:underline flex items-center gap-1">
                                            <Download class="w-3 h-3" /> Download
                                        </a>
                                        <button @click="deleteIRR(rule.id)" class="text-red-500 hover:text-red-700 p-1 rounded-full hover:bg-red-50 transition-colors" title="Delete IRR">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
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