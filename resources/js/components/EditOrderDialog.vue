<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ClientSelector from '@/components/ClientSelector.vue';
import FormOrder from '@/components/FormOrder.vue';
import Textarea from './ui/textarea/Textarea.vue';

interface Order {
    user: {
        id: number;
        name: string;
    };
    group: {
        id: number;
        name: string;
    } | null;
    orders: Array<{
        id: number;
        quantity: number;
        special_price: number | null;
        week: number;
        year: number;
        weekmenu: {
            id: number;
            menu: {
                id: number;
                label: string;
                price: number;
            };
        } | null;
        menu_item: {
            id: number;
            label: string;
            price: number;
        };
        is_gift: boolean;
        notes: string | null;
        pickup_point_id: number | null;
    }>;
    total: number;
    order_date: string;
}

interface PickupPoint {
    id: number;
    name: string;
}

interface Props {
    order: Order;
    open: boolean;
    menus: any[];
    groups: Array<{ id: number; name: string; pickup_points?: PickupPoint[] }>;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:open', 'success']);

const activeTab = ref('change-client');

// Change Client Form
const changeClientForm = useForm({
    new_user_id: null as number | null,
    action: 'move' as 'move' | 'copy',
});

const handleChangeClient = () => {
    if (!changeClientForm.new_user_id) {
        alert('Please select a client');
        return;
    }
    
    const week = props.order.orders[0]?.week;
    const year = props.order.orders[0]?.year;
    
    router.post(`/admin/orders/change-client/${props.order.user.id}`, {
        new_user_id: changeClientForm.new_user_id,
        action: changeClientForm.action,
        week: week,
        year: year,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            emit('success');
        },
        onError: (errors) => {
            console.error('Change client errors:', errors);
        }
    });
};

// Pickup Point Form (applies to every order this client has this week)
const pickupPointForm = useForm({
    pickup_point_id: null as number | null,
    week: null as number | null,
    year: null as number | null,
});

const resetPickupPointForm = () => {
    pickupPointForm.pickup_point_id = props.order.orders[0]?.pickup_point_id ?? null;
    pickupPointForm.week = props.order.orders[0]?.week ?? null;
    pickupPointForm.year = props.order.orders[0]?.year ?? null;
};
resetPickupPointForm();

// EditOrderDialog is a persistent instance reused across different clients
// (see orders/Index.vue's v-if="editingOrder" with no :key), so local form
// state must be reset explicitly whenever the `order` prop changes.
watch(() => props.order, resetPickupPointForm);

const availablePickupPoints = computed(() => {
    const group = props.groups.find((g) => g.id === props.order.group?.id);
    return group?.pickup_points ?? [];
});

const handleUpdatePickupPoint = () => {
    pickupPointForm.post(`/admin/orders/update-pickup-point/${props.order.user.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('success');
        },
    });
};

// Edit Quantities Form
const selectedOrderId = ref<number | null>(null);
const editQuantityForm = useForm({
    quantity: 1,
    special_price: '' as string | number,
    notes: '' as string,
});

const selectOrderForEdit = (orderId: number) => {
    selectedOrderId.value = orderId;
    const order = props.order.orders.find(o => o.id === orderId);
    if (order) {
        editQuantityForm.quantity = order.quantity;
        editQuantityForm.special_price = order.special_price ?? '';
        editQuantityForm.notes = order.notes ?? '';
    }
};

const handleUpdateOrder = () => {
    if (!selectedOrderId.value) {
        alert('Please select an order to edit');
        return;
    }
    
    router.put(`/admin/orders/${selectedOrderId.value}`, {
        quantity: editQuantityForm.quantity,
        special_price: editQuantityForm.special_price || null,
        notes: editQuantityForm.notes || '',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedOrderId.value = null;
            emit('success');
        },
        onError: (errors) => {
            console.error('Update order errors:', errors);
        }
    });
};

const handleOrderAdded = () => {
    emit('success');
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Edit Orders for {{ order.user.name }}</DialogTitle>
                <DialogDescription>Manage orders for this client</DialogDescription>
            </DialogHeader>
            
            <!-- Tab Buttons -->
            <div class="flex gap-2 border-b">
                <button
                    @click="activeTab = 'change-client'"
                    :class="[
                        'px-4 py-2 font-medium text-sm border-b-2 transition-colors',
                        activeTab === 'change-client' 
                            ? 'border-primary text-primary' 
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    ]"
                >
                    Change Client
                </button>
                <button
                    @click="activeTab = 'edit-quantities'"
                    :class="[
                        'px-4 py-2 font-medium text-sm border-b-2 transition-colors',
                        activeTab === 'edit-quantities' 
                            ? 'border-primary text-primary' 
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    ]"
                >
                    Edit Orders
                </button>
                <button
                    @click="activeTab = 'add-order'"
                    :class="[
                        'px-4 py-2 font-medium text-sm border-b-2 transition-colors',
                        activeTab === 'add-order' 
                            ? 'border-primary text-primary' 
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    ]"
                >
                    Add Order
                </button>
            </div>
            
            <!-- Tab 1: Change Client -->
            <div v-if="activeTab === 'change-client'" class="space-y-4 mt-4">
                <div>
                    <Label>Select New Client</Label>
                    <ClientSelector 
                        v-model="changeClientForm.new_user_id"
                    />
                </div>
                
                <div>
                    <Label class="mb-2 block">Action</Label>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input 
                                type="radio" 
                                value="move" 
                                v-model="changeClientForm.action"
                                class="h-4 w-4 text-primary"
                            />
                            <span class="text-sm">Move all orders (remove from {{ order.user.name }})</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input 
                                type="radio" 
                                value="copy" 
                                v-model="changeClientForm.action"
                                class="h-4 w-4 text-primary"
                            />
                            <span class="text-sm">Copy all orders (keep on {{ order.user.name }})</span>
                        </label>
                    </div>
                </div>
                
                <Button 
                    @click="handleChangeClient" 
                    :disabled="!changeClientForm.new_user_id"
                    class="w-full"
                >
                    {{ changeClientForm.action === 'move' ? 'Move' : 'Copy' }} Orders
                </Button>
            </div>
            
            <!-- Tab 2: Edit Quantities -->
            <div v-if="activeTab === 'edit-quantities'" class="space-y-4 mt-4">
                <div class="space-y-2 p-4 border rounded-lg bg-blue-50">
                    <Label for="pickup_point" class="mb-2">Pick-up Point (whole week)</Label>
                    <select
                        id="pickup_point"
                        v-model="pickupPointForm.pickup_point_id"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                    >
                        <option :value="null">No specific pick-up point</option>
                        <option v-for="point in availablePickupPoints" :key="point.id" :value="point.id">
                            {{ point.name }}
                        </option>
                    </select>
                    <p v-if="pickupPointForm.errors.pickup_point_id" class="text-sm text-red-500">
                        {{ pickupPointForm.errors.pickup_point_id }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Changes the pick-up point for all of {{ order.user.name }}'s orders in week {{ order.orders[0]?.week }}/{{ order.orders[0]?.year }}.
                    </p>
                    <Button @click="handleUpdatePickupPoint" size="sm" class="w-full">
                        Update Pick-up Point
                    </Button>
                </div>

                <div>
                    <Label>Select Order to Edit</Label>
                    <div class="space-y-2 mt-2">
                        <button
                            v-for="item in order.orders"
                            :key="item.id"
                            type="button"
                            @click="selectOrderForEdit(item.id)"
                            :class="[
                                'w-full text-left p-3 rounded border transition-colors',
                                selectedOrderId === item.id 
                                    ? 'border-primary bg-primary/5' 
                                    : 'border-gray-200 hover:border-primary/50'
                            ]"
                        >
                            <div class="flex justify-between items-center">
                                <div>
                                    <div class="font-medium">
                                        {{ item.quantity }}× {{ item.menu_item.label }}
                                        <span v-if="item.is_gift" class="ml-1 text-xs font-semibold text-green-700">🎁 FREE</span>
                                    </div>
                                    <div v-if="!item.is_gift" class="text-sm text-muted-foreground">
                                        {{ item.special_price ? `€${item.special_price.toFixed(2)}` : `€${item.menu_item.price.toFixed(2)}` }} each
                                    </div>
                                </div>
                                <div v-if="!item.is_gift" class="font-semibold">
                                    €{{ ((item.special_price ?? item.menu_item.price) * item.quantity).toFixed(2) }}
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
                
                <div v-if="selectedOrderId" class="space-y-4 p-4 border rounded-lg bg-gray-50">
                    <div>
                        <Label for="quantity" class="mb-2">Quantity</Label>
                        <Input
                            id="quantity"
                            v-model.number="editQuantityForm.quantity"
                            type="number"
                            min="1"
                        />
                    </div>
                    
                    <div>
                        <Label for="special_price" class="mb-2">Special Price (optional)</Label>
                        <Input
                            id="special_price"
                            v-model="editQuantityForm.special_price"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Leave empty for default price"
                        />
                    </div>
                    
                    <div>
                        <Label for="notes" class="mb-2">Notes (optional)</Label>
                        <Textarea
                            id="notes"
                            v-model="editQuantityForm.notes"
                            placeholder="Enter any notes for this order"
                        />
                    </div>
                    
                    <Button 
                        @click="handleUpdateOrder" 
                        class="w-full"
                    >
                        Update Order
                    </Button>
                </div>
            </div>
            
            <!-- Tab 3: Add Order -->
            <div v-if="activeTab === 'add-order'" class="mt-4">
                <FormOrder 
                    :user_id="order.user.id"
                    :week="order.orders[0]?.week"
                    :year="order.orders[0]?.year"
                    :menus="menus"
                    :groups="groups"
                    @success="handleOrderAdded"
                    @cancel="() => {}"
                />
            </div>
        </DialogContent>
    </Dialog>
</template>