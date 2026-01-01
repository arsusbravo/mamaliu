<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-vue-next';

interface Props {
    class?: HTMLAttributes['class'];
    sortKey: string;
    currentSort?: string;
    currentDirection?: 'asc' | 'desc';
}

const props = defineProps<Props>();
const emit = defineEmits<{
    sort: [key: string];
}>();

const isActive = computed(() => props.currentSort === props.sortKey);
const direction = computed(() => props.currentDirection);
</script>

<template>
    <th
        :class="cn(
            'h-12 px-4 text-left align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0 cursor-pointer select-none hover:bg-muted/50',
            props.class,
        )"
        @click="emit('sort', sortKey)"
    >
        <div class="flex items-center gap-2">
            <slot />
            <span class="shrink-0">
                <ChevronUp 
                    v-if="isActive && direction === 'asc'" 
                    class="h-4 w-4" 
                />
                <ChevronDown 
                    v-else-if="isActive && direction === 'desc'" 
                    class="h-4 w-4" 
                />
                <ChevronsUpDown 
                    v-else 
                    class="h-4 w-4 opacity-50" 
                />
            </span>
        </div>
    </th>
</template>