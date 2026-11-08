<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter, DialogClose } from '@/components/ui/dialog';
import { Plus, Trash2 } from 'lucide-vue-next';

export interface ExpenseItemData {
    name: string;
    quantity: number;
    price: number;
    category: string | null;
}

export interface ExpenseData {
    id?: number;
    store: string | null;
    purchased_at: string;
    total: number | null;
    notes: string | null;
    receipt_image?: string | null;
    items: ExpenseItemData[];
}

interface Props {
    expense: ExpenseData;
    submitUrl: string;
    method: 'post' | 'put';
    categories: string[];
    // Object URL / route of the receipt photo, shown next to the form for checking
    receiptUrl?: string | null;
}

const props = defineProps<Props>();
const emit = defineEmits(['success']);

const form = useForm({
    store: props.expense.store ?? '',
    purchased_at: props.expense.purchased_at,
    // '' while empty: the server turns it into null
    total: (props.expense.total ?? '') as number | string,
    notes: props.expense.notes ?? '',
    receipt_image: props.expense.receipt_image ?? null,
    items: props.expense.items.map((i) => ({ ...i })),
});

const itemsSum = computed(() =>
    Math.round(form.items.reduce((sum, i) => sum + (Number(i.price) || 0), 0) * 100) / 100,
);
const mismatch = computed(
    () => form.total !== '' && Math.abs(itemsSum.value - Number(form.total)) > 0.01,
);

const addItem = () => form.items.push({ name: '', quantity: 1, price: 0, category: null });
const removeItem = (index: number) => form.items.splice(index, 1);

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => emit('success') };
    if (props.method === 'put') {
        form.put(props.submitUrl, options);
    } else {
        form.post(props.submitUrl, options);
    }
};

// Laravel reports item errors as "items.0.name"
const itemError = (index: number) =>
    form.errors[`items.${index}.name` as keyof typeof form.errors]
    ?? form.errors[`items.${index}.price` as keyof typeof form.errors]
    ?? form.errors[`items.${index}.quantity` as keyof typeof form.errors]
    ?? form.errors[`items.${index}.category` as keyof typeof form.errors];
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
            <div class="grid gap-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="store">Store</Label>
                        <Input id="store" v-model="form.store" type="text" />
                        <span v-if="form.errors.store" class="text-sm text-red-500">{{ form.errors.store }}</span>
                    </div>
                    <div class="grid gap-2">
                        <Label for="purchased_at">Date *</Label>
                        <Input id="purchased_at" v-model="form.purchased_at" type="date" required />
                        <span v-if="form.errors.purchased_at" class="text-sm text-red-500">{{ form.errors.purchased_at }}</span>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="total">Receipt total (€)</Label>
                    <Input id="total" v-model.number="form.total" type="number" step="0.01" />
                </div>
            </div>

            <a v-if="receiptUrl" :href="receiptUrl" target="_blank" class="hidden sm:block">
                <img :src="receiptUrl" alt="Receipt" class="h-40 w-auto rounded-md border object-contain" />
            </a>
        </div>

        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <Label>Items *</Label>
                <Button type="button" variant="outline" size="sm" @click="addItem">
                    <Plus class="h-4 w-4" />
                    Add item
                </Button>
            </div>

            <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                <div v-for="(item, index) in form.items" :key="index" class="space-y-1">
                    <div class="grid grid-cols-[1fr_4rem_5rem_auto] items-center gap-2 sm:grid-cols-[1fr_4rem_5rem_9rem_auto]">
                        <Input v-model="item.name" placeholder="Item" required />
                        <Input v-model.number="item.quantity" type="number" step="0.01" min="0.01" title="Quantity" required />
                        <Input v-model.number="item.price" type="number" step="0.01" title="Price (€)" required />
                        <select
                            v-model="item.category"
                            class="col-span-3 h-10 rounded-md border border-input bg-background px-2 text-sm sm:col-span-1"
                        >
                            <option :value="null">No category</option>
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <Button type="button" variant="ghost" size="sm" title="Remove" @click="removeItem(index)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                    <span v-if="itemError(index)" class="text-sm text-red-500">{{ itemError(index) }}</span>
                </div>
                <p v-if="form.items.length === 0" class="text-sm text-muted-foreground">No items yet.</p>
            </div>
            <span v-if="form.errors.items" class="text-sm text-red-500">{{ form.errors.items }}</span>

            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">Items sum</span>
                <span class="font-semibold">€{{ itemsSum.toFixed(2) }}</span>
            </div>
            <p v-if="mismatch" class="rounded-md bg-amber-50 p-2 text-sm text-amber-800">
                The items add up to €{{ itemsSum.toFixed(2) }} but the receipt total is €{{ Number(form.total).toFixed(2) }}.
                Check for a missed or misread line.
            </p>
        </div>

        <div class="grid gap-2">
            <Label for="notes">Notes</Label>
            <Textarea id="notes" v-model="form.notes" rows="2" />
        </div>

        <DialogFooter class="gap-2">
            <DialogClose as-child>
                <Button type="button" variant="secondary">Cancel</Button>
            </DialogClose>
            <Button type="submit" :disabled="form.processing || form.items.length === 0">
                {{ method === 'post' ? 'Save expense' : 'Update expense' }}
            </Button>
        </DialogFooter>
    </form>
</template>
