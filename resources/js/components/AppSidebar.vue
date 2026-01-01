<script setup lang="ts">
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
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, ListChecksIcon, ListPlusIcon, MailsIcon, MapPinIcon, ReceiptEuro, ShoppingBag, Users2Icon } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Orders',
        href: '/admin/orders',
        icon: ReceiptEuro,
    },
    {
        title: 'Weekmenus',
        href: '/admin/weekmenus',
        icon: ListChecksIcon,
    },
    {
        title: 'Menus',
        href: '/admin/menus',
        icon: ListPlusIcon,
    },
    {
        title: 'Clients',
        href: '/admin/clients',
        icon: Users2Icon,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Client Front Page',
        href: '/',
        icon: ShoppingBag,
    },
    {
        title: 'Groups',
        href: '/admin/groups',
        icon: MapPinIcon,
    },
    {
        title: 'Registration invites',
        href: '/admin/invites',
        icon: Users2Icon,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/admin/dashboard">
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
