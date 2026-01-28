<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { 
    FileText, 
    AlertCircle, 
    ArrowUpRight, 
    Building2, 
    Activity, 
    Download 
} from 'lucide-vue-next';
import VueApexCharts from 'vue3-apexcharts';
import { computed } from 'vue';

// 1. Props (Data coming from DashboardController)
const props = defineProps<{
    stats: {
        total_eos: number;
        eos_this_year: number;
        pending_irrs: number;
        active_offices: number;
    };
    chart: {
        data: number[];
        year: number;
    };
    recent_activity: Array<{
        id: number;
        eo_number: string;
        title: string;
        status: string;
        date: string;
    }>;
}>();

// 2. Chart Configuration (The Green Area Chart)
const chartOptions = computed(() => ({
    chart: {
        type: 'area',
        height: 350,
        fontFamily: 'inherit',
        toolbar: { show: false },
        zoom: { enabled: false }
    },
    colors: ['#16a34a'], // Tailwind Green-600
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.05,
            stops: [0, 90, 100]
        }
    },
    dataLabels: { enabled: false },
    stroke: {
        curve: 'smooth',
        width: 2
    },
    xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        axisBorder: { show: false },
        axisTicks: { show: false },
        tooltip: { enabled: false }
    },
    yaxis: {
        show: true,
        tickAmount: 3,
        labels: {
            formatter: (val: number) => Math.round(val) // No decimals
        }
    },
    grid: {
        borderColor: '#f1f5f9',
        strokeDashArray: 4,
        yaxis: { lines: { show: true } }
    },
    tooltip: {
        theme: 'light',
    }
}));

const chartSeries = computed(() => [{
    name: 'EOs Issued',
    data: props.chart.data // [2, 0, 0, ...]
}]);

// 3. Breadcrumbs
const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-8 overflow-y-auto">
            
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                
                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">Total Issuances</p>
                        <div class="rounded-full bg-blue-50 p-2 text-blue-600">
                            <FileText class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.total_eos }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Recorded in system</p>
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
                        <p class="text-xs text-gray-500 mt-1">Rules drafting / approval</p>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">Issued {{ chart.year }}</p>
                        <div class="rounded-full bg-green-50 p-2 text-green-600">
                            <ArrowUpRight class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.eos_this_year }}</h3>
                        <p class="text-xs text-gray-500 mt-1">New orders this year</p>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">Active Offices</p>
                        <div class="rounded-full bg-purple-50 p-2 text-purple-600">
                            <Building2 class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-2xl font-bold text-gray-900">{{ stats.active_offices }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Leading implementation</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                
                <div class="rounded-xl border bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900">Issuance Trend</h3>
                            <p class="text-sm text-gray-500">Monthly breakdown for {{ chart.year }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-3 py-1 text-xs font-medium text-gray-600">
                            Annual View
                        </div>
                    </div>
                    
                    <div class="h-[300px] w-full">
                        <VueApexCharts 
                            type="area" 
                            height="100%" 
                            :options="chartOptions" 
                            :series="chartSeries" 
                        />
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <Activity class="h-5 w-5 text-gray-400" />
                        <h3 class="font-bold text-gray-900">Recent Updates</h3>
                    </div>

                    <div class="space-y-4">
                        <div v-for="item in recent_activity" :key="item.id" class="flex items-start gap-3 border-b border-gray-50 pb-3 last:border-0 last:pb-0">
                            <div class="mt-1 rounded-full bg-blue-50 p-1.5">
                                <FileText class="h-3 w-3 text-blue-600" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-bold text-gray-900">{{ item.eo_number }}</p>
                                    <span class="text-[10px] text-gray-400">{{ item.date }}</span>
                                </div>
                                <p class="truncate text-xs text-gray-600 mt-0.5" :title="item.title">
                                    {{ item.title }}
                                </p>
                                <div class="mt-1.5 flex items-center gap-1">
                                    <span 
                                        class="inline-flex items-center rounded-sm px-1.5 py-0.5 text-[10px] font-medium bg-green-50 text-green-700"
                                    >
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
                        <a href="/eo" class="text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline">
                            View All Records →
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>