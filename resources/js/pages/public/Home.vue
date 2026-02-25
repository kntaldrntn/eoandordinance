<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    Search, Calendar, Download, Building2, Paperclip, 
    AlertCircle, Link as LinkIcon, UserCheck, Gavel, ChevronDown, ChevronUp,
    CheckCircle2, XCircle, Users // <--- Added Users icon
} from 'lucide-vue-next';
import { ref } from 'vue';
import { debounce } from 'lodash'; 
import TransparencyTimeline from '@/Components/TransparencyTimeline.vue';

const props = defineProps<{
    records: {
        data: Array<any>;
        links: Array<any>;
    };
    departments: Array<{ id: number; name: string }>; // <--- Added departments prop
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

// --- STYLING HELPERS ---
const getStatusColor = (statusName: string) => {
    switch(statusName) {
        case 'Active': return 'bg-green-50 text-green-700 border-green-200 ring-green-600/20';
        case 'Amended': return 'bg-amber-50 text-amber-700 border-amber-200 ring-amber-600/20';
        case 'Repealed': 
        case 'Superseded': return 'bg-red-50 text-red-700 border-red-200 ring-red-600/20';
        default: return 'bg-gray-50 text-gray-700 border-gray-200 ring-gray-500/10';
    }
};

const getCardClass = (isActive: boolean) => {
    if (!isActive) {
        return 'opacity-75 grayscale-[0.5] hover:grayscale-0 hover:opacity-100 bg-gray-50/50'; 
    }
    return 'bg-white'; 
};

// --- DATA HELPERS ---
const formatDate = (date: string) => date ? new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';

const getLeadOffice = (depts: any[]) => {
    const lead = depts?.find(d => d.pivot.role === 'lead');
    return lead ? lead.name : "City Mayor's Office";
};

const getSponsors = (depts: any[]) => {
    const sponsors = depts?.filter(d => d.pivot.role === 'sponsor') || [];
    if (sponsors.length === 0) return 'City Council';
    if (sponsors.length === 1) return sponsors[0].name;
    return `${sponsors[0].name} +${sponsors.length - 1}`;
};
</script>

<template>
    <Head title="Public Records" />

    <div class="min-h-screen bg-gray-50 font-sans text-gray-900">
        
        <header class="bg-white shadow-sm border-b sticky top-0 z-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-blue-600 p-2 rounded-lg shadow-sm">
                        <Gavel class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 leading-none">City Legislation</h1>
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
                        
                        <div class="flex gap-4 w-full md:w-auto">
                            <div class="w-full md:w-40">
                                <select v-model="year" @change="updateParams" class="w-full h-full px-4 py-4 rounded-xl border border-gray-300 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 text-base cursor-pointer">
                                    <option value="">All Years</option>
                                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                            <div class="w-full md:w-40">
                                <select v-model="isActive" @change="updateParams" class="w-full h-full px-4 py-4 rounded-xl border border-gray-300 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 text-base cursor-pointer">
                                    <option value="all">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-8">
                    <button @click="switchTab('eo')" class="pb-4 px-2 text-sm font-bold uppercase tracking-wide border-b-2 transition-all" :class="activeTab === 'eo' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">Executive Orders</button>
                    <button @click="switchTab('ordinance')" class="pb-4 px-2 text-sm font-bold uppercase tracking-wide border-b-2 transition-all" :class="activeTab === 'ordinance' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">City Ordinances</button>
                </div>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div v-if="records.data.length > 0" class="grid gap-6">
                
                <div v-for="item in records.data" :key="item.id" 
                    class="group rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all p-6 relative overflow-hidden"
                    :class="getCardClass(item.is_active)"
                >
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 transition-colors" 
                        :class="item.is_active ? 'bg-green-500' : 'bg-gray-400'">
                    </div>

                    <div class="flex flex-col md:flex-row md:items-start gap-6 pl-2">
                        <div class="flex-1">
                            
                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <span class="bg-gray-100 text-gray-700 px-2.5 py-1 rounded-md text-xs font-bold font-mono border border-gray-200">
                                    {{ activeTab === 'eo' ? item.eo_number : item.ordinance_number }}
                                </span>
                                
                                <span v-if="item.is_active" class="flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-green-100 text-green-700 border border-green-200">
                                    <CheckCircle2 class="w-3 h-3" /> In Effect
                                </span>
                                <span v-else class="flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-500 border border-gray-200">
                                    <XCircle class="w-3 h-3" /> Inactive
                                </span>

                                <span :class="['px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wide border ring-1 ring-inset', getStatusColor(item.status.name)]">
                                    {{ item.status.name }}
                                </span>

                                <span class="text-xs text-gray-400 flex items-center gap-1">
                                    <Calendar class="w-3.5 h-3.5" /> 
                                    {{ activeTab === 'eo' ? formatDate(item.date_issued) : formatDate(item.date_enacted) }}
                                </span>
                            </div>

                            <h3 class="text-lg font-bold text-gray-900 group-hover:text-blue-600 transition-colors mb-3 leading-snug">
                                {{ item.title }}
                            </h3>

                            <div class="space-y-3 mb-4">
                                <div v-if="item.parent_e_o || item.parent_ordinance" class="text-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-blue-50 p-3 rounded-lg border border-blue-100 text-blue-800">
                                    <div class="flex items-start gap-2">
                                        <LinkIcon class="w-4 h-4 mt-0.5 shrink-0 text-blue-500" />
                                        <div>
                                            <p class="font-semibold text-xs uppercase tracking-wide text-blue-600 mb-0.5">Changes / Amends:</p>
                                            <p class="font-medium">
                                                {{ activeTab === 'eo' ? item.parent_e_o?.eo_number : item.parent_ordinance?.ordinance_number }}
                                            </p>
                                        </div>
                                    </div>
                                    <a v-if="activeTab === 'eo' ? item.parent_e_o?.file_url : item.parent_ordinance?.file_url" 
                                       :href="activeTab === 'eo' ? item.parent_e_o?.file_url : item.parent_ordinance?.file_url" 
                                       target="_blank"
                                       class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-blue-200 rounded text-xs font-bold text-blue-600 hover:bg-blue-600 hover:text-white transition-colors"
                                    >
                                        <Download class="w-3 h-3" /> View Parent
                                    </a>
                                </div>

                                <div v-if="item.amendments && item.amendments.length > 0" class="mt-2 space-y-2">
                                    <div v-for="child in item.amendments" :key="child.id" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-red-700 bg-red-50 p-2.5 rounded border border-red-100">
                                        <div class="flex items-center gap-2">
                                            <AlertCircle class="w-3 h-3 shrink-0" />
                                            <span>Amended by <strong>{{ activeTab === 'eo' ? child.eo_number : child.ordinance_number }}</strong></span>
                                        </div>
                                        <a v-if="child.file_url" 
                                           :href="child.file_url" 
                                           target="_blank"
                                           class="shrink-0 inline-flex items-center gap-1 px-2 py-1 bg-white border border-red-200 rounded text-[10px] font-bold text-red-600 hover:bg-red-600 hover:text-white transition-colors"
                                        >
                                            <Download class="w-3 h-3" /> Download {{ activeTab === 'eo' ? child.eo_number : child.ordinance_number }}
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-500 mt-2">
                                <div v-if="activeTab === 'eo'" class="flex items-center gap-1.5">
                                    <Building2 class="w-4 h-4" />
                                    {{ getLeadOffice(item.departments) }}
                                </div>
                                <div v-else class="flex items-center gap-1.5">
                                    <UserCheck class="w-4 h-4" />
                                    {{ getSponsors(item.departments) }}
                                </div>
                                
                                <div v-if="item.implementing_rules?.length > 0" class="flex items-center gap-1.5 text-blue-600 font-medium">
                                    <Paperclip class="w-4 h-4" />
                                    {{ item.implementing_rules.length }} IRR Attached
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-4 mt-5">
                                <button @click="toggleHistory(item.id)" class="text-xs font-bold text-gray-400 hover:text-blue-600 flex items-center gap-1 transition-colors">
                                    <component :is="expandedId === item.id ? ChevronUp : ChevronDown" class="w-3 h-3" />
                                    {{ expandedId === item.id ? 'Hide History' : 'View History & Timeline' }}
                                </button>
                                
                                <button 
                                    v-if="activeTab === 'eo' && item.committee_details && item.committee_details.type !== 'none'" 
                                    @click="openCommitteeModal(item)" 
                                    class="text-xs font-bold text-blue-500 hover:text-blue-700 flex items-center gap-1 transition-colors"
                                >
                                    <Users class="w-3.5 h-3.5" /> 
                                    View {{ item.committee_details.type === 'council' ? 'Committee Members' : 'Program Details' }}
                                </button>
                            </div>

                            <div v-show="expandedId === item.id" class="mt-4">
                                <TransparencyTimeline :timeline="item.public_timeline" />
                            </div>

                        </div>

                        <div class="mt-4 md:mt-0 md:self-start">
                            <a :href="item.file_url" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-50 border border-gray-200 text-gray-700 font-medium text-sm hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all shadow-sm">
                                <Download class="w-4 h-4" />
                                <span class="hidden md:inline">PDF</span>
                            </a>
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
                &copy; {{ new Date().getFullYear() }} City Government Records Management System.
            </div>
        </footer>

        <Transition name="fade">
            <div v-if="showCommitteeModal && selectedCommittee" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
                <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
                    
                    <div class="bg-blue-600 px-6 py-4 flex items-center justify-between shrink-0">
                        <div>
                            <h3 class="text-white font-bold text-lg flex items-center gap-2">
                                <Users class="w-5 h-5 text-blue-200" />
                                {{ selectedCommittee.committee_details.type === 'council' ? 'Council & Committee Structure' : 'Program Implementation Details' }}
                            </h3>
                            <p class="text-blue-200 text-xs mt-0.5">{{ selectedCommittee.eo_number }}</p>
                        </div>
                        <button @click="showCommitteeModal = false" class="text-blue-200 hover:text-white bg-blue-700/50 hover:bg-blue-700 rounded-full p-1.5 transition">
                            <XCircle class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto bg-gray-50/50">
                        
                        <div v-if="selectedCommittee.committee_details.type === 'council'" class="space-y-6">
                            
                            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Leadership</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div v-if="selectedCommittee.committee_details.council.chairman">
                                        <p class="text-xs text-gray-500 font-medium">Chairman</p>
                                        <p class="text-sm font-bold text-gray-900">{{ selectedCommittee.committee_details.council.chairman }}</p>
                                    </div>
                                    <div v-if="selectedCommittee.committee_details.council.co_chairmans">
                                        <p class="text-xs text-gray-500 font-medium">Co-Chairman</p>
                                        <p class="text-sm font-bold text-gray-900 whitespace-pre-wrap">{{ selectedCommittee.committee_details.council.co_chairmans }}</p>
                                    </div>
                                    <div v-if="selectedCommittee.committee_details.council.vice_chairman">
                                        <p class="text-xs text-gray-500 font-medium">Vice Chairman</p>
                                        <p class="text-sm font-bold text-gray-900">{{ selectedCommittee.committee_details.council.vice_chairman }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm" v-if="selectedCommittee.committee_details.council.internal_members">
                                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Internal Members</h4>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ selectedCommittee.committee_details.council.internal_members }}</p>
                                </div>
                                <div class="space-y-4">
                                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm" v-if="selectedCommittee.committee_details.council.external_members">
                                        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">External Members</h4>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ selectedCommittee.committee_details.council.external_members }}</p>
                                    </div>
                                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm" v-if="selectedCommittee.committee_details.council.secretariat">
                                        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Secretariat</h4>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ selectedCommittee.committee_details.council.secretariat }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="selectedCommittee.committee_details.council.twg_head || selectedCommittee.committee_details.council.twg_internal_members" class="bg-blue-50/50 border border-blue-100 rounded-xl p-5 shadow-sm">
                                <h4 class="text-xs font-bold text-blue-800 uppercase tracking-widest mb-4 border-b border-blue-100 pb-2">Technical Working Group (TWG)</h4>
                                
                                <div class="mb-4" v-if="selectedCommittee.committee_details.council.twg_head">
                                    <p class="text-xs text-blue-600 font-medium">TWG Head</p>
                                    <p class="text-sm font-bold text-blue-900">{{ selectedCommittee.committee_details.council.twg_head }}</p>
                                </div>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div v-if="selectedCommittee.committee_details.council.twg_internal_members">
                                        <p class="text-xs text-blue-600 font-medium mb-1">TWG Internal Members</p>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ selectedCommittee.committee_details.council.twg_internal_members }}</p>
                                    </div>
                                    <div v-if="selectedCommittee.committee_details.council.twg_external_members">
                                        <p class="text-xs text-blue-600 font-medium mb-1">TWG External Members</p>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ selectedCommittee.committee_details.council.twg_external_members }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedCommittee.committee_details.type === 'program'" class="space-y-6">
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-blue-50 border border-blue-100 rounded-xl p-5 shadow-sm">
                                    <h4 class="text-[10px] font-bold text-blue-400 uppercase tracking-widest mb-2">Lead Implementing Office</h4>
                                    <p class="text-base font-bold text-blue-900">{{ getDeptName(selectedCommittee.committee_details.program.lead_office_id) }}</p>
                                </div>
                                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Co-Lead Office</h4>
                                    <p class="text-sm font-bold text-gray-800">{{ getDeptName(selectedCommittee.committee_details.program.co_lead_office_id) }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm" v-if="selectedCommittee.committee_details.program.internal_members">
                                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">Internal Team Members</h4>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ selectedCommittee.committee_details.program.internal_members }}</p>
                                </div>
                                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm" v-if="selectedCommittee.committee_details.program.external_members">
                                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 border-b pb-2">External Partners / NGOs</h4>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ selectedCommittee.committee_details.program.external_members }}</p>
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