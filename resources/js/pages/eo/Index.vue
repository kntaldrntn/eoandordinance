<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    FileText, Plus, Search, Calendar, Building2, Link as LinkIcon,
    Download, AlertCircle, Clock, CheckCircle2, XCircle, Info, Users,
    Pencil, Eye, Filter
} from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch, computed } from 'vue';
import { route } from 'ziggy-js';

// --- Props ---
const props = defineProps<{
    eos: { data: Array<any>; links: Array<any>; from: number; to: number; total: number; current_page: number; last_page: number; };
    departments: Array<{ id: number; code?: string; name: string }>;
    statuses: Array<{ id: number; name: string }>;
    classifications: Array<{ id: number; name: string }>;
    existing_eos: Array<{ id: number; eo_number: string; title: string }>;
    peopleRegistry: Array<{ name: string; title: string; type: string }>;
    filters?: { search?: string; year?: string; is_active?: string };
    available_years: number[];
    flash?: { success?: string; error?: string };
}>();

// --- Search & Filter State ---
const searchTerm = ref(props.filters?.search || '');
const filterYear = ref(props.filters?.year || 'all');
const filterActive = ref(props.filters?.is_active || 'all');
const isLoading = ref(false);
let searchTimeout: ReturnType<typeof setTimeout>;

const performSearch = () => {
    clearTimeout(searchTimeout);
    isLoading.value = true;
    searchTimeout = setTimeout(() => {
        router.get(route('eo.index'), { search: searchTerm.value, year: filterYear.value, is_active: filterActive.value }, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => isLoading.value = false
        });
    }, 300);
};

watch([searchTerm, filterYear, filterActive], performSearch);

const clearSearch = () => {
    searchTerm.value = '';
    filterYear.value = 'all';
    filterActive.value = 'all';
};

const goToPage = (url: string) => {
    if (!url) return;
    router.get(url, { search: searchTerm.value, year: filterYear.value, is_active: filterActive.value }, { preserveState: true, preserveScroll: false });
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
    const manualStatuses = ['New', 'Amendment', 'Suspended'];
    const autoStatuses = ['Amended', 'Repealed', 'Superseded'];

    return props.statuses.filter(s => {
        if (autoStatuses.includes(s.name)) {
            return isEdit.value && selectedRecord.value?.status_id === s.id;
        }
        return manualStatuses.includes(s.name);
    });
});

const isAmendmentMode = computed(() => {
    const status = props.statuses.find(s => s.id == form.status_id);
    return status?.name?.toLowerCase().includes('amend') && status?.name !== 'Amended';
});

// --- PARENT EO SUGGESTIVE SEARCH ---
const parentSearchQuery = ref('');
const showParentDropdown = ref(false);

const filteredParents = computed(() => {
    let list = props.existing_eos || [];
    list = list.filter(item => item.is_active);

    if (isEdit.value && editingId.value) {
        list = list.filter(item => item.id !== editingId.value);
    }

    if (form.effectivity_date) {
        const newDocDate = new Date(form.effectivity_date).getTime();
        list = list.filter(item => {
            if (!item.effectivity_date) return true;
            return new Date(item.effectivity_date).getTime() <= newDocDate;
        });
    }

    if (parentSearchQuery.value && !parentSearchQuery.value.startsWith('AMENDING:')) {
        const q = parentSearchQuery.value.toLowerCase();
        list = list.filter(item => {
            const trackingNo = item.eo_number ? item.eo_number.toLowerCase() : '';
            const title = item.title ? item.title.toLowerCase() : '';
            return trackingNo.includes(q) || title.includes(q);
        });
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

// --- DYNAMIC ARRAY HELPERS ---
const createEmptyMember = () => ({ id: null, pmis_id: null, name: '' });

const getListByField = (field: string) => {
    const c = form.committee_details.council;
    const p = form.committee_details.program;
    switch(field) {
        case 'council_internal': return c.internal_members;
        case 'council_external': return c.external_members;
        case 'secretariat_members': return c.secretariat_members;
        case 'twg_internal': return c.twg_internal_members;
        case 'twg_external': return c.twg_external_members;
        case 'program_internal': return p.internal_members;
        case 'program_external': return p.external_members;
        default: return null;
    }
};

const addMember = (field: string) => {
    const list = getListByField(field);
    if (list) list.push(createEmptyMember());
};

const removeMember = (field: string, index: number) => {
    const list = getListByField(field);
    if (!list) return;
    if (list.length > 1) {
        list.splice(index, 1);
    } else {
        list[index] = createEmptyMember();
    }
};

// --- SUGGESTIVE INPUT LOGIC ---
const activeSuggestion = ref<string | null>(null);

const getDeptCode = (titleStr?: string) => {
    if (!titleStr) return '';
    const match = titleStr.match(/\(([^)]+)\)/);
    if (match) {
        const officeName = match[1].trim();
        const dept = props.departments.find(d => d.name === officeName);
        return dept && dept.code ? dept.code : officeName;
    }
    return '';
};

const normalize = (s: string) => s ? String(s).toLowerCase().replace(/[^a-z0-9]/g, '') : '';

const getCurrentlySelectedPeople = (ignoreField: string | null, ignoreIndex: number = -1) => {
    const selected = new Set<string>();
    const c = form.committee_details.council;
    const p = form.committee_details.program;

    const addVal = (val: any, field: string, idx: number = -1) => {
        if (field === ignoreField && idx === ignoreIndex) return;
        if (!val) return;

        // 🚀 Fix: Ensure we are safely extracting the name
        const name = typeof val === 'object' ? val.name : val;

        if (typeof name === 'string' && name.trim() !== '') {
            selected.add(normalize(name));
        }
    };

    addVal(c.chairman, 'chairman');
    addVal(c.co_chairman, 'co_chairman'); // Updated to single object
    addVal(c.vice_chairman, 'vice_chairman');
    addVal(c.lead_secretariat, 'lead_secretariat');
    addVal(c.twg_head, 'twg_head');

    if (Array.isArray(c.internal_members)) c.internal_members.forEach((v, i) => addVal(v, 'council_internal', i));
    if (Array.isArray(c.external_members)) c.external_members.forEach((v, i) => addVal(v, 'council_external', i));
    if (Array.isArray(c.secretariat_members)) c.secretariat_members.forEach((v, i) => addVal(v, 'secretariat_members', i));
    if (Array.isArray(c.twg_internal_members)) c.twg_internal_members.forEach((v, i) => addVal(v, 'twg_internal', i));
    if (Array.isArray(c.twg_external_members)) c.twg_external_members.forEach((v, i) => addVal(v, 'twg_external', i));

    if (Array.isArray(p.internal_members)) p.internal_members.forEach((v, i) => addVal(v, 'program_internal', i));
    if (Array.isArray(p.external_members)) p.external_members.forEach((v, i) => addVal(v, 'program_external', i));

    return Array.from(selected);
};

const getSuggestions = (query: any, typeFilter: string | null = null, fieldName: string | null = null, idx: number = -1) => {
    let list = props.peopleRegistry || [];

    if (typeFilter) {
        list = list.filter(p => {
            if (typeFilter === 'Internal') {
                return p.type === 'Internal' || p.type === 'Internal(Registered)';
            }
            if (typeFilter === 'External') {
                return p.type === 'External' || p.type === 'External(Registered)';
            }
            return p.type === typeFilter;
        });
    }

    const globallySelectedNames = getCurrentlySelectedPeople(fieldName, idx);
    if (globallySelectedNames.length > 0) {
        list = list.filter(p => !globallySelectedNames.includes(normalize(p.name || '')));
    }

    if (!query || typeof query !== 'string' || query.trim() === '') {
        return [];
    }

    const currentSearch = query.trim().toLowerCase();

    if (!currentSearch || currentSearch.length < 2) {
        return [];
    }

    return list.filter(p => {
        const safeName = p.name ? String(p.name).toLowerCase() : '';
        return safeName.includes(currentSearch);
    }).slice(0, 10);
};

const selectPerson = (field: string, person: any, index?: number) => {
    const personObj = {
        id: person.id,
        pmis_id: person.pmis_id,
        name: person.name
    };

    if (field === 'chairman') form.committee_details.council.chairman = personObj;
    else if (field === 'vice_chairman') form.committee_details.council.vice_chairman = personObj;
    else if (field === 'lead_secretariat') form.committee_details.council.lead_secretariat = personObj;
    else if (field === 'twg_head') form.committee_details.council.twg_head = personObj;
    else if (field === 'co_chairman') form.committee_details.council.co_chairman = personObj; // Single object
    else if (index !== undefined) {
        if (field === 'council_internal') form.committee_details.council.internal_members[index] = personObj;
        else if (field === 'council_external') form.committee_details.council.external_members[index] = personObj;
        else if (field === 'secretariat_members') form.committee_details.council.secretariat_members[index] = personObj;
        else if (field === 'twg_internal') form.committee_details.council.twg_internal_members[index] = personObj;
        else if (field === 'twg_external') form.committee_details.council.twg_external_members[index] = personObj;
        else if (field === 'program_internal') form.committee_details.program.internal_members[index] = personObj;
        else if (field === 'program_external') form.committee_details.program.external_members[index] = personObj;
    }
    activeSuggestion.value = null;
};

// LEAD OFFICE SUGGESTIONS
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
        chairman: createEmptyMember(),
        vice_chairman: createEmptyMember(),
        lead_secretariat: createEmptyMember(),
        twg_head: createEmptyMember(),
        co_chairman: createEmptyMember(), // 🚀 Now a single object
        internal_members: Array.from({ length: 5 }, createEmptyMember),
        external_members: Array.from({ length: 5 }, createEmptyMember),
        secretariat_members: Array.from({ length: 5 }, createEmptyMember),
        twg_internal_members: Array.from({ length: 5 }, createEmptyMember),
        twg_external_members: Array.from({ length: 5 }, createEmptyMember)
    },
    program: {
        co_lead_office_id: '' as string | number,
        internal_members: Array.from({ length: 5 }, createEmptyMember),
        external_members: Array.from({ length: 5 }, createEmptyMember)
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

const handleTypeChange = () => {
    const type = form.committee_details.type;
    const defaults = defaultCommitteeDetails();

    if (type === 'none') {
        form.committee_details = defaults;
    } else if (type === 'council') {
        form.committee_details = defaults;
        form.committee_details.type = 'council';
        form.declaration = '';
    } else if (type === 'program') {
        form.committee_details = defaults;
        form.committee_details.type = 'program';
        form.declaration = '';
    }
};

const formatForInput = (dateStr: string | null) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr.split('T')[0].split(' ')[0];
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    selectedRecord.value = null;
    activeModalTab.value = 'details';
    activeCouncilTab.value = 'committee';
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
    activeCouncilTab.value = 'committee';

    form.clearErrors();

    form.eo_number = eo.eo_number;
    form.title = eo.title;
    form.classification_id = eo.classification_id || '';
    form.status_id = eo.status_id || '';

    form.date_issued = formatForInput(eo.date_issued);
    form.effectivity_date = formatForInput(eo.effectivity_date);

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

    const c = defaultCommitteeDetails();

    // Check if the EO has an attached committee
    if (eo.committees && eo.committees.length > 0) {
        const committee = eo.committees[0]; // Assuming 1 committee per EO
        c.type = committee.type;

        // Loop through the relational members and map them by their pivot role
        if (committee.members && committee.members.length > 0) {

            const formatMember = (m: any) => ({
                id: m.id,
                pmis_id: m.pmis_id,
                name: m.name
            });

            let intIdx = 0, extIdx = 0, secIdx = 0, twgIntIdx = 0, twgExtIdx = 0;
            let progIntIdx = 0, progExtIdx = 0;

            committee.members.forEach((m: any) => {
                const role = m.pivot.role;
                const memberObj = formatMember(m);

                if (c.type === 'council') {
                    if (role === 'Chairman') c.council.chairman = memberObj;
                    else if (role === 'Vice Chairman') c.council.vice_chairman = memberObj;
                    else if (role === 'Lead Secretariat') c.council.lead_secretariat = memberObj;
                    else if (role === 'TWG Head') c.council.twg_head = memberObj;
                    else if (role === 'Co-Chairman') c.council.co_chairman = memberObj;
                    else if (role === 'Internal Member') c.council.internal_members[intIdx++] = memberObj;
                    else if (role === 'External Member') c.council.external_members[extIdx++] = memberObj;
                    else if (role === 'Secretariat Member') c.council.secretariat_members[secIdx++] = memberObj;
                    else if (role === 'TWG Internal') c.council.twg_internal_members[twgIntIdx++] = memberObj;
                    else if (role === 'TWG External') c.council.twg_external_members[twgExtIdx++] = memberObj;
                }
                else if (c.type === 'program') {
                    if (role === 'Program Internal') c.program.internal_members[progIntIdx++] = memberObj;
                    else if (role === 'Program External') c.program.external_members[progExtIdx++] = memberObj;
                }
            });
        }
    }

    const lead = eo.departments?.find((d: any) => d.pivot.role === 'lead');
    form.lead_office_id = lead ? lead.id : '';
    leadOfficeSearch.value = lead ? lead.name : '';

    form.file = null;
    form.committee_details = c;
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
    if (target.files && target.files[0]) {
        // Just set the file property. Do NOT reset the whole form.
        form.file = target.files[0];
    }
}

function submitForm() {
    const url = isEdit.value && editingId.value ? route('eo.update', editingId.value) : route('eo.store');
    
    // Inertia supports converting form data to multipart/form-data natively 
    // when using 'post' and specifying _method: 'PUT' for updates.
    const method = isEdit.value ? 'post' : 'post'; 

    form.transform((data) => {
        const transformed = { ...data };
        
        // Clean out empty string members to prevent DB errors
        const filterEmpty = (arr: any[]) => arr.filter(m => m && m.name && m.name.trim() !== '');

        if (transformed.committee_details.type === 'council') {
            transformed.committee_details.council.internal_members = filterEmpty(data.committee_details.council.internal_members);
            transformed.committee_details.council.external_members = filterEmpty(data.committee_details.council.external_members);
            transformed.committee_details.council.secretariat_members = filterEmpty(data.committee_details.council.secretariat_members);
            transformed.committee_details.council.twg_internal_members = filterEmpty(data.committee_details.council.twg_internal_members);
            transformed.committee_details.council.twg_external_members = filterEmpty(data.committee_details.council.twg_external_members);
        } else if (transformed.committee_details.type === 'program') {
            transformed.committee_details.program.internal_members = filterEmpty(data.committee_details.program.internal_members);
            transformed.committee_details.program.external_members = filterEmpty(data.committee_details.program.external_members);
        }

        // Trick Laravel into accepting a PUT request over a multipart form POST
        if (isEdit.value) {
            transformed._method = 'PUT';
        }

        return transformed;
    }).post(url, {
        forceFormData: true, // 🚀 Let Inertia handle the file logic!
        onSuccess: () => {
            showDialog.value = false;
            form.reset();
            notyf.success('EO saved successfully.');
        },
        onError: (errors) => {
            console.error(errors);
            notyf.error('Error saving data. Check console.');
        }
    });
}

const getLeadOffice = (depts: any[]) => {
    if (!depts) return '—';
    const lead = depts.find(d => d.pivot.role === 'lead');
    return lead ? lead.name : '—';
};

const breadcrumbs = [{ title: 'Executive Orders', href: '/eo' }];
</script>

<template>
    <Head title="EO Profiling" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-white p-4 md:p-8">

            <div class="flex flex-col items-center justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm xl:flex-row flex-wrap">
                <div class="relative w-full md:max-w-md xl:flex-1">
                    <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input v-model="searchTerm" type="text" placeholder="Search EO Number, Title or Keywords..." class="block w-full rounded-lg border border-gray-300 bg-gray-50 hover:bg-white focus:bg-white py-2 pr-10 pl-10 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-colors" />
                    <button v-if="searchTerm || filterYear !== 'all' || filterActive !== 'all'" @click="clearSearch" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors" title="Clear Filters">×</button>
                </div>

                <div class="flex items-center justify-between xl:justify-end gap-4 w-full xl:w-auto flex-wrap sm:flex-nowrap">
                    <div class="flex items-center gap-2 bg-gray-50 p-1 rounded-lg border border-gray-200 flex-1 sm:flex-none">
                        <Filter class="w-4 h-4 text-gray-400 ml-2 hidden sm:block" />
                        <select v-model="filterYear" class="border-0 bg-transparent text-xs font-semibold text-gray-600 focus:ring-0 cursor-pointer py-1.5 pl-2 pr-6 border-r border-gray-200 w-full sm:w-auto outline-none">
                            <option value="all">Years</option>
                            <option v-for="y in available_years" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <select v-model="filterActive" class="border-0 bg-transparent text-xs font-semibold text-gray-600 focus:ring-0 cursor-pointer py-1.5 pl-2 pr-6 w-full sm:w-auto outline-none">
                            <option value="all">Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <button v-if="$page.props.auth.user.role !== 'user'" class="flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-blue-700 transition shrink-0" @click="openAddDialog">
                        <Plus class="h-4 w-4" /> Encode EO
                    </button>
                </div>
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
                                <tr
                                    v-for="eo in eos.data"
                                    :key="eo.id"
                                    class="transition-colors border-l-4 border-b border-gray-200"
                                    :class="eo.is_active
                                        ? 'hover:bg-gray-50 border-l-transparent'
                                        : 'bg-gray-50/60 opacity-80 border-l-red-400'"
                                >

                                    <td class="px-6 py-4 align-top">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-mono font-bold text-blue-600 text-base">{{ eo.eo_number }}</span>
                                            <div class="text-xs text-gray-600 mt-1 flex flex-col gap-0.5">
                                                <span><span class="text-gray-400 font-medium">Issued:</span> {{ formatDate(eo.date_issued) }}</span>
                                                <span class="text-blue-500">Eff: {{ formatDate(eo.effectivity_date) }}</span>
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
                                            {{ eo.is_active ? 'Active' : 'Inactive' }}
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

                        <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
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
                                        <input v-model="form.eo_number" type="text" placeholder="e.g. EO No. 1, s. 2026" class="w-full rounded-lg border text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" :class="form.errors.eo_number ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'" required />
                                        <p v-if="form.errors.eo_number" class="text-[10px] text-red-500 mt-1 font-bold">{{ form.errors.eo_number }}</p>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-blue-600 uppercase">Structure Type</label>
                                        <select v-model="form.committee_details.type" @change="handleTypeChange" class="w-full rounded-lg border border-blue-300 text-sm px-3 py-2 bg-blue-50/50 text-blue-800 font-bold outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                                            <option value="none">Standard EO</option>
                                            <option value="council">Council / Committee / TWG</option>
                                            <option value="program">Program-Based Initiative</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Status <span class="text-red-500">*</span></label>
                                        <select v-model="form.status_id" class="w-full rounded-lg border text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" :class="form.errors.status_id ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'" required>
                                            <option value="" disabled>Select Status</option>
                                            <option v-for="status in selectableStatuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                        </select>
                                        <p v-if="form.errors.status_id" class="text-[10px] text-red-500 mt-1 font-bold">{{ form.errors.status_id }}</p>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Classification</label>
                                        <select v-model="form.classification_id" class="w-full rounded-lg border text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" :class="form.errors.classification_id ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'">
                                            <option value="">Select Classification</option>
                                            <option v-for="cls in classifications" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                                        </select>
                                        <p v-if="form.errors.classification_id" class="text-[10px] text-red-500 mt-1 font-bold">{{ form.errors.classification_id }}</p>
                                    </div>
                                </div>

                                <div class="space-y-4 pt-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Executive Order Title <span class="text-red-500">*</span></label>
                                        <textarea v-model="form.title" rows="2" class="w-full rounded-lg border text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" :class="form.errors.title ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'" placeholder="Full title including subject matter..." required></textarea>
                                        <p v-if="form.errors.title" class="text-[10px] text-red-500 mt-1 font-bold">{{ form.errors.title }}</p>
                                    </div>
                                </div>

                                <div class="space-y-4 mt-4">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Legal Basis</label>
                                        <input v-model="form.legal_basis" type="text" class="w-full rounded-lg border text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" :class="form.errors.legal_basis ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'" placeholder="e.g. LGC Section 455" />
                                        <p v-if="form.errors.legal_basis" class="text-[10px] text-red-500 mt-1 font-bold">{{ form.errors.legal_basis }}</p>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Date Issued <span class="text-red-500">*</span></label>
                                            <input v-model="form.date_issued" type="date" class="w-full rounded-lg border text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" :class="form.errors.date_issued ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'" required />
                                            <p v-if="form.errors.date_issued" class="text-[10px] text-red-500 mt-1 font-bold">{{ form.errors.date_issued }}</p>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Effectivity</label>
                                            <input v-model="form.effectivity_date" type="date" :min="form.date_issued" class="w-full rounded-lg border text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" :class="form.errors.effectivity_date ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'" />
                                            <p v-if="form.errors.effectivity_date" class="text-[10px] text-red-500 mt-1 font-bold">{{ form.errors.effectivity_date }}</p>
                                        </div>
                                    </div>
                                </div>

                                <Transition name="fade">
                                    <div v-if="isEdit && selectedRecord?.amendments?.length > 0" class="p-4 bg-blue-50/50 rounded-xl border border-blue-100 space-y-2 mt-4">
                                        <div class="flex items-center gap-2 text-xs font-bold text-blue-800 uppercase tracking-widest">
                                            <Info class="w-4 h-4" /> Executive Order Amended
                                        </div>
                                        <p class="text-sm text-blue-900">
                                            This EO has been amended by:
                                            <span class="font-bold bg-white px-2 py-0.5 rounded border border-blue-200 ml-1">
                                                {{ selectedRecord.amendments[0].eo_number }}
                                            </span>
                                        </p>
                                    </div>
                                </Transition>

                                <Transition name="fade">
                                    <div v-if="isAmendmentMode" class="p-4 bg-amber-50/50 rounded-xl border border-amber-100 space-y-4 mt-2">
                                        <div class="flex items-center gap-2 text-xs font-bold text-amber-800 uppercase tracking-widest mb-1">
                                            <Info class="w-4 h-4" /> Historical Tracker: Amendment Linking
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
                                    <input type="file" @change="handleFileChange" accept="application/pdf" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-600 file:text-white file:font-medium hover:file:bg-blue-700 cursor-pointer transition-colors" :class="{'ring-2 ring-red-500 border border-red-500 rounded-lg': form.errors.file}"/>
                                    <p v-if="form.errors.file" class="text-[10px] text-red-500 mt-1 font-bold">{{ form.errors.file }}</p>
                                    <p v-else class="text-[10px] text-gray-400 mt-2 italic">Max size 20MB. Ensure the document is fully signed before uploading.</p>
                                </div>
                            </div>

                            <div :key="form.committee_details.type" class="bg-gray-50/50 p-5 rounded-xl border border-gray-100 space-y-6 relative animate-in fade-in slide-in-from-bottom-2 duration-300">

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
                                        <button v-if="form.lead_office_id || leadOfficeSearch" @click.prevent="clearLeadOffice" type="button" class="absolute right-2 top-[31px] text-gray-400 hover:text-gray-600 transition">
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
                                        class="w-full rounded-lg border text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500"
                                        :class="form.errors.declaration ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'"
                                        placeholder="Enter the main declaration, directive, or order..."></textarea>
                                    <p v-if="form.errors.declaration" class="text-[10px] text-red-500 mt-1 font-bold">{{ form.errors.declaration }}</p>
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
                                                    v-model="form.committee_details.council.chairman.name"
                                                    @focus.stop="activeSuggestion = 'chairman'"
                                                    @click.stop="activeSuggestion = 'chairman'"
                                                    @input="activeSuggestion = 'chairman'"
                                                    type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />

                                                <div v-if="activeSuggestion === 'chairman' && getSuggestions(form.committee_details.council.chairman.name, null, 'chairman').length > 0"
                                                     class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(form.committee_details.council.chairman.name, null, 'chairman')" :key="idx"
                                                         @mousedown.prevent="selectPerson('chairman', person)"
                                                         class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <div class="text-sm font-bold text-gray-800">
                                                            {{ person.name }}
                                                            <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                        </div>
                                                        <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="relative">
                                                <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Co-Chairman</label>
                                                <input
                                                    v-model="form.committee_details.council.co_chairman.name"
                                                    @focus.stop="activeSuggestion = 'co_chairman'"
                                                    @click.stop="activeSuggestion = 'co_chairman'"
                                                    @input="activeSuggestion = 'co_chairman'"
                                                    type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />

                                                <div v-if="activeSuggestion === 'co_chairman' && getSuggestions(form.committee_details.council.co_chairman.name, null, 'co_chairman').length > 0"
                                                     class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(form.committee_details.council.co_chairman.name, null, 'co_chairman')" :key="idx"
                                                         @mousedown.prevent="selectPerson('co_chairman', person)"
                                                         class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <div class="text-sm font-bold text-gray-800">
                                                            {{ person.name }}
                                                            <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                        </div>
                                                        <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="relative">
                                                <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Vice Chairman</label>
                                                <input
                                                    v-model="form.committee_details.council.vice_chairman.name"
                                                    @focus.stop="activeSuggestion = 'vice_chairman'"
                                                    @click.stop="activeSuggestion = 'vice_chairman'"
                                                    @input="activeSuggestion = 'vice_chairman'"
                                                    type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />

                                                <div v-if="activeSuggestion === 'vice_chairman' && getSuggestions(form.committee_details.council.vice_chairman.name, null, 'vice_chairman').length > 0"
                                                     class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(form.committee_details.council.vice_chairman.name, null, 'vice_chairman')" :key="idx"
                                                         @mousedown.prevent="selectPerson('vice_chairman', person)"
                                                         class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <div class="text-sm font-bold text-gray-800">
                                                            {{ person.name }}
                                                            <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                        </div>
                                                        <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
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
                                                                v-model="member.name"
                                                                @focus.stop="activeSuggestion = 'council_internal_' + index"
                                                                @click.stop="activeSuggestion = 'council_internal_' + index"
                                                                type="text"
                                                                class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500"
                                                                placeholder="Search or type name..."
                                                            />

                                                            <div v-if="activeSuggestion === 'council_internal_' + index && getSuggestions(member.name, 'Internal', 'council_internal', index).length > 0"
                                                                 class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                                <div v-for="(person, idx) in getSuggestions(member.name, 'Internal', 'council_internal', index)" :key="idx"
                                                                     @mousedown.prevent="selectPerson('council_internal', person, index)"
                                                                     class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                                    <div class="text-sm font-bold text-gray-800">
                                                                        {{ person.name }}
                                                                        <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                                    </div>
                                                                    <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
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
                                                            type="text"
                                                            v-model="member.name"
                                                            @focus.stop="activeSuggestion = 'council_external_' + index"
                                                            @click.stop="activeSuggestion = 'council_external_' + index"
                                                            class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500"
                                                            placeholder="Search or type name..."
                                                        />

                                                        <div v-if="activeSuggestion === 'council_external_' + index && getSuggestions(member.name, 'External', 'council_external', index).length > 0"
                                                             class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                            <div v-for="(person, idx) in getSuggestions(member.name, 'External', 'council_external', index)" :key="idx"
                                                                 @mousedown.prevent="selectPerson('council_external', person, index)"
                                                                 class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                                <div class="text-sm font-bold text-gray-800">
                                                                    {{ person.name }}
                                                                    <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                                </div>
                                                                <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
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
                                                        v-model="form.committee_details.council.lead_secretariat.name"
                                                        @focus.stop="activeSuggestion = 'lead_secretariat'"
                                                        @click.stop="activeSuggestion = 'lead_secretariat'"
                                                        @input="activeSuggestion = 'lead_secretariat'"
                                                        type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />

                                                    <div v-if="activeSuggestion === 'lead_secretariat' && getSuggestions(form.committee_details.council.lead_secretariat.name, null, 'lead_secretariat').length > 0"
                                                         class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                        <div v-for="(person, idx) in getSuggestions(form.committee_details.council.lead_secretariat.name, null, 'lead_secretariat')" :key="idx"
                                                             @mousedown.prevent="selectPerson('lead_secretariat', person)"
                                                             class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                            <div class="text-sm font-bold text-gray-800">
                                                                {{ person.name }}
                                                                <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                            </div>
                                                            <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <label class="mb-2 block text-xs font-bold text-gray-500 uppercase">Secretariat Members</label>
                                                    <div v-for="(member, index) in form.committee_details.council.secretariat_members" :key="'sm_'+index" class="relative flex items-center gap-2">
                                                        <span class="text-[10px] font-bold text-gray-400 w-3 text-right">{{ index + 1 }}.</span>
                                                        <div class="relative flex-1">
                                                            <input
                                                                type="text"
                                                                v-model="member.name"
                                                                @focus.stop="activeSuggestion = 'secretariat_members_' + index"
                                                                @click.stop="activeSuggestion = 'secretariat_members_' + index"
                                                                class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500"
                                                                placeholder="Search or type name..."
                                                            />

                                                            <div v-if="activeSuggestion === 'secretariat_members_' + index && getSuggestions(member.name, null, 'secretariat_members', index).length > 0"
                                                                 class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                                <div v-for="(person, idx) in getSuggestions(member.name, null, 'secretariat_members', index)" :key="idx"
                                                                     @mousedown.prevent="selectPerson('secretariat_members', person, index)"
                                                                     class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                                    <div class="text-sm font-bold text-gray-800">
                                                                        {{ person.name }}
                                                                        <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                                    </div>
                                                                    <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
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
                                    </div>

                                    <div v-show="activeCouncilTab === 'twg'" class="space-y-6 animate-in fade-in duration-300">
                                        <div class="w-full md:w-1/2 pr-4">
                                            <div class="relative">
                                                <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">TWG Head</label>
                                                <input
                                                    v-model="form.committee_details.council.twg_head.name"
                                                    @focus.stop="activeSuggestion = 'twg_head'"
                                                    @click.stop="activeSuggestion = 'twg_head'"
                                                    @input="activeSuggestion = 'twg_head'"
                                                    type="text" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search or type name..." />

                                                <div v-if="activeSuggestion === 'twg_head' && getSuggestions(form.committee_details.council.twg_head.name, null, 'twg_head').length > 0"
                                                     class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(form.committee_details.council.twg_head.name, null, 'twg_head')" :key="idx"
                                                         @mousedown.prevent="selectPerson('twg_head', person)"
                                                         class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <div class="text-sm font-bold text-gray-800">
                                                            {{ person.name }}
                                                            <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                        </div>
                                                        <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
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
                                                            type="text"
                                                            v-model="member.name"
                                                            @focus.stop="activeSuggestion = 'twg_internal_' + index"
                                                            @click.stop="activeSuggestion = 'twg_internal_' + index"
                                                            class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500"
                                                            placeholder="Search or type name..."
                                                        />

                                                        <div v-if="activeSuggestion === 'twg_internal_' + index && getSuggestions(member.name, 'Internal', 'twg_internal', index).length > 0"
                                                             class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                            <div v-for="(person, idx) in getSuggestions(member.name, 'Internal', 'twg_internal', index)" :key="idx"
                                                                 @mousedown.prevent="selectPerson('twg_internal', person, index)"
                                                                 class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                                <div class="text-sm font-bold text-gray-800">
                                                                    {{ person.name }}
                                                                    <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                                </div>
                                                                <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
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
                                                            type="text"
                                                            v-model="member.name"
                                                            @focus.stop="activeSuggestion = 'twg_external_' + index"
                                                            @click.stop="activeSuggestion = 'twg_external_' + index"
                                                            class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500"
                                                            placeholder="Search or type name..."
                                                        />

                                                        <div v-if="activeSuggestion === 'twg_external_' + index && getSuggestions(member.name, 'External', 'twg_external', index).length > 0"
                                                             class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                            <div v-for="(person, idx) in getSuggestions(member.name, 'External', 'twg_external', index)" :key="idx"
                                                                 @mousedown.prevent="selectPerson('twg_external', person, index)"
                                                                 class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                                <div class="text-sm font-bold text-gray-800">
                                                                    {{ person.name }}
                                                                    <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                                </div>
                                                                <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
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
                                                        type="text"
                                                        v-model="member.name"
                                                        @focus.stop="activeSuggestion = 'program_internal_' + index"
                                                        @click.stop="activeSuggestion = 'program_internal_' + index"
                                                        class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500"
                                                        placeholder="Search or type name..."
                                                    />

                                                    <div v-if="activeSuggestion === 'program_internal_' + index && getSuggestions(member.name, 'Internal', 'program_internal', index).length > 0"
                                                         class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                        <div v-for="(person, idx) in getSuggestions(member.name, 'Internal', 'program_internal', index)" :key="idx"
                                                             @mousedown.prevent="selectPerson('program_internal', person, index)"
                                                             class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                            <div class="text-sm font-bold text-gray-800">
                                                                {{ person.name }}
                                                                <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                            </div>
                                                            <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
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
                                                        type="text"
                                                        v-model="member.name"
                                                        @focus.stop="activeSuggestion = 'program_external_' + index"
                                                        @click.stop="activeSuggestion = 'program_external_' + index"
                                                        class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500"
                                                        placeholder="Search or type name..."
                                                    />

                                                    <div v-if="activeSuggestion === 'program_external_' + index && getSuggestions(member.name, 'External', 'program_external', index).length > 0"
                                                         class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                        <div v-for="(person, idx) in getSuggestions(member.name, 'External', 'program_external', index)" :key="idx"
                                                             @mousedown.prevent="selectPerson('program_external', person, index)"
                                                             class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                            <div class="text-sm font-bold text-gray-800">
                                                                {{ person.name }}
                                                                <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                            </div>
                                                            <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
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
