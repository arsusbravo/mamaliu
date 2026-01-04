<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Calendar, CheckCircle2, Package, Heart } from 'lucide-vue-next';

interface OrderItem {
    id: number;
    menu_label: string;
    quantity: number;
    price: number;
    total: number;
    notes: string | null;
}

interface OrderGroup {
    week: number;
    year: number;
    total: number;
    items_count: number;
    created_at: string;
    orders: OrderItem[];
}

interface Props {
    orders: OrderGroup[];
}

const props = defineProps<Props>();
const page = usePage();

const selectedOrder = ref<OrderGroup | null>(null);
const showOrderDialog = ref(false);
const isNewOrder = ref(false);

// Check for success flash message and open dialog directly
onMounted(() => {
    const flash = page.props.flash as any;
    if (flash?.success && flash?.orderWeek && flash?.orderYear) {
        // Find and show the order details
        const order = props.orders.find(o => o.week === flash.orderWeek && o.year === flash.orderYear);
        if (order) {
            selectedOrder.value = order;
            isNewOrder.value = true;
            showOrderDialog.value = true;
        }
    }
});

const viewOrderDetails = (order: OrderGroup) => {
    selectedOrder.value = order;
    isNewOrder.value = false;
    showOrderDialog.value = true;
};

const formatDate = (isoString: string) => {
    const date = new Date(isoString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="我的訂單" />
    
    <ClientLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-4xl font-black text-transparent bg-clip-text bg-linear-to-r from-red-600 to-orange-500 mb-2">
                    我的訂單
                </h1>
                <p class="text-gray-600">查看您的訂單歷史與詳細資訊</p>
            </div>

            <!-- Empty State -->
            <div v-if="orders.length === 0" class="text-center py-20">
                <div class="bg-white rounded-3xl shadow-xl p-12 max-w-md mx-auto">
                    <Package class="h-24 w-24 text-gray-300 mx-auto mb-6" />
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">尚無訂單</h2>
                    <p class="text-gray-600 mb-6">開始點餐吧！</p>
                    <Button 
                        @click="$inertia.visit('/')"
                        class="bg-linear-to-r from-red-600 to-orange-500 hover:from-red-700 hover:to-orange-600"
                    >
                        瀏覽菜單
                    </Button>
                </div>
            </div>

            <!-- Orders List -->
            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card 
                    v-for="order in orders" 
                    :key="`${order.year}-${order.week}`"
                    class="overflow-hidden hover:shadow-xl transition-all duration-300 border-2 border-transparent hover:border-red-200 cursor-pointer"
                    @click="viewOrderDetails(order)"
                >
                    <CardHeader class="bg-linear-to-r from-red-50 to-orange-50 border-b-2 border-red-100">
                        <CardTitle class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Calendar class="h-5 w-5 text-red-600" />
                                <span class="text-lg">第 {{ order.week }} 週，{{ order.year }}</span>
                            </div>
                        </CardTitle>
                        <p class="text-sm text-gray-600 mt-1">{{ formatDate(order.created_at) }}</p>
                    </CardHeader>
                    <CardContent class="p-6">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">品項</span>
                                <span class="font-semibold">{{ order.items_count }}</span>
                            </div>
                            <div class="flex items-center justify-between text-lg font-bold border-t pt-3">
                                <span>總計</span>
                                <span class="text-transparent bg-clip-text bg-linear-to-r from-red-600 to-orange-500">
                                    €{{ order.total.toFixed(2) }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Order Details Dialog -->
        <Dialog v-model:open="showOrderDialog">
            <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                <!-- Thank You Banner (only for new orders) -->
                <div v-if="isNewOrder" class="bg-linear-to-r from-red-600 to-orange-500 text-white p-6 rounded-xl mb-4 -mt-6 -mx-6">
                    <div class="flex items-center justify-center gap-3 mb-2">
                        <CheckCircle2 class="h-8 w-8" />
                        <h2 class="text-2xl font-black">謝謝您！</h2>
                    </div>
                    <p class="text-center text-white/90">您的訂單已成功送出</p>
                </div>
                
                <DialogHeader>
                    <DialogTitle class="text-2xl font-bold flex items-center gap-2">
                        <Calendar class="h-6 w-6 text-red-600" />
                        第 {{ selectedOrder?.week }} 週，{{ selectedOrder?.year }}
                    </DialogTitle>
                    <DialogDescription>
                        訂單建立於 {{ selectedOrder ? formatDate(selectedOrder.created_at) : '' }}
                    </DialogDescription>
                </DialogHeader>
                
                <div v-if="selectedOrder" class="space-y-4 mt-4">
                    <!-- Order Items -->
                    <div class="space-y-3">
                        <h3 class="font-semibold text-lg">訂單品項</h3>
                        <div 
                            v-for="item in selectedOrder.orders" 
                            :key="item.id"
                            class="border-2 border-gray-100 rounded-xl p-4 bg-linear-to-r from-white to-orange-50/30"
                        >
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-800">{{ item.menu_label }}</h4>
                                    <p class="text-sm text-gray-600">€{{ item.price.toFixed(2) }} × {{ item.quantity }}</p>
                                </div>
                                <span class="font-bold text-lg">€{{ item.total.toFixed(2) }}</span>
                            </div>
                            <p v-if="item.notes" class="text-sm text-gray-600 italic bg-gray-50 p-2 rounded">
                                "{{ item.notes }}"
                            </p>
                        </div>
                    </div>
                    
                    <!-- Total -->
                    <div class="border-t-2 pt-4 mt-4">
                        <div class="flex justify-between items-center text-2xl font-black">
                            <span>總計</span>
                            <span class="text-transparent bg-clip-text bg-linear-to-r from-red-600 to-orange-500">
                                €{{ selectedOrder.total.toFixed(2) }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Thank you note (only for new orders) -->
                    <div v-if="isNewOrder" class="bg-orange-50 p-4 rounded-xl text-center border-2 border-orange-200">
                        <Heart class="h-6 w-6 text-red-600 mx-auto mb-2" />
                        <p class="text-gray-700 font-medium">
                            感謝您的訂購！我們將用心為您準備餐點。
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </ClientLayout>
</template>