<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DialogFooter, DialogClose } from '@/components/ui/dialog';

interface Group {
    id: number;
    name: string | null;
    slug: string | null;
    active: boolean;
}

interface Props {
    group: Group;
    submitUrl: string;
    method: 'post' | 'put';
}

const props = defineProps<Props>();
const emit = defineEmits(['success']);

const form = useForm({
    name: props.group.name || '',
    slug: props.group.slug || '',
    active: props.group.active,
});

// Auto-generate slug from name
watch(() => form.name, (newName) => {
    if (props.method === 'post' && newName) {
        form.slug = newName
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
});

const submit = () => {
    const submitMethod = props.method === 'post' ? 'post' : 'put';
    
    form[submitMethod](props.submitUrl, {
        onSuccess: () => {
            emit('success');
            form.reset();
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
                <span v-if="form.errors.name" class="text-sm text-red-500">
                    {{ form.errors.name }}
                </span>
            </div>

            <div class="grid gap-2">
                <Label for="slug">Slug *</Label>
                <Input
                    id="slug"
                    v-model="form.slug"
                    type="text"
                    required
                    :class="{ 'border-red-500': form.errors.slug }"
                />
                <span v-if="form.errors.slug" class="text-sm text-red-500">
                    {{ form.errors.slug }}
                </span>
                <p class="text-xs text-muted-foreground">Auto-generated from name, but you can customize it</p>
            </div>

            <div class="flex items-center space-x-2">
                <input
                    id="active"
                    type="checkbox"
                    v-model="form.active"
                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                />
                <Label
                    for="active"
                    class="text-sm font-medium leading-none cursor-pointer"
                >
                    Active
                </Label>
                <span v-if="form.errors.active" class="text-sm text-red-500 ml-2">
                    {{ form.errors.active }}
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
                {{ method === 'post' ? 'Create Group' : 'Update Group' }}
            </Button>
        </DialogFooter>
    </form>
</template>