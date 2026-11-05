<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter, DialogClose } from '@/components/ui/dialog';
import { Upload, X } from 'lucide-vue-next';

export interface LabelItem {
    id: number;
    name: string;
    text: string | null;
    show_logo: boolean;
    text_color: string;
    background_url: string | null;
    has_background: boolean;
}

interface Props {
    label: LabelItem;
    submitUrl: string;
    method: 'post' | 'put';
}

const props = defineProps<Props>();
const emit = defineEmits(['success']);

const form = useForm({
    name: props.label.name || '',
    text: props.label.text || '',
    show_logo: props.label.show_logo ?? true,
    text_color: props.label.text_color || '#000000',
    background: null as File | null,
    delete_background: false,
});

const backgroundPreview = ref<string | null>(props.label.background_url || null);
// Browsers can't render HEIC/HEIF in an <img> tag, so show the filename instead.
const selectedFileName = ref<string | null>(null);

const handleBackgroundChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    form.background = file;
    form.delete_background = false;

    const isHeic = /\.(heic|heif)$/i.test(file.name) || /hei[cf]/i.test(file.type);
    backgroundPreview.value = isHeic ? null : URL.createObjectURL(file);
    selectedFileName.value = isHeic ? file.name : null;
};

const removeBackground = () => {
    form.background = null;
    backgroundPreview.value = null;
    selectedFileName.value = null;

    if (props.method === 'put' && props.label.has_background) {
        form.delete_background = true;
    }

    const input = document.getElementById('background') as HTMLInputElement;
    if (input) input.value = '';
};

const submit = () => {
    // Always POST for file uploads; PUT is spoofed via _method
    const url = props.method === 'put' ? `${props.submitUrl}?_method=PUT` : props.submitUrl;

    form.post(url, {
        onSuccess: () => {
            emit('success');
            form.reset();
            backgroundPreview.value = null;
            selectedFileName.value = null;
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="grid gap-4">
            <div class="grid gap-2">
                <Label for="name">Name *</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    :class="{ 'border-red-500': form.errors.name }"
                />
                <span v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</span>
            </div>

            <div class="grid gap-2">
                <Label for="text">Description / text</Label>
                <Textarea
                    id="text"
                    v-model="form.text"
                    rows="3"
                    :class="{ 'border-red-500': form.errors.text }"
                />
                <span v-if="form.errors.text" class="text-sm text-red-500">{{ form.errors.text }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-6">
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.show_logo" type="checkbox" class="h-4 w-4" />
                    Show logo
                </label>
                <label class="flex items-center gap-2 text-sm">
                    Text colour
                    <input v-model="form.text_color" type="color" class="h-8 w-12 cursor-pointer rounded border" />
                </label>
            </div>
            <span v-if="form.errors.text_color" class="text-sm text-red-500">{{ form.errors.text_color }}</span>

            <div class="grid gap-2">
                <Label for="background">Background image</Label>

                <!-- Live preview -->
                <div
                    class="relative mx-auto flex aspect-[63.5/72] w-40 flex-col items-center justify-center gap-1 overflow-hidden rounded-md border bg-white p-3 text-center"
                    :style="{ color: form.text_color }"
                >
                    <img
                        v-if="backgroundPreview"
                        :src="backgroundPreview"
                        alt=""
                        class="absolute left-0 top-0 h-auto w-full"
                    />
                    <img v-if="form.show_logo" src="/images/smallogo.png" alt="" class="relative w-16" />
                    <div class="relative text-sm font-bold leading-tight">{{ form.name || 'Name' }}</div>
                    <div v-if="form.text" class="relative whitespace-pre-line text-[10px] leading-tight">{{ form.text }}</div>
                </div>

                <div v-if="selectedFileName" class="flex items-center justify-between gap-3 rounded-lg border bg-gray-50 p-3">
                    <span class="truncate text-sm text-gray-700">{{ selectedFileName }}</span>
                    <p class="text-xs text-muted-foreground">No preview for this format</p>
                </div>

                <div class="flex items-center gap-2">
                    <label
                        for="background"
                        class="flex h-20 flex-1 cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        <Upload class="mb-1 h-5 w-5 text-gray-500" />
                        <span class="text-sm text-gray-500">
                            Click to {{ backgroundPreview || selectedFileName ? 'replace' : 'upload' }} (JPG, PNG, HEIC up to 8MB)
                        </span>
                        <input
                            id="background"
                            type="file"
                            class="hidden"
                            accept="image/*,.heic,.heif"
                            @change="handleBackgroundChange"
                        />
                    </label>
                    <Button
                        v-if="backgroundPreview || selectedFileName"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="removeBackground"
                    >
                        <X class="h-4 w-4" />
                        Remove
                    </Button>
                </div>
                <span v-if="form.errors.background" class="text-sm text-red-500">{{ form.errors.background }}</span>
            </div>
        </div>

        <DialogFooter class="gap-2">
            <DialogClose as-child>
                <Button type="button" variant="secondary">Cancel</Button>
            </DialogClose>
            <Button type="submit" :disabled="form.processing">
                {{ method === 'post' ? 'Create Label' : 'Update Label' }}
            </Button>
        </DialogFooter>
    </form>
</template>
