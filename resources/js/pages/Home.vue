<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import ClientLayout from '@/layouts/ClientLayout.vue';
import MenuCard from '@/components/MenuCard.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ShoppingCart, Plus, Minus, X, ZoomIn, Sparkles, Calendar, Strikethrough } from 'lucide-vue-next';

interface Menu {
    id: number;
    label: string;
    description: string;
    price: number;
    image_url: string | null;
    has_image: boolean;
}

interface PickupPoint {
    id: number;
    name: string;
}

interface Group {
    id: number;
    name: string;
    pickup_points: PickupPoint[];
}

interface Weekmenu {
    id: number;
    week: number;
    year: number;
    quantity: number;
    menu: Menu;
    group: Group | null;
}

interface CartItem {
    weekmenu_id: number;
    menu_id: number;
    menu_label: string;
    quantity: number;
    price: number;
    max_quantity: number;
    notes: string;
}

interface FutureWeek {
    week: number;
    year: number;
}

interface Props {
    weekmenus: Weekmenu[];
    currentWeek: number;
    currentYear: number;
    userName: string;
    welcome: boolean;
    futureWeeks: FutureWeek[];
    isPreOrder: boolean;
    userPickupPoints: PickupPoint[];
    hasActiveDiscounts: boolean;
    hasActiveGifts: boolean;
}

const props = defineProps<Props>();

const goToWeek = (week: number, year: number) => {
    router.get('/', { week, year });
};

const cart = ref<CartItem[]>([]);
const showCart = ref(false);
const showImageDialog = ref(false);
const selectedImage = ref<string | null>(null);

const selectedPickupPointId = ref<number | null>(
    props.userPickupPoints.length > 0 ? props.userPickupPoints[0].id : null
);

const discountCode = ref('');
const appliedDiscount = ref<{ amount: number } | null>(null);
const discountError = ref<string | null>(null);
const applyingDiscount = ref(false);

const giftCode = ref('');
const appliedGift = ref<{ quantity: number; rewardMenuLabel: string } | null>(null);
const giftError = ref<string | null>(null);
const applyingGift = ref(false);

const getCookie = (name: string): string => {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
};

const addToCart = (weekmenu: Weekmenu, quantity: number) => {
    const existingItem = cart.value.find(item => item.weekmenu_id === weekmenu.id);
    
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.value.push({
            weekmenu_id: weekmenu.id,
            menu_id: weekmenu.menu.id,
            menu_label: weekmenu.menu.label,
            quantity: quantity,
            price: weekmenu.menu.price,
            max_quantity: weekmenu.quantity,
            notes: '',
        });
    }
};

const removeFromCart = (weekmenuId: number) => {
    cart.value = cart.value.filter(item => item.weekmenu_id !== weekmenuId);
};

const updateCartQuantity = (weekmenuId: number, delta: number) => {
    const item = cart.value.find(i => i.weekmenu_id === weekmenuId);
    if (item) {
        const newQuantity = item.quantity + delta;
        if (newQuantity <= 0) {
            removeFromCart(weekmenuId);
        } else if (newQuantity <= item.max_quantity) {
            item.quantity = newQuantity;
        }
    }
};

const cartTotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

const discountAmount = computed(() => appliedDiscount.value?.amount ?? 0);

const cartTotalAfterDiscount = computed(() => Math.max(0, cartTotal.value - discountAmount.value));

const cartCount = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.quantity, 0);
});

// Clear any applied/attempted discount/gift preview whenever the cart changes, so a
// stale amount is never shown. The server always recalculates authoritatively on submit.
watch(cart, () => {
    appliedDiscount.value = null;
    discountError.value = null;
    appliedGift.value = null;
    giftError.value = null;
}, { deep: true });

const applyDiscountCode = async () => {
    if (!discountCode.value.trim()) return;

    applyingDiscount.value = true;
    discountError.value = null;
    appliedDiscount.value = null;

    try {
        const response = await fetch('/validate-discount', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify({
                discount_code: discountCode.value.trim(),
                orders: cart.value.map(item => ({
                    weekmenu_id: item.weekmenu_id,
                    quantity: item.quantity,
                })),
            }),
        });
        const data = await response.json();

        if (data.valid) {
            appliedDiscount.value = { amount: data.discount_amount };
        } else {
            discountError.value = data.message || '此優惠碼無效';
        }
    } catch (error) {
        discountError.value = '無法驗證優惠碼，請稍後再試';
    } finally {
        applyingDiscount.value = false;
    }
};

const applyGiftCode = async () => {
    if (!giftCode.value.trim()) return;

    applyingGift.value = true;
    giftError.value = null;
    appliedGift.value = null;

    try {
        const response = await fetch('/validate-gift', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify({
                gift_code: giftCode.value.trim(),
                orders: cart.value.map(item => ({
                    weekmenu_id: item.weekmenu_id,
                    quantity: item.quantity,
                })),
            }),
        });
        const data = await response.json();

        if (data.valid) {
            appliedGift.value = { quantity: data.quantity, rewardMenuLabel: data.reward_menu_label };
        } else {
            giftError.value = data.message || '此贈品碼無效';
        }
    } catch (error) {
        giftError.value = '無法驗證贈品碼，請稍後再試';
    } finally {
        applyingGift.value = false;
    }
};

const placeOrder = () => {
    if (cart.value.length === 0) {
        alert('您的購物車是空的');
        return;
    }

    if (props.userPickupPoints.length > 1 && !selectedPickupPointId.value) {
        alert('請選擇取貨地點');
        return;
    }

    const orders = cart.value.map(item => ({
        weekmenu_id: item.weekmenu_id,
        quantity: item.quantity,
        notes: item.notes,
    }));

    router.post('/place-order', {
        pickup_point_id: selectedPickupPointId.value,
        discount_code: discountCode.value.trim() || null,
        gift_code: giftCode.value.trim() || null,
        orders,
    }, {
        onSuccess: () => {
            cart.value = [];
            showCart.value = false;
            selectedPickupPointId.value = props.userPickupPoints.length > 0 ? props.userPickupPoints[0].id : null;
            discountCode.value = '';
            appliedDiscount.value = null;
            discountError.value = null;
            giftCode.value = '';
            appliedGift.value = null;
            giftError.value = null;
        },
        onError: (errors) => {
            if (errors.discount_code) {
                discountError.value = errors.discount_code;
            }
            if (errors.gift_code) {
                giftError.value = errors.gift_code;
            }
        },
    });
};

const enlargeImage = (imageUrl: string) => {
    selectedImage.value = imageUrl;
    showImageDialog.value = true;
};

const selectedImageUrl = computed(() => selectedImage.value || '');
</script>

<template>
    <Head title="點餐菜單" />
    
    <ClientLayout :cart-count="cartCount" @show-cart="showCart = true">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 font-[--font-family-wenkai]">
            <!-- Hero Section -->
            <div class="bg-linear-to-r from-red-600 to-orange-500 rounded-3xl shadow-2xl p-8 mb-8 text-white">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black mb-2 flex items-center gap-3">
                            <Sparkles class="h-8 w-8 animate-pulse" />
                            你好，{{ userName }}！
                        </h1>
                        <h3 v-if="welcome">您現在位於 <strong class="text-yellow-100">mamaliu.com</strong>！從現在起您可以在我們的新網域訂購，而不是舊的網域 <s class=" text-red-400">mama-liu.com</s>。</h3>
                        <div class="flex items-center gap-3 text-white/90 text-lg">
                            <Calendar class="h-5 w-5" />
                            <span class="font-semibold">第 {{ currentWeek }} 週，{{ currentYear }}</span>
                            <span v-if="isPreOrder" class="px-4 py-1.5 bg-white/20 backdrop-blur-sm rounded-full text-sm font-bold border-2 border-white/30">
                                🎉 預購
                            </span>
                        </div>
                    </div>
                    <div class="hidden md:block">
                        <img src="/images/logo.png" alt="Chef" class="h-32 w-32 object-contain drop-shadow-2xl" />
                    </div>
                </div>
            </div>

            <!-- No Menus State -->
            <div v-if="weekmenus.length === 0" class="text-center py-20">
                <div class="bg-white rounded-3xl shadow-xl p-12 max-w-lg mx-auto">
                    <img src="/images/logo.png" alt="Mama Liu" class="h-32 w-32 object-contain mx-auto mb-6 opacity-50" />
                    <h2 class="text-3xl font-bold text-gray-800 mb-4">目前沒有菜單</h2>
                    <p class="text-gray-600 text-lg mb-6">本週菜單尚未推出，敬請期待！</p>

                    <!-- Pre-order buttons for future weeks -->
                    <div v-if="futureWeeks.length > 0" class="space-y-4">
                        <p class="text-gray-500 text-sm">或者您可以預購以下週次的菜單：</p>
                        <div class="flex flex-wrap justify-center gap-3">
                            <Button
                                v-for="fw in futureWeeks"
                                :key="`${fw.year}-${fw.week}`"
                                @click="goToWeek(fw.week, fw.year)"
                                class="bg-linear-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-bold px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all"
                            >
                                <Calendar class="h-4 w-4 mr-2" />
                                第 {{ fw.week }} 週
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Menu Grid -->
            <div v-else>
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <span class="bg-linear-to-r from-red-600 to-orange-500 text-white px-4 py-2 rounded-xl">
                        本週菜單
                    </span>
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
                    <MenuCard 
                        v-for="weekmenu in weekmenus" 
                        :key="weekmenu.id"
                        :weekmenu="weekmenu"
                        @add-to-cart="addToCart"
                        @enlarge-image="enlargeImage"
                    />
                </div>
            </div>

            <!-- Floating Cart Button -->
            <button
                v-if="cartCount > 0"
                @click="showCart = true"
                class="fixed bottom-8 right-8 bg-linear-to-r from-red-600 to-orange-500 hover:from-red-700 hover:to-orange-600 text-white rounded-full p-5 shadow-2xl transition-all hover:scale-110 flex items-center gap-3 z-50 group"
            >
                <ShoppingCart class="h-7 w-7 group-hover:animate-bounce" />
                <span class="font-black text-xl">{{ cartCount }}</span>
            </button>
        </div>

        <!-- Cart Dialog -->
        <Dialog v-model:open="showCart">
            <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-bold flex items-center gap-2">
                        <ShoppingCart class="h-6 w-6 text-red-600" />
                        您的購物車
                    </DialogTitle>
                    <DialogDescription>請在送出訂單前確認您的餐點</DialogDescription>
                </DialogHeader>
                
                <div v-if="cart.length === 0" class="text-center py-12 text-gray-500">
                    <ShoppingCart class="h-16 w-16 mx-auto mb-4 text-gray-300" />
                    <p class="text-lg">您的購物車是空的</p>
                </div>
                
                <div v-else class="space-y-4">
                    <div 
                        v-for="item in cart" 
                        :key="item.weekmenu_id"
                        class="border-2 border-gray-100 rounded-xl p-4 hover:border-red-200 transition-colors bg-linear-to-r from-white to-orange-50/30"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <h3 class="font-bold text-lg text-gray-800">{{ item.menu_label }}</h3>
                                <p class="text-sm text-red-600 font-semibold">€{{ item.price.toFixed(2) }} 每份</p>
                            </div>
                            <button 
                                @click="removeFromCart(item.weekmenu_id)"
                                class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition-colors"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                        
                        <div class="flex items-center gap-3 mb-3">
                            <button 
                                @click="updateCartQuantity(item.weekmenu_id, -1)"
                                class="p-2 rounded-lg border-2 border-red-200 hover:bg-red-50 hover:border-red-400 transition-colors"
                            >
                                <Minus class="h-4 w-4 text-red-600" />
                            </button>
                            <span class="w-16 text-center font-bold text-xl text-gray-800">{{ item.quantity }}</span>
                            <button 
                                @click="updateCartQuantity(item.weekmenu_id, 1)"
                                :disabled="item.quantity >= item.max_quantity"
                                class="p-2 rounded-lg border-2 border-red-200 hover:bg-red-50 hover:border-red-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <Plus class="h-4 w-4 text-red-600" />
                            </button>
                            <span class="text-sm text-gray-500 ml-auto font-semibold">
                                €{{ (item.price * item.quantity).toFixed(2) }}
                            </span>
                        </div>
                        
                        <Textarea
                            v-model="item.notes"
                            placeholder="特殊要求或備註..."
                            rows="2"
                            class="text-sm border-gray-200 focus:border-red-400 focus:ring-red-400"
                        />
                    </div>
                    
                    <div class="border-t-2 border-gray-200 pt-6 mt-6">
                        <!-- Pick-up point selector (only shown when group has multiple options) -->
                        <div v-if="userPickupPoints.length > 1" class="mb-6">
                            <Label class="text-base font-semibold text-gray-700 mb-2 block">取貨地點 *</Label>
                            <Select v-model="selectedPickupPointId">
                                <SelectTrigger class="w-full border-2 border-orange-200 focus:border-orange-400">
                                    <SelectValue placeholder="請選擇取貨地點..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="point in userPickupPoints"
                                        :key="point.id"
                                        :value="point.id"
                                    >
                                        {{ point.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Discount code (only shown when an active discount currently exists) -->
                        <div v-if="hasActiveDiscounts" class="mb-6">
                            <Label class="text-base font-semibold text-gray-700 mb-2 block">優惠碼</Label>
                            <div class="flex gap-2">
                                <Input
                                    v-model="discountCode"
                                    placeholder="請輸入您的I僑卡號碼"
                                    class="border-2 border-orange-200 focus:border-orange-400"
                                    @keydown.enter.prevent="applyDiscountCode"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="!discountCode.trim() || applyingDiscount"
                                    @click="applyDiscountCode"
                                >
                                    套用
                                </Button>
                            </div>
                            <p v-if="discountError" class="text-sm text-red-600 mt-2">{{ discountError }}</p>
                            <p v-if="appliedDiscount" class="text-sm text-green-600 mt-2">
                                優惠碼已套用：-€{{ appliedDiscount.amount.toFixed(2) }}
                            </p>
                        </div>

                        <!-- Gift code (only shown when an active gift currently exists) -->
                        <div v-if="hasActiveGifts" class="mb-6">
                            <Label class="text-base font-semibold text-gray-700 mb-2 block">贈品碼</Label>
                            <div v-if="!appliedGift" class="flex gap-2">
                                <Input
                                    v-model="giftCode"
                                    placeholder="請輸入您的I僑卡號碼"
                                    class="border-2 border-orange-200 focus:border-orange-400"
                                    @keydown.enter.prevent="applyGiftCode"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="!giftCode.trim() || applyingGift"
                                    @click="applyGiftCode"
                                >
                                    套用
                                </Button>
                            </div>
                            <p v-if="giftError" class="text-sm text-red-600 mt-2">{{ giftError }}</p>
                            <p v-if="appliedGift" class="text-sm text-green-600 mt-2">
                                🎁 贈品碼已套用：您將獲得 {{ appliedGift.quantity }}× {{ appliedGift.rewardMenuLabel }} — 免費贈送！
                            </p>
                        </div>

                        <div class="space-y-2 mb-6">
                            <div class="flex items-center justify-between text-lg text-gray-600">
                                <span>小計：</span>
                                <span>€{{ cartTotal.toFixed(2) }}</span>
                            </div>
                            <div v-if="appliedDiscount" class="flex items-center justify-between text-lg text-green-600">
                                <span>折扣：</span>
                                <span>-€{{ discountAmount.toFixed(2) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-3xl font-black text-gray-800">
                                <span>總計：</span>
                                <span class="text-transparent bg-clip-text bg-linear-to-r from-red-600 to-orange-500">
                                    €{{ cartTotalAfterDiscount.toFixed(2) }}
                                </span>
                            </div>
                        </div>

                        <Button
                            @click="placeOrder"
                            class="w-full bg-linear-to-r from-red-600 to-orange-500 hover:from-red-700 hover:to-orange-600 text-white font-bold text-lg py-6 rounded-xl shadow-lg hover:shadow-xl transition-all"
                            size="lg"
                        >
                            立即下單 🎉
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Image Zoom Dialog -->
        <Dialog v-model:open="showImageDialog">
            <DialogContent class="max-w-4xl bg-black/95">
                <img 
                    v-if="selectedImageUrl" 
                    :src="selectedImageUrl" 
                    alt="Menu image" 
                    class="w-full h-auto rounded-lg" 
                />
            </DialogContent>
        </Dialog>
    </ClientLayout>
</template>