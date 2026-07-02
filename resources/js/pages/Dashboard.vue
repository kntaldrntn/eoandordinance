<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    FileText, Gavel, BookOpen, TrendingUp, PieChart, BarChart3, Award, Filter, ArrowUpRight, Building2, Users
} from 'lucide-vue-next';
import VueApexCharts from 'vue3-apexcharts';
import { computed, ref, watch } from 'vue';
import { debounce } from 'lodash';

const props = defineProps<{
    stats: any;
    eo_analytics: { trend: number[]; trend_labels: string[]; class_labels: string[]; class_data: number[]; status_labels: string[]; status_data: number[] };
    ord_analytics: { trend: number[]; trend_labels: string[]; status_labels: string[]; status_data: number[] };
    recent_eos: Array<any>;
    recent_ords: Array<any>;
    top_departments: Array<any>;
    top_committees: Array<any>;
    filters: any;
    available_years: number[];
    available_classifications: Array<{ id: number; name: string }>;
    available_statuses: Array<{ id: number; name: string }>;
}>();

// --- EO Filters ---
const eoYear = ref(props.filters.eo_year || new Date().getFullYear());
const eoClass = ref(props.filters.eo_class || 'all');
const eoStatus = ref(props.filters.eo_status || 'all');
const eoActive = ref(props.filters.eo_active || 'all');
const eoTrendTime = ref(props.filters.eo_trend_time || 'monthly');

// --- Ordinance Filters ---
const ordYear = ref(props.filters.ord_year || new Date().getFullYear());
const ordStatus = ref(props.filters.ord_status || 'all');
const ordIrr = ref(props.filters.ord_irr || 'all');
const ordActive = ref(props.filters.ord_active || 'all');
const ordTrendTime = ref(props.filters.ord_trend_time || 'monthly');

// --- Reload Dashboard ---
const updateDashboard = debounce(() => {
    router.get('/dashboard', { 
        eo_year: eoYear.value, eo_class: eoClass.value, eo_status: eoStatus.value, eo_active: eoActive.value, eo_trend_time: eoTrendTime.value,
        ord_year: ordYear.value, ord_status: ordStatus.value, ord_irr: ordIrr.value, ord_active: ordActive.value, ord_trend_time: ordTrendTime.value
    }, { preserveState: true, preserveScroll: true });
}, 300);

watch([eoYear, eoClass, eoStatus, eoActive, ordYear, ordStatus, ordIrr, ordActive], updateDashboard);

// --- Helpers ---
const getStatusColor = (statusName: string) => {
    switch(statusName) {
        case 'Active': case 'In Effect': return 'bg-emerald-100 text-emerald-700';
        case 'Amended': return 'bg-blue-100 text-blue-700';
        case 'Repealed': return 'bg-red-100 text-red-700';
        case 'Superseded': return 'bg-blue-100 text-blue-700';
        case 'Suspended': return 'bg-amber-100 text-amber-700';
        default: return 'bg-gray-100 text-gray-700';
    }
};

// Brand colors
const brand = {
    blue: '#1DB2F5',
    orange: '#F78219',
    yellow: '#FEC41B',
    blueBg: '#e8f7fe',
    orangeBg: '#fff3e8',
    yellowBg: '#fffbe6',
};

const pieColors = [brand.blue, brand.orange, brand.yellow, '#0e8fc7', '#c4640f', '#c49a14'];

// --- Chart Configurations ---
const baseChartOptions = {
    chart: { fontFamily: 'inherit', toolbar: { show: false }, parentHeightOffset: 0 },
    dataLabels: { 
        enabled: true, 
        style: { fontSize: '10px', fontWeight: 'bold', colors: ['#1e293b'] },
        background: { enabled: true, foreColor: '#ffffff', padding: 4, borderRadius: 4, opacity: 0.9 }
    },
    grid: { show: true, borderColor: '#f1f5f9', strokeDashArray: 4, padding: { top: 0, right: 0, bottom: 0, left: 10 } }
};

const eoTrendOptions = computed(() => ({
    ...baseChartOptions,
    chart: { ...baseChartOptions.chart, type: 'area' },
    colors: [brand.blue], 
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0 } },
    xaxis: { categories: props.eo_analytics.trend_labels, labels: { style: { fontSize: '10px', colors: '#64748b' } }, axisBorder: {show: false}, axisTicks: {show: false} },
    yaxis: { tickAmount: 3, labels: { style: { fontSize: '10px', colors: '#64748b' }, formatter: (val: number) => Math.round(val) } }
}));

const ordTrendOptions = computed(() => ({
    ...eoTrendOptions.value,
    colors: [brand.blue], 
    xaxis: { categories: props.ord_analytics.trend_labels, labels: { style: { fontSize: '10px', colors: '#64748b' } }, axisBorder: {show: false}, axisTicks: {show: false} },
}));

const eoClassOptions = computed(() => ({
    ...baseChartOptions,
    chart: { ...baseChartOptions.chart, type: 'bar' },
    colors: [brand.blue],
    plotOptions: { bar: { horizontal: false, borderRadius: 4, columnWidth: '40%' } },
    xaxis: { categories: props.eo_analytics.class_labels, labels: { style: { fontSize: '10px', colors: '#64748b' } }, axisBorder: {show: false}, axisTicks: {show: false} },
    yaxis: { tickAmount: 3, labels: { style: { fontSize: '10px', colors: '#64748b' }, formatter: (val: number) => Math.round(val) } },
}));

const pieOptions = (labels: string[]) => ({
    chart: { type: 'donut', fontFamily: 'inherit', parentHeightOffset: 0 },
    labels: labels,
    colors: pieColors,
    plotOptions: { pie: { donut: { size: '75%', labels: { show: true, name: {show: false}, value: { fontSize: '24px', fontWeight: 'bold', offsetY: 8 } } } } },
    dataLabels: { 
        enabled: true,
        formatter: function (val: number, opts: any) {
            return opts.w.config.series[opts.seriesIndex];
        },
        style: { fontSize: '12px', fontWeight: 'bold' }
    },
    stroke: { width: 0 },
    legend: { show: true, position: 'right', fontSize: '10px', markers: { radius: 12 } }
});

const eoStatusOptions = computed(() => pieOptions(props.eo_analytics.status_labels));
const ordStatusOptions = computed(() => pieOptions(props.ord_analytics.status_labels));

const committeeLabels = computed(() => (props.top_committees || []).map((c: any) => c.name));
const committeeData = computed(() => (props.top_committees || []).map((c: any) => c.total_involved));

const committeeChartOptions = computed(() => ({
    ...baseChartOptions,
    chart: { ...baseChartOptions.chart, type: 'bar' },
    colors: [brand.orange],
    plotOptions: { 
        bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } 
    },
    dataLabels: { 
        enabled: true, 
        textAnchor: 'start', 
        style: { colors: ['#fff'], fontSize: '10px' }, 
        offsetX: 5,
        background: { enabled: false }
    },
    xaxis: { 
        categories: committeeLabels.value, 
        labels: { style: { fontSize: '10px', colors: '#64748b' } }, 
        axisBorder: {show: false}, 
        axisTicks: {show: false} 
    },
    yaxis: { 
        labels: { 
            style: { fontSize: '10px', colors: '#64748b', fontWeight: 'bold' }, 
            maxWidth: 120,
            formatter: (val: string) => val && val.length > 18 ? val.substring(0, 18) + '...' : val 
        } 
    }
}));

const breadcrumbs = [{ title: 'Dashboard', href: '/dashboard' }];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-6 lg:p-8 bg-[#f8fafc] min-h-screen font-sans text-gray-900 space-y-6">

            <!-- STATS ROW -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">       
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm relative overflow-hidden group">
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-sm font-medium text-gray-500">Total Executive Orders</p>
                        <Link href="/eo" class="p-1.5 rounded-full hover:bg-gray-50 transition-colors group/link" title="Go to Executive Orders">
                            <ArrowUpRight class="w-4 h-4 text-gray-400 group-hover/link:text-[#1DB2F5]" />
                        </Link>
                    </div>
                    <h3 class="text-4xl font-bold text-gray-900">{{ stats.total_eos }}</h3>
                    <p class="text-[10px] text-gray-400 mt-2 flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center" style="background:#e8f7fe">
                            <FileText class="w-2.5 h-2.5" style="color:#1DB2F5" />
                        </span>
                        Executive Orders
                    </p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm relative overflow-hidden group">
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-sm font-medium text-gray-500">Total Ordinances</p>
                        <Link href="/ordinances" class="p-1.5 rounded-full hover:bg-gray-50 transition-colors group/link" title="Go to Ordinances">
                            <ArrowUpRight class="w-4 h-4 text-gray-400 group-hover/link:text-[#F78219]" />
                        </Link>
                    </div>
                    <h3 class="text-4xl font-bold text-gray-900">{{ stats.total_ordinances }}</h3>
                    <p class="text-[10px] text-gray-400 mt-2 flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center" style="background:#fff3e8">
                            <Gavel class="w-2.5 h-2.5" style="color:#F78219" />
                        </span>
                        City Ordinances
                    </p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm relative overflow-hidden cursor-default">
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-sm font-medium text-gray-500">Ordinances with IRR's</p>
                    </div>
                    <h3 class="text-4xl font-bold text-gray-900">{{ stats.ords_with_irrs }}</h3>
                    <p class="text-[10px] text-gray-400 mt-2 flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center" style="background:#fffbe6">
                            <BookOpen class="w-2.5 h-2.5" style="color:#FEC41B" />
                        </span>
                        With Attached Rules and Regulations
                    </p>
                </div>
            </div>

            <!-- EXECUTIVE ORDERS BENTO -->
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <FileText class="w-5 h-5" style="color:#1DB2F5" /> Executive Orders
                    </h2>
                    <div class="flex items-center gap-2 bg-white rounded-xl border border-gray-200 p-1 shadow-sm overflow-x-auto custom-scrollbar">
                        <select v-model="eoYear" class="text-xs border-0 bg-transparent py-1 pl-2 pr-6 focus:ring-0 font-medium text-gray-600 cursor-pointer">
                            <option value="all">All Years</option>
                            <option v-for="y in available_years" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <div class="w-px h-3 bg-gray-200"></div>
                        <select v-model="eoClass" class="text-xs border-0 bg-transparent py-1 pl-2 pr-6 focus:ring-0 font-medium text-gray-600 cursor-pointer">
                            <option value="all">Classifications</option>
                            <option v-for="c in available_classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <div class="w-px h-3 bg-gray-200"></div>
                        <select v-model="eoActive" class="text-xs border-0 bg-transparent py-1 pl-2 pr-6 focus:ring-0 font-medium text-gray-600 cursor-pointer">
                            <option value="all">Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- EO Trend -->
                    <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-bold text-gray-800">Issued Executive Orders</h3>
                            <select v-model="eoTrendTime" @change="updateDashboard" class="text-[10px] uppercase font-bold border-0 rounded px-2 py-1 cursor-pointer focus:ring-0 outline-none tracking-wider" style="color:#1DB2F5; background:#e8f7fe">
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                        <div class="flex-1 -ml-2 -mb-2">
                            <VueApexCharts :key="'eotrend'+eo_analytics.trend.join()" type="area" height="180" :options="eoTrendOptions" :series="[{name: 'Issued', data: eo_analytics.trend}]" />
                        </div>
                    </div>

                    <!-- EO Status Donut -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col">
                        <h3 class="text-sm font-bold text-gray-800 mb-2">Status Overview</h3>
                        <div class="flex-1 flex items-center justify-center" v-if="eo_analytics.status_data.length > 0">
                            <VueApexCharts :key="'eostat'+eo_analytics.status_data.length" type="donut" height="160" width="100%" :options="eoStatusOptions" :series="eo_analytics.status_data" />
                        </div>
                        <div v-else class="flex-1 flex items-center justify-center text-xs text-gray-400">No data</div>
                    </div>

                    <!-- EO Classifications -->
                    <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col">
                        <h3 class="text-sm font-bold text-gray-800 mb-4">Classifications</h3>
                        <div class="flex-1 -ml-2 -mb-2" v-if="eo_analytics.class_data.length > 0">
                            <VueApexCharts :key="'eoclass'+eo_analytics.class_data.length" type="bar" height="180" :options="eoClassOptions" :series="[{name: 'Count', data: eo_analytics.class_data}]" />
                        </div>
                        <div v-else class="flex-1 flex items-center justify-center text-xs text-gray-400">No data</div>
                    </div>

                    <!-- Recent EOs -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2">
                            <h3 class="text-sm font-bold text-gray-800">Recent Executive Orders</h3>
                            <Link href="/eo" class="text-[10px] font-bold px-2 py-1 rounded hover:opacity-90 transition-colors" style="color:#1DB2F5; background:#e8f7fe">+ View All</Link>
                        </div>
                        <div class="flex-1 overflow-y-auto custom-scrollbar max-h-[160px] pr-2">
                            <div v-for="item in recent_eos" :key="item.id" class="flex items-center gap-3 group py-3 border-b border-gray-100 last:border-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background:#e8f7fe">
                                    <FileText class="w-4 h-4" style="color:#1DB2F5" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-gray-900 truncate">{{ item.number }}</p>
                                    <p class="text-[10px] text-gray-500">{{ item.title }}</p>
                                </div>
                                <span :class="['px-1.5 py-0.5 rounded text-[9px] font-bold uppercase shrink-0 border', getStatusColor(item.status)]">{{ item.status }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VISUAL DIVIDER -->
            <div class="py-2">
                <hr class="border-t border-gray-300 border-dashed" />
            </div>

            <!-- ORDINANCES BENTO -->
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <Gavel class="w-5 h-5" style="color:#F78219" /> City Ordinances
                    </h2>
                    <div class="flex items-center gap-2 bg-white rounded-xl border border-gray-200 p-1 shadow-sm overflow-x-auto custom-scrollbar">
                        <select v-model="ordYear" class="text-xs border-0 bg-transparent py-1 pl-2 pr-6 focus:ring-0 font-medium text-gray-600 cursor-pointer">
                            <option value="all">All Years</option>
                            <option v-for="y in available_years" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <div class="w-px h-3 bg-gray-200"></div>
                        <select v-model="ordIrr" class="text-xs border-0 bg-transparent py-1 pl-2 pr-6 focus:ring-0 font-medium text-gray-600 cursor-pointer">
                            <option value="all">All Records</option>
                            <option value="with">With IRR</option>
                            <option value="without">Without IRR</option>
                        </select>
                        <div class="w-px h-3 bg-gray-200"></div>
                        <select v-model="ordActive" class="text-xs border-0 bg-transparent py-1 pl-2 pr-6 focus:ring-0 font-medium text-gray-600 cursor-pointer">
                            <option value="all">Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Ord Trend -->
                    <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-bold text-gray-800">Enacted City Ordinances</h3>
                            <select v-model="ordTrendTime" @change="updateDashboard" class="text-[10px] uppercase font-bold border-0 rounded px-2 py-1 cursor-pointer focus:ring-0 outline-none tracking-wider" style="color:#F78219; background:#fff3e8">
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                        <div class="flex-1 -ml-2 -mb-2">
                            <VueApexCharts :key="'ordtrend'+ord_analytics.trend.join()" type="area" height="180" :options="ordTrendOptions" :series="[{name: 'Enacted', data: ord_analytics.trend}]" />
                        </div>
                    </div>

                    <!-- Ord Status Donut -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col">
                        <h3 class="text-sm font-bold text-gray-800 mb-2">Status Overview</h3>
                        <div class="flex-1 flex items-center justify-center" v-if="ord_analytics.status_data.length > 0">
                            <VueApexCharts :key="'ordstat'+ord_analytics.status_data.length" type="donut" height="160" width="100%" :options="ordStatusOptions" :series="ord_analytics.status_data" />
                        </div>
                        <div v-else class="flex-1 flex items-center justify-center text-xs text-gray-400">No data</div>
                    </div>

                    <!-- Top Sponsorship Committees -->
                    <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col">
                        <h3 class="text-sm font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2 flex items-center gap-2">
                            <Users class="w-4 h-4" style="color:#F78219" /> Top Sponsorship Committees
                        </h3>
                        
                        <div v-if="top_committees.length > 0" class="flex-1 grid grid-cols-1 md:grid-cols-5 gap-6">
                            <div class="md:col-span-3 -ml-4 -mb-2">
                                <VueApexCharts 
                                    :key="'commchart'+committeeData.join()" 
                                    type="bar" 
                                    height="180" 
                                    :options="committeeChartOptions" 
                                    :series="[{name: 'Ordinances', data: committeeData}]" 
                                />
                            </div>
                            
                            <div class="md:col-span-2 flex flex-col gap-3 overflow-y-auto custom-scrollbar max-h-[180px] pr-2 mt-2 md:mt-0">
                                <div v-for="(committee, index) in top_committees" :key="committee.id" class="flex items-center gap-3 group">
                                    <span 
                                        class="w-6 h-6 flex items-center justify-center font-bold text-xs rounded-full shrink-0"
                                        :style="index === 0 
                                            ? 'background:#fffbe6;color:#FEC41B' 
                                            : index === 1 
                                                ? 'background:#f1f5f9;color:#64748b' 
                                                : index === 2 
                                                    ? 'background:#fff3e8;color:#F78219' 
                                                    : 'background:#f1f5f9;color:#94a3b8'"
                                    >
                                        {{ index + 1 }}
                                    </span>
                                    <p class="text-xs font-bold text-gray-800 truncate flex-1 transition" :title="committee.name"
                                        @mouseover="($event.target as HTMLElement).style.color='#1DB2F5'"
                                        @mouseleave="($event.target as HTMLElement).style.color=''">
                                        {{ committee.name }}
                                    </p>
                                    <span class="text-xs font-black px-2 py-0.5 rounded border" style="color:#F78219; background:#fff3e8; border-color:#fcd9b0">
                                        {{ committee.total_involved }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="flex-1 flex items-center justify-center text-xs text-gray-400">
                            No sponsorship committee data found.
                        </div>
                    </div>

                    <!-- Recent Ords -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2">
                            <h3 class="text-sm font-bold text-gray-800">Recent Ordinances</h3>
                            <Link href="/ordinances" class="text-[10px] font-bold px-2 py-1 rounded hover:opacity-90 transition-colors" style="color:#F78219; background:#fff3e8">+ View All</Link>
                        </div>
                        <div class="flex-1 overflow-y-auto custom-scrollbar max-h-[160px] pr-2">
                            <div v-for="item in recent_ords" :key="item.id" class="flex items-center gap-3 group py-3 border-b border-gray-100 last:border-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background:#fff3e8">
                                    <Gavel class="w-4 h-4" style="color:#F78219" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-gray-900 truncate">{{ item.number }}</p>
                                    <p class="text-[10px] text-gray-500">{{ item.title }}</p>
                                </div>
                                <span :class="['px-1.5 py-0.5 rounded text-[9px] font-bold uppercase shrink-0 border', getStatusColor(item.status)]">{{ item.status }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VISUAL DIVIDER -->
            <div class="py-2">
                <hr class="border-t border-gray-300 border-dashed" />
            </div>

            <!-- TOP IMPLEMENTING DEPARTMENTS -->
            <div class="space-y-4">
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <Building2 class="w-5 h-5" style="color:#FEC41B" /> Implementing Departments Overview
                </h2>
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                    
                    <div v-if="top_departments && top_departments.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div v-for="(dept, index) in top_departments" :key="dept.id" class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:shadow-sm transition-all">
                            <span 
                                class="w-8 h-8 flex items-center justify-center font-bold text-sm rounded-full shrink-0 bg-white shadow-sm border border-gray-100"
                                :style="index === 0 
                                    ? 'color:#1DB2F5' 
                                    : index === 1 
                                        ? 'color:#F78219' 
                                        : index === 2 
                                            ? 'color:#FEC41B' 
                                            : 'color:#94a3b8'"
                            >
                                {{ index + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-gray-900 truncate">{{ dept.name }}</p>
                                <p class="text-[10px] text-gray-500 font-medium">{{ dept.total_involved }} Implementations</p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-8">
                        <p class="text-xs text-gray-400 font-medium">No implementing department data found.</p>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>