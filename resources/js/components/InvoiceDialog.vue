<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { X } from 'lucide-vue-next';

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

interface Order {
    id: number;
    quantity: number;
    special_price: number | null;
    notes: string | null;
    week: number;
    year: number;
    weekmenu: Weekmenu;
}

interface User {
    id: number;
    name: string;
}

interface Group {
    id: number;
    name: string;
}

interface GroupedOrder {
    user: User;
    group: Group | null;
    orders: Order[];
    total: number;
    order_date: string;
}

interface Invoice {
    id: number;
    invoice_number: string;
    invoice_date: string;
    total: number;
    items_count: number;
}

interface Props {
    order: GroupedOrder;
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:open', 'success']);

const selectedOrderIds = ref<number[]>(props.order.orders.map(o => o.id));
const existingInvoices = ref<Invoice[]>([]);
const loadingInvoices = ref(false);

// Generate invoice number
const generateInvoiceNumber = computed(() => {
    const year = props.order.orders[0]?.year || new Date().getFullYear();
    const week = props.order.orders[0]?.week || 1;
    const weekPadded = String(week).padStart(2, '0');
    const clientIdPadded = String(props.order.user.id).padStart(5, '0');
    return `${year}${weekPadded}${clientIdPadded}`;
});

const form = useForm({
    invoice_number: generateInvoiceNumber.value,
    invoice_date: new Date().toISOString().split('T')[0],
    user_id: props.order.user.id,
    order_ids: selectedOrderIds.value,
});

const selectedOrders = computed(() => {
    return props.order.orders.filter(o => selectedOrderIds.value.includes(o.id));
});

const getOrderPrice = (order: Order) => {
    return order.special_price ?? order.weekmenu.menu.price;
};

const getOrderTotal = (order: Order) => {
    return order.quantity * getOrderPrice(order);
};

const totalInclTax = computed(() => {
    return selectedOrders.value.reduce((sum, order) => sum + getOrderTotal(order), 0);
});

const totalExclTax = computed(() => {
    return totalInclTax.value / 1.09;
});

const vatAmount = computed(() => {
    return totalInclTax.value - totalExclTax.value;
});

const toggleOrder = (orderId: number) => {
    const index = selectedOrderIds.value.indexOf(orderId);
    if (index > -1) {
        selectedOrderIds.value.splice(index, 1);
    } else {
        selectedOrderIds.value.push(orderId);
    }
};

const loadInvoices = async () => {
    loadingInvoices.value = true;
    try {
        const response = await fetch(`/admin/invoices/by-user/${props.order.user.id}`);
        const data = await response.json();
        existingInvoices.value = data.invoices || [];
    } catch (error) {
        console.error('Failed to load invoices:', error);
    } finally {
        loadingInvoices.value = false;
    }
};

loadInvoices();

watch(() => props.order.user.id, () => {
    loadInvoices();
    // Reset form for new client
    form.invoice_number = generateInvoiceNumber.value;
    form.invoice_date = new Date().toISOString().split('T')[0];
    form.user_id = props.order.user.id;
    selectedOrderIds.value = props.order.orders.map(o => o.id);
});

const handleSubmit = () => {
    if (selectedOrderIds.value.length === 0) {
        alert('Please select at least one order');
        return;
    }
    
    form.order_ids = selectedOrderIds.value;
    
    router.post('/admin/invoices', form.data(), {
        preserveScroll: true,
        onSuccess: () => {
            loadInvoices();
            
            form.invoice_number = generateInvoiceNumber.value;
            form.invoice_date = new Date().toISOString().split('T')[0];
            selectedOrderIds.value = props.order.orders.map(o => o.id);
        },
        onError: (errors) => {
            console.error('Invoice creation errors:', errors);
        }
    });
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const viewInvoice = (invoiceId: number) => {
    window.open(`/admin/invoices/${invoiceId}/pdf`, '_blank');
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-6xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Generate Invoice for {{ order.user.name }}</DialogTitle>
                <DialogDescription>Create a new invoice or view existing invoices</DialogDescription>
            </DialogHeader>
            
            <div class="grid grid-cols-2 gap-6">
                <!-- Left: Create Invoice -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold">New Invoice</h3>
                    
                    <div>
                        <Label for="invoice_number">Invoice Number *</Label>
                        <Input
                            id="invoice_number"
                            v-model="form.invoice_number"
                            :class="{ 'border-red-500': form.errors.invoice_number }"
                        />
                        <p v-if="form.errors.invoice_number" class="text-sm text-red-500 mt-1">
                            {{ form.errors.invoice_number }}
                        </p>
                    </div>
                    
                    <div>
                        <Label for="invoice_date">Invoice Date *</Label>
                        <Input
                            id="invoice_date"
                            type="date"
                            v-model="form.invoice_date"
                            :class="{ 'border-red-500': form.errors.invoice_date }"
                        />
                        <p v-if="form.errors.invoice_date" class="text-sm text-red-500 mt-1">
                            {{ form.errors.invoice_date }}
                        </p>
                    </div>
                    
                    <div>
                        <Label class="mb-2 block">Orders (click to remove)</Label>
                        <div class="space-y-2 max-h-64 overflow-y-auto border rounded-lg p-2">
                            <button
                                v-for="orderItem in order.orders"
                                :key="orderItem.id"
                                type="button"
                                @click="toggleOrder(orderItem.id)"
                                :class="[
                                    'w-full text-left p-3 rounded border transition-colors flex items-center justify-between',
                                    selectedOrderIds.includes(orderItem.id)
                                        ? 'border-primary bg-primary/5'
                                        : 'border-gray-300 bg-gray-100 opacity-50'
                                ]"
                            >
                                <div class="flex-1">
                                    <div class="font-medium">
                                        {{ orderItem.quantity }}× {{ orderItem.weekmenu.menu.label }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        €{{ getOrderPrice(orderItem).toFixed(2) }} each
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold">€{{ getOrderTotal(orderItem).toFixed(2) }}</span>
                                    <X v-if="selectedOrderIds.includes(orderItem.id)" class="h-4 w-4 text-red-500" />
                                </div>
                            </button>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span>Total Excl. VAT</span>
                            <span>€{{ totalExclTax.toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>VAT (9%)</span>
                            <span>€{{ vatAmount.toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-lg border-t pt-2">
                            <span>Total Incl. VAT</span>
                            <span>€{{ totalInclTax.toFixed(2) }}</span>
                        </div>
                    </div>
                    
                    <Button 
                        @click="handleSubmit" 
                        :disabled="selectedOrderIds.length === 0 || form.processing"
                        class="w-full"
                    >
                        Generate Invoice
                    </Button>
                </div>
                
                <!-- Right: Existing Invoices -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold">Existing Invoices</h3>
                    
                    <div v-if="loadingInvoices" class="text-center py-8 text-muted-foreground">
                        Loading invoices...
                    </div>
                    
                    <div v-else-if="existingInvoices.length === 0" class="text-center py-8 text-muted-foreground">
                        No invoices found for this client
                    </div>
                    
                    <div v-else class="space-y-2 max-h-[500px] overflow-y-auto">
                        <button
                            v-for="invoice in existingInvoices"
                            :key="invoice.id"
                            type="button"
                            @click="viewInvoice(invoice.id)"
                            class="w-full text-left p-4 rounded border hover:border-primary hover:bg-primary/5 transition-colors"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-semibold">{{ invoice.invoice_number }}</div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ formatDate(invoice.invoice_date) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground mt-1">
                                        {{ invoice.items_count }} item(s)
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold">€{{ invoice.total.toFixed(2) }}</div>
                                    <div class="text-xs text-primary mt-1">View PDF →</div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>