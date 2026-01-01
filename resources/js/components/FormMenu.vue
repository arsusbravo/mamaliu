<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DialogFooter, DialogClose } from '@/components/ui/dialog';
import { Upload, X } from 'lucide-vue-next';

interface Menu {
    id: number;
    label: string | null;
    description: string | null;
    price: number | null;
    menutype: string;
    image_url: string | null;
    has_image: boolean;
}

interface Props {
    menu: Menu;
    submitUrl: string;
    method: 'post' | 'put';
}

const props = defineProps<Props>();
const emit = defineEmits(['success']);

const form = useForm({
    label: props.menu.label || '',
    description: props.menu.description || '',
    price: props.menu.price || 0,
    menutype: props.menu.menutype || 'normal',
    image: null as File | null,
    delete_image: false,
});

const imagePreview = ref<string | null>(
    props.menu.image_url || null
);
const hasOriginalImage = ref(props.menu.has_image);

const handleImageChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (file) {
        form.image = file;
        form.delete_image = false;
        imagePreview.value = URL.createObjectURL(file);
    }
};

const removeImage = () => {
    form.image = null;
    imagePreview.value = null;
    
    // Mark for deletion if editing and has original image
    if (props.method === 'put' && hasOriginalImage.value) {
        form.delete_image = true;
    }
    
    const input = document.getElementById('image') as HTMLInputElement;
    if (input) input.value = '';
};

const submit = () => {
    const submitMethod = props.method === 'post' ? 'post' : 'post'; // Always use post for file uploads
    const url = props.method === 'put' ? `${props.submitUrl}?_method=PUT` : props.submitUrl;
    
    form[submitMethod](url, {
        onSuccess: () => {
            emit('success');
            form.reset();
            imagePreview.value = null;
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="grid gap-4">
            <div class="grid gap-2">
                <Label for="label">Label *</Label>
                <Input
                    id="label"
                    v-model="form.label"
                    type="text"
                    required
                    :class="{ 'border-red-500': form.errors.label }"
                />
                <span v-if="form.errors.label" class="text-sm text-red-500">
                    {{ form.errors.label }}
                </span>
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <Textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    :class="{ 'border-red-500': form.errors.description }"
                />
                <span v-if="form.errors.description" class="text-sm text-red-500">
                    {{ form.errors.description }}
                </span>
            </div>

            <div class="grid gap-2">
                <Label for="price">Price (€) *</Label>
                <Input
                    id="price"
                    v-model="form.price"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    :class="{ 'border-red-500': form.errors.price }"
                />
                <span v-if="form.errors.price" class="text-sm text-red-500">
                    {{ form.errors.price }}
                </span>
            </div>

            <div class="grid gap-2">
                <Label for="menutype">Menu Type *</Label>
                <Select v-model="form.menutype" required>
                    <SelectTrigger
                        id="menutype"
                        :class="{ 'border-red-500': form.errors.menutype }"
                    >
                        <SelectValue placeholder="Select menu type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="normal">Normal</SelectItem>
                        <SelectItem value="menuset">Menu Set</SelectItem>
                    </SelectContent>
                </Select>
                <span v-if="form.errors.menutype" class="text-sm text-red-500">
                    {{ form.errors.menutype }}
                </span>
            </div>

            <div class="grid gap-2">
                <Label for="image">Image</Label>
                
                <!-- Image Preview -->
                <div v-if="imagePreview" class="space-y-2">
                    <div class="relative w-full max-w-md rounded-lg overflow-hidden border">
                        <img :src="imagePreview" alt="Preview" class="w-full h-auto object-cover" />
                        <button
                            type="button"
                            @click="removeImage"
                            class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-2 hover:bg-red-600 shadow-lg"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <p class="text-xs text-muted-foreground">Click the X button to remove or upload a new image to replace</p>
                </div>

                <!-- Upload Button -->
                <div class="flex items-center justify-center w-full">
                    <label
                        for="image"
                        class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <Upload class="w-8 h-8 mb-2 text-gray-500" />
                            <p class="mb-2 text-sm text-gray-500">
                                <span class="font-semibold">Click to {{ imagePreview ? 'replace' : 'upload' }}</span> or drag and drop
                            </p>
                            <p class="text-xs text-gray-500">JPG, PNG up to 5MB (will be resized to fit 800x600)</p>
                        </div>
                        <input
                            id="image"
                            type="file"
                            class="hidden"
                            accept="image/jpeg,image/jpg,image/png"
                            @change="handleImageChange"
                        />
                    </label>
                </div>
                <span v-if="form.errors.image" class="text-sm text-red-500">
                    {{ form.errors.image }}
                </span>
            </div>
        </div>

        <DialogFooter class="gap-2">
            <DialogClose as-child>
                <Button type="button" variant="secondary">
                    Cancel
                </Button>
            </DialogClose>
            <Button type="submit" :disabled="form.processing">
                {{ method === 'post' ? 'Create Menu' : 'Update Menu' }}
            </Button>
        </DialogFooter>
    </form>
</template>