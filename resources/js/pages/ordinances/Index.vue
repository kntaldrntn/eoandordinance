<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Gavel, Plus, Search, Building2, BookOpen, AlertCircle,
    UserCheck, Clock, Trash2, XCircle, Info, Pencil, Eye, Filter, CheckCircle2, Paperclip, AlertTriangle
} from 'lucide-vue-next';
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import { ref, watch, computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    ordinances: {
        data: Array<any>;
        links: Array<any>;
        from: number; to: number; total: number;
        current_page: number; last_page: number;
    };
    departments: Array<{ id: number; code?: string; name: string }>;
    statuses: Array<{ id: number; name: string }>;
    existing_ordinances: Array<{ id: number; ordinance_number: string; title: string }>;
    peopleRegistry: Array<{ name: string; title: string; type: string }>;
    committeeRegistries: Array<{ id: number; name: string; members: Array<any> }>;
    ordinance_codes: Array<{ id: number; name: string }>;
    filters?: { search?: string; year?: string; is_active?: string };
    available_years: number[];
    flash?: { success?: string; error?: string };
}>();

const searchTerm = ref(props.filters?.search || '');
const filterYear = ref(props.filters?.year || 'all');
const filterActive = ref(props.filters?.is_active || 'all');
const isLoading = ref(false);
let searchTimeout: ReturnType<typeof setTimeout>;

const performSearch = () => {
    clearTimeout(searchTimeout);
    isLoading.value = true;
    searchTimeout = setTimeout(() => {
        router.get(route('ordinances.index'), { search: searchTerm.value, year: filterYear.value, is_active: filterActive.value }, { preserveState: true, preserveScroll: true, onFinish: () => isLoading.value = false });
    }, 300);
};

watch([searchTerm, filterYear, filterActive], performSearch);
const clearSearch = () => { searchTerm.value = ''; filterYear.value = 'all'; filterActive.value = 'all'; };
const goToPage = (url: string) => { if (url) router.get(url, { search: searchTerm.value, year: filterYear.value, is_active: filterActive.value }, { preserveState: true, preserveScroll: false }); };

const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'top' } });
watch(() => props.flash, (flash) => {
    if (flash?.success) notyf.success(flash.success);
    if (flash?.error) notyf.error(flash.error);
}, { immediate: true, deep: true });

// --- MODAL STATE ---
const showDialog = ref(false);
const showIrrDialog = ref(false);
const showDisableIrrDialog = ref(false);
const isEdit = ref(false);
const editingId = ref<number | null>(null);
const activeModalTab = ref('details');
const activeAuthorTab = ref('authors');
const selectedRecord = ref<any>(null);
const selectedIrrId = ref<number | null>(null);
const selectedIrr = ref<any>(null);
const allIrrs = ref<any[]>([]);
const selectedIrrIndex = ref(0);

watch(showIrrDialog, (v) => {
    if (!v) {
        selectedIrr.value = null;
        selectedIrrId.value = null;
        disableIrrForm.reset();
    }
});

const parentSearchQuery = ref('');
const showParentDropdown = ref(false);
const implementingSearchQuery = ref('');
const supportSearchQuery = ref('');
const deptIrrSearchQuery = ref('');

const filteredParents = computed(() => {
    let list = props.existing_ordinances || [];
    list = list.filter(item => item.is_active);

    if (isEdit.value && editingId.value) {
        list = list.filter(item => item.id !== editingId.value);
    }

    if (form.effectivity_date) {
        const newDocDate = new Date(form.effectivity_date).getTime();
        list = list.filter(item => {
            if (!item.effectivity_date) return true;
            return new Date(item.effectivity_date).getTime() <= newDocDate;
        });
    }

    if (parentSearchQuery.value && !parentSearchQuery.value.startsWith('AMENDING:')) {
        const q = parentSearchQuery.value.toLowerCase();
        list = list.filter(item => {
            const trackingNo = item.ordinance_number ? item.ordinance_number.toLowerCase() : '';
            const title = item.title ? item.title.toLowerCase() : '';
            return trackingNo.includes(q) || title.includes(q);
        });
    }
    return list.slice(0, 15);
});

const selectableStatuses = computed(() => {
    const autoStatuses = ['Amended', 'Repealed', 'Superseded'];
    const filtered = props.statuses.filter(s => {
        if (autoStatuses.includes(s.name)) {
            return isEdit.value && selectedRecord.value?.status_id === s.id;
        }
        return true;
    });
    return filtered.sort((a, b) => {
        if (a.name === 'New') return -1;
        if (b.name === 'New') return 1;
        return a.name.localeCompare(b.name);
    });
});

const isAmendmentMode = computed(() => {
    const status = props.statuses.find(s => s.id == form.status_id);
    return status?.name?.toLowerCase().includes('amend') && status?.name !== 'Amended';
});

const selectParent = (parent: any) => {
    form.amends_ordinance_id = parent.id;
    parentSearchQuery.value = `${parent.ordinance_number} - ${parent.title}`;
    showParentDropdown.value = false;
};

const clearParent = () => {
    form.amends_ordinance_id = '';
    parentSearchQuery.value = '';
    form.relationship_type = 'Partial Amendment';
};

// 🚀 THE OBJECT RULE
const createEmptyMember = () => ({ id: null, pmis_id: null, name: '' });

const defaultAuthorDetails = () => ({
    introduced_by: createEmptyMember(),
    is_primary_author: true,
    sponsorship_committee: { id: null, name: '' },
    committee_chairmanship: createEmptyMember(),
    co_authors: Array.from({ length: 4 }, createEmptyMember),
    committee_members: Array.from({ length: 4 }, createEmptyMember)
});

const defaultIrrExternalInstitutions = () => ({
    members: Array.from({ length: 4 }, createEmptyMember),
    ngos: Array.from({ length: 4 }, createEmptyMember),
    others: Array.from({ length: 4 }, createEmptyMember)
});

const addMember = (field: string) => {
    if (field === 'co_authors') form.author_details.co_authors.push(createEmptyMember());
    else if (field === 'committee_members') form.author_details.committee_members.push(createEmptyMember());
    else if (field === 'external_members') form.external_institutions.members.push(createEmptyMember());
    else if (field === 'external_ngos') form.external_institutions.ngos.push(createEmptyMember());
    else if (field === 'external_others') form.external_institutions.others.push(createEmptyMember());
    else if (field === 'irr_external_members') irrForm.external_institutions.members.push(createEmptyMember());
    else if (field === 'irr_external_ngos') irrForm.external_institutions.ngos.push(createEmptyMember());
    else if (field === 'irr_external_others') irrForm.external_institutions.others.push(createEmptyMember());
};

const removeMember = (field: string, index: number) => {
    if (field === 'co_authors') form.author_details.co_authors.splice(index, 1);
    else if (field === 'committee_members') form.author_details.committee_members.splice(index, 1);
    else if (field === 'external_members') form.external_institutions.members.splice(index, 1);
    else if (field === 'external_ngos') form.external_institutions.ngos.splice(index, 1);
    else if (field === 'external_others') form.external_institutions.others.splice(index, 1);
    else if (field === 'irr_external_members') irrForm.external_institutions.members.splice(index, 1);
    else if (field === 'irr_external_ngos') irrForm.external_institutions.ngos.splice(index, 1);
    else if (field === 'irr_external_others') irrForm.external_institutions.others.splice(index, 1);
};

const form = useForm({
    ordinance_number: '',
    title: '',
    subject_matter: '',
    ordinance_code_id: '' as string | number,
    date_approved: '',
    effectivity_date: '',
    date_enacted: '',
    presiding_officer: '',
    attested_by: '',
    approved_by: '',
    status_id: '' as string | number,
    is_active: true,
    amends_ordinance_id: '' as string | number,
    relationship_type: 'Partial Amendment',
    remarks: '',
    author_details: defaultAuthorDetails(),
    selected_registry_id: '' as string | number,
    lead_office_id: '' as string | number,
    support_office_ids: [] as number[],
    external_institutions: {
        members: Array.from({ length: 4 }, createEmptyMember),
        ngos: Array.from({ length: 4 }, createEmptyMember),
        others: Array.from({ length: 4 }, createEmptyMember)
    },
    file: null as File | null,
});

const activeOrdinanceExternalTab = ref('members');

const irrForm = useForm({
    status: 'Active',
    lead_office_id: '' as string | number,
    support_offices: [{ id: '' as string | number, name: '' }],
    external_institutions: defaultIrrExternalInstitutions(),
    file: null as File | null,
});

const activeIrrExternalTab = ref('members');
const disableIrrForm = useForm({ reason: '' });

// --- SUGGESTIVE INPUT LOGIC ---
const activeSuggestion = ref<string | null>(null);

const getDeptCode = (titleStr?: string) => {
    if (!titleStr) return '';
    const match = titleStr.match(/\(([^)]+)\)/);
    if (match) {
        const officeName = match[1].trim();
        const dept = props.departments.find(d => d.name === officeName);
        return dept && dept.code ? dept.code : officeName;
    }
    return '';
};

const normalize = (s: string) => s ? String(s).toLowerCase().replace(/[^a-z0-9]/g, '') : '';

const getCurrentlySelectedPeople = (ignoreField: string | null, ignoreIndex: number = -1) => {
    const selected = new Set<string>();
    const a = form.author_details;
    const e = form.external_institutions;
    const irrE = irrForm.external_institutions;

    const addVal = (val: any, field: string, idx: number = -1) => {
        if (field === ignoreField && idx === ignoreIndex) return;
        if (!val) return;

        const name = typeof val === 'object' ? val.name : val;

        if (typeof name === 'string' && name.trim() !== '') {
            name.split(/[,]+/).forEach((v: string) => {
                const norm = normalize(v);
                if (norm) selected.add(norm);
            });
        }
    };

    addVal(a.introduced_by, 'introduced_by');
    addVal(a.committee_chairmanship, 'committee');
    a.co_authors.forEach((v, i) => addVal(v, 'co_authors', i));
    a.committee_members.forEach((v, i) => addVal(v, 'committee_members', i));

    e.members.forEach((v, i) => addVal(v, 'external_members', i));
    e.ngos.forEach((v, i) => addVal(v, 'external_ngos', i));
    e.others.forEach((v, i) => addVal(v, 'external_others', i));

    irrE.members.forEach((v, i) => addVal(v, 'irr_external_members', i));
    irrE.ngos.forEach((v, i) => addVal(v, 'irr_external_ngos', i));
    irrE.others.forEach((v, i) => addVal(v, 'irr_external_others', i));

    return Array.from(selected);
};

const getSuggestions = (query: any, typeFilter: string | null = null, fieldName: string | null = null, idx: number = -1) => {
    let list = props.peopleRegistry || [];

    if (typeFilter) {
        list = list.filter(p => {
            if (typeFilter === 'Internal') return p.type === 'Internal' || p.type === 'Internal(Registered)';
            if (typeFilter === 'External') return p.type === 'External' || p.type === 'External(Registered)';
            return p.type === typeFilter;
        });
    }

    const globallySelectedNames = getCurrentlySelectedPeople(fieldName, idx);
    if (globallySelectedNames.length > 0) {
        list = list.filter(p => !globallySelectedNames.includes(normalize(p.name || '')));
    }

    if (!query || typeof query !== 'string' || query.trim() === '') return [];

    const parts = query.split(/[\n,]+/).map(s => s.trim());
    const currentSearch = parts[parts.length - 1].toLowerCase();

    if (!currentSearch || currentSearch.length < 2) return [];

    return list.filter(p => {
        const safeName = p.name ? String(p.name).toLowerCase() : '';
        return safeName.includes(currentSearch);
    }).slice(0, 10);
};

const selectPerson = (field: string, person: any, index?: number) => {
    const personObj = { id: person.id || null, pmis_id: person.pmis_id || null, name: person.name };
    const nameStr = person.name;

    // Object fields for Ordinance Main Form
    if (field === 'introduced_by') form.author_details.introduced_by = personObj;
    else if (field === 'committee') form.author_details.committee_chairmanship = personObj;
    else if (field === 'co_authors' && index !== undefined) form.author_details.co_authors[index] = personObj;
    else if (field === 'committee_members' && index !== undefined) form.author_details.committee_members[index] = personObj;
    else if (field === 'external_members' && index !== undefined) form.external_institutions.members[index] = personObj;
    else if (field === 'external_ngos' && index !== undefined) form.external_institutions.ngos[index] = personObj;
    else if (field === 'external_others' && index !== undefined) form.external_institutions.others[index] = personObj;

    // Object fields for IRR Form
    else if (field === 'irr_external_members' && index !== undefined) irrForm.external_institutions.members[index] = personObj;
    else if (field === 'irr_external_ngos' && index !== undefined) irrForm.external_institutions.ngos[index] = personObj;
    else if (field === 'irr_external_others' && index !== undefined) irrForm.external_institutions.others[index] = personObj;

    // String fields
    else if (field === 'presiding_officer') form.presiding_officer = nameStr;
    else if (field === 'attested_by') form.attested_by = nameStr;
    else if (field === 'approved_by') form.approved_by = nameStr;

    activeSuggestion.value = null;
};

// 🚀 NEW: COMMITTEE REGISTRY LOGIC
const selectedRegistryId = ref('');
const showRegistryMenu = ref(false);
const sponsorshipSearchQuery = ref('');

const selectRegistry = (reg: any) => {
    if (!reg) return;
    selectedRegistryId.value = reg.id;
    form.selected_registry_id = reg.id;
    sponsorshipSearchQuery.value = reg.name;
    loadFromRegistry();
    showRegistryMenu.value = false;
};

const clearSponsorship = () => {
    form.author_details.sponsorship_committee = { id: null, name: '' };
    sponsorshipSearchQuery.value = '';
    selectedRegistryId.value = '';
    form.selected_registry_id = '';
    // Clear committee members UI when sponsorship is cleared
    form.author_details.committee_members = Array.from({ length: 4 }, createEmptyMember);
    activeSuggestion.value = null;
};


const loadFromRegistry = () => {
    if (!selectedRegistryId.value) return;
    const selectedRegistry = props.committeeRegistries.find(c => c.id == Number(selectedRegistryId.value));
    
    if (selectedRegistry && selectedRegistry.members) {
        // Set the sponsorship committee name/id and map the database members into the form structure
        form.author_details.sponsorship_committee = form.author_details.sponsorship_committee || { id: null, name: '' };
        form.author_details.sponsorship_committee.id = selectedRegistry.id;
        form.author_details.sponsorship_committee.name = selectedRegistry.name;

        form.author_details.committee_members = selectedRegistry.members.map((m: any) => ({
            id: m.id,
            pmis_id: m.pmis_id,
            name: m.name
        }));

        // Ensure UI slots are filled up to fixed 4 entries
        while (form.author_details.committee_members.length < 4) {
            form.author_details.committee_members.push(createEmptyMember());
        }

        notyf.success(`Loaded members from ${selectedRegistry.name}`);
    }
};

const getCommitteeSuggestions = (query: string) => {
    if (!props.committeeRegistries) return [];
    const q = (query || '').toLowerCase();
    return props.committeeRegistries.filter((c: any) => c.name.toLowerCase().includes(q)).slice(0, 10);
};

const filteredCommitteeRegistries = computed(() => {
    if (!sponsorshipSearchQuery.value) return (props.committeeRegistries || []).slice(0, 10);
    return (props.committeeRegistries || []).filter((c: any) => c.name.toLowerCase().includes(sponsorshipSearchQuery.value.toLowerCase())).slice(0, 10);
});

const filteredLeadOffices = computed(() => {
    if (!implementingSearchQuery.value) return props.departments.slice(0, 10);
    return props.departments.filter(d => d.name.toLowerCase().includes(implementingSearchQuery.value.toLowerCase())).slice(0, 10);
});

const selectLeadOffice = (dept: any, targetForm: any = form) => {
    targetForm.lead_office_id = dept.id;
    if(targetForm === form) implementingSearchQuery.value = dept.name;
    activeSuggestion.value = null;
};

const clearLeadOffice = () => {
    form.lead_office_id = '';
    implementingSearchQuery.value = '';
};

const getIrrLeadSuggestions = (query: string) => {
    let list = props.departments;
    const supportIds = irrForm.support_offices.map(o => o.id).filter(id => id !== '');
    if (supportIds.length > 0) list = list.filter(d => !supportIds.includes(d.id));
    if (!query) return list.slice(0, 10);
    return list.filter(d => d.name.toLowerCase().includes(query.toLowerCase())).slice(0, 10);
};

const filteredSupportOffices = computed(() => {
    let list = props.departments;
    if (form.lead_office_id) {
        list = list.filter(d => d.id !== form.lead_office_id);
    }
    if (!supportSearchQuery.value) return list;
    return list.filter(dept => dept.name.toLowerCase().includes(supportSearchQuery.value.toLowerCase()));
});

const getIrrDeptSuggestions = (query: string, currentIndex: number = -1) => {
    let list = props.departments;
    if (irrForm.lead_office_id) list = list.filter(d => d.id !== irrForm.lead_office_id);
    const selectedNames = irrForm.support_offices.map((o, idx) => idx !== currentIndex && o.name ? o.name.toLowerCase().trim() : null).filter(Boolean);
    if (selectedNames.length > 0) list = list.filter(d => !selectedNames.includes(d.name.toLowerCase().trim()));
    if (!query) return list.slice(0, 10);
    return list.filter(d => d.name.toLowerCase().includes(query.toLowerCase())).slice(0, 10);
};

const selectIrrLeadOffice = (dept: any, formTarget: any) => {
    formTarget.lead_office_id = dept.id;
    deptIrrSearchQuery.value = dept.name;
    activeSuggestion.value = null;
}

const selectIrrSupportOffice = (dept: any, index: number) => {
    irrForm.support_offices[index] = { id: dept.id, name: dept.name };
    activeSuggestion.value = null;
};

const formatForInput = (dateStr: string | null) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr.split('T')[0].split(' ')[0];
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// --- FUNCTIONS ---
function openAddDialog() {
    isEdit.value = false; editingId.value = null; selectedRecord.value = null;
    activeModalTab.value = 'details'; activeAuthorTab.value = 'authors';
    activeOrdinanceExternalTab.value = 'members';
    implementingSearchQuery.value = ''; parentSearchQuery.value = ''; supportSearchQuery.value = '';
    selectedRegistryId.value = '';
    sponsorshipSearchQuery.value = '';

    form.reset(); form.clearErrors();
    form.relationship_type = 'Partial Amendment';
    form.is_active = true;
    form.ordinance_code_id = '';
    form.author_details = defaultAuthorDetails();
    form.support_office_ids = [];
    form.external_institutions = {
        members: Array.from({ length: 4 }, createEmptyMember),
        ngos: Array.from({ length: 4 }, createEmptyMember),
        others: Array.from({ length: 4 }, createEmptyMember)
    };

    const newStatus = selectableStatuses.value.find(s => s.name === 'New');
    if (newStatus) form.status_id = newStatus.id;

    showDialog.value = true;
}

function openEditDialog(ord: any) {
    isEdit.value = true; editingId.value = ord.id; selectedRecord.value = ord;
    activeModalTab.value = 'details'; activeAuthorTab.value = 'authors';
    activeOrdinanceExternalTab.value = 'members';
    supportSearchQuery.value = '';
    selectedRegistryId.value = '';
    form.clearErrors();

    form.ordinance_number = ord.ordinance_number;
    form.title = ord.title;
    form.subject_matter = ord.subject_matter || '';
    form.date_approved = formatForInput(ord.date_approved);
    form.effectivity_date = formatForInput(ord.effectivity_date);
    form.date_enacted = formatForInput(ord.date_enacted);
    form.presiding_officer = ord.presiding_officer || '';
    form.attested_by = ord.attested_by || '';
    form.approved_by = ord.approved_by || '';
    form.status_id = ord.status_id;
    form.is_active = Boolean(ord.is_active);
    form.ordinance_code_id = ord.ordinance_code_id || '';
    form.amends_ordinance_id = ord.amends_ordinance_id || '';
    form.relationship_type = ord.relationship_type || 'Partial Amendment';
    form.remarks = ord.remarks || '';

    if (ord.amends_ordinance_id) {
        const parent = props.existing_ordinances.find(o => o.id === ord.amends_ordinance_id);
        parentSearchQuery.value = parent ? `${parent.ordinance_number} - ${parent.title}` : '';
    } else { parentSearchQuery.value = ''; }

    const a = defaultAuthorDetails();
    const e = {
        members: Array.from({ length: 4 }, createEmptyMember),
        ngos: Array.from({ length: 4 }, createEmptyMember),
        others: Array.from({ length: 4 }, createEmptyMember)
    };

    if (ord.committees && ord.committees.length > 0) {
        const committee = ord.committees.find((c: any) => c.type === 'ordinance_sponsors');
        if (committee && committee.members) {
            let coAuthorIdx = 0, commMemberIdx = 0, extMemberIdx = 0, ngoIdx = 0, otherIdx = 0;
            const formatMember = (m: any) => ({ id: m.id, pmis_id: m.pmis_id, name: m.name });

            committee.members.forEach((m: any) => {
                const role = m.pivot.role;
                const memberObj = formatMember(m);

                if (role === 'Primary Author' || role === 'Introduced By') {
                    a.introduced_by = memberObj;
                    a.is_primary_author = role === 'Primary Author';
                }
                else if (role === 'Committee Chairman') a.committee_chairmanship = memberObj;
                else if (role === 'Co-Author') a.co_authors[coAuthorIdx++] = memberObj;
                else if (role === 'Committee Member') a.committee_members[commMemberIdx++] = memberObj;
                else if (role === 'External Member') e.members[extMemberIdx++] = memberObj;
                else if (role === 'NGO Partner') e.ngos[ngoIdx++] = memberObj;
                else if (role === 'Other Organization') e.others[otherIdx++] = memberObj;
            });

            // If this committee is linked to a registry, preselect it so the sponsorship input shows the registry name
                    if (committee.registry_id) {
                        selectedRegistryId.value = committee.registry_id;
                        form.selected_registry_id = committee.registry_id;
                        const reg = props.committeeRegistries.find((r: any) => r.id == committee.registry_id);
                        if (reg) {
                            a.sponsorship_committee.id = reg.id;
                            a.sponsorship_committee.name = reg.name;
                            sponsorshipSearchQuery.value = reg.name;

                            // Prefer registry members for the committee members list when a registry is linked
                            if (reg.members && reg.members.length > 0) {
                                a.committee_members = reg.members.map((m: any) => ({ id: m.id, pmis_id: m.pmis_id, name: m.name }));
                                while (a.committee_members.length < 4) a.committee_members.push(createEmptyMember());
                            }
                        }
                    }
        }
    }

    form.author_details = a;
    form.external_institutions = e;

    const lead = ord.departments.find((d: any) => d.pivot.role === 'lead');
    form.lead_office_id = lead ? lead.id : '';
    implementingSearchQuery.value = lead ? lead.name : '';

    const supports = ord.departments.filter((d: any) => d.pivot.role === 'support');
    form.support_office_ids = supports.map((s:any) => s.id);

    form.file = null; showDialog.value = true;
}

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.file = target.files[0];
    }
}

// 🚀 FIXED: Using FormData to handle both Objects AND Files properly!
function submitForm() {
    const url = isEdit.value && editingId.value ? route('ordinances.update', editingId.value) : route('ordinances.store');
    const method = isEdit.value ? 'post' : 'post'; // Use post, rely on _method: PUT

    form.transform((data) => {
        const transformed = new FormData();
        
        const filterEmpty = (arr: any[]) => arr.filter(m => m && m.name && m.name.trim() !== '');

        // Manually format the JSON structures so they survive FormData serialization
        const cleanAuthorDetails = {
            is_primary_author: data.author_details.is_primary_author,
            introduced_by: data.author_details.introduced_by,
            primary_author: data.author_details.is_primary_author ? data.author_details.introduced_by : null,
            committee_chairmanship: data.author_details.committee_chairmanship,
            sponsorship_committee: data.author_details.sponsorship_committee || { id: null, name: '' },
            selected_registry_id: data.selected_registry_id || null,
            co_authors: filterEmpty(data.author_details.co_authors),
            committee_members: filterEmpty(data.author_details.committee_members)
        };

        const cleanExternal = {
            members: filterEmpty(data.external_institutions.members),
            ngos: filterEmpty(data.external_institutions.ngos),
            others: filterEmpty(data.external_institutions.others)
        };

        // Append basic data
        for (let key in data) {
            if (key === 'file' && data.file) {
                transformed.append('file', data.file);
            } else if (key === 'author_details') {
                transformed.append('author_details', JSON.stringify(cleanAuthorDetails));
            } else if (key === 'external_institutions') {
                transformed.append('external_institutions', JSON.stringify(cleanExternal));
            } else if (key === 'support_office_ids') {
                data.support_office_ids.forEach((id: any) => transformed.append('support_office_ids[]', id));
            } else if (data[key as keyof typeof data] !== null && data[key as keyof typeof data] !== undefined) {
                transformed.append(key, String(data[key as keyof typeof data]));
            }
        }

        if (isEdit.value) transformed.append('_method', 'PUT');

        // (debug flag removed)

        return transformed;
    }).post(url, {
        forceFormData: true,
        onSuccess: () => { showDialog.value = false; form.reset(); notyf.success('Ordinance saved successfully.');},
        onError: () => { notyf.error('Please check the form for errors.'); }
    });
}

// ... (IRR Functions remain completely unchanged below here) ...
const irrStatuses = ['Active', 'On-hold', 'Dropped'];

const parseFixed4MemberObjects = (rawArray: any[]) => {
    const arr = Array.isArray(rawArray) ? rawArray : [];
    const parsed = arr.map(item => {
        if (typeof item === 'string') return { id: null, pmis_id: null, name: item };
        if (typeof item === 'object' && item !== null) return { id: item.id || null, pmis_id: item.pmis_id || null, name: item.name || '' };
        return createEmptyMember();
    }).filter(m => m.name.trim() !== '');

    while (parsed.length < 4) parsed.push(createEmptyMember());
    return parsed.slice(0, 4);
};

// Load an IRR object into the irrForm (parse external_institutions or derive from committees)
function loadIrr(irrObj: any) {
    const irr = irrObj || null;
    selectedIrr.value = irr;

    try {
        let raw: any = null;
        if (irr) {
            const keys = ['external_institutions','externalInstitutions','external_institution','external_institutions_json','external_institutions_data','externals'];
            for (const k of keys) {
                if (irr[k]) { raw = irr[k]; break; }
            }
            if (!raw && typeof irr === 'string') {
                try { const p = JSON.parse(irr); raw = p.external_institutions || p.externals || null; } catch(e) {}
            }
        }

        if (raw) {
            const ei = typeof raw === 'string' ? JSON.parse(raw) : raw;
            irrForm.external_institutions = {
                members: parseFixed4MemberObjects(ei.members),
                ngos: parseFixed4MemberObjects(ei.ngos),
                others: parseFixed4MemberObjects(ei.others)
            };
        } else {
            irrForm.external_institutions = defaultIrrExternalInstitutions();
        }
    } catch(err) {
        irrForm.external_institutions = defaultIrrExternalInstitutions();
        console.warn('loadIrr: failed parsing external_institutions', err);
    }

    if ((!irr || !irr.external_institutions) && selectedRecord.value?.committees && selectedRecord.value.committees.length > 0) {
        const committee = selectedRecord.value.committees.find((c: any) => c.type === 'ordinance_sponsors' || c.type === 'committee_member_registry');
        if (committee && committee.members) {
            const e = {
                members: Array.from({ length: 4 }, createEmptyMember),
                ngos: Array.from({ length: 4 }, createEmptyMember),
                others: Array.from({ length: 4 }, createEmptyMember)
            } as any;

            let extMemberIdx = 0, ngoIdx = 0, otherIdx = 0;
            const formatMember = (m: any) => ({ id: m.id || null, pmis_id: m.pmis_id || null, name: m.name || '' });

            committee.members.forEach((m: any) => {
                const role = m.pivot?.role;
                const memberObj = formatMember(m);
                if (role === 'External Member') e.members[extMemberIdx++] = memberObj;
                else if (role === 'NGO Partner') e.ngos[ngoIdx++] = memberObj;
                else if (role === 'Other Organization') e.others[otherIdx++] = memberObj;
            });

            const foundAny = e.members.some((x: any) => x && x.name && x.name.trim() !== '') || e.ngos.some((x: any) => x && x.name && x.name.trim() !== '') || e.others.some((x: any) => x && x.name && x.name.trim() !== '');
            if (foundAny) {
                irrForm.external_institutions = {
                    members: parseFixed4MemberObjects(e.members),
                    ngos: parseFixed4MemberObjects(e.ngos),
                    others: parseFixed4MemberObjects(e.others)
                };
            }
        }
    }

    if (irr) {
        selectedIrrId.value = irr.id || null;
        irrForm.status = irr.status || 'Active';

        irrForm.lead_office_id = irr.lead_office_id || '';
        const leadDept = props.departments.find(d => d.id == irr.lead_office_id);
        deptIrrSearchQuery.value = leadDept ? leadDept.name : '';

        if (irr.support_offices) {
            try {
                const so = Array.isArray(irr.support_offices) ? irr.support_offices : (typeof irr.support_offices === 'string' ? JSON.parse(irr.support_offices) : []);
                irrForm.support_offices = so.map((s: any) => {
                    if (typeof s === 'object') return { id: s.id || s, name: s.name || (props.departments.find(d => d.id == (s.id || s))?.name || '') };
                    return { id: s, name: props.departments.find(d => d.id == s)?.name || '' };
                });
                if (irrForm.support_offices.length === 0) irrForm.support_offices = [{ id: '', name: '' }];
            } catch (e) {
                irrForm.support_offices = [{ id: '', name: '' }];
            }
        }
        irrForm.file = null;
    } else {
        selectedIrrId.value = null;
    }
}

function openIrrDialog(ord: any) {
    selectedRecord.value = ord;
    irrForm.reset();
    irrForm.clearErrors();
    activeIrrExternalTab.value = 'members';
    deptIrrSearchQuery.value = '';

    // Load all IRRs for this ordinance
    allIrrs.value = Array.isArray(ord.implementing_rules) ? ord.implementing_rules : [];
    selectedIrrIndex.value = 0;

    // Always populate the NEW IRR form from the ordinance's current departments
    const lead = ord.departments.find((d: any) => d.pivot.role === 'lead');
    irrForm.lead_office_id = lead ? lead.id : '';
    deptIrrSearchQuery.value = lead ? lead.name : '';

    const supports = ord.departments.filter((d: any) => d.pivot.role === 'support');
    irrForm.support_offices = supports.length > 0
        ? supports.map((s: any) => ({ id: s.id, name: s.name }))
        : [{ id: '', name: '' }];

    // Mirror external institutions from the ordinance's committee — same as openEditDialog
    const e = {
        members: Array.from({ length: 4 }, createEmptyMember) as any[],
        ngos: Array.from({ length: 4 }, createEmptyMember) as any[],
        others: Array.from({ length: 4 }, createEmptyMember) as any[]
    };

    if (ord.committees && ord.committees.length > 0) {
        const committee = ord.committees.find((c: any) => c.type === 'ordinance_sponsors');
        if (committee && committee.members) {
            let extMemberIdx = 0, ngoIdx = 0, otherIdx = 0;
            committee.members.forEach((m: any) => {
                const role = m.pivot?.role;
                const memberObj = { id: m.id, pmis_id: m.pmis_id, name: m.name };
                if (role === 'External Member') e.members[extMemberIdx++] = memberObj;
                else if (role === 'NGO Partner') e.ngos[ngoIdx++] = memberObj;
                else if (role === 'Other Organization') e.others[otherIdx++] = memberObj;
            });
        }
    }

    irrForm.external_institutions = {
        members: parseFixed4MemberObjects(e.members),
        ngos: parseFixed4MemberObjects(e.ngos),
        others: parseFixed4MemberObjects(e.others)
    };

    // loadIrr is no longer called — IRR cards just use allIrrs directly
    const firstIrr = allIrrs.value.length > 0 ? allIrrs.value[0] : null;
    if (firstIrr) {
        selectedIrr.value = firstIrr;
        selectedIrrId.value = firstIrr.id;
    }

    showIrrDialog.value = true;
}

function submitIrrForm() {
    irrForm.transform((data) => {
        return {
            ...data,
            support_offices: data.support_offices.map(o => o.id).filter(id => id !== ''),
            external_institutions: {
                members: data.external_institutions.members.filter((e: any) => e.name && e.name.trim() !== ''),
                ngos: data.external_institutions.ngos.filter((e: any) => e.name && e.name.trim() !== ''),
                others: data.external_institutions.others.filter((e: any) => e.name && e.name.trim() !== '')
            }
        };
    }).post(route('ordinance.irr.store', selectedRecord.value.id), {
        forceFormData: true,
        onSuccess: () => {
            showIrrDialog.value = false;
            irrForm.reset();
            notyf.success('IRR Added Successfully');
        },
    });
}

function confirmDisableIrr(irrId: number) {
    selectedIrrId.value = irrId;
    disableIrrForm.reset();
    showDisableIrrDialog.value = true;
}

function submitDisableIrr() {
    disableIrrForm.post(route('ordinance.irr.disable', selectedIrrId.value!), {
        onSuccess: (page: any) => {
            showDisableIrrDialog.value = false;
            disableIrrForm.reset();
            selectedIrrId.value = null;
            // Refresh allIrrs from the updated ordinance in the page props
            const updatedOrd = (page.props as any).ordinances?.data?.find((o: any) => o.id === selectedRecord.value?.id);
            if (updatedOrd) {
                allIrrs.value = Array.isArray(updatedOrd.implementing_rules) ? updatedOrd.implementing_rules : [];
            }
            notyf.success('IRR Disabled');
        },
        onError: () => notyf.error('Failed to disable IRR.'),
    });
}

const formatDate = (dateString: string | null) => dateString ? new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
const formatAuditDate = (date: string) => new Date(date).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

const getAuthorLabel = (ord: any) => {
    if (ord.committees && ord.committees.length > 0) {
        const committee = ord.committees.find((c: any) => c.type === 'ordinance_sponsors');
        if (committee && committee.members) {
            const author = committee.members.find((m: any) => m.pivot.role === 'Primary Author' || m.pivot.role === 'Introduced By');
            if (author) return author.name;
        }
    }
    return 'City Council';
};

const getLeadOffice = (depts: any[]) => depts.find(d => d.pivot.role === 'lead')?.name || '—';
const getChangedFields = (audit: any) => {
    if (audit.action === 'Created') return 'Initial Record Created';
    if (audit.action === 'Deleted') return 'Record Deleted';
    let newVals = audit.new_values;
    if (typeof newVals === 'string') { try { newVals = JSON.parse(newVals); } catch (e) { return 'Updated details'; } }
    if (newVals) {
        const changes = Object.keys(newVals).filter(key => key !== 'updated_at').map(key => key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
        if (changes.length === 0) return 'Updated metadata';
        return 'Modified: ' + changes.join(', ');
    }
    return 'Updated record';
};

const breadcrumbs = [{ title: 'Ordinances', href: '/ordinances' }];
</script>

<template>
    <Head title="Ordinances" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-gray-50/50 p-4 md:p-8">

            <div class="flex flex-col items-center justify-between gap-4 rounded-xl border bg-white p-4 shadow-sm xl:flex-row flex-wrap">
                <div class="relative w-full md:max-w-md xl:flex-1">
                    <Search class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input v-model="searchTerm" type="text" placeholder="Search Ord. No. or Title..." class="block w-full rounded-lg border border-gray-300 bg-gray-50 hover:bg-white focus:bg-white py-2 pr-10 pl-10 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-colors" />
                    <button v-if="searchTerm || filterYear !== 'all' || filterActive !== 'all'" @click="clearSearch" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors" title="Clear Filters">×</button>
                </div>

                <div class="flex items-center justify-between xl:justify-end gap-4 w-full xl:w-auto flex-wrap sm:flex-nowrap">
                    <div class="flex items-center gap-2 bg-gray-50 p-1 rounded-lg border border-gray-200 flex-1 sm:flex-none">
                        <Filter class="w-4 h-4 text-gray-400 ml-2 hidden sm:block" />
                        <select v-model="filterYear" class="border-0 bg-transparent text-xs font-semibold text-gray-600 focus:ring-0 cursor-pointer py-1.5 pl-2 pr-6 border-r border-gray-200 w-full sm:w-auto outline-none">
                            <option value="all">Years</option>
                            <option v-for="y in available_years" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <select v-model="filterActive" class="border-0 bg-transparent text-xs font-semibold text-gray-600 focus:ring-0 cursor-pointer py-1.5 pl-2 pr-6 w-full sm:w-auto outline-none">
                            <option value="all">Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <button v-if="$page.props.auth.user.role !== 'user'" class="flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-blue-700 transition shrink-0" @click="openAddDialog">
                        <Plus class="h-4 w-4" /> Encode Ordinance
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="bg-gray-50 text-xs text-gray-600 uppercase border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 font-semibold">Ord. Tracking</th>
                                <th class="px-6 py-4 font-semibold w-1/3">Subject Title</th>
                                <th class="px-6 py-4 font-semibold">Lead Office</th>
                                <th class="px-6 py-4 font-semibold text-center">Status / IRR</th>
                                <th class="px-6 py-4 text-center font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-if="isLoading"><tr v-for="n in 5" :key="n" class="animate-pulse"><td colspan="5" class="px-6 py-4"><div class="h-4 bg-gray-100 rounded w-full"></div></td></tr></template>

                            <template v-else-if="ordinances.data.length > 0">
                                <tr v-for="ord in ordinances.data" :key="ord.id" class="transition-colors border-l-4 border-b border-gray-200" :class="ord.is_active ? 'hover:bg-gray-50 border-l-transparent' : 'bg-gray-50/60 opacity-80 border-l-red-400'">

                                    <td class="px-6 py-4 align-top">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-mono font-bold text-blue-600 text-base">{{ ord.ordinance_number }}</span>
                                            <div class="text-[10px] text-gray-500 mt-1 flex flex-col gap-0.5 font-bold tracking-wide uppercase">
                                                <span>App: {{ formatDate(ord.date_approved) }}</span>
                                                <span>Eff: {{ formatDate(ord.effectivity_date) }}</span>
                                                <span class="text-blue-500">Imp: {{ formatDate(ord.date_enacted) }}</span>
                                            </div>
                                            <div v-if="!ord.is_active" class="mt-1 inline-flex items-center gap-1 w-fit px-2 py-0.5 rounded bg-red-100 text-red-600 text-[10px] font-bold uppercase border border-red-200">
                                                <XCircle class="w-3 h-3" /> Inactive
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 align-top">
                                        <div class="text-gray-900 font-semibold mb-1 leading-snug">{{ ord.title }}</div>

                                        <div class="text-[10px] text-gray-500 font-medium uppercase mb-2 flex items-center gap-1">
                                            <UserCheck class="w-3 h-3" /> Author: {{ getAuthorLabel(ord) }}
                                        </div>

                                        <div v-if="ord.parent_ordinance" class="mb-2 flex items-center gap-1 text-xs text-amber-700 font-bold bg-amber-50 px-2 py-1 rounded w-fit border border-amber-100 uppercase">
                                            <AlertCircle class="w-3.5 h-3.5" /> {{ ord.relationship_type }}: {{ ord.parent_ordinance.ordinance_number }}
                                        </div>
                                        <div v-if="ord.remarks" class="mt-2 pl-3 border-l-2 border-gray-300 bg-gray-50 py-1.5 pr-3 rounded-r">
                                            <p class="text-xs text-gray-600 italic">"{{ ord.remarks }}"</p>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-xs text-gray-600 align-top">
                                        <span class="flex items-center gap-1 mt-1">
                                            <Building2 class="w-4 h-4 text-gray-400" />
                                            {{ getLeadOffice(ord.departments) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 align-top text-center">
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-[10px] font-bold text-gray-600 uppercase border border-gray-200 mb-2">{{ ord.is_active ? 'Active' : 'Inactive' }}</span>
                                        <div v-if="ord.implementing_rules?.length > 0" class="flex flex-col gap-1 items-center">
                                            <span class="text-[10px] font-bold text-blue-500 uppercase tracking-widest">{{ ord.implementing_rules.length }} IRRs Attached</span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center align-middle">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <div class="flex items-center gap-2">
                                                <a v-if="ord.file_url" :href="ord.file_url" target="_blank" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View PDF">
                                                    <Eye class="w-4 h-4" />
                                                </a>
                                                <button v-if="$page.props.auth.user.role !== 'user'" @click="openEditDialog(ord)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                                    <Pencil class="w-4 h-4" />
                                                </button>
                                            </div>
                                            <button v-if="$page.props.auth.user.role !== 'user'" @click="openIrrDialog(ord)" class="text-[10px] font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-100 px-2 py-1 rounded transition w-full flex items-center justify-center gap-1">
                                                <BookOpen class="w-3 h-3" /> Manage IRR
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else><td colspan="5" class="px-6 py-12 text-center text-gray-500"><div class="flex flex-col items-center justify-center"><div class="bg-gray-50 p-4 rounded-full mb-3"><Search class="h-6 w-6 text-gray-400" /></div><p class="text-base font-medium text-gray-900">No Ordinances found</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
                 <div v-if="ordinances.last_page > 1" class="flex items-center justify-between border-t bg-gray-50 px-6 py-3">
                    <p class="text-sm text-gray-600">Showing <span class="font-medium">{{ ordinances.from }}</span>–<span class="font-medium">{{ ordinances.to }}</span> of <span class="font-medium">{{ ordinances.total }}</span></p>
                    <div class="flex gap-1"><button v-for="(link, index) in ordinances.links.slice(1, -1)" :key="index" @click="goToPage(String(link.url))" :disabled="!link.url" class="rounded-md px-3 py-1 text-sm transition" :class="[link.active ? 'bg-blue-600 font-medium text-white shadow' : 'border bg-white text-gray-600 hover:bg-gray-100']"><span v-html="link.label"></span></button></div>
                </div>
            </div>

            <Transition name="fade">
                <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto custom-scrollbar" @click="activeSuggestion = null; showParentDropdown = false">

                        <div class="flex items-center justify-between mb-6 border-b pb-4">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2"><Gavel class="w-5 h-5 text-blue-600" /> {{ isEdit ? 'Edit Ordinance' : 'Encode Ordinance' }}</h2>
                                <p class="text-xs text-gray-500 mt-1">{{ isEdit ? 'Update details.' : 'Create a new continuous record.' }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <button v-if="isEdit" @click="activeModalTab = activeModalTab === 'history' ? 'details' : 'history'" class="text-xs font-bold text-blue-600 hover:underline">
                                    {{ activeModalTab === 'history' ? 'Back to Edit' : 'View Audit History' }}
                                </button>
                                <button @click="showDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center transition">×</button>
                            </div>
                        </div>

                        

                        <div v-if="activeModalTab === 'history'" class="py-4">
                            <div v-if="selectedRecord?.audits?.length > 0" class="space-y-6 relative border-l-2 border-gray-100 ml-4 pl-6">
                                <div v-for="audit in selectedRecord.audits" :key="audit.id" class="relative">
                                    <div class="absolute -left-[33px] top-1.5 h-3.5 w-3.5 rounded-full border-2 border-white" :class="audit.action === 'Created' ? 'bg-green-500' : 'bg-blue-500'"></div>
                                    <div class="flex flex-col gap-1">
                                        <p class="text-sm font-bold text-gray-900">{{ audit.action }} by <span class="text-blue-600">{{ audit.user?.name || 'Unknown' }}</span></p>
                                        <p class="text-xs text-gray-500">{{ getChangedFields(audit) }}</p>
                                        <span class="text-[10px] text-gray-400 font-mono">{{ formatAuditDate(audit.created_at) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-10">
                                <Clock class="w-10 h-10 text-gray-200 mx-auto mb-2" />
                                <p class="text-sm text-gray-400">No history found.</p>
                            </div>
                        </div>

                        <form v-else @submit.prevent="submitForm" class="space-y-8">

                            <div class="bg-gray-50/50 p-5 rounded-xl border border-gray-100 space-y-5 relative">
                                <div class="absolute -top-3 left-4 bg-white px-2 text-[10px] font-bold text-blue-600 uppercase tracking-widest border border-blue-100 rounded-full">1. Metadata & Status</div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-5 pt-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Ordinance Number <span class="text-red-500">*</span></label>
                                        <input v-model="form.ordinance_number" type="text" placeholder="e.g., Ord. No. 2026-05" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" required />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Status <span class="text-red-500">*</span></label>
                                        <select v-model="form.status_id" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" required>
                                            <option value="" disabled>Select Status</option>
                                            <option v-for="status in selectableStatuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-4 pt-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Subject Title <span class="text-red-500">*</span></label>
                                        <textarea v-model="form.title" rows="2" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Describe the ordinance..." required></textarea>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Subject Matter</label>
                                        <textarea v-model="form.subject_matter" rows="2" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Brief summary of the subject matter..."></textarea>
                                    </div>

                                    <div class="border-t border-gray-100 pt-4 mt-4">
                                        <label class="mb-1 block text-xs font-bold text-blue-800 uppercase">Subject Code <span class="text-gray-400 font-normal">(Optional)</span></label>
                                        <select v-model="form.ordinance_code_id" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">None</option>
                                            <option v-for="code in ordinance_codes" :key="code.id" :value="code.id">
                                                {{ code.name }}
                                            </option>
                                        </select>
                                        <p class="text-[10px] text-gray-400 mt-1 italic">Select if this ordinance amends or falls under a specific city code (e.g., The Traffic Code).</p>
                                    </div>
                                </div>

                                <Transition name="fade">
                                    <div v-if="isEdit && selectedRecord?.amendments?.length > 0" class="p-4 bg-blue-50/50 rounded-xl border border-blue-100 space-y-2 mt-4">
                                        <div class="flex items-center gap-2 text-xs font-bold text-blue-800 uppercase tracking-widest">
                                            <Info class="w-4 h-4" /> Ordinance Superseded / Amended
                                        </div>
                                        <p class="text-sm text-blue-900">
                                            This ordinance has been affected by:
                                            <span class="font-bold bg-white px-2 py-0.5 rounded border border-blue-200 ml-1">
                                                {{ selectedRecord.amendments[0].ordinance_number }}
                                            </span>
                                        </p>
                                    </div>
                                </Transition>

                                <Transition name="fade">
                                    <div v-if="isAmendmentMode" class="p-4 bg-amber-50/50 rounded-xl border border-amber-100 space-y-4 mt-2">
                                        <div class="flex items-center gap-2 text-xs font-bold text-amber-800 uppercase tracking-widest mb-1">
                                            <Info class="w-4 h-4" /> Historical Tracker
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="relative">
                                                <label class="mb-1 block text-xs font-bold text-amber-900 uppercase">Select Previous Amendment</label>
                                                <div class="relative">
                                                    <input type="text" v-model="parentSearchQuery" @focus.stop="showParentDropdown = true" @click.stop="showParentDropdown = true" class="w-full rounded-lg border border-amber-200 text-sm px-3 py-2 pr-8 bg-white outline-none focus:ring-2 focus:ring-amber-500" placeholder="Search Ord. Number or Title..." />
                                                    <button v-if="form.amends_ordinance_id || parentSearchQuery" @click="clearParent" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-amber-400 hover:text-amber-600 transition"><XCircle class="w-4 h-4" /></button>
                                                </div>
                                                <div v-if="showParentDropdown && filteredParents.length > 0" class="absolute z-50 w-full mt-1 bg-white border border-amber-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="ex in filteredParents" :key="ex.id" @mousedown.prevent="selectParent(ex)" class="px-3 py-2 hover:bg-amber-50 cursor-pointer border-b border-amber-50 last:border-0 text-sm transition-colors">
                                                        <div class="font-bold text-amber-900">{{ ex.ordinance_number }}</div>
                                                        <div class="text-xs text-gray-500 truncate">{{ ex.title }}</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-if="form.amends_ordinance_id">
                                                <label class="mb-1 block text-xs font-bold text-amber-900 uppercase">Amendment Type</label>
                                                <select v-model="form.relationship_type" class="w-full rounded-lg border border-amber-200 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-amber-500">
                                                    <option value="Partial Amendment">Partial Amendment</option>
                                                    <option value="Full Amendment">Full Amendment</option>
                                                    <option value="Repeals">Repeals</option>
                                                    <option value="Supersedes">Supersedes</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </Transition>
                            </div>

                            <div class="bg-blue-50/30 p-5 rounded-xl border border-blue-100/80 space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-b border-blue-100 pb-5">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-blue-600 uppercase">Date of Approval <span class="text-red-500">*</span></label>
                                        <input v-model="form.date_approved" type="date" class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" required />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-blue-600 uppercase">Date of Effectivity <span class="text-red-500">*</span></label>
                                        <input v-model="form.effectivity_date" :min="form.date_approved" type="date" class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" required />
                                        <p class="text-[9px] text-blue-500 mt-1 italic">Must be on or after Approval</p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-blue-600 uppercase">Implementation (Enacted) <span class="text-red-500">*</span></label>
                                        <input v-model="form.date_enacted" :min="form.effectivity_date" type="date" class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" required />
                                        <p class="text-[9px] text-blue-500 mt-1 italic">Must be on or after Effectivity</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="relative">
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Presiding Officer <span class="text-red-500">*</span></label>
                                        <input v-model="form.presiding_officer" type="text"
                                            @focus.stop="activeSuggestion = 'presiding_officer'"
                                            @click.stop="activeSuggestion = 'presiding_officer'"
                                            @input="activeSuggestion = 'presiding_officer'"
                                            placeholder="e.g., City Vice Mayor" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 bg-white" required />

                                        <div v-if="activeSuggestion === 'presiding_officer' && getSuggestions(form.presiding_officer, null, 'presiding_officer').length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                            <div v-for="(person, idx) in getSuggestions(form.presiding_officer, null, 'presiding_officer')" :key="idx" @mousedown.prevent="selectPerson('presiding_officer', person)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-sm font-bold text-gray-800">
                                                <div class="text-sm font-bold text-gray-800">
                                                    {{ person.name }}
                                                    <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                </div>
                                                <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="relative">
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Attested By</label>
                                        <input v-model="form.attested_by" type="text"
                                            @focus.stop="activeSuggestion = 'attested_by'"
                                            @click.stop="activeSuggestion = 'attested_by'"
                                            @input="activeSuggestion = 'attested_by'"
                                            placeholder="e.g., SP Secretary" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 bg-white" />

                                        <div v-if="activeSuggestion === 'attested_by' && getSuggestions(form.attested_by, null, 'attested_by').length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                            <div v-for="(person, idx) in getSuggestions(form.attested_by, null, 'attested_by')" :key="idx" @mousedown.prevent="selectPerson('attested_by', person)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-sm font-bold text-gray-800">
                                                <div class="text-sm font-bold text-gray-800">
                                                    {{ person.name }}
                                                    <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                </div>
                                                <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="relative">
                                        <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Approved By</label>
                                        <input v-model="form.approved_by" type="text"
                                            @focus.stop="activeSuggestion = 'approved_by'"
                                            @click.stop="activeSuggestion = 'approved_by'"
                                            @input="activeSuggestion = 'approved_by'"
                                            placeholder="e.g., City Mayor" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 bg-white" />

                                        <div v-if="activeSuggestion === 'approved_by' && getSuggestions(form.approved_by, null, 'approved_by').length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                            <div v-for="(person, idx) in getSuggestions(form.approved_by, null, 'approved_by')" :key="idx" @mousedown.prevent="selectPerson('approved_by', person)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-sm font-bold text-gray-800">
                                                <div class="text-sm font-bold text-gray-800">
                                                    {{ person.name }}
                                                    <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                </div>
                                                <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50/50 p-5 rounded-xl border border-gray-100 space-y-6 relative animate-in fade-in duration-300">
                                <div class="absolute -top-3 left-4 bg-white px-2 text-[10px] font-bold text-blue-600 uppercase tracking-widest border border-blue-100 rounded-full">2. Authorship & Sponsorship</div>

                                <div class="flex items-center gap-6 border-b border-gray-200 pt-2">
                                    <button type="button" @click="activeAuthorTab = 'authors'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeAuthorTab === 'authors' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Authorship</button>
                                    <button type="button" @click="activeAuthorTab = 'committees'" class="pb-3 px-1 text-xs font-bold uppercase tracking-wider border-b-2 transition-all" :class="activeAuthorTab === 'committees' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">Sponsorship Committee</button>
                                </div>

                                <div v-show="activeAuthorTab === 'authors'" class="animate-in fade-in duration-300">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-3">

                                        <div>
                                            <div class="mb-1 w-fit">
                                                <select v-model="form.author_details.is_primary_author" class="block text-xs font-bold text-blue-800 uppercase tracking-widest bg-transparent border-0 px-0 py-0 pr-5 focus:ring-0 cursor-pointer hover:text-blue-900 transition-colors">
                                                    <option :value="true">PRIMARY AUTHOR</option>
                                                    <option :value="false">INTRODUCED BY</option>
                                                </select>
                                            </div>
                                            <div class="relative w-full">
                                                <input
                                                    v-model="form.author_details.introduced_by.name"
                                                    @focus.stop="activeSuggestion = 'introduced'"
                                                    @click.stop="activeSuggestion = 'introduced'"
                                                    @input="activeSuggestion = 'introduced'"
                                                    type="text" class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 bg-white" placeholder="Search or type name..." />

                                                <div v-if="activeSuggestion === 'introduced' && getSuggestions(form.author_details.introduced_by.name, null, 'introduced_by').length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(form.author_details.introduced_by.name, null, 'introduced_by')" :key="idx" @mousedown.prevent="selectPerson('introduced_by', person)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-sm font-bold text-gray-800">
                                                        <div class="text-sm font-bold text-gray-800">
                                                            {{ person.name }}
                                                            <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                        </div>
                                                        <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-xs font-bold text-blue-800 uppercase">Co-Authors</label>
                                            <div class="space-y-2">
                                                <div v-for="(member, index) in form.author_details.co_authors" :key="'ca_'+index" class="relative flex items-center gap-2">
                                                    <span class="text-[10px] font-bold text-blue-300 w-3 text-right">{{ index + 1 }}.</span>
                                                    <div class="relative flex-1">
                                                        <input
                                                            v-model="member.name"
                                                            @focus.stop="activeSuggestion = 'co_author_' + index"
                                                            @click.stop="activeSuggestion = 'co_author_' + index"
                                                            @input="activeSuggestion = 'co_author_' + index"
                                                            type="text" class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 bg-white" placeholder="Search or type name..." />

                                                        <div v-if="activeSuggestion === 'co_author_' + index && getSuggestions(member.name, null, 'co_authors', index).length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                            <div v-for="(person, idx) in getSuggestions(member.name, null, 'co_authors', index)" :key="idx" @mousedown.prevent="selectPerson('co_authors', person, index)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-sm font-bold text-gray-800">
                                                                <div class="text-sm font-bold text-gray-800">
                                                                    {{ person.name }}
                                                                    <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                                </div>
                                                                <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button @click="removeMember('co_authors', index)" type="button" class="text-red-300 hover:text-red-500 transition p-1" v-if="form.author_details.co_authors.length > 1">
                                                        <XCircle class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </div>
                                            <button type="button" @click="addMember('co_authors')" class="mt-2 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-blue-600 hover:text-blue-800 transition ml-5">
                                                <Plus class="w-3.5 h-3.5" /> Add Row
                                            </button>
                                        </div>

                                    </div>
                                </div>

                                <div v-show="activeAuthorTab === 'committees'" class="animate-in fade-in duration-300">
                                    <!-- Registry select moved next to Sponsorship Committee input below -->

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-bold text-blue-800 uppercase">Sponsorship Committee</label>
                                                <div class="relative w-full flex items-center gap-2">
                                                    <div class="relative flex-1">
                                                        <div class="relative">
                                                            <input
                                                                v-model="sponsorshipSearchQuery"
                                                                @focus.stop="activeSuggestion = 'sponsorship_committee'"
                                                                @click.stop="activeSuggestion = 'sponsorship_committee'"
                                                                @input="activeSuggestion = 'sponsorship_committee'; form.selected_registry_id = ''"
                                                                type="text" class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 outline-none bg-white" placeholder="Search and select sponsorship committee..." />

                                                            <button v-if="(form.author_details.sponsorship_committee && form.author_details.sponsorship_committee.name) || sponsorshipSearchQuery" @click.prevent="clearSponsorship" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                                                <XCircle class="w-4 h-4" />
                                                            </button>

                                                            <div v-if="activeSuggestion === 'sponsorship_committee' && filteredCommitteeRegistries.length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                                <div v-for="(c, idx) in filteredCommitteeRegistries" :key="c.id" @mousedown.prevent="selectRegistry(c)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-sm font-medium text-gray-800">
                                                                    <div class="text-sm font-medium text-gray-800">{{ c.name }}</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        <div>
                                            <label class="mb-2 block text-xs font-bold text-blue-800 uppercase">Committee Members</label>
                                            <div class="space-y-2">
                                                <div v-for="(member, index) in form.author_details.committee_members" :key="'cm_'+index" class="relative flex items-center gap-2">
                                                    <span class="text-[10px] font-bold text-blue-300 w-3 text-right">{{ index + 1 }}.</span>
                                                    <div class="relative flex-1">
                                                        <input
                                                            v-model="member.name"
                                                            @focus.stop="activeSuggestion = 'committee_members_' + index"
                                                            @click.stop="activeSuggestion = 'committee_members_' + index"
                                                            @input="activeSuggestion = 'committee_members_' + index"
                                                            type="text" class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 bg-white" placeholder="Search or type name..." />

                                                        <div v-if="activeSuggestion === 'committee_members_' + index && getSuggestions(member.name, null, 'committee_members', index).length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                            <div v-for="(person, idx) in getSuggestions(member.name, null, 'committee_members', index)" :key="idx" @mousedown.prevent="selectPerson('committee_members', person, index)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-sm font-bold text-gray-800">
                                                                <div class="text-sm font-bold text-gray-800">
                                                                    {{ person.name }}
                                                                    <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                                </div>
                                                                <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button @click="removeMember('committee_members', index)" type="button" class="text-red-300 hover:text-red-500 transition p-1" v-if="form.author_details.committee_members.length > 1">
                                                        <XCircle class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </div>
                                            <button type="button" @click="addMember('committee_members')" class="mt-2 flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-blue-600 hover:text-blue-800 transition ml-5">
                                                <Plus class="w-3.5 h-3.5" /> Add Row
                                            </button>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="bg-blue-50/50 p-5 rounded-xl border border-blue-100 space-y-6 relative">
                                <div class="absolute -top-3 left-4 bg-white px-2 text-[10px] font-bold text-blue-600 uppercase tracking-widest border border-blue-100 rounded-full">3. Implementing Offices</div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-blue-800 uppercase">Lead Implementing Office</label>
                                        <div class="relative w-full">
                                            <input
                                                type="text" v-model="implementingSearchQuery"
                                                @focus.stop="activeSuggestion = 'lead_office'" @click.stop="activeSuggestion = 'lead_office'"
                                                @input="activeSuggestion = 'lead_office'; form.lead_office_id = ''"
                                                class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 pr-8 outline-none focus:ring-2 focus:ring-blue-500 bg-white" placeholder="Search department..." />

                                            <button v-if="form.lead_office_id || implementingSearchQuery" @click.prevent="clearLeadOffice" type="button" class="absolute right-2 top-[10px] text-gray-400 hover:text-gray-600 transition">
                                                <XCircle class="w-4 h-4" />
                                            </button>

                                            <div v-if="activeSuggestion === 'lead_office' && filteredLeadOffices.length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="dept in filteredLeadOffices" :key="dept.id" @mousedown.prevent="selectLeadOffice(dept, form)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm font-medium text-gray-800">
                                                    {{ dept.name }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="text-xs font-bold text-blue-800 uppercase">Supporting Offices</label>
                                            <span class="text-[10px] font-bold text-white bg-blue-500 px-2 py-0.5 rounded-full shadow-sm">{{ form.support_office_ids.length }} Selected</span>
                                        </div>
                                        <div class="relative mb-2">
                                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                                            <input v-model="supportSearchQuery" type="text" placeholder="Filter offices..." class="w-full pl-9 pr-3 py-1.5 text-sm rounded-lg border border-blue-200 outline-none focus:ring-2 focus:ring-blue-500 bg-white" />
                                        </div>
                                        <div class="flex flex-col gap-1 max-h-40 overflow-y-auto p-2 bg-white rounded-lg border border-blue-100 custom-scrollbar">
                                            <label v-for="dept in filteredSupportOffices" :key="dept.id" class="flex items-center gap-2 cursor-pointer hover:bg-blue-50 p-1.5 rounded transition">
                                                <input type="checkbox" :value="dept.id" v-model="form.support_office_ids" class="rounded text-blue-600 focus:ring-blue-500 h-4 w-4 border-gray-300" />
                                                <span class="text-xs text-gray-700 leading-tight">{{ dept.name }}</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="border-t border-blue-100 pt-5 mt-5">
                                    <label class="mb-3 block text-xs font-bold text-blue-800 uppercase">External Institutions / Partners</label>

                                    <div class="flex border-b border-gray-200 mb-4">
                                        <button type="button" @click="activeOrdinanceExternalTab = 'members'" :class="activeOrdinanceExternalTab === 'members' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500'" class="px-4 py-2 text-[10px] font-bold uppercase tracking-widest transition-colors">Members</button>
                                        <button type="button" @click="activeOrdinanceExternalTab = 'ngos'" :class="activeOrdinanceExternalTab === 'ngos' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500'" class="px-4 py-2 text-[10px] font-bold uppercase tracking-widest transition-colors">NGO's</button>
                                        <button type="button" @click="activeOrdinanceExternalTab = 'others'" :class="activeOrdinanceExternalTab === 'others' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500'" class="px-4 py-2 text-[10px] font-bold uppercase tracking-widest transition-colors">Other Organizations</button>
                                    </div>

                                    <div class="space-y-2">
                                        <div v-for="(member, index) in form.external_institutions[activeOrdinanceExternalTab]" :key="activeOrdinanceExternalTab + index" class="relative flex items-center gap-2">
                                            <span class="text-[10px] font-bold text-blue-300 w-3 text-right">{{ index + 1 }}.</span>
                                            <div class="relative flex-1">
                                                <input v-model="member.name" type="text"
                                                    @focus.stop="activeSuggestion = 'external_' + activeOrdinanceExternalTab + '_' + index"
                                                    @click.stop="activeSuggestion = 'external_' + activeOrdinanceExternalTab + '_' + index"
                                                    @input="activeSuggestion = 'external_' + activeOrdinanceExternalTab + '_' + index"
                                                    class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" placeholder="Type name..." />

                                                <div v-if="activeSuggestion === 'external_' + activeOrdinanceExternalTab + '_' + index && getSuggestions(member.name, 'External', 'external_' + activeOrdinanceExternalTab, index).length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <div v-for="(person, idx) in getSuggestions(member.name, 'External', 'external_' + activeOrdinanceExternalTab, index)" :key="idx" @mousedown.prevent="selectPerson('external_' + activeOrdinanceExternalTab, person, index)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-sm font-bold text-gray-800">
                                                        <div class="text-sm font-bold text-gray-800">
                                                            {{ person.name }}
                                                            <span v-if="getDeptCode(person.title)" class="text-xs text-gray-500 font-normal ml-1">({{ getDeptCode(person.title) }})</span>
                                                        </div>
                                                        <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" @click="removeMember('external_' + activeOrdinanceExternalTab, index)"
                                                    class="text-red-300 hover:text-red-500 transition-colors" v-if="form.external_institutions[activeOrdinanceExternalTab].length > 1">
                                                <XCircle class="w-4 h-4" />
                                            </button>
                                        </div>
                                        <button type="button" @click="addMember('external_' + activeOrdinanceExternalTab)"
                                                class="mt-2 text-[10px] font-bold uppercase text-blue-600 hover:text-blue-800 ml-5 transition-colors">
                                            + Add Row
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 border-t pt-5">
                                <label class="mb-2 block text-xs font-bold text-gray-600 uppercase">Document (PDF)</label>
                                <input type="file" @change="handleFileChange" accept="application/pdf" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"/>
                                <p class="mt-1 text-[10px] text-gray-400 italic">{{ isEdit ? 'Leave empty to keep existing PDF.' : 'Optional: Upload the signed PDF.' }}</p>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100 mt-8">
                                <button type="button" @click="showDialog = false" class="rounded-lg bg-gray-100 px-5 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-200 transition-colors">Cancel</button>
                                <button type="submit" :disabled="form.processing" class="flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-bold text-white shadow hover:bg-blue-700 transition-colors disabled:opacity-70">
                                    <CheckCircle2 v-if="!form.processing" class="w-4 h-4" /> {{ form.processing ? 'Saving...' : 'Save Record' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- IRR Modal -->
            <Transition name="fade">
                <div v-if="showIrrDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto custom-scrollbar">
                        <div class="flex items-center justify-between mb-4 border-b pb-3">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2"><BookOpen class="w-5 h-5 text-blue-600" /> Manage IRR</h3>
                                <p class="text-xs text-gray-500 mt-1">Ordinance: {{ selectedRecord?.ordinance_number || selectedRecord?.title }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button @click="showIrrDialog = false" class="text-gray-400 hover:text-gray-600 bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center transition">×</button>
                            </div>
                        </div>

                        <form @submit.prevent="submitIrrForm" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Status</label>
                                    <select v-model="irrForm.status" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500">
                                        <option v-for="s in irrStatuses" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Lead Implementing Office</label>
                                    <div class="relative">
                                        <input type="text" v-model="deptIrrSearchQuery" @input="activeSuggestion = 'irr_lead'; irrForm.lead_office_id = ''" @focus.stop="activeSuggestion='irr_lead'" class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search department..." />
                                        <div v-if="activeSuggestion === 'irr_lead' && getIrrLeadSuggestions(deptIrrSearchQuery).length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                            <div v-for="d in getIrrLeadSuggestions(deptIrrSearchQuery)" :key="d.id" @mousedown.prevent="selectIrrLeadOffice(d, irrForm)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm">{{ d.name }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">Supporting Offices</label>
                                <div class="space-y-2 mt-2">
                                    <div v-for="(o, idx) in irrForm.support_offices" :key="'s_'+idx" class="flex items-center gap-2">
                                        <input type="text" v-model="o.name" @input="supportSearchQuery = o.name; activeSuggestion = 'irr_support_'+idx" @focus.stop="activeSuggestion='irr_support_'+idx" class="flex-1 rounded-lg border border-gray-300 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search department..." />
                                        <button type="button" @click="irrForm.support_offices.splice(idx,1)" class="text-red-300 hover:text-red-500 transition p-1" v-if="irrForm.support_offices.length > 1">
                                            <XCircle class="w-4 h-4" />
                                        </button>

                                        <div v-if="activeSuggestion === 'irr_support_'+idx && getIrrDeptSuggestions(o.name, idx).length > 0" class="absolute z-30 mt-10 w-64 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                            <div v-for="d in getIrrDeptSuggestions(o.name, idx)" :key="d.id" @mousedown.prevent="selectIrrSupportOffice(d, idx)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm">{{ d.name }}</div>
                                        </div>
                                    </div>
                                    <button type="button" @click="irrForm.support_offices.push({ id: '', name: '' })" class="mt-2 text-[10px] font-bold uppercase text-blue-600">+ Add Row</button>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-500 uppercase">External Institutions / Partners</label>
                                <div class="flex gap-2 mt-2">
                                    <button type="button" @click.prevent="activeIrrExternalTab = 'members'" :class="activeIrrExternalTab === 'members' ? 'text-blue-600 font-bold' : 'text-gray-500'" class="px-3 py-1 rounded">Members</button>
                                    <button type="button" @click.prevent="activeIrrExternalTab = 'ngos'" :class="activeIrrExternalTab === 'ngos' ? 'text-blue-600 font-bold' : 'text-gray-500'" class="px-3 py-1 rounded">NGO's</button>
                                    <button type="button" @click.prevent="activeIrrExternalTab = 'others'" :class="activeIrrExternalTab === 'others' ? 'text-blue-600 font-bold' : 'text-gray-500'" class="px-3 py-1 rounded">Other Orgs</button>
                                </div>

                                <div class="space-y-2 mt-3">
                                    <div v-for="(member, index) in (irrForm.external_institutions as any)[activeIrrExternalTab]" :key="activeIrrExternalTab + index" class="relative flex items-center gap-2">
                                        <span class="text-[10px] font-bold text-blue-300 w-3 text-right">{{ Number(index) + 1 }}.</span>
                                        <div class="flex-1">
                                            <input v-model="member.name" type="text" @focus.stop="activeSuggestion = 'irr_external_' + activeIrrExternalTab + '_' + index" @click.stop="activeSuggestion = 'irr_external_' + activeIrrExternalTab + '_' + index" @input="activeSuggestion = 'irr_external_' + activeIrrExternalTab + '_' + index" class="w-full rounded-lg border border-blue-200 text-sm px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-blue-500" placeholder="Type name..." />

                                            <div v-if="activeSuggestion === 'irr_external_' + activeIrrExternalTab + '_' + String(index) && getSuggestions(member.name, 'External', 'irr_external_' + activeIrrExternalTab, Number(index)).length > 0" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div v-for="(person, idx) in getSuggestions(member.name, 'External', 'irr_external_' + activeIrrExternalTab, Number(index))" :key="idx" @mousedown.prevent="selectPerson('irr_external_' + activeIrrExternalTab, person, Number(index))" class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 text-sm font-bold text-gray-800">
                                                    <div class="text-sm font-bold text-gray-800">{{ person.name }}</div>
                                                    <div class="text-[10px] font-bold uppercase tracking-wider mt-0.5" :class="person.type === 'Internal' ? 'text-blue-600' : 'text-green-600'">{{ person.type }}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" @click="removeMember('irr_external_' + activeIrrExternalTab, Number(index))" class="text-red-300 hover:text-red-500 transition-colors" v-if="(irrForm.external_institutions as any)[activeIrrExternalTab].length > 1">
                                            <XCircle class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <button type="button" @click="addMember('irr_external_' + activeIrrExternalTab)" class="mt-2 text-[10px] font-bold uppercase text-blue-600">+ Add Row</button>
                                </div>
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-bold text-gray-600 uppercase">Document (PDF)</label>
                                <input type="file" @change="(e) => { const t = e.target as HTMLInputElement; irrForm.file = t.files && t.files[0] ? t.files[0] : null }" accept="application/pdf" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"/>

                                <!-- Current IRRs Linked -->
                                <div v-if="allIrrs.length > 0" class="mt-4">
                                    <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Current IRRs Linked</div>
                                    <div class="space-y-2">
                                        <div v-for="irr in allIrrs" :key="irr.id"
                                            class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3 transition-colors"
                                            :class="!irr.is_active
                                                ? 'bg-red-50 border-red-300'
                                                : 'bg-white border-gray-200 hover:border-blue-200'">

                                            <div class="flex items-start gap-3 min-w-0">
                                                <Paperclip class="w-4 h-4 mt-0.5 shrink-0"
                                                    :class="!irr.is_active ? 'text-red-400' : 'text-blue-500'" />
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="text-sm font-semibold text-gray-800">IRR Document</span>
                                                        <span v-if="!irr.is_active"
                                                            class="text-[10px] font-bold uppercase tracking-wide text-red-500 bg-red-50 border border-red-100 px-1.5 py-0.5 rounded">
                                                            Disabled
                                                        </span>
                                                    </div>
                                                    <div class="text-xs text-gray-500 mt-0.5">
                                                        Lead: {{ irr.lead_office_id ? (props.departments.find((d: any) => d.id == irr.lead_office_id)?.name || 'Unknown Office') : '—' }}
                                                    </div>
                                                    <div v-if="!irr.is_active && irr.disable_reason"
                                                        class="text-xs text-red-500 italic mt-0.5 font-medium">
                                                        Reason: {{ irr.disable_reason }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2 shrink-0">
                                                <a v-if="irr.file_url || irr.file_path"
                                                    :href="irr.file_url || irr.file_path" target="_blank"
                                                    class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="View PDF">
                                                    <Eye class="w-4 h-4" />
                                                </a>
                                                <button v-if="irr.is_active"
                                                    type="button"
                                                    @click.prevent="confirmDisableIrr(irr.id)"
                                                    class="p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg transition" title="Disable this IRR">
                                                    <AlertTriangle class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 mt-4">
                                <button type="button" @click="showIrrDialog = false" class="rounded-lg bg-gray-100 px-5 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-200 transition-colors">Cancel</button>
                                <button type="submit" :disabled="irrForm.processing" class="flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-bold text-white shadow hover:bg-blue-700 transition-colors disabled:opacity-70">Save IRR</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- Disable IRR Confirmation Modal -->
            <Transition name="fade">
                <div v-if="showDisableIrrDialog" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
                    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-red-100">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-50 border border-red-100 shrink-0">
                                <AlertTriangle class="w-5 h-5 text-red-500" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-red-700">Disable this IRR?</h3>
                            </div>
                        </div>

                        <p class="text-sm text-gray-600 mb-1">This will mark the IRR as inactive. Please provide a reason (e.g., "Amended by new IRR", "Superseded").</p>
                        <ul class="text-xs text-gray-500 list-disc list-inside mb-4 space-y-0.5">
                            <li>This action cannot be undone from this screen.</li>
                            <li>Provide a specific reason for disabling it (e.g., "Superseded by new IRR").</li>
                        </ul>

                        <textarea
                            v-model="disableIrrForm.reason"
                            rows="3"
                            class="w-full rounded-xl border border-red-200 text-sm px-3 py-2 outline-none focus:ring-2 focus:ring-red-400 bg-white resize-none"
                            placeholder="Amended by new IRR / Superseded"
                            required>
                        </textarea>

                        <div class="flex items-center justify-end gap-3 mt-4">
                            <button type="button" @click="showDisableIrrDialog = false; disableIrrForm.reset()"
                                class="px-5 py-2 rounded-lg bg-gray-100 text-sm font-semibold text-gray-700 hover:bg-gray-200 transition">
                                Cancel
                            </button>
                            <button type="button" @click="submitDisableIrr()" :disabled="disableIrrForm.processing || !disableIrrForm.reason.trim()"
                                class="flex items-center gap-2 px-5 py-2 rounded-lg bg-red-600 text-sm font-bold text-white hover:bg-red-700 transition disabled:opacity-60">
                                <XCircle class="w-4 h-4" />
                                Confirm Disable
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 8px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>