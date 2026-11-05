<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface LabelFormat {
    id: string;
    name: string;
    size: string;
    count: number;
}

interface Props {
    open: boolean;
    formats: LabelFormat[];
    defaultFormat: string;
    description: string;
    // Builds the PDF url for the chosen sheet format
    buildUrl: (format: string) => string;
    canDownload?: boolean;
}

const props = withDefaults(defineProps<Props>(), { canDownload: true });
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const STORAGE_KEY = 'menuLabelFormat';
const format = ref(props.defaultFormat);

// Restore the last used format every time the dialog opens
watch(
    () => props.open,
    (open) => {
        if (!open) return;
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved && props.formats.some((f) => f.id === saved)) {
                format.value = saved;
            }
        } catch {
            // localStorage unavailable; keep the current format
        }
    },
);

const download = () => {
    try {
        localStorage.setItem(STORAGE_KEY, format.value);
    } catch {
        // ignore
    }
    window.open(props.buildUrl(format.value), '_blank');
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Print labels</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <slot />
            <select
                v-model="format"
                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <option v-for="f in formats" :key="f.id" :value="f.id">
                    {{ f.name }} - {{ f.size }} ({{ f.count }} labels)
                </option>
            </select>
            <div class="flex justify-end gap-2">
                <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                <Button :disabled="!canDownload" @click="download">Download PDF</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
