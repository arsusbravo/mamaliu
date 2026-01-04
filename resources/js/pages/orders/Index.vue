<script setup lang="ts">
import { ref, computed } from 'vue';
import { Pencil, FileText, Filter } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import FormOrder from '@/components/FormOrder.vue';
import EditOrderDialog from '@/components/EditOrderDialog.vue';
import InvoiceDialog from '@/components/InvoiceDialog.vue';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Search } from 'lucide-vue-next';

interface Menu {
    id: number;
    label: string;
    price: number;
    image_url: string | null;
    has_image: boolean;
}

interface Weekmenu {
    id: number;
    menu: Menu;
}

interface User {
    id: number;
    name: string;
}

interface Group {
    id: number;
    name: string;
}

interface Order {
    id: number;
    quantity: number;
    special_price: number | null;
    notes: string | null;
    weekmenu: Weekmenu;
    week: number;
    year: number;
}

interface GroupedOrder {
    user: User;
    group: Group | null;
    orders: Order[];
    total: number;
    order_date: string;
}

interface Props {
    groupedOrders?: GroupedOrder[];
    groups?: Group[];
    menus?: Menu[];
    currentWeek?: number;
    currentYear?: number;
    filters?: {
        search?: string;
        group_id?: string;
    };
}

const props = withDefaults(defineProps<Props>(), {
    groupedOrders: () => [],
    groups: () => [],
    menus: () => [],
    currentWeek: 1,
    currentYear: 2025,
    filters: () => ({
        search: '',
        group_id: '',
    }),
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
    {
        title: 'Orders',
        href: '/admin/orders',
    },
];

const selectedWeek = ref(props.currentWeek);
const selectedYear = ref(props.currentYear);
const selectedGroup = ref(props.filters?.group_id || '');
const searchQuery = ref(props.filters?.search || '');
let searchTimeout: ReturnType<typeof setTimeout>;

const showFilters = ref(true);

    // Generate week options (1-53)
const weekOptions = computed(() => {
    return Array.from({ length: 53 }, (_, i) => i + 1);
});

// Generate year options (3 years ago to 1 year later)
const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let year = currentYear - 3; year <= currentYear + 1; year++) {
        years.push(year);
    }
    return years;
});

const applyFilters = () => {
    router.get('/admin/orders', {
        week: selectedWeek.value,
        year: selectedYear.value,
        group_id: selectedGroup.value,
        search: searchQuery.value,
    });
};

const handleWeekChange = () => {
    applyFilters();
};

const handleGroupFilter = () => {
    applyFilters();
};

const handleSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getPrice = (order: Order): number => {
    return order.special_price ?? order.weekmenu.menu.price;
};

const getSubtotal = (order: Order): number => {
    return order.quantity * getPrice(order);
};

const getUniqueNotes = (orders: Order[]): string[] => {
    const notes = orders
        .map(order => order.notes)
        .filter(note => note !== null && note !== '') as string[];
    return [...new Set(notes)];
};

const showAddOrderForm = ref(false);

const handleOrderAdded = () => {
    showAddOrderForm.value = false;
    router.reload();
};

const handlePrint = () => {
    const params = new URLSearchParams({
        week: String(selectedWeek.value),
        year: String(selectedYear.value),
    });
    
    if (selectedGroup.value) {
        params.append('group_id', selectedGroup.value);
    }
    
    if (searchQuery.value) {
        params.append('search', searchQuery.value);
    }
    
    window.open(`/admin/orders/pdf?${params.toString()}`, '_blank');
};

const handleExport = () => {
    const params = new URLSearchParams({
        week: String(selectedWeek.value),
        year: String(selectedYear.value),
    });
    
    if (selectedGroup.value) {
        params.append('group_id', selectedGroup.value);
    }
    
    if (searchQuery.value) {
        params.append('search', searchQuery.value);
    }
    
    window.location.href = `/admin/orders/export?${params.toString()}`;
};
const editingOrder = ref<GroupedOrder | null>(null);
const showEditDialog = ref(false);

const handleEditOrder = (order: GroupedOrder) => {
    editingOrder.value = order;
    showEditDialog.value = true;
};

const handleEditSuccess = () => {
    showEditDialog.value = false;
    editingOrder.value = null;
    router.reload();
};

const invoicingOrder = ref<GroupedOrder | null>(null);
const showInvoiceDialog = ref(false);

const handleInvoice = (order: GroupedOrder) => {
    invoicingOrder.value = order;
    showInvoiceDialog.value = true;
};

const handleInvoiceSuccess = () => {
    showInvoiceDialog.value = false;
    invoicingOrder.value = null;
    router.reload();
};
</script>

<template>
    <Head title="Orders" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <!-- Header with buttons -->
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-2xl font-bold">Orders</h2>
                <div class="flex gap-2">
                    <!-- Toggle filters on mobile -->
                    <Button 
                        variant="outline" 
                        size="sm"
                        @click="showFilters = !showFilters"
                        class="md:hidden"
                    >
                        <Filter class="h-4 w-4" />
                    </Button>
                    
                    <Button @click="showAddOrderForm = true" size="sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="hidden md:inline">Add Order</span>
                    </Button>
                    <Button variant="outline" @click="handlePrint" size="sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span class="hidden md:inline">Print</span>
                    </Button>
                    <Button variant="outline" @click="handleExport" size="sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="hidden md:inline">Export Excel</span>
                    </Button>
                </div>
            </div>

            <!-- Filters Section -->
            <div v-show="showFilters" class="space-y-3">
                <!-- Desktop layout (horizontal) -->
                <div class="hidden md:flex md:items-center md:justify-between">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label for="week" class="text-sm font-medium">Week:</label>
                            <select
                                id="week"
                                v-model.number="selectedWeek"
                                @change="handleWeekChange"
                                class="h-10 w-20 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option v-for="week in weekOptions" :key="week" :value="week">
                                    {{ week }}
                                </option>
                            </select>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <label for="year" class="text-sm font-medium">Year:</label>
                            <select
                                id="year"
                                v-model.number="selectedYear"
                                @change="handleWeekChange"
                                class="h-10 w-24 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option v-for="year in yearOptions" :key="year" :value="year">
                                    {{ year }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <label for="group-filter" class="text-sm font-medium">Group:</label>
                            <select
                                id="group-filter"
                                v-model="selectedGroup"
                                @change="handleGroupFilter"
                                class="h-10 w-[200px] rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">All Groups</option>
                                <option v-for="group in groups" :key="group.id" :value="String(group.id)">
                                    {{ group.name }}
                                </option>
                            </select>
                        </div>

                        <div class="relative flex-1 max-w-sm">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                @input="handleSearch"
                                type="search"
                                placeholder="Search by client or menu name..."
                                class="pl-9"
                            />
                        </div>
                    </div>

                    <div v-if="groupedOrders.length > 0" class="flex justify-end">
                        <div class="text-right">
                            <div class="text-sm text-muted-foreground">Total Orders</div>
                            <div class="text-2xl font-bold">
                                €{{ groupedOrders.reduce((sum, order) => sum + order.total, 0).toFixed(2) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile layout (vertical/stacked) -->
                <div class="md:hidden space-y-3">
                    <!-- Week & Year in one row -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label for="week-mobile" class="text-sm font-medium">Week</label>
                            <select
                                id="week-mobile"
                                v-model.number="selectedWeek"
                                @change="handleWeekChange"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option v-for="week in weekOptions" :key="week" :value="week">
                                    {{ week }}
                                </option>
                            </select>
                        </div>
                        
                        <div class="space-y-1.5">
                            <label for="year-mobile" class="text-sm font-medium">Year</label>
                            <select
                                id="year-mobile"
                                v-model.number="selectedYear"
                                @change="handleWeekChange"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option v-for="year in yearOptions" :key="year" :value="year">
                                    {{ year }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Group filter - full width -->
                    <div class="space-y-1.5">
                        <label for="group-filter-mobile" class="text-sm font-medium">Group</label>
                        <select
                            id="group-filter-mobile"
                            v-model="selectedGroup"
                            @change="handleGroupFilter"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All Groups</option>
                            <option v-for="group in groups" :key="group.id" :value="String(group.id)">
                                {{ group.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Search - full width -->
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            @input="handleSearch"
                            type="search"
                            placeholder="Search..."
                            class="pl-9"
                        />
                    </div>

                    <!-- Total on mobile -->
                    <div v-if="groupedOrders.length > 0" class="flex items-center justify-between p-3 bg-muted/50 rounded-lg">
                        <span class="text-sm font-medium">Total Orders</span>
                        <span class="text-xl font-bold">
                            €{{ groupedOrders.reduce((sum, order) => sum + order.total, 0).toFixed(2) }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="groupedOrders.length === 0" class="text-center py-12 text-muted-foreground">
                No orders found for the selected filters.
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 print-cards">
                <Card v-for="groupedOrder in groupedOrders" :key="groupedOrder.user.id" class="border-l-4 border-blue-400 bg-linear-to-br from-blue-50/50 via-white to-orange-50/30 hover:shadow-lg transition-shadow">
                    <CardHeader class="bg-linear-to-r from-blue-50 to-orange-50 border-b border-blue-100">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle class="text-blue-900">{{ groupedOrder.user.name }}</CardTitle>
                                <CardDescription>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 bg-orange-400 rounded-full"></span>
                                            <span>{{ groupedOrder.group?.name ?? 'No Group' }}</span>
                                        </div>
                                        <div class="text-xs">{{ formatDate(groupedOrder.order_date) }}</div>
                                    </div>
                                </CardDescription>
                            </div>
                            <div class="flex gap-1 no-print">
                                <Button 
                                    size="sm" 
                                    variant="ghost"
                                    @click="handleEditOrder(groupedOrder)"
                                    class="hover:bg-blue-100 hover:text-blue-700"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button 
                                    size="sm" 
                                    variant="ghost"
                                    @click="handleInvoice(groupedOrder)"
                                    class="hover:bg-orange-100 hover:text-orange-700"
                                >
                                    <FileText class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    
                    <CardContent>
                        <div class="space-y-3">
                            <div v-if="getUniqueNotes(groupedOrder.orders).length > 0" class="text-sm bg-yellow-50 border-l-2 border-yellow-400 p-2 rounded">
                                <div class="font-medium mb-1 text-yellow-800">Notes:</div>
                                <div 
                                    v-for="(note, index) in getUniqueNotes(groupedOrder.orders)" 
                                    :key="index"
                                    class="text-yellow-700"
                                >
                                    • {{ note }}
                                </div>
                                <div class="border-t border-yellow-200 mt-3 mb-3" />
                            </div>

                            <div 
                                v-for="order in groupedOrder.orders" 
                                :key="order.id"
                                class="flex justify-between text-sm hover:bg-blue-50 p-2 rounded transition-colors"
                            >
                                <div class="flex-1">
                                    <span class="font-medium text-blue-700">{{ order.quantity }}×</span>
                                    <span class="ml-2">{{ order.weekmenu.menu.label }}</span>
                                </div>
                                <div class="text-right font-medium text-gray-700">
                                    €{{ getSubtotal(order).toFixed(2) }}
                                </div>
                            </div>

                            <div class="border-t border-blue-200" />

                            <div class="flex justify-between font-bold bg-linear-to-r from-blue-50 to-orange-50 p-3 rounded-lg">
                                <span class="text-gray-700">Total</span>
                                <span class="text-xl bg-linear-to-r from-blue-600 to-orange-600 bg-clip-text text-transparent">
                                    €{{ groupedOrder.total.toFixed(2) }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
    <Dialog v-model:open="showAddOrderForm">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Add Order</DialogTitle>
                <DialogDescription>Create a new order for a client</DialogDescription>
            </DialogHeader>
            <FormOrder 
                :week="currentWeek"
                :year="currentYear"
                :menus="menus"
                :groups="groups"
                @success="handleOrderAdded"
                @cancel="showAddOrderForm = false"
            />
        </DialogContent>
    </Dialog>
    <EditOrderDialog
        v-if="editingOrder"
        :order="editingOrder"
        :open="showEditDialog"
        :menus="menus"
        :groups="groups"
        @update:open="showEditDialog = $event"
        @success="handleEditSuccess"
    />
    <InvoiceDialog
        v-if="invoicingOrder"
        :order="invoicingOrder"
        :open="showInvoiceDialog"
        @update:open="showInvoiceDialog = $event"
        @success="handleInvoiceSuccess"
    />
</template>
