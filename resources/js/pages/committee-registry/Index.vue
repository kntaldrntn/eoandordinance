<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, Users, X, CheckCircle2 } from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, computed, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    committees: Array<any>;
    allMembers: Array<any>;
    flash?: {
        success?: string;
        error?: string;
    };
}>();

// Table search
const searchTerm = ref('');
const filteredCommittees = computed(() => {
    if (!searchTerm.value) return props.committees;
    const q = searchTerm.value.toLowerCase();
    return props.committees.filter((c: any) => c.name.toLowerCase().includes(q));
});

const clearSearch = () => { searchTerm.value = ''; };

// Notifications
const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'top' } });
watch(() => props.flash, (flash) => {
    if (flash?.success) notyf.success(flash.success);
    if (flash?.error) notyf.error(flash.error);
}, { immediate: true, deep: true });

const breadcrumbs = [
    { title: 'Sponsorship Committee', href: route('committee-registries.index') },
];

// Modal states
const showDialog = ref(false);
const showMemberModal = ref(false);
const showDeleteModal = ref(false);

const isEdit = ref(false);
const editingId = ref<number | null>(null);
const selectedCommittee = ref<any>(null);

const itemToDeleteUrl = ref('');
const itemToDeleteName = ref('');

// Forms
const form = useForm({ name: '' });
const memberForm = useForm({ member_ids: [] as number[] });

// --- Member modal search ---
const memberSearch = ref('');

const filteredAllMembers = computed(() => {
    if (!memberSearch.value.trim()) return props.allMembers;
    const term = memberSearch.value.toLowerCase();
    return props.allMembers.filter(
        (m: any) =>
            m.name.toLowerCase().includes(term) ||
            (m.position || '').toLowerCase().includes(term) ||
            (m.agency || '').toLowerCase().includes(term),
    );
});

// --- DIALOG ACTIONS ---
function openAddDialog() {
    isEdit.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEditDialog(item: any) {
    isEdit.value = true;
    editingId.value = item.id;
    form.name = item.name;
    form.clearErrors();
    showDialog.value = true;
}

function openMemberEditor(committee: any) {
    selectedCommittee.value = committee;
    
    // Sort so Chairman is index 0, Vice is index 1
    if (committee.members) {
        const sortedMembers = [...committee.members].sort((a: any, b: any) => {
            const roleA = a.pivot?.role || 'Member';
            const roleB = b.pivot?.role || 'Member';
            if (roleA === 'Chairman') return -1;
            if (roleB === 'Chairman') return 1;
            if (roleA === 'Vice Chairman') return -1;
            if (roleB === 'Vice Chairman') return 1;
            return 0;
        });
        memberForm.member_ids = sortedMembers.map((m: any) => m.id);
    } else {
        memberForm.member_ids = [];
    }
    
    memberSearch.value = '';
    showMemberModal.value = true;
}

function openDeleteModal(item: any) {
    itemToDeleteUrl.value = route('committee-registries.destroy', item.id);
    itemToDeleteName.value = item.name;
    showDeleteModal.value = true;
}

// --- SUBMIT ACTIONS ---
function submitForm() {
    if (isEdit.value && editingId.value !== null) {
        form.put(route('committee-registries.update', editingId.value), {
            onSuccess: () => { showDialog.value = false; form.reset(); },
        });
    } else {
        form.post(route('committee-registries.store'), {
            onSuccess: () => { showDialog.value = false; form.reset(); },
        });
    }
}

function saveMembers() {
    memberForm.post(route('committee-registries.sync', selectedCommittee.value.id), {
        onSuccess: () => { showMemberModal.value = false; memberSearch.value = ''; }
    });
}

const getDeptCode = (titleStr?: string) => {
    if (!titleStr) return '';
    const match = titleStr.match(/\(([^)]+)\)/);
    return match ? match[1].trim() : '';
};
</script>

<template>
    <Head title="Sponsorship Committee" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-white p-4 md:p-8">
            
            <div class="flex flex-col items-center justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm md:flex-row">
                <div class="relative max-w-md flex-1 w-full md:w-auto">
                    <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchTerm"
                        type="text"
                        placeholder="Search committees..."
                        class="block w-full rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-10 text-sm placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                    <button v-if="searchTerm" @click="clearSearch" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600">×</button>
                </div>

                <button
                    class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm transition-colors duration-200 hover:bg-blue-700 w-full md:w-auto justify-center"
                    @click="openAddDialog"
                >
                    <Plus class="h-4 w-4" /> Create Committee
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
                            <tr>
                                <th class="px-6 py-3 font-medium tracking-wide">ID</th>
                                <th class="px-6 py-3 font-medium tracking-wide w-1/2">Committee Name</th>
                                <th class="px-6 py-3 font-medium tracking-wide text-center">Members</th>
                                <th class="px-6 py-3 text-center font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="committee in filteredCommittees"
                                :key="committee.id"
                                class="transition-colors duration-150 hover:bg-gray-50 border-b border-gray-200"
                            >
                                <td class="px-6 py-4 font-mono text-gray-900">{{ committee.id }}</td>
                                <td class="px-6 py-4 font-bold text-gray-800">{{ committee.name }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-600 border border-blue-100">
                                        {{ committee.members?.length || 0 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-center gap-2">
                                        <button
                                            class="rounded-lg bg-blue-50 p-2 text-blue-600 transition hover:bg-blue-100 focus:ring-2 focus:ring-blue-400 flex items-center gap-1"
                                            @click="openMemberEditor(committee)"
                                            title="Manage Members"
                                        >
                                            <Users class="h-4 w-4" />
                                            <span class="text-xs font-bold px-1">Manage</span>
                                        </button>
                                        <button
                                            class="rounded-lg bg-blue-50 p-2 text-blue-600 transition hover:bg-blue-100 focus:ring-2 focus:ring-blue-400"
                                            @click="openEditDialog(committee)"
                                            title="Edit Name"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            class="rounded-lg bg-red-50 p-2 text-red-600 transition hover:bg-red-100 focus:ring-2 focus:ring-red-400"
                                            @click="openDeleteModal(committee)"
                                            title="Delete"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="filteredCommittees.length === 0" class="py-12 text-center">
                        <div class="flex flex-col items-center">
                            <Search class="mb-4 h-12 w-12 text-gray-300" />
                            <h3 class="text-lg font-semibold text-gray-900">No committees found</h3>
                            <p class="mt-1 mb-4 text-sm text-gray-500">
                                {{ searchTerm ? 'Try adjusting your search terms.' : 'Get started by creating your first committee.' }}
                            </p>
                            <button v-if="!searchTerm" @click="openAddDialog" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-blue-700">
                                Create Committee
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <DeleteConfirmationModal
                v-model:show="showDeleteModal"
                :deleteUrl="itemToDeleteUrl"
                :item-name="itemToDeleteName"
                title="Delete Committee"
                message="Are you sure you want to delete this committee? This action cannot be undone."
            />

            <!-- Create / Edit Committee Dialog -->
            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h2 class="mb-6 text-lg font-semibold text-gray-900">
                            {{ isEdit ? 'Edit Committee' : 'Create New Committee' }}
                        </h2>
                        <form @submit.prevent="submitForm" class="space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700" for="name">Committee Name</label>
                                <input
                                    v-model="form.name"
                                    id="name"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 font-medium focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none"
                                    required
                                    placeholder="e.g., Committee on Health and Sanitation"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                            </div>
                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 transition hover:bg-gray-200 font-medium" @click="showDialog = false">Cancel</button>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white transition hover:bg-blue-700 disabled:opacity-50 font-bold" :disabled="form.processing">
                                    {{ form.processing ? 'Saving...' : isEdit ? 'Update Committee' : 'Create Committee' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- Manage Members Modal -->
            <Transition name="fade">
                <div v-if="showMemberModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
                    <div class="w-full max-w-2xl bg-white rounded-xl shadow-2xl flex flex-col max-h-[85vh]">

                        <!-- Header -->
                        <div class="flex justify-between items-center px-6 pt-6 pb-4 border-b border-gray-100">
                            <div>
                                <h3 class="font-bold text-lg text-gray-900">Manage Committee Members</h3>
                                <p class="text-xs text-blue-600 font-bold mt-1 uppercase tracking-widest">{{ selectedCommittee?.name }}</p>
                            </div>
                            <button @click="showMemberModal = false; memberSearch = ''" class="text-gray-400 hover:bg-gray-100 p-1.5 rounded-full transition">
                                <X class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Search bar -->
                        <div class="px-6 py-3 border-b border-gray-100">
                            <div class="relative">
                                <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    v-model="memberSearch"
                                    type="text"
                                    placeholder="Search by name, position, or agency..."
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pr-8 pl-9 text-sm placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none"
                                />
                                <button v-if="memberSearch" @click="memberSearch = ''" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-base leading-none">×</button>
                            </div>
                            <p v-if="memberSearch" class="mt-1.5 text-xs text-gray-400">
                                Showing {{ filteredAllMembers.length }} of {{ allMembers.length }} members
                            </p>
                        </div>

                        <!-- Member list -->
                        <div class="flex-1 overflow-y-auto p-2 bg-gray-50 custom-scrollbar">
                            <label
                                v-for="member in filteredAllMembers"
                                :key="member.id"
                                class="flex items-center gap-3 p-3 hover:bg-white rounded-lg transition border-b border-gray-100 last:border-0 cursor-pointer"
                            >
                                <input type="checkbox" :value="member.id" v-model="memberForm.member_ids" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4" />
                                <div class="flex flex-col w-full">
                                    <span class="text-sm font-bold text-gray-800 flex justify-between items-center w-full pr-4">
                                        <span>
                                            {{ member.name }}
                                            <span v-if="getDeptCode(member.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(member.title) }})</span>
                                        </span>
                                        
                                        <span v-if="memberForm.member_ids.indexOf(member.id) === 0" class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-bold rounded-md tracking-widest shrink-0">CHAIRMAN</span>
                                        <span v-else-if="memberForm.member_ids.indexOf(member.id) === 1" class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded-md tracking-widest shrink-0">VICE CHAIRMAN</span>
                                        <span v-else-if="memberForm.member_ids.indexOf(member.id) > 1" class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[10px] font-bold rounded-md tracking-widest shrink-0">MEMBER</span>
                                    </span>
                                    
                                    <span v-if="member.type" class="text-[10px] font-bold uppercase tracking-wider mt-1" :class="member.type.includes('Internal') ? 'text-blue-600' : 'text-green-600'">
                                        {{ member.type }}
                                    </span>
                                </div>
                            </label>

                            <div v-if="filteredAllMembers.length === 0" class="text-center py-10 text-gray-400 text-sm">
                                {{ memberSearch ? 'No members match your search.' : 'No registered members found in the system.' }}
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex justify-between items-center px-6 py-4 border-t border-gray-100">
                            <span class="text-xs font-bold text-gray-500">{{ memberForm.member_ids.length }} Members Selected</span>
                            <div class="flex gap-2">
                                <button @click="showMemberModal = false; memberSearch = ''" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition font-medium">Cancel</button>
                                <button @click="saveMembers" :disabled="memberForm.processing" class="px-5 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold flex items-center gap-2 transition disabled:opacity-50">
                                    <CheckCircle2 class="w-4 h-4" /> {{ memberForm.processing ? 'Saving...' : 'Save Assignments' }}
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </Transition>

        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>