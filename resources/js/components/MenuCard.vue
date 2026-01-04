<script setup lang="ts">
import { ref, computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ZoomIn, Plus, Minus, ShoppingBag, MapPin } from 'lucide-vue-next';

interface Menu {
    id: number;
    label: string;
    description: string;
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
    quantity: number;
    menu: Menu;
    group: Group | null;
}

interface Props {
    weekmenu: Weekmenu;
}

const props = defineProps<Props>();
const emit = defineEmits(['addToCart', 'enlargeImage']);

const selectedQuantity = ref(1);

const increaseQuantity = () => {
    if (selectedQuantity.value < props.weekmenu.quantity) {
        selectedQuantity.value++;
    }
};

const decreaseQuantity = () => {
    if (selectedQuantity.value > 1) {
        selectedQuantity.value--;
    }
};

const addToCart = () => {
    emit('addToCart', props.weekmenu, selectedQuantity.value);
    selectedQuantity.value = 1;
};

const imageUrl = computed(() => props.weekmenu.menu.image_url || '');
</script>

<template>
    <Card class="overflow-hidden hover:shadow-2xl transition-all duration-300 border-2 border-transparent hover:border-red-200 hover:scale-105 bg-white rounded-2xl">
        <div class="relative group">
            <div v-if="weekmenu.menu.has_image && imageUrl" class="relative overflow-hidden">
                <img 
                    :src="imageUrl" 
                    :alt="weekmenu.menu.label"
                    class="w-full h-56 object-cover cursor-pointer group-hover:scale-110 transition-transform duration-300"
                    @click="emit('enlargeImage', imageUrl)"
                />
                <div class="absolute inset-0 bg-linear-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            </div>
            <div v-else class="w-full h-56 bg-linear-to-br from-red-100 to-orange-100 flex items-center justify-center">
                <span class="text-gray-400 text-lg font-semibold">🍜 沒有圖片</span>
            </div>
            
            <button 
                v-if="weekmenu.menu.has_image && imageUrl"
                @click="emit('enlargeImage', imageUrl)"
                class="absolute top-3 right-3 bg-white/95 backdrop-blur-sm p-2.5 rounded-full hover:bg-white transition-all shadow-lg hover:scale-110"
            >
                <ZoomIn class="h-5 w-5 text-red-600" />
            </button>
            
            <div v-if="weekmenu.quantity <= 5" class="absolute top-3 left-3 bg-linear-to-r from-red-600 to-orange-500 text-white px-3 py-1.5 rounded-full text-xs font-bold shadow-lg">
                僅剩 {{ weekmenu.quantity }} 份！
            </div>
        </div>
        
        <CardContent class="p-5">
            <h3 class="text-xl font-black text-gray-800 mb-2 line-clamp-1">{{ weekmenu.menu.label }}</h3>
            <p class="text-gray-600 text-sm mb-4 line-clamp-2 min-h-10">{{ weekmenu.menu.description }}</p>

            <div v-if="weekmenu.group" class="flex items-center gap-1.5 text-xs text-gray-600 mb-3 bg-orange-50 px-2 py-1.5 rounded-lg">
                <MapPin class="h-3.5 w-3.5 text-orange-600" />
                <span class="font-medium">送餐地點：<span class="text-gray-800 font-semibold">{{ weekmenu.group.name }}</span></span>
            </div>
            
            <div class="flex items-center justify-between mb-5">
                <span class="text-3xl font-black text-transparent bg-clip-text bg-linear-to-r from-red-600 to-orange-500">
                    €{{ weekmenu.menu.price.toFixed(2) }}
                </span>
            </div>
            
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1 border-2 border-red-200 rounded-xl bg-red-50/50">
                    <button 
                        @click="decreaseQuantity"
                        :disabled="selectedQuantity <= 1"
                        class="p-2.5 hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed rounded-l-xl transition-colors"
                    >
                        <Minus class="h-4 w-4 text-red-600" />
                    </button>
                    <span class="w-12 text-center font-bold text-gray-800">{{ selectedQuantity }}</span>
                    <button 
                        @click="increaseQuantity"
                        :disabled="selectedQuantity >= weekmenu.quantity"
                        class="p-2.5 hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed rounded-r-xl transition-colors"
                    >
                        <Plus class="h-4 w-4 text-red-600" />
                    </button>
                </div>
                
                <Button 
                    @click="addToCart" 
                    class="flex-1 bg-linear-to-r from-red-600 to-orange-500 hover:from-red-700 hover:to-orange-600 text-white font-bold py-6 rounded-xl shadow-lg hover:shadow-xl transition-all"
                >
                    <ShoppingBag class="h-4 w-4 mr-2" />
                    加入購物車
                </Button>
            </div>
        </CardContent>
    </Card>
</template>