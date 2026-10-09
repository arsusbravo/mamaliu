<script setup lang="ts">
import { ref, computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';

interface Menu {
    id: number;
    label: string;
    price: number;
    image_url: string | null;
    has_image: boolean;
}

interface Props {
    menus: Menu[];
    modelValue: number[];
    error?: string;
}

const props = withDefaults(defineProps<Props>(), {
    error: '',
});
const emit = defineEmits(['update:modelValue']);

const searchQuery = ref('');

const filteredMenus = computed(() => {
    if (!searchQuery.value) return props.menus;

    const query = searchQuery.value.toLowerCase();
    return props.menus.filter(menu => menu.label.toLowerCase().includes(query));
});

const isSelected = (menuId: number) => props.modelValue.includes(menuId);

const toggle = (menuId: number) => {
    const next = isSelected(menuId)
        ? props.modelValue.filter(id => id !== menuId)
        : [...props.modelValue, menuId];
    emit('update:modelValue', next);
};
</script>

<template>
    <div class="grid gap-2">
        <Input
            v-model="searchQuery"
            type="text"
            placeholder="Search menus..."
            :class="{ 'border-red-500': error }"
        />

        <div class="max-h-64 overflow-y-auto border rounded-md">
            <label
                v-for="menu in filteredMenus"
                :key="menu.id"
                class="flex items-center gap-3 w-full px-3 py-2 hover:bg-accent cursor-pointer"
            >
                <Checkbox :checked="isSelected(menu.id)" @update:checked="toggle(menu.id)" />
                <span class="flex-1">{{ menu.label }}</span>
                <span class="text-xs text-muted-foreground">€{{ Number(menu.price).toFixed(2) }}</span>
            </label>

            <div v-if="filteredMenus.length === 0" class="p-4 text-center text-muted-foreground">
                No menus found
            </div>
        </div>

        <p class="text-xs text-muted-foreground">{{ modelValue.length }} selected</p>
        <span v-if="error" class="text-sm text-red-500">{{ error }}</span>
    </div>
</template>
