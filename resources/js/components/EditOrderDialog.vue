<script setup lang="ts">
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ClientSelector from '@/components/ClientSelector.vue';
import FormOrder from '@/components/FormOrder.vue';

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
        };
        notes: string | null;
    }>;
    total: number;
    order_date: string;
}

interface Props {
    order: Order;
    open: boolean;
    menus: any[];
    groups: any[];
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

// Edit Quantities Form
const selectedOrderId = ref<number | null>(null);
const editQuantityForm = useForm({
    quantity: 1,
    special_price: '' as string | number,
});

const selectOrderForEdit = (orderId: number) => {
    selectedOrderId.value = orderId;
    const order = props.order.orders.find(o => o.id === orderId);
    if (order) {
        editQuantityForm.quantity = order.quantity;
        editQuantityForm.special_price = order.special_price ?? '';
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
                                    <div class="font-medium">{{ item.quantity }}× {{ item.weekmenu.menu.label }}</div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ item.special_price ? `€${item.special_price.toFixed(2)}` : `€${item.weekmenu.menu.price.toFixed(2)}` }} each
                                    </div>
                                </div>
                                <div class="font-semibold">
                                    €{{ ((item.special_price ?? item.weekmenu.menu.price) * item.quantity).toFixed(2) }}
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
                
                <div v-if="selectedOrderId" class="space-y-4 p-4 border rounded-lg bg-gray-50">
                    <div>
                        <Label for="quantity">Quantity</Label>
                        <Input
                            id="quantity"
                            v-model.number="editQuantityForm.quantity"
                            type="number"
                            min="1"
                        />
                    </div>
                    
                    <div>
                        <Label for="special_price">Special Price (optional)</Label>
                        <Input
                            id="special_price"
                            v-model="editQuantityForm.special_price"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Leave empty for default price"
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