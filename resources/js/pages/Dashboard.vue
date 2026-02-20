<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { 
    FileText, AlertCircle, ArrowUpRight, Building2, Activity, Gavel, Filter 
} from 'lucide-vue-next';
import VueApexCharts from 'vue3-apexcharts';
import { computed, ref, watch } from 'vue';
import { debounce } from 'lodash';

const props = defineProps<{
    stats: {
        total_eos: number;
        total_ordinances: number; 
        issued_this_year: number; 
        pending_irrs: number;
        active_offices: number;
    };
    chart: {
        data: number[];
        year: number;
    };
    recent_activity: Array<{
        id: number;
        number: string;
        title: string;
        status: string;
        type: string;   
        date: string;
    }>;
    filters: {
        year: string | number;
        is_active: string;
    };
    available_years: number[];
}>();

// --- Filter State ---
const selectedYear = ref(props.filters.year);
const selectedActive = ref(props.filters.is_active);

// --- Reload Dashboard on Filter Change ---
const updateDashboard = debounce(() => {
    router.get('/dashboard', { 
        year: selectedYear.value, 
        is_active: selectedActive.value 
    }, { 
        preserveState: true, 
        preserveScroll: true,
        only: ['stats', 'chart', 'recent_activity', 'filters'] 
    });
}, 300);

watch([selectedYear, selectedActive], () => {
    updateDashboard();
});

// --- Chart Config ---
const chartOptions = computed(() => ({
    chart: { type: 'area', height: 350, fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false } },
    colors: ['#16a34a'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    xaxis: { categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
    yaxis: { show: true, tickAmount: 3, labels: { formatter: (val: number) => Math.round(val) } },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4, yaxis: { lines: { show: true } } },
    tooltip: { theme: 'light' }
}));

const chartSeries = computed(() => [{
    name: 'Total Issuances', 
    data: props.chart.data
}]);

const breadcrumbs = [{ title: 'Dashboard', href: '/dashboard' }];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-8 overflow-y-auto">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Overview</h2>
                    <p class="text-sm text-gray-500">Legislative performance and status report.</p>
                </div>
                
                <div class="flex items-center gap-3 bg-white p-1.5 rounded-xl border shadow-sm">
                    <div class="px-3 flex items-center gap-2 text-sm font-medium text-gray-600 border-r pr-4">
                        <Filter class="w-4 h-4" /> Filters
                    </div>
                    
                    <select v-model="selectedYear" class="border-0 bg-transparent text-sm font-semibold text-gray-700 focus:ring-0 cursor-pointer py-1 pr-8">
                        <option value="all">All Years</option>
                        <option v-for="y in available_years" :key="y" :value="y">{{ y }}</option>
                    </select>

                    <select v-model="selectedActive" class="border-0 border-l bg-transparent text-sm font-semibold text-gray-700 focus:ring-0 cursor-pointer py-1 pl-4 pr-8">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                
                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">Filtered E.O.s</p>
                        <div class="rounded-full bg-blue-50 p-2 text-blue-600">
                            <FileText class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.total_eos }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Executive Orders</p>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">Filtered Ordinances</p>
                        <div class="rounded-full bg-indigo-50 p-2 text-indigo-600">
                            <Gavel class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.total_ordinances }}</h3>
                        <p class="text-xs text-gray-500 mt-1">City Ordinances</p>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">Total Output</p>
                        <div class="rounded-full bg-green-50 p-2 text-green-600">
                            <ArrowUpRight class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.issued_this_year }}</h3>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ selectedYear === 'all' ? 'All time volume' : `Volume in ${selectedYear}` }}
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm border-l-4 border-l-amber-400">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-amber-700">Pending IRR</p>
                        <div class="rounded-full bg-amber-50 p-2 text-amber-600">
                            <AlertCircle class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.pending_irrs }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Drafting / Approval</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                
                <div class="rounded-xl border bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900">Issuance Trend</h3>
                            <p class="text-sm text-gray-500">Combined Volume for {{ chart.year }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-3 py-1 text-xs font-medium text-gray-600">
                            Monthly View
                        </div>
                    </div>
                    <div class="h-[300px] w-full">
                        <VueApexCharts type="area" height="100%" :options="chartOptions" :series="chartSeries" />
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <Activity class="h-5 w-5 text-gray-400" />
                        <h3 class="font-bold text-gray-900">Recent Updates</h3>
                    </div>

                    <div class="space-y-4">
                        <div v-for="item in recent_activity" :key="item.type + item.id" class="flex items-start gap-3 border-b border-gray-50 pb-3 last:border-0 last:pb-0">
                            <div class="mt-1 rounded-full p-1.5" :class="item.type === 'EO' ? 'bg-blue-50' : 'bg-indigo-50'">
                                <FileText v-if="item.type === 'EO'" class="h-3 w-3 text-blue-600" />
                                <Gavel v-else class="h-3 w-3 text-indigo-600" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-bold text-gray-900">{{ item.number }}</p>
                                    <span class="text-[10px] text-gray-400">{{ item.date }}</span>
                                </div>
                                <p class="truncate text-xs text-gray-600 mt-0.5" :title="item.title">
                                    {{ item.title }}
                                </p>
                                <div class="mt-1.5 flex items-center gap-1">
                                    <span class="inline-flex items-center rounded-sm px-1.5 py-0.5 text-[10px] font-bold" :class="item.type === 'EO' ? 'bg-blue-100 text-blue-700' : 'bg-indigo-100 text-indigo-700'">
                                        {{ item.type }}
                                    </span>
                                    <span class="inline-flex items-center rounded-sm px-1.5 py-0.5 text-[10px] font-medium bg-green-50 text-green-700">
                                        {{ item.status }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="recent_activity.length === 0" class="py-8 text-center">
                            <p class="text-sm text-gray-400">No recent activity found.</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 border-t pt-4 text-center">
                        <a href="/ordinances" class="text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline">
                            View All Records →
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>