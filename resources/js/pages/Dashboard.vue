<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { 
    ShoppingCart, 
    DollarSign, 
    Calendar,
    UtensilsCrossed,
    MessageSquare,
    Sparkles,
    Plus
} from 'lucide-vue-next';

interface Stats {
    total_orders_this_week: number;
    total_revenue_this_quarter: number;
    total_weekmenus_this_quarter: number;
    total_pre_orders: number;
    total_clients: number;
    current_quarter: number;
}

interface RecentOrder {
    user_id: number;
    client_name: string;
    total: number;
    order_count: number;
    latest_date: string;
    is_new: boolean;
}

interface PreOrder {
    user_id: number;
    client_name: string;
    total: number;
    order_count: number;
    earliest_week: number;
    earliest_year: number;
}

interface RecentNote {
    client_name: string;
    notes: string;
    menu_label: string;
    created_at: string;
}

interface Props {
    stats?: Stats;
    recentOrders?: RecentOrder[];
    preOrders?: PreOrder[];
    recentNotes?: RecentNote[];
    currentWeek?: number;
    currentYear?: number;
    latestPreOrderWeek?: number;
    latestPreOrderYear?: number;
}

const props = withDefaults(defineProps<Props>(), {
    stats: () => ({
        total_orders_this_week: 0,
        total_revenue_this_quarter: 0,
        total_weekmenus_this_quarter: 0,
        total_pre_orders: 0,
        total_clients: 0,
        current_quarter: 1,
    }),
    recentOrders: () => [],
    preOrders: () => [],
    recentNotes: () => [],
    currentWeek: 1,
    currentYear: 2025,
    latestPreOrderWeek: 1,
    latestPreOrderYear: 2025,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
];

const formatTime = (isoString: string) => {
    const date = new Date(isoString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffMinutes = Math.floor(diffMs / (1000 * 60));
    
    if (diffMinutes < 60) {
        return `${diffMinutes}m ago`;
    } else if (diffHours < 24) {
        return `${diffHours}h ago`;
    } else {
        return date.toLocaleDateString();
    }
};

const goToOrders = () => {
    router.get('/admin/orders', {
        week: props.currentWeek,
        year: props.currentYear,
    });
};

const goToPreOrders = () => {
    router.get('/admin/orders', {
        week: props.latestPreOrderWeek,
        year: props.latestPreOrderYear,
    });
};

const goToWeekmenus = () => {
    router.get('/admin/weekmenus', {
        week: props.currentWeek,
        year: props.currentYear,
    });
};
const goToCurrentOrders = () => {
    if (props.stats.total_orders_this_week === 0) {
        // No orders, go to weekmenus to create some
        goToWeekmenus();
    } else {
        // Has orders, view them
        goToOrders();
    }
};

const goToFirstPreOrder = () => {
    if (props.preOrders.length > 0) {
        router.get('/admin/orders', {
            week: props.preOrders[0].earliest_week,
            year: props.preOrders[0].earliest_year,
        });
    } else {
        goToPreOrders();
    }
};

const goToWeekmenusList = () => {
    router.get('/admin/weekmenus', {
        week: props.currentWeek,
        year: props.currentYear,
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Orders This Week -->
                <Card class="cursor-pointer hover:bg-accent transition-colors border-l-4 border-blue-500 bg-linear-to-r from-blue-50 to-white" @click="goToCurrentOrders">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Orders This Week</CardTitle>
                        <div class="p-2 bg-blue-500 rounded-lg">
                            <ShoppingCart class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="stats.total_orders_this_week === 0" class="space-y-2">
                            <div class="text-lg font-semibold text-muted-foreground">No orders yet</div>
                            <p class="text-xs text-muted-foreground">
                                Create week menus first →
                            </p>
                        </div>
                        <div v-else>
                            <div class="text-2xl font-bold text-blue-700">{{ stats.total_orders_this_week }}</div>
                            <p class="text-xs text-muted-foreground">
                                Week {{ currentWeek }}, {{ currentYear }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Revenue This Quarter -->
                <Card class="cursor-pointer hover:bg-accent transition-colors border-l-4 border-green-500 bg-linear-to-r from-green-50 to-white" @click="goToOrders">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Revenue This Quarter</CardTitle>
                        <div class="p-2 bg-green-500 rounded-lg">
                            <DollarSign class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-700">
                            €{{ (stats.total_revenue_this_quarter || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Q{{ stats.current_quarter || 1 }} {{ currentYear }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Pre-Orders -->
                <Card class="cursor-pointer hover:bg-accent transition-colors border-l-4 border-orange-500 bg-linear-to-r from-orange-50 to-white" @click="goToFirstPreOrder">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Pre-Orders</CardTitle>
                        <div class="p-2 bg-orange-500 rounded-lg">
                            <Calendar class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-700">{{ stats.total_pre_orders }}</div>
                        <p class="text-xs text-muted-foreground">
                            Future weeks
                        </p>
                    </CardContent>
                </Card>

                <!-- Week Menus This Quarter -->
                <Card class="cursor-pointer hover:bg-accent transition-colors border-l-4 border-purple-500 bg-linear-to-r from-purple-50 to-white" @click="goToWeekmenusList">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Week Menus This Quarter</CardTitle>
                        <div class="p-2 bg-purple-500 rounded-lg">
                            <UtensilsCrossed class="h-4 w-4 text-white" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-purple-700">
                            {{ stats.total_weekmenus_this_quarter || 0 }} <span class="font-thin text-xs italic text-slate-400">of appr. 13 weeks</span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Q{{ stats.current_quarter || 1 }} {{ currentYear }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
                <!-- Recent Orders -->
                <Card class="col-span-1 md:col-span-2 lg:col-span-4">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Recent Orders</CardTitle>
                                <CardDescription>Client orders this quarter</CardDescription>
                            </div>
                            <Button variant="outline" size="sm" @click="goToOrders">
                                View All
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- Empty State -->
                        <div v-if="recentOrders.length === 0" class="text-center py-12">
                            <ShoppingCart class="h-16 w-16 text-blue-300 mx-auto mb-4 opacity-50" />
                            <h3 class="text-lg font-semibold mb-2">No Orders Yet</h3>
                            <p class="text-sm text-muted-foreground mb-4">
                                Start by creating week menus, then clients can place orders
                            </p>
                            <Button @click="goToWeekmenus" size="sm">
                                <Plus class="h-4 w-4 mr-2" />
                                Create Week Menu
                            </Button>
                        </div>
                        
                        <!-- Orders List Grouped by Client -->
                        <div v-else class="space-y-4">
                            <div 
                                v-for="order in recentOrders" 
                                :key="order.user_id"
                                class="flex items-start justify-between border-b pb-3 last:border-0 last:pb-0"
                            >
                                <div class="space-y-1 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium leading-none">
                                            {{ order.client_name }}
                                        </p>
                                        <Sparkles 
                                            v-if="order.is_new" 
                                            class="h-3 w-3 text-yellow-500 animate-pulse" 
                                        />
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ order.order_count }} order{{ order.order_count !== 1 ? 's' : '' }} this quarter
                                    </p>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span>{{ formatTime(order.latest_date) }}</span>
                                    </div>
                                </div>
                                <div class="font-medium text-green-600">
                                    €{{ order.total.toFixed(2) }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Pre-Orders & Notes -->
                <Card class="col-span-1 md:col-span-2 lg:col-span-3">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Pre-Orders</CardTitle>
                                <CardDescription>Upcoming orders by client</CardDescription>
                            </div>
                            <Button variant="outline" size="sm" @click="goToPreOrders">
                                View All
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div 
                                v-for="order in preOrders.slice(0, 5)" 
                                :key="order.user_id"
                                class="flex items-start justify-between text-sm border-b pb-2 last:border-0 last:pb-0"
                            >
                                <div class="flex-1">
                                    <p class="font-medium">{{ order.client_name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ order.order_count }} order{{ order.order_count !== 1 ? 's' : '' }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        From week {{ order.earliest_week }}, {{ order.earliest_year }}
                                    </p>
                                </div>
                                <div class="text-sm font-medium text-orange-600">
                                    €{{ order.total.toFixed(2) }}
                                </div>
                            </div>
                            
                            <div v-if="preOrders.length === 0" class="text-center py-4 text-muted-foreground text-sm">
                                No pre-orders
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Notes -->
            <Card v-if="recentNotes.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5 text-purple-500" />
                        Recent Client Notes
                    </CardTitle>
                    <CardDescription>Notes from the last 7 days</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div 
                            v-for="(note, index) in recentNotes" 
                            :key="index"
                            class="border-l-2 border-purple-500 pl-4 py-2"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-sm font-medium">{{ note.client_name }}</p>
                                <span class="text-xs text-muted-foreground">{{ note.created_at }}</span>
                            </div>
                            <p class="text-sm text-muted-foreground italic">"{{ note.notes }}"</p>
                            <p class="text-xs text-muted-foreground mt-1">{{ note.menu_label }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>