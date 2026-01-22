<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import FormWeekmenu from '@/components/FormWeekmenu.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Link2, Filter } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import Toast from '@/components/ui/toast/Toast.vue';
import { usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ImageOff, GripVertical } from 'lucide-vue-next';
import draggable from 'vuedraggable';
import axios from 'axios';

interface Menu {
    id: number;
    label: string;
    price: number;
    image_url: string | null;
    has_image: boolean;
}

interface Group {
    id: number;
    name: string;
}

interface Weekmenu {
    id: number;
    week: number;
    year: number;
    group_id: number;
    menu_id: number;
    quantity: number;
    ordering: number;
    menu: Menu | null;
    group: Group | null;
    invitation: number;
}

interface Props {
    weekmenus?: Weekmenu[];
    menus?: Menu[];
    groups?: Group[];
    currentWeek?: number;
    currentYear?: number;
    groupId?: string;
}

const props = withDefaults(defineProps<Props>(), {
    weekmenus: () => [],
    menus: () => [],
    groups: () => [],
    currentWeek: 1,
    currentYear: 2025,
    groupId: '',
});

// Initialize from props
const selectedWeek = ref(props.currentWeek);
const selectedYear = ref(props.currentYear);
const selectedGroup = ref(props.groupId);

// Watch for props changes
watch(() => props.groupId, (newVal) => {
    selectedGroup.value = newVal;
});

watch(() => props.currentWeek, (newVal) => {
    selectedWeek.value = newVal;
});

watch(() => props.currentYear, (newVal) => {
    selectedYear.value = newVal;
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
    {
        title: 'Week Menus',
        href: '/admin/weekmenus',
    },
];

// Toast state
const page = usePage();
const showToast = ref(false);
const toastMessage = ref('');
const toastVariant = ref<'success' | 'destructive'>('success');

// Dialog states
const showAddDialog = ref(false);
const showEditDialog = ref(false);
const showDeleteDialog = ref(false);
const selectedWeekmenu = ref<Weekmenu | null>(null);

// Draggable weekmenus
const draggableWeekmenus = ref<Weekmenu[]>([...props.weekmenus]);

// Watch for props changes
watch(() => props.weekmenus, (newMenus) => {
    draggableWeekmenus.value = [...newMenus];
}, { deep: true });

// Empty weekmenu for add form - computed so it updates when selectedWeek/selectedYear change
const emptyWeekmenu = computed<Weekmenu>(() => ({
    id: 0,
    week: selectedWeek.value,
    year: selectedYear.value,
    group_id: 0,
    menu_id: 0,
    quantity: 1,
    ordering: 0,
    menu: null,
    group: null,
    invitation: 0,
}));

const deleteForm = useForm({});
const isDragging = ref(false);

// Actions
const openAddDialog = () => {
    showAddDialog.value = true;
};

const openEditDialog = (weekmenu: Weekmenu) => {
    selectedWeekmenu.value = weekmenu;
    showEditDialog.value = true;
};

const openDeleteDialog = (weekmenu: Weekmenu) => {
    selectedWeekmenu.value = weekmenu;
    showDeleteDialog.value = true;
};

const handleFormSuccess = () => {
    showAddDialog.value = false;
    showEditDialog.value = false;
};

const submitDelete = () => {
    if (selectedWeekmenu.value) {
        deleteForm.delete(`/admin/weekmenus/${selectedWeekmenu.value.id}`, {
            onSuccess: () => {
                showDeleteDialog.value = false;
            },
        });
    }
};

// Navigation
const goToWeek = (week: number, year: number) => {
    router.get('/admin/weekmenus', {
        week,
        year,
        group_id: selectedGroup.value,
    }, {
        preserveState: true,
    });
};

// Copy deep link to clipboard
const copyDeepLink = async () => {
    const baseUrl = window.location.origin;
    const deepLink = `${baseUrl}/?week=${selectedWeek.value}&year=${selectedYear.value}`;

    try {
        // Try modern clipboard API first
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(deepLink);
        } else {
            // Fallback for non-secure contexts (HTTP)
            const textArea = document.createElement('textarea');
            textArea.value = deepLink;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
        toastVariant.value = 'success';
        toastMessage.value = 'Deep link copied to clipboard!';
        showToast.value = true;
        setTimeout(() => {
            showToast.value = false;
        }, 3000);
    } catch (error) {
        toastVariant.value = 'destructive';
        toastMessage.value = 'Failed to copy link.';
        showToast.value = true;
        setTimeout(() => {
            showToast.value = false;
        }, 3000);
    }
};

const handleWeekChange = () => {
    goToWeek(selectedWeek.value, selectedYear.value);
};

// Add filter toggle
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

const handleGroupFilter = () => {
    router.get('/admin/weekmenus', {
        week: selectedWeek.value,
        year: selectedYear.value,
        group_id: selectedGroup.value,
    }, {
        preserveState: true,
    });
};

// Add close order dialog state
const showCloseOrderDialog = ref(false);
const closeOrderForm = useForm({
    week: props.currentWeek,
    year: props.currentYear,
});

// Update when week/year changes
watch([() => props.currentWeek, () => props.currentYear], () => {
    closeOrderForm.week = props.currentWeek;
    closeOrderForm.year = props.currentYear;
});

const openCloseOrderDialog = () => {
    closeOrderForm.week = selectedWeek.value;
    closeOrderForm.year = selectedYear.value;
    showCloseOrderDialog.value = true;
};

const submitCloseOrder = () => {
    closeOrderForm.post('/admin/weekmenus/close-order', {
        onSuccess: () => {
            showCloseOrderDialog.value = false;
        },
    });
};

// Drag and drop handlers
const onDragStart = () => {
    isDragging.value = true;
};

const onDragEnd = async () => {
    isDragging.value = false;
    
    // Update ordering based on new positions
    const items = draggableWeekmenus.value.map((item, index) => ({
        id: item.id,
        ordering: index,
    }));
    
    try {
        await axios.post('/admin/weekmenus/reorder', { items });
        
        toastVariant.value = 'success';
        toastMessage.value = 'Order updated successfully!';
        showToast.value = true;
        setTimeout(() => {
            showToast.value = false;
        }, 3000);
    } catch (error) {
        toastVariant.value = 'destructive';
        toastMessage.value = 'Failed to update order.';
        showToast.value = true;
        setTimeout(() => {
            showToast.value = false;
        }, 3000);
        
        // Revert to original order
        draggableWeekmenus.value = [...props.weekmenus];
    }
};

// Watch for flash messages
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
        toastVariant.value = 'success';
        toastMessage.value = flash.success;
        showToast.value = true;
        setTimeout(() => {
            showToast.value = false;
        }, 3000);
    }
    if (flash?.error) {
        toastVariant.value = 'destructive';
        toastMessage.value = flash.error;
        showToast.value = true;
        setTimeout(() => {
            showToast.value = false;
        }, 3000);
    }
}, { deep: true });

const updatingQuantity = ref<number | null>(null);
let quantityTimeout: ReturnType<typeof setTimeout>;

const updateQuantity = (weekmenu: Weekmenu, newQuantity: number) => {
    if (newQuantity < 1) return;
    
    clearTimeout(quantityTimeout);
    quantityTimeout = setTimeout(async () => {
        updatingQuantity.value = weekmenu.id;
        
        try {
            await axios.patch(`/admin/weekmenus/${weekmenu.id}/quantity`, {
                quantity: newQuantity,
            });
            
            const index = draggableWeekmenus.value.findIndex(w => w.id === weekmenu.id);
            if (index !== -1) {
                draggableWeekmenus.value[index].quantity = newQuantity;
            }
            
            toastVariant.value = 'success';
            toastMessage.value = 'Quantity updated!';
            showToast.value = true;
            setTimeout(() => {
                showToast.value = false;
            }, 2000);
        } catch (error) {
            toastVariant.value = 'destructive';
            toastMessage.value = 'Failed to update quantity.';
            showToast.value = true;
            setTimeout(() => {
                showToast.value = false;
            }, 3000);
        } finally {
            updatingQuantity.value = null;
        }
    }, 500);
};
</script>

<template>
    <Head title="Week Menus" />

    <!-- Toast Notification -->
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
    >
        <Toast
            v-if="showToast"
            :variant="toastVariant"
            position="top-right"
        >
            {{ toastMessage }}
        </Toast>
    </Transition>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <!-- Header with responsive buttons -->
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-2xl font-bold">Week Menus</h2>
                <div class="flex items-center justify-end gap-2">
                    <!-- Toggle filters on mobile -->
                    <Button 
                        variant="outline" 
                        size="sm"
                        @click="showFilters = !showFilters"
                        class="md:hidden"
                    >
                        <Filter class="h-4 w-4" />
                    </Button>
                    
                    <Button @click="openAddDialog" size="sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="hidden md:inline">Add Week Menu</span>
                    </Button>
                    
                    <Button variant="destructive" @click="openCloseOrderDialog" v-if="draggableWeekmenus.length > 0" size="sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span class="hidden md:inline">Close Order</span>
                    </Button>
                    
                    <Button
                        v-if="draggableWeekmenus.length > 0"
                        variant="outline"
                        @click="copyDeepLink"
                        size="sm"
                    >
                        <Link2 class="h-4 w-4 md:mr-2" />
                        <span class="hidden md:inline">Copy Link</span>
                    </Button>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="space-y-3">
                <!-- Desktop layout (always visible) -->
                <div class="hidden md:flex md:items-center md:gap-4">
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
                            <option :value="null">All Groups</option>
                            <option value="none">No group</option>
                            <option v-for="group in groups" :key="group.id" :value="String(group.id)">
                                {{ group.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Mobile layout (toggleable) -->
                <div v-show="showFilters" class="md:hidden space-y-3">
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
                            <option :value="null">All Groups</option>
                            <option value="none">No group</option>
                            <option v-for="group in groups" :key="group.id" :value="String(group.id)">
                                {{ group.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Week Menus Table with Drag & Drop -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12"></TableHead>
                            <TableHead class="w-20">Image</TableHead>
                            <TableHead>Menu & Group</TableHead>
                            <TableHead>Price</TableHead>
                            <TableHead>Quantity</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <draggable
                        v-model="draggableWeekmenus"
                        tag="tbody"
                        item-key="id"
                        handle=".drag-handle"
                        @start="onDragStart"
                        @end="onDragEnd"
                    >
                        <template #item="{ element: weekmenu }">
                            <tr :class="['border-b transition-colors hover:bg-muted/50', { 'opacity-50': isDragging }]">
                                <td class="p-4 align-middle cursor-move drag-handle">
                                    <GripVertical class="h-5 w-5 text-muted-foreground" />
                                </td>
                                <td class="p-4 align-middle">
                                    <div v-if="weekmenu.menu?.has_image && weekmenu.menu?.image_url" class="w-16 h-16 rounded overflow-hidden">
                                        <img
                                            :src="weekmenu.menu.image_url"
                                            :alt="weekmenu.menu?.label"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>
                                    <div v-else class="w-16 h-16 rounded bg-gray-100 flex items-center justify-center">
                                        <ImageOff class="h-6 w-6 text-gray-400" />
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <div class="flex flex-col">
                                        <Link 
                                            :href="`/admin/menus?search=${encodeURIComponent(weekmenu.menu?.label ?? '')}`"
                                            class="font-medium text-primary hover:underline cursor-pointer"
                                        >
                                            {{ weekmenu.menu?.label ?? '-' }}
                                        </Link>
                                        <span class="text-sm text-muted-foreground">{{ weekmenu.group?.name ?? 'No Group' }}</span>
                                    </div>
                                </td>
                                <td class="p-4 align-middle">€{{ weekmenu.menu?.price ? Number(weekmenu.menu.price).toFixed(2) : '0.00' }}</td>
                                <td class="p-4 align-middle">
                                    <Input
                                        v-model.number="weekmenu.quantity"
                                        type="number"
                                        min="1"
                                        class="w-20"
                                        :class="{ 'opacity-50': updatingQuantity === weekmenu.id }"
                                        @input="updateQuantity(weekmenu, weekmenu.quantity)"
                                    />
                                </td>
                                <td class="p-4 align-middle text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openEditDialog(weekmenu)"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            @click="openDeleteDialog(weekmenu)"
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </draggable>
                </Table>
                
                <!-- Empty state outside draggable -->
                <div v-if="draggableWeekmenus.length === 0" class="p-8 text-center text-muted-foreground border-t">
                    No menus for week {{ selectedWeek }}, {{ selectedYear }}. Add your first menu to get started.
                </div>
            </div>

            <!-- Add Weekmenu Dialog -->
            <Dialog v-model:open="showAddDialog">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Add Week Menu</DialogTitle>
                        <DialogDescription>
                            Add a menu for week {{ selectedWeek }}, {{ selectedYear }}.
                        </DialogDescription>
                    </DialogHeader>

                    <FormWeekmenu
                        :key="`add-${selectedWeek}-${selectedYear}`"
                        :weekmenu="emptyWeekmenu"
                        :menus="menus"
                        :groups="groups"
                        :current-week="selectedWeek"
                        :current-year="selectedYear"
                        submit-url="/admin/weekmenus"
                        method="post"
                        @success="handleFormSuccess"
                        @cancel="showAddDialog = false"
                    />
                </DialogContent>
            </Dialog>

            <!-- Edit Weekmenu Dialog -->
            <Dialog v-model:open="showEditDialog">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Edit Week Menu</DialogTitle>
                        <DialogDescription>
                            Update the week menu information.
                        </DialogDescription>
                    </DialogHeader>

                    <FormWeekmenu
                        v-if="selectedWeekmenu"
                        :weekmenu="selectedWeekmenu"
                        :menus="menus"
                        :groups="groups"
                        :current-week="selectedWeek"
                        :current-year="selectedYear"
                        :submit-url="`/admin/weekmenus/${selectedWeekmenu.id}`"
                        method="put"
                        @success="handleFormSuccess"
                        @cancel="showEditDialog = false"
                    />
                </DialogContent>
            </Dialog>

            <!-- Delete Weekmenu Dialog -->
            <Dialog v-model:open="showDeleteDialog">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Delete Week Menu</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this week menu for <strong>{{ selectedWeekmenu?.menu?.label }}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="flex justify-end gap-2 mt-6">
                        <Button
                            type="button"
                            variant="secondary"
                            @click="showDeleteDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            :disabled="deleteForm.processing"
                            @click="submitDelete"
                        >
                            Delete Week Menu
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Close Order Confirmation Dialog -->
            <Dialog v-model:open="showCloseOrderDialog">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Close Order</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to close all orders for <strong>Week {{ selectedWeek }}, {{ selectedYear }}</strong>?
                            <br><br>
                            This will set all quantities to 0. This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="flex justify-end gap-2 mt-6">
                        <Button
                            type="button"
                            variant="secondary"
                            @click="showCloseOrderDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            :disabled="closeOrderForm.processing"
                            @click="submitCloseOrder"
                        >
                            Close All Orders
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
.flip-list-move {
    transition: transform 0.3s;
}
</style>