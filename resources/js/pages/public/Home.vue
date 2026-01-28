<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { 
    Search, 
    FileText, 
    Calendar, 
    Download, 
    Building2, 
    BookOpen, 
    Paperclip,
    AlertCircle
} from 'lucide-vue-next';
import { ref } from 'vue';
import { debounce } from 'lodash'; 

const props = defineProps<{
    eos: {
        data: Array<{
            id: number;
            eo_number: string;
            title: string;
            date_issued: string;
            file_url: string;
            status: { name: string };
            implementing_rules?: Array<{
                id: number;
                file_url: string;
                status: string;
                lead_office?: { name: string };
            }>;
            departments: Array<{ name: string; pivot: { role: string } }>;
            parent_e_o?: { eo_number: string };
        }>;
        links: Array<any>;
    };
    filters: { search?: string; year?: string };
    years: number[];
}>();

const search = ref(props.filters.search || '');
const year = ref(props.filters.year || '');

const handleSearch = debounce(() => {
    router.get('/', { search: search.value, year: year.value }, { preserveState: true, preserveScroll: true });
}, 300);

const handleYearChange = () => {
    router.get('/', { search: search.value, year: year.value }, { preserveState: true, preserveScroll: true });
};

// Formatting Helper
const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};

const getLeadOffice = (depts: any[]) => {
    const lead = depts.find(d => d.pivot.role === 'lead');
    return lead ? lead.name : 'City Mayor\'s Office';
};
</script>

<template>
    <Head title="Public Records" />

    <div class="min-h-screen bg-gray-50 font-sans text-gray-900">
        
        <header class="bg-white shadow-sm border-b sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-blue-600 p-2 rounded-lg">
                        <FileText class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 leading-none">City Ordinances</h1>
                        <p class="text-xs text-gray-500 font-medium tracking-wide mt-0.5">PUBLIC RECORDS PORTAL</p>
                    </div>
                </div>

                <Link href="/login" class="text-sm font-medium text-gray-500 hover:text-blue-600 transition-colors">
                    Staff Login &rarr;
                </Link>
            </div>
        </header>

        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                <div class="max-w-3xl">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Find Executive Orders & Issuances</h2>
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input 
                                v-model="search"
                                @input="handleSearch"
                                type="text" 
                                placeholder="Search by EO Number, Title, or Keywords..." 
                                class="w-full pl-12 pr-4 py-4 rounded-xl border border-gray-300 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-base"
                            />
                        </div>
                        
                        <div class="w-full md:w-48">
                            <select 
                                v-model="year" 
                                @change="handleYearChange"
                                class="w-full h-full px-4 py-4 rounded-xl border border-gray-300 bg-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-base cursor-pointer"
                            >
                                <option value="">All Years</option>
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            
            <div v-if="eos.data.length > 0" class="grid gap-6">
                <div v-for="eo in eos.data" :key="eo.id" class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all p-6">
                    <div class="flex flex-col md:flex-row md:items-start gap-6">
                        
                        <div class="hidden md:block">
                            <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <BookOpen class="w-6 h-6" />
                            </div>
                        </div>

                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="bg-gray-100 text-gray-700 px-2.5 py-1 rounded-md text-xs font-bold font-mono border border-gray-200">
                                    {{ eo.eo_number }}
                                </span>
                                <span v-if="eo.status.name === 'Active'" class="text-xs font-medium text-green-600 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                                </span>
                                <span v-else class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                                    {{ eo.status.name }}
                                </span>
                            </div>

                            <h3 class="text-lg font-bold text-gray-900 group-hover:text-blue-600 transition-colors mb-2">
                                {{ eo.title }}
                            </h3>

                            <div class="flex flex-wrap items-center gap-y-2 gap-x-6 text-sm text-gray-500">
                                <div class="flex items-center gap-1.5">
                                    <Calendar class="w-4 h-4" />
                                    {{ formatDate(eo.date_issued) }}
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <Building2 class="w-4 h-4" />
                                    {{ getLeadOffice(eo.departments) }}
                                </div>
                                <div v-if="eo.parent_e_o" class="text-amber-600 bg-amber-50 px-2 py-0.5 rounded text-xs font-medium border border-red-100 flex items-center gap-1">
                                    <Paperclip class="w-3 h-3" /> <span>Amends {{ eo.parent_e_o.eo_number }}</span>
                                </div>

                                <div v-if="eo.amendments && eo.amendments.length > 0" class="flex flex-col gap-1">
                                    <div v-for="child in eo.amendments" :key="child.id" class="text-red-600 bg-red-50 px-2 py-0.5 rounded text-xs font-medium border border-red-100 flex items-center gap-1">
                                        <AlertCircle class="w-3 h-3" /> <span>Amended by {{ child.eo_number }}</span>
                                    </div>
                                </div>
                            </div>

                            <div v-if="eo.implementing_rules && eo.implementing_rules.length > 0" class="mt-4 pt-3 border-t border-gray-100">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Rules and Regulations (IRR)</p>
                                <div class="space-y-2">
                                    <a 
                                        v-for="irr in eo.implementing_rules" 
                                        :key="irr.id" 
                                        :href="irr.file_url"
                                        target="_blank" 
                                        class="group/irr flex items-center gap-2 text-sm text-gray-600 hover:text-blue-700 transition-colors w-fit"
                                    >
                                        <div class="bg-gray-100 p-1 rounded group-hover/irr:bg-blue-50">
                                            <Paperclip class="w-3.5 h-3.5" />
                                        </div>
                                        <span class="font-medium underline decoration-gray-300 underline-offset-2 group-hover/irr:decoration-blue-300">
                                            Download IRR
                                        </span>
                                        <span class="text-xs text-gray-400 no-underline border-l pl-2 ml-1">
                                            {{ irr.status }} <span v-if="irr.lead_office">({{ irr.lead_office.name }})</span>
                                        </span>
                                    </a>
                                </div>
                            </div>

                        </div>

                        <div class="mt-4 md:mt-0 md:self-center">
                            <a 
                                :href="eo.file_url" 
                                target="_blank"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-white border border-gray-300 text-gray-700 font-medium text-sm hover:bg-gray-50 hover:text-blue-600 hover:border-blue-300 transition-all w-full md:w-auto justify-center"
                            >
                                <Download class="w-4 h-4" />
                                Download PDF
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
                <p class="text-gray-500 mt-1">Try adjusting your search terms or year filter.</p>
            </div>

            <div v-if="eos.links.length > 3" class="mt-8 flex justify-center gap-1">
                <Link 
                    v-for="(link, i) in eos.links" 
                    :key="i" 
                    :href="link.url || '#'" 
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="[
                        link.active ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200',
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