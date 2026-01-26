<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { 
    LayoutGrid, 
    FileText, 
    ScrollText, 
    Briefcase, 
    Activity, 
    Users, 
    Building2,
    BookOpen 
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

const userRole = computed(() => {
    return page.props.auth.role || page.props.auth.user?.role || 'guest';
});

const mainNavItems = computed(() => {
    const menuConfig = [
        {
            title: 'Dashboard',
            href: dashboard(), 
            icon: LayoutGrid,
            roles: ['system_admin', 'supervisor', 'focal_person', 'monitoring_committee', 'read_only']
        },
        {
            title: 'EO Profiling',
            href: route('eo.index'),
            icon: FileText,
            roles: ['system_admin', 'supervisor']
        },
        {
            title: 'Ordinances',
            href: '#',
            icon: ScrollText,
            roles: ['system_admin', 'supervisor', 'read_only']
        },
        {
            title: 'My Assignments',
            href: '#',
            icon: Briefcase,
            roles: ['focal_person']
        },
        {
            title: 'Monitoring Board',
            href: '#',
            icon: Activity,
            roles: ['monitoring_committee', 'system_admin']
        },
        {
            title: 'User Management',
            href: route('users.index'),
            icon: Users,
            roles: ['system_admin']
        },
        {
            title: 'Status Management', 
            href: route('statuses.index'), 
            icon: Activity, 
            roles: ['system_admin']
        },
        {
            title: 'Departments',
            href: route('departments.index'),
            icon: Building2,
            roles: ['system_admin']
        }
    ];

    // Filter based on role
    return menuConfig.filter(item => item.roles.includes(userRole.value));
});

const footerNavItems: NavItem[] = [
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>