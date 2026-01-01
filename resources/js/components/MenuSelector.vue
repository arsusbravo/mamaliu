<script setup lang="ts">
import { ref, computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ImageOff, Check } from 'lucide-vue-next';

interface Menu {
    id: number;
    label: string;
    price: number;
    image_url: string | null;
    has_image: boolean;
}

interface Props {
    menus: Menu[];
    modelValue: string;
    error?: string;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const searchQuery = ref('');
const isOpen = ref(false);

const selectedMenu = computed(() => {
    return props.menus.find(m => String(m.id) === props.modelValue);
});

const filteredMenus = computed(() => {
    if (!searchQuery.value) return props.menus;
    
    const query = searchQuery.value.toLowerCase();
    return props.menus.filter(menu => 
        menu.label.toLowerCase().includes(query)
    );
});

const selectMenu = (menuId: number) => {
    emit('update:modelValue', String(menuId));
    isOpen.value = false;
    searchQuery.value = '';
};

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
    }
};
</script>

<template>
    <div class="grid gap-2">
        <Label>Menu *</Label>
        
        <!-- Selected Menu Display -->
        <button
            type="button"
            @click="toggleDropdown"
            :class="[
                'flex items-center gap-3 w-full px-3 py-2 text-left border rounded-md bg-background hover:bg-accent',
                error ? 'border-red-500' : 'border-input'
            ]"
        >
            <div v-if="selectedMenu" class="flex items-center gap-3 flex-1">
                <div v-if="selectedMenu.has_image && selectedMenu.image_url" class="w-10 h-10 rounded overflow-hidden shrink-0">
                    <img
                        :src="selectedMenu.image_url"
                        :alt="selectedMenu.label"
                        class="w-full h-full object-cover"
                    />
                </div>
                <div v-else class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center shrink-0">
                    <ImageOff class="h-5 w-5 text-gray-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium truncate">{{ selectedMenu.label }}</div>
                    <div class="text-xs text-muted-foreground">€{{ Number(selectedMenu.price).toFixed(2) }}</div>
                </div>
            </div>
            <span v-else class="text-muted-foreground">Select a menu</span>
            <svg class="w-4 h-4 ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown -->
        <div v-if="isOpen" class="relative">
            <div class="absolute z-50 w-full mt-1 bg-background border rounded-md shadow-lg">
                <!-- Search Input -->
                <div class="p-2 border-b">
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search menus..."
                        class="w-full"
                    />
                </div>

                <!-- Menu List - Replace ScrollArea with simple div -->
                <div class="max-h-64 overflow-y-auto">
                    <div class="p-1">
                        <button
                            v-for="menu in filteredMenus"
                            :key="menu.id"
                            type="button"
                            @click="selectMenu(menu.id)"
                            class="flex items-center gap-3 w-full px-3 py-2 rounded hover:bg-accent transition-colors"
                        >
                            <div v-if="menu.has_image && menu.image_url" class="w-12 h-12 rounded overflow-hidden shrink-0">
                                <img
                                    :src="menu.image_url"
                                    :alt="menu.label"
                                    class="w-full h-full object-cover"
                                />
                            </div>
                            <div v-else class="w-12 h-12 rounded bg-gray-100 flex items-center justify-center shrink-0">
                                <ImageOff class="h-6 w-6 text-gray-400" />
                            </div>
                            <div class="flex-1 text-left min-w-0">
                                <div class="font-medium truncate">{{ menu.label }}</div>
                                <div class="text-sm text-muted-foreground">€{{ Number(menu.price).toFixed(2) }}</div>
                            </div>
                            <Check v-if="String(menu.id) === modelValue" class="w-4 h-4 text-primary shrink-0" />
                        </button>

                        <div v-if="filteredMenus.length === 0" class="p-4 text-center text-muted-foreground">
                            No menus found
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <span v-if="error" class="text-sm text-red-500">
            {{ error }}
        </span>
    </div>
</template>