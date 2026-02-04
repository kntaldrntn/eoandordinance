<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    Search, FileText, Calendar, Download, Building2, Paperclip, 
    AlertCircle, Link as LinkIcon, Clock, UserCheck, Gavel 
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { debounce } from 'lodash'; 

const props = defineProps<{
    records: {
        data: Array<any>; // Using 'any' for flexibility since fields differ slightly
        links: Array<any>;
    };
    filters: { search?: string; year?: string; type?: string };
    years: number[];
    activeType: string;
}>();

const search = ref(props.filters.search || '');
const year = ref(props.filters.year || '');
const activeTab = ref(props.activeType || 'eo');

// --- SEARCH & FILTER LOGIC ---
const updateParams = debounce(() => {
    router.get('/', { 
        search: search.value, 
        year: year.value,
        type: activeTab.value // Keep the tab state
    }, { 
        preserveState: true, 
        preserveScroll: true 
    });
}, 300);

const switchTab = (type: string) => {
    activeTab.value = type;
    search.value = ''; // Optional: clear search on tab switch
    year.value = '';
    updateParams();
};

// --- HELPERS ---
const formatDate = (date: string) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};

// Helper for EO Lead Office
const getLeadOffice = (depts: any[]) => {
    if (!depts) return '';
    const lead = depts.find(d => d.pivot.role === 'lead');
    return lead ? lead.name : 'City Mayor\'s Office';
};

// Helper for Ordinance Sponsors
const getSponsors = (depts: any[]) => {
    if (!depts) return '';
    const sponsors = depts.filter(d => d.pivot.role === 'sponsor');
    if (sponsors.length === 0) return 'City Council';
    if (sponsors.length === 1) return sponsors[0].name;
    return `${sponsors[0].name} +${sponsors.length - 1}`;
};

const getStatusColor = (statusName: string) => {
    switch(statusName) {
        case 'Active': return 'bg-green-100 text-green-700 border-green-200';
        case 'Amended': return 'bg-orange-100 text-orange-700 border-orange-200';
        case 'Repealed': return 'bg-red-100 text-red-700 border-red-200';
        default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
};
</script>

<template>
    <Head title="Public Records" />

    <div class="min-h-screen bg-gray-50 font-sans text-gray-900">
        
        <header class="bg-white shadow-sm border-b sticky top-0 z-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-indigo-600 p-2 rounded-lg shadow-sm">
                        <Gavel class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 leading-none">City Legislation</h1>
                        <p class="text-xs text-gray-500 font-medium tracking-wide mt-0.5">PUBLIC RECORDS PORTAL</p>
                    </div>
                </div>
                <Link href="/login" class="text-sm font-medium text-gray-500 hover:text-indigo-600 transition-colors">
                    Staff Login &rarr;
                </Link>
            </div>
        </header>

        <div class="bg-white border-b border-gray-200 pt-10 pb-0">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="max-w-3xl mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Find Laws & Issuances</h2>
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input 
                                v-model="search"
                                @input="updateParams"
                                type="text" 
                                placeholder="Search Number, Title, or Keywords..." 
                                class="w-full pl-12 pr-4 py-4 rounded-xl border border-gray-300 bg-white shadow-sm focus:ring-2 focus:ring-indigo-500 text-base"
                            />
                        </div>
                        <div class="w-full md:w-48">
                            <select 
                                v-model="year" 
                                @change="updateParams"
                                class="w-full h-full px-4 py-4 rounded-xl border border-gray-300 bg-white shadow-sm focus:ring-2 focus:ring-indigo-500 text-base cursor-pointer"
                            >
                                <option value="">All Years</option>
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex gap-8">
                    <button 
                        @click="switchTab('eo')"
                        class="pb-4 px-2 text-sm font-bold uppercase tracking-wide border-b-2 transition-all"
                        :class="activeTab === 'eo' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    >
                        Executive Orders
                    </button>
                    <button 
                        @click="switchTab('ordinance')"
                        class="pb-4 px-2 text-sm font-bold uppercase tracking-wide border-b-2 transition-all"
                        :class="activeTab === 'ordinance' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    >
                        City Ordinances
                    </button>
                </div>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            
            <div v-if="records.data.length > 0" class="grid gap-6">
                <div v-for="item in records.data" :key="item.id" class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all p-6 relative overflow-hidden">
                    
                    <div class="absolute left-0 top-0 bottom-0 w-1.5" 
                        :class="{
                            'bg-green-500': item.status.name === 'Active',
                            'bg-orange-400': item.status.name === 'Amended',
                            'bg-red-500': item.status.name === 'Repealed',
                            'bg-gray-300': !['Active','Amended','Repealed'].includes(item.status.name)
                        }">
                    </div>

                    <div class="flex flex-col md:flex-row md:items-start gap-6 pl-2">
                        
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <span class="bg-gray-100 text-gray-700 px-2.5 py-1 rounded-md text-xs font-bold font-mono border border-gray-200">
                                    {{ activeTab === 'eo' ? item.eo_number : item.ordinance_number }}
                                </span>
                                
                                <span :class="['px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wide border', getStatusColor(item.status.name)]">
                                    {{ item.status.name }}
                                </span>

                                <span class="text-xs text-gray-400 flex items-center gap-1">
                                    <Calendar class="w-3.5 h-3.5" /> 
                                    {{ activeTab === 'eo' ? formatDate(item.date_issued) : formatDate(item.date_enacted) }}
                                </span>

                                <span v-if="item.effectivity_date" class="text-xs text-gray-500 flex items-center gap-1 bg-gray-50 px-2 py-0.5 rounded border border-gray-100">
                                    <Clock class="w-3.5 h-3.5 text-gray-400" /> 
                                    <span>Eff: {{ formatDate(item.effectivity_date) }}</span>
                                </span>
                            </div>

                            <h3 class="text-lg font-bold text-gray-900 group-hover:text-indigo-600 transition-colors mb-3 leading-snug">
                                {{ item.title }}
                            </h3>

                            <div class="space-y-2 mb-4">
                                <div v-if="item.parent_e_o || item.parent_ordinance" class="text-sm flex items-start gap-2 bg-indigo-50 p-2.5 rounded-lg border border-indigo-100 text-indigo-800">
                                    <LinkIcon class="w-4 h-4 mt-0.5 shrink-0 text-indigo-500" />
                                    <div class="flex-1">
                                        <p class="font-semibold text-xs uppercase tracking-wide text-indigo-600 mb-0.5">Relationship</p>
                                        <p>
                                            <span class="font-bold">{{ item.relationship_type || 'Amends' }}:</span> 
                                            {{ activeTab === 'eo' ? item.parent_e_o?.eo_number : item.parent_ordinance?.ordinance_number }}
                                        </p>
                                        <p v-if="item.remarks" class="mt-1 text-xs text-indigo-700 italic border-l-2 border-indigo-200 pl-2">"{{ item.remarks }}"</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-500 mt-2">
                                <div v-if="activeTab === 'eo'" class="flex items-center gap-1.5">
                                    <Building2 class="w-4 h-4" />
                                    {{ getLeadOffice(item.departments) }}
                                </div>
                                <div v-else class="flex items-center gap-1.5" title="Sponsors">
                                    <UserCheck class="w-4 h-4" />
                                    {{ getSponsors(item.departments) }}
                                </div>

                                <div v-if="item.implementing_rules && item.implementing_rules.length > 0" class="flex items-center gap-1.5 text-indigo-600 font-medium">
                                    <Paperclip class="w-4 h-4" />
                                    {{ item.implementing_rules.length }} IRR Attached
                                </div>
                            </div>

                            <div v-if="item.implementing_rules && item.implementing_rules.length > 0" class="mt-3 pl-4 border-l-2 border-gray-100">
                                <a v-for="irr in item.implementing_rules" :key="irr.id" :href="irr.file_url" target="_blank" class="block text-xs text-gray-500 hover:text-indigo-600 hover:underline py-0.5">
                                    Download IRR: {{ irr.status }}
                                </a>
                            </div>

                        </div>

                        <div class="mt-4 md:mt-0 md:self-start">
                            <a 
                                :href="item.file_url" 
                                target="_blank"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-50 border border-gray-200 text-gray-700 font-medium text-sm hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all shadow-sm"
                            >
                                <Download class="w-4 h-4" />
                                <span class="hidden md:inline">PDF</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-20 bg-white rounded-xl border border-dashed border-gray-300">
                <div class="bg-gray-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Search class="w-8 h-8 text-gray-400" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900">No records found</h3>
                <p class="text-gray-500 mt-1">Try adjusting your search terms or filters.</p>
            </div>

            <div v-if="records.links.length > 3" class="mt-8 flex justify-center gap-1">
                <Link 
                    v-for="(link, i) in records.links" 
                    :key="i" 
                    :href="link.url || '#'" 
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="[
                        link.active ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200',
                        !link.url && 'opacity-50 cursor-not-allowed'
                    ]"
                    v-html="link.label"
                />
            </div>
        </main>

        <footer class="bg-white border-t border-gray-200 mt-auto py-8">
            <div class="max-w-7xl mx-auto px-4 text-center text-sm text-gray-500">
                &copy; {{ new Date().getFullYear() }} City Government Records Management System. All rights reserved.
            </div>
        </footer>

    </div>
</template>