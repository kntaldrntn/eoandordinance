<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Search, Calendar, Download, Building2, Paperclip,
    AlertCircle, Link as LinkIcon, UserCheck, Gavel, ChevronDown, ChevronUp,
    CheckCircle2, XCircle, Users, FileText,
    Eye
} from 'lucide-vue-next';
import { ref } from 'vue';
import { debounce } from 'lodash';
import TransparencyTimeline from '@/Components/TransparencyTimeline.vue';

const props = defineProps<{
    records: {
        data: Array<any>;
        links: Array<any>;
    };
    departments: Array<{ id: number; name: string }>;
    filters: { search?: string; year?: string; type?: string; is_active?: string };
    years: number[];
    activeType: string;
}>();

const search = ref(props.filters.search || '');
const year = ref(props.filters.year || '');
const isActive = ref(props.filters.is_active || 'all');
const activeTab = ref(props.activeType || 'eo');
const expandedId = ref<number | null>(null);

// --- MODAL STATE ---
const showCommitteeModal = ref(false);
const selectedCommittee = ref<any>(null);

const openCommitteeModal = (item: any) => {
    selectedCommittee.value = item;
    showCommitteeModal.value = true;
};

// --- RELATIONAL DATA HELPERS ---
const matchRole = (pivotRole: string | undefined, role: string) => {
    if (!pivotRole) return false;
    const p = String(pivotRole).toLowerCase();
    const r = String(role).toLowerCase();

    // direct contains match (covers 'Committee Chairman' vs 'Chairman')
    if (p.includes(r)) return true;

    // synonyms and broader mappings
    const map = {
        'internal member': ['committee member', 'internal member', 'program internal'],
        'external member': ['external member', 'ngo partner', 'other organization', 'program external'],
        'chairman': ['committee chairman', 'chairman'],
        'co-chairman': ['co-chairman', 'cochairman', 'co-chair'],
        'vice chairman': ['vice chairman', 'vice-chairman', 'vicechair'],
        'secretariat member': ['secretariat member', 'secretariat'],
        'lead secretariat': ['lead secretariat'],
        'twg head': ['twg head'],
        'twg internal': ['twg internal', 'twg member'],
    };

    const candidates = (map as any)[r] || [];
    for (const c of candidates) {
        if (p.includes(c)) return true;
    }

    return false;
};

const getMemberByRole = (item: any, role: string) => {
    const committee = item.committees?.[0];
    if (!committee || !committee.members) return 'N/A';
    const member = committee.members.find((m: any) => matchRole(m.pivot?.role, role));
    if (member) return member.name;

    // Try deriving from common pivot role variants
    const roleKey = role.toLowerCase();
    const fallbackMap: any = {
        'chairman': ['committee chairman', 'chairman', 'presiding officer'],
        'co-chairman': ['co-chairman', 'cochairman', 'co-chair'],
        'vice chairman': ['vice chairman', 'vice-chairman', 'vicechair'],
        'lead secretariat': ['lead secretariat'],
        'twg head': ['twg head'],
    };

    const derived = getMemberByPivot(item, fallbackMap[roleKey] || [role]);
    if (derived) return derived;

    // Fallbacks: some author roles are stored in author_details rather than as pivots
    const details = item.author_details;
    if (details) {
        const chair = details.committee_chairmanship;
        if (chair && role.toLowerCase().includes('chair')) return Array.isArray(chair) ? chair.join(', ') : chair;
    }

    return 'N/A';
};

const getMembersByRole = (item: any, role: string) => {
    const committee = item.committees?.[0];
    if (!committee || !committee.members) return 'None';
    const names = committee.members
        .filter((m: any) => matchRole(m.pivot?.role, role))
        .map((m: any) => m.name);
    return names.length ? names.join(', ') : 'None';
};

// Helpers that use the serialized simple debug list when present
const getSimpleByKeywords = (item: any, keywords: string[] = []) => {
    if (!item || !item.committee_members_simple) return [];
    const lowerKeys = keywords.map(k => k.toLowerCase());
    return item.committee_members_simple
        .filter((m: any) => {
            const r = (m.role || '').toLowerCase();
            return lowerKeys.some(k => r.includes(k));
        })
        .map((m: any) => m.name || '')
        .filter(Boolean);
};

const getSimpleFirst = (item: any, keywords: string[] = []) => {
    const list = getSimpleByKeywords(item, keywords);
    return list.length ? list[0] : null;
};

// Derive members directly from pivot roles (useful when author_details isn't populated)
const getMembersByPivot = (item: any, roles: string[] = []) => {
    const committee = item.committees?.[0];
    if (!committee || !committee.members) return [];
    return committee.members
        .filter((m: any) => roles.some(r => matchRole(m.pivot?.role, r)))
        .map((m: any) => m.name || '');
};

// Exact-match pivot role extractor (no synonym mapping) — use for External/NGO/Other to avoid broad matches
const getMembersByPivotExact = (item: any, roles: string[] = []) => {
    const committee = item.committees?.[0];
    if (!committee || !committee.members) return [];
    return committee.members
        .filter((m: any) => {
            const r = (m.pivot?.role || '').toLowerCase();
            return roles.some(role => r.includes(String(role).toLowerCase()));
        })
        .map((m: any) => m.name || '');
};

const getMemberByPivot = (item: any, roles: string[] = []) => {
    const list = getMembersByPivot(item, roles);
    if (!list || list.length === 0) return null;
    return list.length === 1 ? list[0] : list.join(', ');
};

// Map Department ID to Name for Programs
const getDeptName = (id: string | number) => {
    if (!id) return 'None Assigned';
    const dept = props.departments.find(d => d.id == id);
    return dept ? dept.name : 'Unknown Office';
};

// --- LOGIC ---
const updateParams = debounce(() => {
    router.get('/', {
        search: search.value,
        year: year.value,
        is_active: isActive.value,
        type: activeTab.value
    }, { preserveState: true, preserveScroll: true });
}, 300);

const switchTab = (type: string) => {
    activeTab.value = type;
    search.value = '';
    year.value = '';
    isActive.value = 'all';
    updateParams();
};

const toggleHistory = (id: number) => {
    expandedId.value = expandedId.value === id ? null : id;
};

// --- ORDINANCE HELPERS (Kept intact) ---
const getParsedExt = (val: any) => {
    if (typeof val === 'string') {
        try {
            const parsed = JSON.parse(val);
            if (typeof parsed === 'object' && parsed !== null) return parsed;
        } catch(e) {
            return val;
        }
    }
    return val || '';
};

const extractName = (item: any): string => {
    if (!item) return '';
    if (typeof item === 'string') return item.trim();
    if (typeof item === 'object' && item.name) return String(item.name).trim();
    return '';
};

const isValidString = (v: any) => {
    const name = extractName(v);
    return name !== 'null' && name !== '';
};

const isNewStructure = (arr: any) => {
    const parsed = getParsedExt(arr);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) && ('members' in parsed || 'ngos' in parsed || 'others' in parsed);
};

const hasValidData = (arr: any) => {
    let parsed = getParsedExt(arr);
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        const m = Array.isArray(parsed.members) ? parsed.members.filter(isValidString).length > 0 : false;
        const n = Array.isArray(parsed.ngos) ? parsed.ngos.filter(isValidString).length > 0 : false;
        const o = Array.isArray(parsed.others) ? parsed.others.filter(isValidString).length > 0 : false;
        return m || n || o;
    }
    if (!Array.isArray(parsed)) return isValidString(parsed);
    return parsed.filter(isValidString).length > 0;
};

const displaySafeArray = (arr: any) => {
    let parsed = getParsedExt(arr);
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        let combined: string[] = [];
        if (Array.isArray(parsed.members)) combined = combined.concat(parsed.members.filter(isValidString).map(extractName));
        if (Array.isArray(parsed.ngos)) combined = combined.concat(parsed.ngos.filter(isValidString).map(extractName));
        if (Array.isArray(parsed.others)) combined = combined.concat(parsed.others.filter(isValidString).map(extractName));
        return combined.join(', ');
    }
    if (!Array.isArray(parsed)) return extractName(parsed);
    return parsed.filter(isValidString).map(extractName).join(', ');
};

// Robust external display helpers: prefer pivot-derived simple keywords, then committee pivot members, then parsed external_institutions
const getExternalMembersDisplay = (item: any) => {
    // Prefer direct pivot-derived arrays if controller provided them (top-level or committee-scoped)
    if (item && Array.isArray(item.external_members_from_pivot) && item.external_members_from_pivot.length) {
        return item.external_members_from_pivot.join(', ');
    }
    const committeeScoped = item?.committees?.[0];
    if (committeeScoped && Array.isArray(committeeScoped.external_members_from_pivot) && committeeScoped.external_members_from_pivot.length) {
        return committeeScoped.external_members_from_pivot.join(', ');
    }

    const fromSimple = getSimpleByKeywords(item, ['external member']);
    if (fromSimple.length) return fromSimple.join(', ');

    const fromPivot = getMembersByRole(item, 'External Member');
    if (fromPivot && fromPivot !== 'None') return fromPivot;

    // fallback to external_institutions (any structure)
    const ext = item.external_institutions;
    const disp = displaySafeArray(ext);
    return disp ? disp : '';
};

const getExternalNgosDisplay = (item: any) => {
    if (item && Array.isArray(item.external_ngos_from_pivot) && item.external_ngos_from_pivot.length) {
        return item.external_ngos_from_pivot.join(', ');
    }
    const cScope = item?.committees?.[0];
    if (cScope && Array.isArray(cScope.external_ngos_from_pivot) && cScope.external_ngos_from_pivot.length) {
        return cScope.external_ngos_from_pivot.join(', ');
    }

    const fromSimple = getSimpleByKeywords(item, ['ngo partner','ngo']);
    if (fromSimple.length) return fromSimple.join(', ');
    const ext = item.external_institutions;
    const parsed = getParsedExt(ext);
    if (parsed && typeof parsed === 'object' && Array.isArray(parsed.ngos) && parsed.ngos.filter(isValidString).length) {
        return parsed.ngos.filter(isValidString).map(extractName).join(', ');
    }
    return '';
};

const getExternalOthersDisplay = (item: any) => {
    if (item && Array.isArray(item.external_others_from_pivot) && item.external_others_from_pivot.length) {
        return item.external_others_from_pivot.join(', ');
    }
    const cScope2 = item?.committees?.[0];
    if (cScope2 && Array.isArray(cScope2.external_others_from_pivot) && cScope2.external_others_from_pivot.length) {
        return cScope2.external_others_from_pivot.join(', ');
    }

    const fromSimple = getSimpleByKeywords(item, ['other organization','other org']);
    if (fromSimple.length) return fromSimple.join(', ');
    const ext = item.external_institutions;
    const parsed = getParsedExt(ext);
    if (parsed && typeof parsed === 'object' && Array.isArray(parsed.others) && parsed.others.filter(isValidString).length) {
        return parsed.others.filter(isValidString).map(extractName).join(', ');
    }
    return '';
};

const hasAuthorDetails = (item: any) => {
    const details = item.author_details;
    const external = item.external_institutions;

    if (!details && !external) return false;

    let hasCoAuthors = false;
    if (details && details.co_authors) {
        hasCoAuthors = Array.isArray(details.co_authors)
            ? details.co_authors.filter(isValidString).length > 0
            : Boolean(details.co_authors);
    }

    return Boolean(
        (details && details.primary_author) ||
        (details && details.introduced_by) ||
        (details && details.committee_chairmanship) ||
        hasCoAuthors ||
        hasValidData(external)
    );
};

const getStatusColor = (statusName: string) => {
    switch(statusName) {
        case 'Active':
        case 'In Effect':
            return 'bg-white text-gray-800 border-gray-300 ring-gray-900/10 font-bold';
        case 'Inactive':
            return 'bg-gray-100 text-gray-500 border-gray-200 ring-gray-500/10';
        case 'Amended':
            return 'bg-blue-50 text-blue-700 border-blue-200 ring-blue-600/20';
        case 'Supplements':
        case 'Supplemented':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 ring-emerald-600/20';
        case 'Suspended':
            return 'bg-amber-50 text-amber-700 border-amber-200 ring-amber-600/20';
        case 'Repealed':
        case 'Supersede':
            return 'bg-red-50 text-red-700 border-red-200 ring-red-600/20';
        default:
            return 'bg-white text-gray-700 border-gray-200 ring-gray-500/10';
    }
};

const getCardClass = (isActive: boolean) => {
    if (!isActive) {
        return 'opacity-75 grayscale-[0.5] hover:grayscale-0 hover:opacity-100 bg-gray-50/50';
    }
    return 'bg-white';
};

const formatDate = (date: string) => date ? new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';

const getLeadOffice = (depts: any[]) => {
    const lead = depts?.find(d => d.pivot.role === 'lead');
    return lead ? lead.name : "City Mayor's Office";
};

const getSupportOffices = (depts: any[]) => {
    if (!depts) return [];
    return depts.filter(d => d.pivot.role === 'support').map(d => d.name);
};

const getSponsors = (item: any) => {
    if (!item.author_details) return 'City Council';

    const primary = item.author_details.primary_author || item.author_details.introduced_by || item.author_details.committee_chairmanship;
    let coAuthorsCount = 0;

    if (item.author_details.co_authors) {
        coAuthorsCount = Array.isArray(item.author_details.co_authors)
            ? item.author_details.co_authors.filter(isValidString).length
            : 0;
    }

    if (!primary && coAuthorsCount === 0) return 'City Council';
    if (primary && coAuthorsCount === 0) return primary;
    if (primary && coAuthorsCount > 0) return `${primary} +${coAuthorsCount}`;
    if (!primary && coAuthorsCount > 0) return `Multiple Authors (${coAuthorsCount})`;

    return 'City Council';
};

const getActiveIrrs = (irrs: any[]) => {
    if (!Array.isArray(irrs)) return [];
    return irrs.filter(irr => irr.is_active == true || irr.is_active == 1);
};

// --- ARRAY-BASED ROLE FORMATTER ---
const formatStructuredMembers = (namesArray: string[]) => {
    if (!namesArray || namesArray.length === 0 || (namesArray.length === 1 && namesArray[0] === 'None')) return '';
    
    let output = '';
    let membersList: string[] = [];

    namesArray.forEach((name, index) => {
        const cleanName = name.trim();
        if (cleanName.toLowerCase() === 'all members') {
            output += cleanName + '\n';
            return;
        }
        
        if (index === 0) {
            output += 'Chairperson: Hon. ' + cleanName + '\n';
        } else if (index === 1) {
            output += 'Vice Chairperson: Hon. ' + cleanName + '\n';
        } else {
            // Collect the rest into a separate array instead of printing them immediately
            membersList.push('Hon. ' + cleanName);
        }
    });

    // If there are standard members, group them under one header!
    if (membersList.length > 0) {
        output += 'Members:\n' + membersList.join('\n');
    }
    
    return output.trim();
};

</script>

<template>
    <Head title="Public Records" />

    <div class="min-h-screen flex flex-col bg-gray-50 font-sans text-gray-900">

        <header class="bg-white shadow-sm border-b sticky top-0 z-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-blue-600 p-2 rounded-lg shadow-sm">
                        <Gavel class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 leading-none">Executive Orders and City Ordinances</h1>
                        <p class="text-xs text-gray-500 font-medium tracking-wide mt-0.5">PUBLIC RECORDS PORTAL</p>
                    </div>
                </div>
                <Link href="/login" class="text-sm font-medium text-gray-500 hover:text-blue-600 transition-colors"> Login </Link>
            </div>
        </header>

        <div class="bg-white border-b border-gray-200 pt-10 pb-0">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-4xl mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Find Laws & Issuances</h2>
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input v-model="search" @input="updateParams" type="text" placeholder="Search Number, Title, or Keywords..." class="w-full pl-12 pr-4 py-4 rounded-xl border border-gray-300 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 text-base" />
                        </div>
                    </div>
                </div>

                <div class="flex gap-8">
                    <button @click="switchTab('eo')" class="pb-4 px-2 text-sm font-bold uppercase tracking-wide border-b-2 transition-all" :class="activeTab === 'eo' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">Executive Orders</button>
                    <button @click="switchTab('ordinance')" class="pb-4 px-2 text-sm font-bold uppercase tracking-wide border-b-2 transition-all" :class="activeTab === 'ordinance' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">City Ordinances</button>
                </div>
            </div>
        </div>

        <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div v-if="records.data.length > 0" class="grid gap-6">

                <div v-for="item in records.data" :key="item.id"
                    class="group rounded-xl border border-gray-200 bg-white shadow-sm hover:shadow-md transition-all duration-200 p-5 relative overflow-hidden"
                    :class="!item.is_active ? 'opacity-80 grayscale-[0.3]' : ''"
                >
                    <!-- Status Indicator Bar on Left -->
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 transition-colors"
                        :class="item.is_active ? 'bg-green-500' : 'bg-gray-400'">
                    </div>

                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-5 pl-2">
                        
                        <!-- Left Side: Content -->
                        <div class="flex-1 w-full min-w-0">
                            
                            <!-- Tags Row -->
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="inline-flex items-center justify-center bg-gray-50 text-gray-700 px-2.5 py-1 rounded-md text-xs font-bold font-mono border border-gray-200 shadow-sm">
                                    {{ activeTab === 'eo' ? item.eo_number : item.ordinance_number }}
                                </span>

                                <span v-if="item.is_active" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm">
                                    <CheckCircle2 class="w-3 h-3" /> In Effect
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest bg-gray-50 text-gray-500 border border-gray-200 shadow-sm">
                                    <XCircle class="w-3 h-3" /> Inactive
                                </span>

                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest bg-white text-gray-800 border border-gray-300 shadow-sm">
                                    NEW
                                </span>
                            </div>

                            <!-- Crisp Title -->
                            <h3 class="text-lg md:text-xl font-bold text-gray-900 group-hover:text-blue-700 transition-colors mb-3 leading-snug pr-2">
                                {{ item.title }}
                            </h3>

                            <!-- Declaration/Remarks block -->
                            <div v-if="activeTab === 'eo' && item.declaration" class="mb-3 pl-3 border-l-4 border-blue-400 bg-blue-50/40 py-2 rounded-r-lg pr-3">
                                <p class="text-[10px] font-bold text-blue-800 uppercase tracking-widest mb-0.5">Declaration / Directive:</p>
                                <p class="text-xs text-gray-700 leading-relaxed whitespace-pre-wrap font-medium">
                                    {{ item.declaration }}
                                </p>
                            </div>

                            <div v-if="item.remarks" class="mb-3 pl-3 border-l-4 border-gray-300 bg-gray-50 py-2 rounded-r-lg pr-3">
                                <p class="text-xs text-gray-600 italic font-medium">
                                    "{{ item.remarks }}"
                                </p>
                            </div>

                            <!-- Metadata Row (Sponsors, Authors, etc) -->
                            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-gray-600 mt-1 font-medium">
                                <div v-if="activeTab === 'eo'" class="flex items-center gap-1.5">
                                    <Building2 class="w-3.5 h-3.5 text-gray-400" />
                                    <span><span class="text-gray-900 font-bold">{{ getLeadOffice(item.departments) }}</span></span>
                                </div>
                                <div v-else class="flex items-center gap-1.5">
                                    <UserCheck class="w-3.5 h-3.5 text-gray-400" />
                                    <span class="text-gray-900 font-bold">{{ getSponsors(item) }}</span>
                                </div>
                            </div>

                            <!-- Compact Beautiful IRR Box -->
                            <div v-if="getActiveIrrs(item.implementing_rules).length > 0" class="w-full mt-4 bg-blue-50/50 rounded-lg p-3 border border-blue-100 shadow-inner">
                                <p class="text-[10px] uppercase font-extrabold text-blue-800 mb-2 flex items-center gap-1.5">
                                    <Paperclip class="w-3.5 h-3.5" /> 
                                    Rules and Regulations ({{ getActiveIrrs(item.implementing_rules).length }})
                                </p>
                                
                                <div class="space-y-1.5">
                                    <div v-for="irr in getActiveIrrs(item.implementing_rules)" :key="irr.id" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-white p-2 rounded-md border border-blue-100 shadow-sm hover:shadow transition-shadow">
                                        <div class="flex items-center gap-2">
                                            <div class="p-1.5 bg-blue-50 rounded shrink-0">
                                                <FileText class="w-3.5 h-3.5 text-blue-600" />
                                            </div>
                                            <span class="text-xs font-bold text-gray-800 leading-tight">
                                                {{ irr.status }}
                                                <span v-if="irr.lead_office" class="text-gray-500 font-normal ml-1">({{ irr.lead_office.name }})</span>
                                            </span>
                                        </div>
                                        <a v-if="irr.file_url" :href="irr.file_url" target="_blank" class="shrink-0 flex items-center justify-center gap-1 px-2.5 py-1 text-[10px] font-bold text-blue-700 bg-blue-50 hover:bg-blue-600 hover:text-white rounded border border-blue-200 hover:border-blue-600 transition-all">
                                            <Eye class="w-3 h-3" /> Read
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Timeline -->
                            <div v-show="expandedId === item.id" class="mt-4 border-t border-gray-100 pt-4">
                                <TransparencyTimeline :timeline="item.public_timeline" />
                            </div>
                        </div>

                        <!-- Right Side: Action Buttons -->
                        <div class="mt-4 md:mt-0 flex flex-row md:flex-col gap-2 shrink-0 md:w-36">
                            
                            <!-- Primary Action: View PDF -->
                            <a v-if="item.file_url" :href="item.file_url" target="_blank" 
                            class="flex items-center justify-center gap-2 px-3 py-3 w-full rounded-lg bg-blue-600 text-white font-bold text-xs hover:bg-blue-700 transition-all">
                                <Eye class="w-4 h-4" />
                                <span>View PDF</span>
                            </a>

                            <!-- Secondary Action: View Details -->
                            <button
                                v-if="(activeTab === 'eo' && item.committees && item.committees.length > 0) || (activeTab === 'ordinance' && (hasAuthorDetails(item) || (item.committees && item.committees.length > 0)))"
                                @click="openCommitteeModal(item)"
                                class="flex items-center justify-center gap-2 px-3 py-3 w-full rounded-lg bg-white border border-gray-200 text-gray-700 font-bold text-xs hover:bg-gray-50 transition-all"
                            >
                                <Users class="w-4 h-4 text-gray-500" />
                                <span>{{ (item.committees && item.committees[0] && (item.committees[0].type === 'council' || item.committees[0].type === 'ordinance_sponsors')) ? 'Committee' : 'Program' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-20 bg-white rounded-xl border border-dashed border-gray-300">
                <Search class="w-8 h-8 text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-semibold text-gray-900">No records found</h3>
                <p class="text-gray-500 mt-1">Try adjusting your search terms or filters.</p>
            </div>

            <div v-if="records.links.length > 3" class="mt-8 flex justify-center gap-1">
                <Link v-for="(link, i) in records.links" :key="i" :href="link.url || '#'" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors" :class="[link.active ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200', !link.url && 'opacity-50 cursor-not-allowed']" v-html="link.label" />
            </div>
        </main>

        <footer class="bg-white border-t border-gray-200 mt-auto py-8">
            <div class="max-w-7xl mx-auto px-4 text-center text-sm text-gray-500">
                &copy; {{ new Date().getFullYear() }} Executive Orders and Ordinances Management System.
            </div>
        </footer>

        <Transition name="fade">
            <div v-if="showCommitteeModal && selectedCommittee" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
                <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">

                    <div class="bg-blue-600 px-6 py-4 flex items-center justify-between shrink-0">
                        <div>
                            <h3 class="text-white font-bold text-lg flex items-center gap-2">
                                <FileText class="w-5 h-5 text-blue-200" />
                                <template v-if="activeTab === 'eo'">
                                    <span v-if="selectedCommittee.committees?.[0]?.type === 'council'">Council & Committee Structure</span>
                                    <span v-else-if="selectedCommittee.committees?.[0]?.type === 'program'">Program Implementation Details</span>
                                    <span v-else>Executive Order Details</span>
                                </template>
                                <template v-else>
                                    Ordinance & Authorship Details
                                </template>
                            </h3>
                            <p class="text-blue-200 text-xs mt-0.5">
                                {{ activeTab === 'eo' ? selectedCommittee.eo_number : selectedCommittee.ordinance_number }}
                            </p>
                            <p v-if="typeof selectedCommittee.committee_member_count !== 'undefined'" class="text-blue-100 text-xs mt-0.5">
                                Committee members: {{ selectedCommittee.committee_member_count }}
                            </p>
                        </div>
                        <button @click="showCommitteeModal = false" class="text-blue-200 hover:text-white bg-blue-700/50 hover:bg-blue-700 rounded-full p-1.5 transition">
                            <XCircle class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto bg-gray-50/50">

                        <div v-if="(activeTab === 'eo' || activeTab === 'ordinance') && selectedCommittee.committees?.length > 0">

                            <div v-if="['council','ordinance_sponsors'].includes(selectedCommittee.committees[0].type)" class="space-y-4">
                                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Leadership</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <template v-if="activeTab === 'ordinance'">
                                            <div>
                                                <p class="text-xs text-gray-500 font-medium">Presiding Officer</p>
                                                <p class="text-sm font-bold text-gray-900">{{ selectedCommittee.presiding_officer || (getMemberByRole(selectedCommittee, 'Chairman') !== 'N/A' ? getMemberByRole(selectedCommittee, 'Chairman') : getSimpleFirst(selectedCommittee, ['committee chairman','presiding officer','presiding'])) }}</p>
                                            </div>

                                            <div>
                                                <p class="text-xs text-gray-500 font-medium">Attested By</p>
                                                <p class="text-sm font-bold text-gray-900">{{ selectedCommittee.attested_by || selectedCommittee.author_details?.attested_by || getSimpleFirst(selectedCommittee, ['attested by','attested_by']) || '—' }}</p>
                                            </div>

                                            <div>
                                                <p class="text-xs text-gray-500 font-medium">Approved By</p>
                                                <p class="text-sm font-bold text-gray-900">{{ selectedCommittee.approved_by || selectedCommittee.author_details?.approved_by || getSimpleFirst(selectedCommittee, ['approved by','approved_by']) || '—' }}</p>
                                            </div>
                                        </template>

                                        <template v-else>
                                            <div v-if="(getMemberByRole(selectedCommittee, 'Chairman') !== 'N/A') || selectedCommittee.presiding_officer || getSimpleFirst(selectedCommittee, ['committee chairman','presiding officer','presiding'])">
                                                <p class="text-xs text-gray-500 font-medium">Chairman</p>
                                                <p class="text-sm font-bold text-gray-900">{{ getMemberByRole(selectedCommittee, 'Chairman') !== 'N/A' ? getMemberByRole(selectedCommittee, 'Chairman') : (selectedCommittee.presiding_officer || getSimpleFirst(selectedCommittee, ['committee chairman','presiding officer','presiding'])) }}</p>
                                            </div>

                                            <div v-if="getMemberByRole(selectedCommittee, 'Co-Chairman') !== 'N/A'">
                                                <p class="text-xs text-gray-500 font-medium">Co-Chairman</p>
                                                <p class="text-sm font-bold text-gray-900 whitespace-pre-wrap">{{ getMemberByRole(selectedCommittee, 'Co-Chairman') }}</p>
                                            </div>

                                            <div v-if="getMemberByRole(selectedCommittee, 'Vice Chairman') !== 'N/A'">
                                                <p class="text-xs text-gray-500 font-medium">Vice Chairman</p>
                                                <p class="text-sm font-bold text-gray-900">{{ getMemberByRole(selectedCommittee, 'Vice Chairman') }}</p>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm" v-if="activeTab !== 'ordinance' && ((selectedCommittee.committee_members_simple && getSimpleByKeywords(selectedCommittee, ['committee member','internal member']).length) || getMembersByRole(selectedCommittee, 'Internal Member') !== 'None')">
                                        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Internal Members</h4>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ selectedCommittee.committee_members_simple ? getSimpleByKeywords(selectedCommittee, ['committee member','internal member']).join('\n') : getMembersByRole(selectedCommittee, 'Internal Member') }}</p>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm" v-if="activeTab !== 'ordinance' && ((selectedCommittee.committee_members_simple && getSimpleByKeywords(selectedCommittee, ['external member','ngo partner','other organization']).length) || getMembersByRole(selectedCommittee, 'External Member') !== 'None')">
                                            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">External Members</h4>
                                            <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ selectedCommittee.committee_members_simple ? getSimpleByKeywords(selectedCommittee, ['external member','ngo partner','other organization']).join('\n') : getMembersByRole(selectedCommittee, 'External Member') }}</p>
                                        </div>

                                        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm" v-if="(selectedCommittee.committee_members_simple && (getSimpleByKeywords(selectedCommittee, ['lead secretariat']).length || getSimpleByKeywords(selectedCommittee, ['secretariat member','secretariat']).length)) || getMemberByRole(selectedCommittee, 'Lead Secretariat') !== 'N/A' || getMembersByRole(selectedCommittee, 'Secretariat Member') !== 'None'">
                                            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Secretariat</h4>

                                            <div v-if="getMemberByRole(selectedCommittee, 'Lead Secretariat') !== 'N/A'" class="mb-3">
                                                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-0.5">Lead Secretariat</p>
                                                <p class="text-sm font-bold text-gray-900">{{ getSimpleFirst(selectedCommittee, ['lead secretariat']) || getMemberByRole(selectedCommittee, 'Lead Secretariat') }}</p>
                                            </div>

                                            <div v-if="getMembersByRole(selectedCommittee, 'Secretariat Member') !== 'None'">
                                                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-0.5">Members</p>
                                                <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ selectedCommittee.committee_members_simple ? getSimpleByKeywords(selectedCommittee, ['secretariat member','secretariat']).join('\n') : getMembersByRole(selectedCommittee, 'Secretariat Member') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="getMemberByRole(selectedCommittee, 'TWG Head') !== 'N/A' || getMembersByRole(selectedCommittee, 'TWG Internal') !== 'None'" class="bg-blue-50/50 border border-blue-100 rounded-xl p-5 shadow-sm">
                                    <h4 class="text-xs font-bold text-blue-800 uppercase tracking-widest mb-4 border-b border-blue-100 pb-2">Technical Working Group (TWG)</h4>

                                    <div class="mb-4" v-if="getMemberByRole(selectedCommittee, 'TWG Head') !== 'N/A'">
                                        <p class="text-xs text-blue-600 font-medium">TWG Head</p>
                                        <p class="text-sm font-bold text-blue-900">{{ getMemberByRole(selectedCommittee, 'TWG Head') }}</p>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div v-if="getMembersByRole(selectedCommittee, 'TWG Internal') !== 'None'">
                                            <p class="text-xs text-blue-600 font-medium mb-1">TWG Internal Members</p>
                                            <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ getMembersByRole(selectedCommittee, 'TWG Internal').split(', ').join('\n') }}</p>
                                        </div>
                                        <div v-if="getMembersByRole(selectedCommittee, 'TWG External') !== 'None'">
                                            <p class="text-xs text-blue-600 font-medium mb-1">TWG External Members</p>
                                            <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ getMembersByRole(selectedCommittee, 'TWG External').split(', ').join('\n') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>


                                    <!-- Sponsorship / Authors (ordinance-specific) split into two cards -->
                                    <div v-if="(
                                            (getParsedExt(selectedCommittee.author_details) && (getParsedExt(selectedCommittee.author_details).sponsorship_committee || getParsedExt(selectedCommittee.author_details).primary_author || getParsedExt(selectedCommittee.author_details).introduced_by || getParsedExt(selectedCommittee.author_details).co_authors))
                                            || (selectedCommittee.committee_members_simple && getSimpleByKeywords(selectedCommittee, ['primary author', 'introduced by', 'co-author', 'attested by', 'committee member']).length)
                                        )" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2 mb-6">

                                            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Sponsorship</h4>
                                                <div>
                                                    <div v-if="getParsedExt(selectedCommittee.author_details)?.sponsorship_committee?.name || (selectedCommittee.committees?.[0]?.registry?.name && !getParsedExt(selectedCommittee.author_details)?.sponsorship_committee?.name)">
                                                        <p class="text-xs text-gray-500 font-medium">Sponsorship Committee</p>
                                                        <p class="text-sm font-bold text-gray-900 mb-3">{{ getParsedExt(selectedCommittee.author_details)?.sponsorship_committee?.name || selectedCommittee.committees?.[0]?.registry?.name }}</p>
                                                    </div>

                                                    <div v-if="(selectedCommittee.committee_members_simple && getSimpleByKeywords(selectedCommittee, ['chairperson', 'vice chairperson', 'committee member', 'internal member']).length) || getMembersByRole(selectedCommittee, 'Internal Member') !== 'None'">
                                                        <p class="text-xs text-gray-500 font-medium">Committee Members</p>

                                                        <template v-if="selectedCommittee.committee_members_simple">
                                                            <div v-if="getSimpleFirst(selectedCommittee, ['chairperson'])" class="mt-1">
                                                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Chairperson</p>
                                                                <p class="text-sm font-bold text-gray-900">Hon. {{ getSimpleFirst(selectedCommittee, ['chairperson']) }}</p>
                                                            </div>
                                                            <div v-if="getSimpleFirst(selectedCommittee, ['vice chairperson'])" class="mt-2">
                                                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Vice Chairperson</p>
                                                                <p class="text-sm font-bold text-gray-900">Hon. {{ getSimpleFirst(selectedCommittee, ['vice chairperson']) }}</p>
                                                            </div>
                                                            <div v-if="getSimpleByKeywords(selectedCommittee, ['committee member', 'internal member']).length" class="mt-2">
                                                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Members</p>
                                                                <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed mt-0.5">Hon. {{ getSimpleByKeywords(selectedCommittee, ['committee member', 'internal member']).join('\nHon. ') }}</p>
                                                            </div>
                                                        </template>
                                                        <template v-else>
                                                            <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed mt-1">{{ formatStructuredMembers(getMembersByRole(selectedCommittee, 'Internal Member').split(', ')) }}</p>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>

                                        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                                            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Authors</h4>
                                            <div>
                                                <div v-if="getParsedExt(selectedCommittee.author_details)?.primary_author || getSimpleFirst(selectedCommittee, ['Primary Author', 'primary author'])">
                                                    <p class="text-xs text-blue-600 font-bold mb-1">Primary Author</p>
                                                    <p class="text-sm font-bold text-gray-900">
                                                        {{ getParsedExt(selectedCommittee.author_details)?.primary_author?.name || getParsedExt(selectedCommittee.author_details)?.primary_author || getSimpleFirst(selectedCommittee, ['Primary Author', 'primary author']) }}
                                                    </p>
                                                </div>
                                                <div v-else-if="getParsedExt(selectedCommittee.author_details)?.introduced_by || getSimpleFirst(selectedCommittee, ['Introduced By', 'introduced by'])">
                                                    <p class="text-xs text-blue-600 font-bold mb-1">Introduced By</p>
                                                    <p class="text-sm font-bold text-gray-900">
                                                        {{ getParsedExt(selectedCommittee.author_details)?.introduced_by?.name || getParsedExt(selectedCommittee.author_details)?.introduced_by || getSimpleFirst(selectedCommittee, ['Introduced By', 'introduced by']) }}
                                                    </p>
                                                </div>

                                                <div v-if="hasValidData(getParsedExt(selectedCommittee.author_details)?.co_authors) || getSimpleByKeywords(selectedCommittee, ['co-author']).length" class="mt-3">
                                                    <p class="text-xs text-gray-500 font-bold mb-1">Co-Authors</p>
                                                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed mt-1">
                                                        <template v-if="hasValidData(getParsedExt(selectedCommittee.author_details)?.co_authors)">
                                                            {{ displaySafeArray(getParsedExt(selectedCommittee.author_details).co_authors) }}
                                                        </template>
                                                        <template v-else>
                                                            {{ getSimpleByKeywords(selectedCommittee, ['co-author']).join('\n') }}
                                                        </template>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                            <div v-if="selectedCommittee.committees[0].type === 'program'" class="space-y-6">
                            <!-- Lead + Co-Lead offices side by side -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-blue-50 border border-blue-100 rounded-xl p-5 shadow-sm">
                                    <h4 class="text-[10px] font-bold text-blue-400 uppercase tracking-widest mb-2">Lead Implementing Office</h4>
                                    <p class="text-base font-bold text-blue-900">{{ getLeadOffice(selectedCommittee.departments) }}</p>
                                </div>

                                <!-- ── FIX: show co-lead office when present ── -->
                                <div
                                    v-if="selectedCommittee.committees[0].co_lead_office_name"
                                    class="bg-blue-50/60 border border-blue-100 rounded-xl p-5 shadow-sm"
                                >
                                    <h4 class="text-[10px] font-bold text-blue-400 uppercase tracking-widest mb-2">Co-Lead Office</h4>
                                    <p class="text-base font-bold text-blue-900">
                                        {{ selectedCommittee.committees[0].co_lead_office_name }}
                                    </p>
                                </div>
                            </div>

                            <!-- Internal + External members -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div
                                    class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm"
                                    v-if="getMembersByRole(selectedCommittee, 'Program Internal') !== 'None'"
                                >
                                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Internal Team Members</h4>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                                        {{
                                            selectedCommittee.committee_members_simple
                                                ? getSimpleByKeywords(selectedCommittee, ['program internal']).join('\n')
                                                : getMembersByRole(selectedCommittee, 'Program Internal')
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm"
                                    v-if="getMembersByRole(selectedCommittee, 'Program External') !== 'None'"
                                >
                                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">External Partners / NGOs</h4>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                                        {{
                                            selectedCommittee.committee_members_simple
                                                ? getSimpleByKeywords(selectedCommittee, ['program external']).join('\n')
                                                : getMembersByRole(selectedCommittee, 'Program External')
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        </div>





                        <div v-if="activeTab === 'ordinance' && (selectedCommittee.author_details || selectedCommittee.external_institutions || getExternalMembersDisplay(selectedCommittee) || getExternalNgosDisplay(selectedCommittee) || getExternalOthersDisplay(selectedCommittee))" class="space-y-6">
                            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4 border-b pb-2">External Institutions</h4>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 mb-0">
                                    <div v-if="selectedCommittee.author_details?.primary_author || selectedCommittee.author_details?.introduced_by">
                                        <p class="text-xs text-blue-600 font-bold mb-1">
                                            {{ selectedCommittee.author_details.is_primary_author ? 'Primary Sponsor / Author' : 'Introduced By' }}
                                        </p>
                                        <p class="text-sm font-bold text-gray-900">
                                            {{ selectedCommittee.author_details.primary_author || selectedCommittee.author_details.introduced_by }}
                                        </p>
                                    </div>

                                    <div v-if="selectedCommittee.author_details?.committee_chairmanship">
                                        <p class="text-xs text-blue-600 font-bold mb-1">Committee Chairmanship(s)</p>
                                        <p class="text-sm font-bold text-gray-900 whitespace-pre-wrap">{{ selectedCommittee.author_details.committee_chairmanship }}</p>
                                    </div>
                                </div>

                                <div v-if="hasValidData(selectedCommittee.author_details?.co_authors)" class="border-t border-gray-100 pt-4">
                                    <p class="text-xs text-gray-500 font-bold mb-2">Co-Authors / Committee Members</p>
                                    <p class="text-sm font-medium text-gray-800 whitespace-pre-wrap leading-relaxed">{{ displaySafeArray(selectedCommittee.author_details.co_authors) }}</p>
                                </div>

                                <div v-if="(getExternalMembersDisplay(selectedCommittee) || getExternalNgosDisplay(selectedCommittee) || getExternalOthersDisplay(selectedCommittee)) || hasValidData(selectedCommittee.external_institutions) || ((selectedCommittee.committee_members_simple && getSimpleByKeywords(selectedCommittee, ['external member','ngo partner','other organization']).length) || getMembersByRole(selectedCommittee, 'External Member') !== 'None')" class="pt-2 mt-2">
                                    <template v-if="activeTab === 'ordinance'">
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                            <!-- Members Column -->
                                            <div v-if="getMembersByPivotExact(selectedCommittee, ['External Member']).length > 0 || (getParsedExt(selectedCommittee.external_institutions)?.members && getParsedExt(selectedCommittee.external_institutions).members.length > 0)">
                                                <p class="text-xs text-gray-500 font-medium">Members</p>
                                                <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ 
                                                    getMembersByPivotExact(selectedCommittee, ['External Member']).length 
                                                    ? getMembersByPivotExact(selectedCommittee, ['External Member']).join('\n') 
                                                    : getParsedExt(selectedCommittee.external_institutions).members.join('\n') 
                                                }}</p>
                                            </div>

                                            <!-- NGO's Column -->
                                            <div v-if="getMembersByPivotExact(selectedCommittee, ['NGO Partner']).length > 0 || (getParsedExt(selectedCommittee.external_institutions)?.ngos && getParsedExt(selectedCommittee.external_institutions).ngos.length > 0)">
                                                <p class="text-xs text-gray-500 font-medium">NGO's</p>
                                                <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ 
                                                    getMembersByPivotExact(selectedCommittee, ['NGO Partner']).length 
                                                    ? getMembersByPivotExact(selectedCommittee, ['NGO Partner']).join('\n') 
                                                    : getParsedExt(selectedCommittee.external_institutions).ngos.join('\n') 
                                                }}</p>
                                            </div>

                                            <!-- Other Organizations Column -->
                                            <div v-if="getMembersByPivotExact(selectedCommittee, ['Other Organization']).length > 0 || (getParsedExt(selectedCommittee.external_institutions)?.others && getParsedExt(selectedCommittee.external_institutions).others.length > 0)">
                                                <p class="text-xs text-gray-500 font-medium">Other Organizations</p>
                                                <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ 
                                                    getMembersByPivotExact(selectedCommittee, ['Other Organization']).length 
                                                    ? getMembersByPivotExact(selectedCommittee, ['Other Organization']).join('\n') 
                                                    : getParsedExt(selectedCommittee.external_institutions).others.join('\n') 
                                                }}</p>
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <template v-if="isNewStructure(selectedCommittee.external_institutions)">
                                            <div class="space-y-3">
                                                <div v-if="hasValidData(getParsedExt(selectedCommittee.external_institutions).members)">
                                                    <p class="text-xs text-gray-500 font-bold mb-">External Members</p>
                                                    <p class="text-sm font-medium text-gray-800 whitespace-pre-wrap leading-relaxed">{{ displaySafeArray(getParsedExt(selectedCommittee.external_institutions).members) }}</p>
                                                </div>
                                                </div>
                                        </template>
                                        <template v-else>
                                            <p class="text-xs text-gray-500 font-bold mb-2">External Institutions</p>
                                            <p class="text-sm font-medium text-gray-800 whitespace-pre-wrap leading-relaxed">{{ displaySafeArray(selectedCommittee.external_institutions) }}</p>
                                        </template>
                                    </template>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </Transition>

    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
