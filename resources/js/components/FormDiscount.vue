<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DialogFooter, DialogClose } from '@/components/ui/dialog';
import ClientSelector from '@/components/ClientSelector.vue';
import MenuSelector from '@/components/MenuSelector.vue';

interface Menu {
    id: number;
    label: string;
    price: number;
    image_url: string | null;
    has_image: boolean;
}

interface Group {
    id: number;
    name: string;
}

interface Discount {
    id: number;
    code: string | null;
    description: string | null;
    type: 'percentage' | 'fixed';
    value: number | null;
    target_type: 'general' | 'user' | 'group' | 'menu';
    user_id: number | null;
    group_id: number | null;
    menu_id: number | null;
    min_order_total: number | null;
    valid_from: string | null;
    valid_until: string | null;
    usage_limit: number | null;
    usage_limit_per_user: number | null;
    active: boolean;
}

interface Props {
    discount: Discount;
    submitUrl: string;
    method: 'post' | 'put';
    menus: Menu[];
    groups: Group[];
}

const props = defineProps<Props>();
const emit = defineEmits(['success']);

const form = useForm({
    code: props.discount.code || '',
    description: props.discount.description || '',
    type: props.discount.type || 'percentage',
    value: props.discount.value,
    target_type: props.discount.target_type || 'general',
    user_id: props.discount.user_id,
    group_id: props.discount.group_id,
    menu_id: props.discount.menu_id ? String(props.discount.menu_id) : '',
    min_order_total: props.discount.min_order_total,
    valid_from: props.discount.valid_from ? props.discount.valid_from.slice(0, 10) : '',
    valid_until: props.discount.valid_until ? props.discount.valid_until.slice(0, 10) : '',
    usage_limit: props.discount.usage_limit,
    usage_limit_per_user: props.discount.usage_limit_per_user,
    active: props.discount.active ?? true,
});

const submit = () => {
    const submitMethod = props.method === 'post' ? 'post' : 'put';

    form.transform((data) => ({
        ...data,
        menu_id: data.menu_id || null,
        min_order_total: data.min_order_total || null,
        valid_from: data.valid_from || null,
        valid_until: data.valid_until || null,
        usage_limit: data.usage_limit || null,
        usage_limit_per_user: data.usage_limit_per_user || null,
    }))[submitMethod](props.submitUrl, {
        onSuccess: () => {
            emit('success');
            form.reset();
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="code">Code *</Label>
                <Input
                    id="code"
                    v-model="form.code"
                    type="text"
                    required
                    :class="{ 'border-red-500': form.errors.code }"
                />
                <span v-if="form.errors.code" class="text-sm text-red-500">{{ form.errors.code }}</span>
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <Input id="description" v-model="form.description" type="text" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="type">Type *</Label>
                <select
                    id="type"
                    v-model="form.type"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="percentage">Percentage</option>
                    <option value="fixed">Fixed amount</option>
                </select>
            </div>

            <div class="grid gap-2">
                <Label for="value">{{ form.type === 'percentage' ? 'Value (%) *' : 'Value (€) *' }}</Label>
                <Input
                    id="value"
                    v-model.number="form.value"
                    type="number"
                    step="0.01"
                    min="0"
                    :max="form.type === 'percentage' ? 100 : undefined"
                    required
                    :class="{ 'border-red-500': form.errors.value }"
                />
                <span v-if="form.errors.value" class="text-sm text-red-500">{{ form.errors.value }}</span>
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="target_type">Applies to *</Label>
            <select
                id="target_type"
                v-model="form.target_type"
                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
                <option value="general">Anyone (whole order)</option>
                <option value="user">A specific client</option>
                <option value="group">A specific group</option>
                <option value="menu">A specific item</option>
            </select>
        </div>

        <div v-if="form.target_type === 'user'" class="grid gap-2">
            <Label>Client *</Label>
            <ClientSelector v-model="form.user_id" :error="form.errors.user_id" />
        </div>

        <div v-if="form.target_type === 'group'" class="grid gap-2">
            <Label for="group_id">Group *</Label>
            <select
                id="group_id"
                v-model.number="form.group_id"
                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                :class="{ 'border-red-500': form.errors.group_id }"
            >
                <option :value="null">Select a group</option>
                <option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}</option>
            </select>
            <span v-if="form.errors.group_id" class="text-sm text-red-500">{{ form.errors.group_id }}</span>
        </div>

        <div v-if="form.target_type === 'menu'" class="grid gap-2">
            <Label>Item *</Label>
            <MenuSelector :menus="menus" v-model="form.menu_id" :error="form.errors.menu_id" />
        </div>

        <div class="grid gap-2">
            <Label for="min_order_total">Minimum order total (€)</Label>
            <Input id="min_order_total" v-model.number="form.min_order_total" type="number" step="0.01" min="0" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="valid_from">Valid from</Label>
                <Input id="valid_from" v-model="form.valid_from" type="date" />
            </div>
            <div class="grid gap-2">
                <Label for="valid_until">Valid until</Label>
                <Input id="valid_until" v-model="form.valid_until" type="date" :class="{ 'border-red-500': form.errors.valid_until }" />
                <span v-if="form.errors.valid_until" class="text-sm text-red-500">{{ form.errors.valid_until }}</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="usage_limit">Total usage limit</Label>
                <Input id="usage_limit" v-model.number="form.usage_limit" type="number" min="1" placeholder="Unlimited" />
            </div>
            <div class="grid gap-2">
                <Label for="usage_limit_per_user">Usage limit per client</Label>
                <Input id="usage_limit_per_user" v-model.number="form.usage_limit_per_user" type="number" min="1" placeholder="Unlimited" />
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <input
                id="active"
                type="checkbox"
                v-model="form.active"
                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
            />
            <Label for="active" class="text-sm font-medium leading-none cursor-pointer">Active</Label>
        </div>

        <DialogFooter class="gap-2">
            <DialogClose as-child>
                <Button type="button" variant="secondary">Cancel</Button>
            </DialogClose>
            <Button type="submit" :disabled="form.processing">
                {{ method === 'post' ? 'Create Discount' : 'Update Discount' }}
            </Button>
        </DialogFooter>
    </form>
</template>
