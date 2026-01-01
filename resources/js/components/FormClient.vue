<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DialogFooter, DialogClose } from '@/components/ui/dialog';

interface Group {
    id: number;
    name: string;
}

interface Client {
    id: number;
    name: string | null;
    phone: string | null;
    username: string | null;
    group_id: number | null;
    active: boolean;
}

interface Props {
    client: Client;
    groups: Group[];
    submitUrl: string;
    method: 'post' | 'put';
}

const props = defineProps<Props>();
const emit = defineEmits(['success']);

const form = useForm({
    name: props.client.name || '',
    phone: props.client.phone || '',
    username: props.client.username || '',
    group_id: props.client.group_id ? String(props.client.group_id) : '',
    active: props.client.active,
    password: '',
});

const submit = () => {
    const submitMethod = props.method === 'post' ? 'post' : 'put';
    
    const submitData = {
        ...form.data(),
        group_id: form.group_id ? Number(form.group_id) : null,
    };
    
    form.transform(() => submitData)[submitMethod](props.submitUrl, {
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
                <Label for="username">Email *</Label>
                <Input
                    id="username"
                    v-model="form.username"
                    type="email"
                    required
                    :class="{ 'border-red-500': form.errors.username }"
                />
                <span v-if="form.errors.username" class="text-sm text-red-500">
                    {{ form.errors.username }}
                </span>
            </div>

            <div class="grid gap-2">
                <Label for="phone">Phone</Label>
                <Input
                    id="phone"
                    v-model="form.phone"
                    type="text"
                    :class="{ 'border-red-500': form.errors.phone }"
                />
                <span v-if="form.errors.phone" class="text-sm text-red-500">
                    {{ form.errors.phone }}
                </span>
            </div>

            <div class="grid gap-2">
                <Label for="group">Group *</Label>
                <Select v-model="form.group_id" required>
                    <SelectTrigger
                        id="group"
                        :class="{ 'border-red-500': form.errors.group_id }"
                    >
                        <SelectValue placeholder="Select a group" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="group in groups"
                            :key="group.id"
                            :value="String(group.id)"
                        >
                            {{ group.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <span v-if="form.errors.group_id" class="text-sm text-red-500">
                    {{ form.errors.group_id }}
                </span>
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

            <div class="grid gap-2">
                <Label for="password">
                    Password {{ method === 'put' ? '(leave blank to keep current)' : '*' }}
                </Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    :required="method === 'post'"
                    :class="{ 'border-red-500': form.errors.password }"
                />
                <span v-if="form.errors.password" class="text-sm text-red-500">
                    {{ form.errors.password }}
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
                {{ method === 'post' ? 'Create Client' : 'Update Client' }}
            </Button>
        </DialogFooter>
    </form>
</template>