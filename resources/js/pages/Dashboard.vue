<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { 
    FileText, AlertCircle, ArrowUpRight, Building2, Activity, Gavel, Filter, BookOpen, TrendingUp, Award 
} from 'lucide-vue-next';
import VueApexCharts from 'vue3-apexcharts';
import { computed, ref, watch } from 'vue';
import { debounce } from 'lodash';

const props = defineProps<{
    stats: any;
    trend_chart: {
        labels: string[];
        data: number[];
        year: number;
    };
    recent_activity: Array<any>;
    top_departments: Array<any>;
    filters: any;
    available_years: number[];
    available_classifications: Array<{ id: number; name: string }>; // Updated Prop
}>();

// --- Filter State ---
const selectedYear = ref(props.filters.year);
const selectedActive = ref(props.filters.is_active);
const selectedClass = ref(props.filters.classification);
const trendTime = ref(props.filters.trend_time);
const trendType = ref(props.filters.trend_type);
const deptType = ref(props.filters.dept_type);

// --- Reload Dashboard ---
const updateDashboard = debounce(() => {
    router.get('/dashboard', { 
        year: selectedYear.value, 
        is_active: selectedActive.value,
        classification: selectedClass.value,
        trend_time: trendTime.value,
        trend_type: trendType.value,
        dept_type: deptType.value,
    }, { 
        preserveState: true, 
        preserveScroll: true,
        only: ['stats', 'trend_chart', 'recent_activity', 'top_departments', 'filters'] 
    });
}, 300);

watch([selectedYear, selectedActive, selectedClass, trendTime, trendType, deptType], () => {
    updateDashboard();
});

// --- Dynamic Chart Config ---
const chartOptions = computed(() => ({
    chart: { type: 'area', height: 350, fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false } },
    colors: ['#16a34a'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    xaxis: { 
        categories: props.trend_chart.labels,
        axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } 
    },
    yaxis: { show: true, tickAmount: 3, labels: { formatter: (val: number) => Math.round(val) } },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4, yaxis: { lines: { show: true } } },
    tooltip: { theme: 'light' }
}));

const chartSeries = computed(() => [{
    name: 'Total Issuances', 
    data: props.trend_chart.data
}]);

// Helper for Department Visuals
const getMaxDeptValue = () => {
    if (props.top_departments.length === 0) return 1;
    return Math.max(...props.top_departments.map((d: any) => d.total_involved));
};

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
                
                <div class="flex items-center gap-3 bg-white p-1.5 rounded-xl border shadow-sm flex-wrap">
                    <div class="px-3 flex items-center gap-2 text-sm font-medium text-gray-600 border-r pr-4">
                        <Filter class="w-4 h-4" /> Filters
                    </div>
                    
                    <select v-model="selectedClass" class="border-0 bg-transparent text-sm font-semibold text-gray-700 focus:ring-0 cursor-pointer py-1 pr-8">
                        <option value="all">All Classifications</option>
                        <!-- Updated to use c.id and c.name -->
                        <option v-for="c in available_classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>

                    <select v-model="selectedYear" class="border-0 border-l bg-transparent text-sm font-semibold text-gray-700 focus:ring-0 cursor-pointer py-1 pl-4 pr-8">
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
                        <p class="text-sm font-medium text-gray-500">Executive Orders</p> 
                        <div class="rounded-full bg-blue-50 p-2 text-blue-600"><FileText class="h-4 w-4" /></div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.total_eos }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Total Executive Orders</p>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">Ordinances</p> 
                        <div class="rounded-full bg-indigo-50 p-2 text-indigo-600"><Gavel class="h-4 w-4" /></div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.total_ordinances }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Total City Ordinances</p>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">Active Offices</p>
                        <div class="rounded-full bg-emerald-50 p-2 text-emerald-600"><Building2 class="h-4 w-4" /></div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.active_offices }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Offices in Legislation</p> </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">With IRR's</p>
                        <div class="rounded-full bg-purple-50 p-2 text-purple-600"><BookOpen class="h-4 w-4" /></div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.ords_with_irrs }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Ordinances with IRR's</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                
                <div class="rounded-xl border bg-white p-6 shadow-sm lg:col-span-2 flex flex-col">
                    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h3 class="font-bold text-gray-900 flex items-center gap-2"><TrendingUp class="w-4 h-4 text-blue-500" /> Issuance Trend</h3>
                            <p class="text-sm text-gray-500">Volume for {{ trend_chart.year }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select v-model="trendType" class="text-xs py-1.5 pl-3 pr-8 border-gray-200 rounded-lg font-medium text-gray-600">
                                <option value="all">Both Types</option>
                                <option value="eo">EOs Only</option>
                                <option value="ord">Ords Only</option>
                            </select>
                            <select v-model="trendTime" class="text-xs py-1.5 pl-3 pr-8 border-gray-200 rounded-lg font-medium text-gray-600">
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex-1 min-h-[300px] w-full">
                        <VueApexCharts type="area" height="100%" :options="chartOptions" :series="chartSeries" />
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm flex flex-col">
                    <div class="mb-4 flex items-center gap-2">
                        <Activity class="h-5 w-5 text-gray-400" />
                        <h3 class="font-bold text-gray-900">Recent Updates</h3>
                    </div>

                    <div class="space-y-4 flex-1">
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

            <div class="rounded-xl border bg-white p-6 shadow-sm">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 flex items-center gap-2"><Award class="w-4 h-4 text-blue-500" /> Most Active Offices</h3>
                        <p class="text-xs text-gray-500 mt-1">Departments ranked by involvement in active legislation.</p>
                    </div>
                    <select v-model="deptType" class="text-xs py-1.5 pl-3 pr-8 border-gray-200 rounded-lg font-medium text-gray-600 bg-gray-50">
                        <option value="all">Overall Activity</option>
                        <option value="eo">EO Activity Only</option>
                        <option value="ord">Ordinance Activity</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 max-h-[260px] overflow-y-auto pr-3 custom-scrollbar">
                    
                    <div v-for="(dept, index) in top_departments" :key="dept.id" class="group flex flex-col justify-center p-2 rounded-lg hover:bg-gray-50/50 transition-colors">
                        <div class="flex items-end justify-between mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black text-gray-300 w-4">{{ index + 1 }}.</span>
                                <span class="text-sm font-bold text-gray-800 truncate max-w-[220px]" :title="dept.name">{{ dept.name }}</span>
                            </div>
                            <span class="text-sm font-black text-gray-900">{{ dept.total_involved }}</span>
                        </div>
                        
                        <div class="w-full bg-gray-100 rounded-full h-3 flex overflow-hidden">
                            <div 
                                v-if="dept.lead_count > 0"
                                class="bg-blue-500 h-full transition-all duration-500"
                                :style="{ width: `${(dept.lead_count / getMaxDeptValue()) * 100}%` }"
                                :title="`Lead Office: ${dept.lead_count}`"
                            ></div>
                            <div 
                                v-if="dept.support_count > 0"
                                class="bg-sky-300 h-full transition-all duration-500 border-l border-white/50"
                                :style="{ width: `${(dept.support_count / getMaxDeptValue()) * 100}%` }"
                                :title="`Support / Sponsor: ${dept.support_count}`"
                            ></div>
                        </div>
                        
                        <div class="flex gap-4 mt-1 opacity-0 group-hover:opacity-100 transition-opacity pl-6">
                            <span class="text-[10px] font-bold text-blue-600 uppercase">Lead: {{ dept.lead_count }}</span>
                            <span class="text-[10px] font-bold text-sky-500 uppercase">Support: {{ dept.support_count }}</span>
                        </div>
                    </div>

                </div>

                <div v-if="top_departments.length === 0" class="text-center py-10 text-gray-400 text-sm">
                    No department data available for this filter.
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f8fafc; 
    border-radius: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1; 
    border-radius: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8; 
}
</style>