<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DialogFooter, DialogClose } from '@/components/ui/dialog';
import MenuSelector from '@/components/MenuSelector.vue';

interface Menu {
    id: number;
    label: string;
    price: number;
    image_url: string | null;
    has_image: boolean;
}

interface Gift {
    id: number;
    threshold_amount: number | null;
    reward_menu_id: number | null;
    valid_from: string | null;
    valid_until: string | null;
    active: boolean;
}

interface Props {
    gift: Gift;
    submitUrl: string;
    method: 'post' | 'put';
    menus: Menu[];
}

const props = defineProps<Props>();
const emit = defineEmits(['success']);

const form = useForm({
    threshold_amount: props.gift.threshold_amount,
    reward_menu_id: props.gift.reward_menu_id ? String(props.gift.reward_menu_id) : '',
    valid_from: props.gift.valid_from ? props.gift.valid_from.slice(0, 10) : '',
    valid_until: props.gift.valid_until ? props.gift.valid_until.slice(0, 10) : '',
    active: props.gift.active ?? true,
});

const submit = () => {
    const submitMethod = props.method === 'post' ? 'post' : 'put';

    form.transform((data) => ({
        ...data,
        valid_from: data.valid_from || null,
        valid_until: data.valid_until || null,
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
        <div class="grid gap-2">
            <Label for="threshold_amount">Order total threshold (€) *</Label>
            <Input
                id="threshold_amount"
                v-model.number="form.threshold_amount"
                type="number"
                step="0.01"
                min="0.01"
                required
                :class="{ 'border-red-500': form.errors.threshold_amount }"
            />
            <p class="text-xs text-muted-foreground">
                1 gift per threshold reached, e.g. threshold €50: a €120 order → 2 gifts.
            </p>
            <span v-if="form.errors.threshold_amount" class="text-sm text-red-500">{{ form.errors.threshold_amount }}</span>
        </div>

        <div class="grid gap-2">
            <Label>Reward item *</Label>
            <MenuSelector :menus="menus" v-model="form.reward_menu_id" :error="form.errors.reward_menu_id" />
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
                {{ method === 'post' ? 'Create Gift' : 'Update Gift' }}
            </Button>
        </DialogFooter>
    </form>
</template>
