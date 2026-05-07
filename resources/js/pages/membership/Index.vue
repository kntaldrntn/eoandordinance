<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { FileText, Gavel, Users, ShieldAlert, Eye, Search, Filter, XCircle } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';

const props = defineProps<{
    memberships: Array<{
        id: string;
        number: string;
        title: string;
        type: string;
        role: string;
        status: string;
        is_active: boolean;
        date: string;
        url: string | null;
    }>;
    userName: string;
    filters: { type: string; year: string; is_active: string; classification: string };
    available_years: number[];
    available_classifications: Array<{ id: number; name: string }>; // Updated prop type
}>();

// Search & Filter State
const searchQuery = ref('');
const filterType = ref(props.filters.type); 
const filterYear = ref(props.filters.year);
const filterActive = ref(props.filters.is_active);
const filterClass = ref(props.filters.classification);

// Watch Dropdowns to trigger backend filter
watch([filterType, filterYear, filterActive, filterClass], () => {
    router.get('/membership', {
        type: filterType.value,
        year: filterYear.value,
        is_active: filterActive.value,
        classification: filterClass.value
    }, { 
        preserveState: true, 
        preserveScroll: true 
    });
});

const clearFilters = () => {
    searchQuery.value = '';
    filterType.value = 'all';
    filterYear.value = 'all';
    filterActive.value = 'all';
    filterClass.value = 'all';
};

// Client-side text search (Fast filtering within their specific records)
const filteredMemberships = computed(() => {
    if (!searchQuery.value) return props.memberships;
    const q = searchQuery.value.toLowerCase();
    return props.memberships.filter(m => 
        m.title.toLowerCase().includes(q) || 
        m.number.toLowerCase().includes(q) ||
        m.role.toLowerCase().includes(q)
    );
});
const breadcrumbs = [{ title: 'Membership', href: '/membership' }];
</script>

<template>
    <Head title="My Memberships" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto p-4 md:p-8 bg-gray-50/50">
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col md:flex-row items-center gap-6">
                <div class="w-20 h-20 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-2xl font-black shrink-0 shadow-inner">
                    {{ userName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() }}
                </div>
                
                <div class="flex-1 text-center md:text-left">
                    <h2 class="text-2xl font-bold text-gray-900">{{ userName }}</h2>
                    <p class="text-sm text-gray-500 mt-1">Legislative Committee & Program Memberships</p>
                </div>

                <div class="bg-blue-50 px-6 py-4 rounded-xl border border-blue-100 text-center shrink-0">
                    <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">Filtered Involvements</p>
                    <h3 class="text-3xl font-black text-blue-900">{{ memberships.length }}</h3>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col flex-1 overflow-hidden">
                
                <div class="p-4 border-b border-gray-100 flex flex-col xl:flex-row items-center justify-between gap-4 bg-white">
                    
                    <div class="relative w-full md:max-w-md xl:flex-1">
                        <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Search your records by title, number, or role..." 
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 hover:bg-white focus:bg-white py-2 pr-10 pl-10 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-colors" 
                        />
                        <button v-if="searchQuery || filterType !== 'all' || filterYear !== 'all' || filterActive !== 'all' || filterClass !== 'all'" @click="clearFilters" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors" title="Clear Filters">×</button>
                    </div>

                    <div class="flex items-center justify-between xl:justify-end gap-4 w-full xl:w-auto flex-wrap sm:flex-nowrap">
                        <div class="flex items-center gap-2 bg-gray-50 p-1 rounded-lg border border-gray-200 flex-1 sm:flex-none">
                            <Filter class="w-4 h-4 text-gray-400 ml-2 hidden sm:block" />
                            
                            <select v-model="filterType" class="border-0 bg-transparent text-xs font-semibold text-gray-600 focus:ring-0 cursor-pointer py-1.5 pl-2 pr-6 border-r border-gray-200 w-full sm:w-auto outline-none">
                                <option value="all">All Documents</option>
                                <option value="Executive Order">Executive Orders</option>
                                <option value="Ordinance">Ordinances</option>
                            </select>

                            <select v-model="filterClass" class="border-0 bg-transparent text-xs font-semibold text-gray-600 focus:ring-0 cursor-pointer py-1.5 pl-2 pr-6 border-r border-gray-200 w-full sm:w-auto outline-none" :disabled="filterType === 'Ordinance'" :class="{ 'opacity-50': filterType === 'Ordinance' }">
                                <option value="all">Classifications</option>
                                <option v-for="c in available_classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>

                            <select v-model="filterYear" class="border-0 bg-transparent text-xs font-semibold text-gray-600 focus:ring-0 cursor-pointer py-1.5 pl-2 pr-6 border-r border-gray-200 w-full sm:w-auto outline-none">
                                <option value="all">Years</option>
                                <option v-for="y in available_years" :key="y" :value="y">{{ y }}</option>
                            </select>

                            <select v-model="filterActive" class="border-0 bg-transparent text-xs font-semibold text-gray-600 focus:ring-0 cursor-pointer py-1.5 pl-2 pr-6 w-full sm:w-auto outline-none">
                                <option value="all">Statuses</option>
                                <option value="active">Active Only</option>
                                <option value="inactive">Inactive Only</option>
                            </select>
                        </div>
                    </div>

                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50/80 text-xs text-gray-500 uppercase font-bold border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4">Document</th>
                                <th class="px-6 py-4 w-1/2">Subject Title</th>
                                <th class="px-6 py-4">My Designated Role</th>
                                <th class="px-6 py-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="item in filteredMemberships" :key="item.id" class="hover:bg-blue-50/30 transition-colors group">
                                
                                <td class="px-6 py-4 align-top">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <div class="p-1.5 rounded-md" :class="item.type === 'Executive Order' ? 'bg-blue-50 text-blue-600' : 'bg-indigo-50 text-indigo-600'">
                                                <FileText v-if="item.type === 'Executive Order'" class="w-3.5 h-3.5" />
                                                <Gavel v-else class="w-3.5 h-3.5" />
                                            </div>
                                            <p class="font-bold text-gray-900">{{ item.number }}</p>
                                        </div>
                                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mt-1">{{ item.date }}</p>
                                        <div v-if="!item.is_active" class="mt-1 inline-flex items-center gap-1 w-fit px-1.5 py-0.5 rounded bg-red-100 text-red-600 text-[9px] font-bold uppercase border border-red-200">
                                            <XCircle class="w-2.5 h-2.5" /> Inactive
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 align-top">
                                    <p class="font-medium text-gray-800 leading-snug">{{ item.title }}</p>
                                    <span class="inline-block mt-2 px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-600 border border-gray-200">
                                        Status: {{ item.status }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 align-top">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200 shadow-sm">
                                        <Users class="w-3.5 h-3.5" />
                                        {{ item.role }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-center align-middle">
                                    <a v-if="item.url" :href="item.url" target="_blank" title="View Document" class="inline-flex p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors">
                                        <Eye class="w-5 h-5" />
                                    </a>
                                    <span v-else class="text-xs text-gray-400 italic">No File</span>
                                </td>
                            </tr>

                            <tr v-if="filteredMemberships.length === 0">
                                <td colspan="4" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <ShieldAlert class="w-12 h-12 mb-3 text-gray-300" />
                                        <p class="text-base font-bold text-gray-600">No Memberships Found</p>
                                        <p class="text-sm mt-1 max-w-sm">We couldn't find your name listed in any records matching these filters.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>