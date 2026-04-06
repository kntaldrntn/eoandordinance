<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { 
    Printer, Search, FileText, Calendar, Filter, FileOutput, FileDown
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

// --- Props ---
// We expect a unified array of records from the backend for the preview
const props = defineProps<{
    records: {
        data: Array<any>;
        total: number;
    };
    statuses: Array<{ id: number; name: string }>;
    filters: {
        type: string;
        status_id: string;
        date_from: string;
        date_to: string;
        search: string;
    };
}>();

// --- Filter State ---
const filterForm = ref({
    type: props.filters.type || 'all', // 'all', 'eo', 'ordinance'
    status_id: props.filters.status_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    search: props.filters.search || '',
});

const isLoading = ref(false);
let searchTimeout: ReturnType<typeof setTimeout>;

// --- Live Preview Reloading ---
const applyFilters = () => {
    isLoading.value = true;
    router.get(route('reports.index'), filterForm.value, { 
        preserveState: true, 
        preserveScroll: true,
        onFinish: () => isLoading.value = false 
    });
};

// Debounce text search
watch(() => filterForm.value.search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});

const clearFilters = () => {
    filterForm.value = { type: 'all', status_id: '', date_from: '', date_to: '', search: '' };
    applyFilters();
};

// --- PDF Generation ---
const generatePDF = () => {
    // We open the PDF route in a new tab, passing all the current filters in the URL!
    const queryParams = new URLSearchParams(filterForm.value as any).toString();
    window.open(route('reports.generate') + '?' + queryParams, '_blank');
};

const formatDate = (dateString: string) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Report Generator" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-white p-4 md:p-8">
            
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-2">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <Printer class="w-6 h-6 text-blue-600" /> Dynamic Reporting
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Filter and export official records to PDF.</p>
                </div>
                
                <button @click="generatePDF" class="flex items-center gap-2 rounded-lg bg-gray-600 px-5 py-2.5 font-bold text-white shadow-sm hover:bg-gray-700 transition">
                    <FileDown class="h-4 w-4" /> Export PDF Report
                </button>
            </div>

            <div class="bg-gray-50 p-5 rounded-xl border border-gray-200 shadow-inner">
                <div class="flex items-center gap-2 text-sm font-bold text-gray-700 uppercase tracking-wider mb-4">
                    <Filter class="w-4 h-4 text-blue-600" /> Report Parameters
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Document Type</label>
                        <select v-model="filterForm.type" @change="applyFilters" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all">Combined (EOs & Ordinances)</option>
                            <option value="eo">Executive Orders Only</option>
                            <option value="ordinance">Ordinances Only</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Legal Status</label>
                        <select v-model="filterForm.status_id" @change="applyFilters" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Date From</label>
                        <input v-model="filterForm.date_from" @change="applyFilters" type="date" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Date To</label>
                        <input v-model="filterForm.date_to" @change="applyFilters" type="date" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Keyword Search</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                            <input v-model="filterForm.search" type="text" placeholder="Title, number..." class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-300 outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button @click="clearFilters" class="text-xs font-bold text-gray-500 hover:text-red-600 transition underline">Clear Filters</button>
                </div>
            </div>

            <div class="mt-2 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-700">Data Preview</h3>
                <span class="text-xs font-medium text-gray-500 bg-gray-100 px-3 py-1 rounded-full">{{ props.records.total }} Records Match</span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm flex-1">
                <div class="overflow-x-auto h-full max-h-[500px]">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase border-b border-gray-200 sticky top-0 z-10">
                            <tr>
                                <th class="px-6 py-3 font-semibold">Type</th>
                                <th class="px-6 py-3 font-semibold">Tracking No.</th>
                                <th class="px-6 py-3 font-semibold w-1/3">Title</th>
                                <th class="px-6 py-3 font-semibold">Date</th>
                                <th class="px-6 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-if="isLoading">
                                <tr v-for="n in 5" :key="n" class="animate-pulse">
                                    <td colspan="5" class="px-6 py-4"><div class="h-4 bg-gray-100 rounded w-full"></div></td>
                                </tr>
                            </template>
                            
                            <template v-else-if="records.data.length > 0">
                                <tr v-for="(record, index) in records.data" :key="index" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-md" :class="record.doc_type === 'EO' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700'">
                                            <FileText class="w-3 h-3" /> {{ record.doc_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 font-mono font-bold text-gray-900">{{ record.tracking_number }}</td>
                                    <td class="px-6 py-3 text-xs leading-tight font-medium">{{ record.title }}</td>
                                    <td class="px-6 py-3 text-xs text-gray-500">{{ formatDate(record.date) }}</td>
                                    <td class="px-6 py-3">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-600 bg-gray-100 px-2 py-1 rounded border border-gray-200">
                                            {{ record.status_name }}
                                        </span>
                                    </td>
                                </tr>
                            </template>
                            
                            <tr v-else>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <FileOutput class="w-8 h-8 text-gray-300 mx-auto mb-2" />
                                    <p class="text-sm font-medium">No records match your filters.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>