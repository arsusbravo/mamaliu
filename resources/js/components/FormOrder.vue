<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import ClientSelector from '@/components/ClientSelector.vue';
import FormWeekmenu from '@/components/FormWeekmenu.vue';

interface Menu {
    id: number;
    label: string;
    price: number;
    image_url: string | null; 
    has_image: boolean;
}

interface Weekmenu {
    id: number;
    menu: Menu;
}

interface Order {
    id?: number;
    user_id: number;
    weekmenu_id: number;
    quantity: number;
    notes: string | null;
    week: number;
    year: number;
}

interface Group {
    id: number;
    name: string;
}

interface Props {
    order?: Order | null;
    user_id?: number | null;
    week?: number;
    year?: number;
    menus: Menu[];
    groups: Group[];
}

const props = withDefaults(defineProps<Props>(), {
    order: null,
    user_id: null,
});

const emit = defineEmits(['success', 'cancel']);

const weekmenus = ref<Weekmenu[]>([]);
const loading = ref(false);
const showAddWeekmenu = ref(false);

const currentDate = new Date();
const getWeekNumber = (d: Date) => {
    const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    const dayNum = date.getUTCDay() || 7;
    date.setUTCDate(date.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    return Math.ceil((((date.getTime() - yearStart.getTime()) / 86400000) + 1) / 7);
};

const form = useForm({
    user_id: props.order?.user_id || props.user_id || null,
    weekmenu_id: props.order?.weekmenu_id || null,
    quantity: props.order?.quantity || 1,
    notes: props.order?.notes || '',
    week: props.order?.week || props.week || getWeekNumber(currentDate),
    year: props.order?.year || props.year || currentDate.getFullYear(),
});

// Load weekmenus for the selected week/year
const loadWeekmenus = async () => {
    try {
        loading.value = true;
        const response = await fetch(`/admin/weekmenus/list?week=${form.week}&year=${form.year}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const data = await response.json();
        weekmenus.value = data.weekmenus || [];
    } catch (error) {
        console.error('Failed to load weekmenus:', error);
    } finally {
        loading.value = false;
    }
};

watch([() => form.week, () => form.year], () => {
    loadWeekmenus();
});

loadWeekmenus();

const handleSubmit = () => {
    if (props.order?.id) {
        form.put(`/admin/orders/${props.order.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
            },
        });
    } else {
        form.post('/admin/orders', {
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
            },
        });
    }
};

const handleWeekmenuAdded = () => {
    showAddWeekmenu.value = false;
    loadWeekmenus();
};
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <Label for="week">Week *</Label>
                <Input
                    id="week"
                    v-model.number="form.week"
                    type="number"
                    min="1"
                    max="53"
                    :disabled="!!order"
                    :class="{ 'border-red-500': form.errors.week }"
                />
                <p v-if="form.errors.week" class="text-sm text-red-500 mt-1">
                    {{ form.errors.week }}
                </p>
            </div>

            <div>
                <Label for="year">Year *</Label>
                <Input
                    id="year"
                    v-model.number="form.year"
                    type="number"
                    min="2020"
                    max="2100"
                    :disabled="!!order"
                    :class="{ 'border-red-500': form.errors.year }"
                />
                <p v-if="form.errors.year" class="text-sm text-red-500 mt-1">
                    {{ form.errors.year }}
                </p>
            </div>
        </div>

        <div>
            <Label>Client *</Label>
            <ClientSelector 
                v-model="form.user_id" 
                :error="form.errors.user_id"
                :disabled="!!order"
            />
        </div>

        <div>
            <div class="flex items-center justify-between mb-2">
                <Label for="weekmenu">Menu *</Label>
                <Button 
                    type="button" 
                    variant="link" 
                    size="sm" 
                    class="h-auto p-0"
                    @click="showAddWeekmenu = true"
                >
                    + Add Menu to Week
                </Button>
            </div>
            
            <select
                id="weekmenu"
                v-model="form.weekmenu_id"
                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                :class="{ 'border-red-500': form.errors.weekmenu_id }"
            >
                <option :value="null">Select a menu</option>
                <option v-for="wm in weekmenus" :key="wm.id" :value="wm.id">
                    {{ wm.menu.label }} (€{{ wm.menu.price.toFixed(2) }})
                </option>
            </select>
            <p v-if="form.errors.weekmenu_id" class="text-sm text-red-500 mt-1">
                {{ form.errors.weekmenu_id }}
            </p>
            <p v-if="!loading && weekmenus.length === 0" class="text-sm text-muted-foreground mt-1">
                No menus available for this week. Add one using the button above.
            </p>
        </div>

        <div>
            <Label for="quantity">Quantity *</Label>
            <Input
                id="quantity"
                v-model.number="form.quantity"
                type="number"
                min="1"
                :class="{ 'border-red-500': form.errors.quantity }"
            />
            <p v-if="form.errors.quantity" class="text-sm text-red-500 mt-1">
                {{ form.errors.quantity }}
            </p>
        </div>

        <div>
            <Label for="notes">Notes</Label>
            <Textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                placeholder="Optional notes..."
            />
        </div>

        <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" @click="emit('cancel')">
                Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ order ? 'Update' : 'Add' }} Order
            </Button>
        </div>
    </form>

    <!-- Add Weekmenu Dialog -->
    <Dialog v-model:open="showAddWeekmenu">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Add Menu to Week {{ form.week }}, {{ form.year }}</DialogTitle>
                <DialogDescription>Add a menu to this week</DialogDescription>
            </DialogHeader>
            <FormWeekmenu 
                :menus="menus"
                :groups="groups"
                :defaultWeek="form.week"
                :defaultYear="form.year"
                submitUrl="/admin/weekmenus"
                method="post"
                @success="handleWeekmenuAdded"
                @cancel="showAddWeekmenu = false"
            />
        </DialogContent>
    </Dialog>
</template>