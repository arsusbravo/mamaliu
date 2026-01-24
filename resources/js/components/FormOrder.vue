<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import ClientSelector from '@/components/ClientSelector.vue';
import MenuSelector from '@/components/MenuSelector.vue';

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
const menuMode = ref<'week' | 'all'>('week');
const selectedMenuId = ref<string>('');

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
    weekmenu_id: props.order?.weekmenu_id || null as number | null,
    menu_id: null as number | null,
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

// Reset selection when switching modes
watch(menuMode, () => {
    form.weekmenu_id = null;
    form.menu_id = null;
    selectedMenuId.value = '';
});

// Sync selectedMenuId with form.menu_id
watch(selectedMenuId, (newVal) => {
    form.menu_id = newVal ? Number(newVal) : null;
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

const menuError = computed(() => {
    return form.errors.weekmenu_id || form.errors.menu_id;
});
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
                <Label>Menu *</Label>
                <div class="flex gap-1 text-sm">
                    <button
                        type="button"
                        @click="menuMode = 'week'"
                        :class="[
                            'px-2 py-1 rounded transition-colors',
                            menuMode === 'week'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted hover:bg-muted/80'
                        ]"
                    >
                        Week Menus
                    </button>
                    <button
                        type="button"
                        @click="menuMode = 'all'"
                        :class="[
                            'px-2 py-1 rounded transition-colors',
                            menuMode === 'all'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted hover:bg-muted/80'
                        ]"
                    >
                        All Menus
                    </button>
                </div>
            </div>

            <!-- Week Menus Mode -->
            <template v-if="menuMode === 'week'">
                <select
                    id="weekmenu"
                    v-model="form.weekmenu_id"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    :class="{ 'border-red-500': menuError }"
                >
                    <option :value="null">Select a menu</option>
                    <option v-for="wm in weekmenus" :key="wm.id" :value="wm.id">
                        {{ wm.menu.label }} (€{{ wm.menu.price.toFixed(2) }})
                    </option>
                </select>
                <p v-if="!loading && weekmenus.length === 0" class="text-sm text-muted-foreground mt-1">
                    No menus for this week. Switch to "All Menus" to select any menu.
                </p>
            </template>

            <!-- All Menus Mode -->
            <template v-else>
                <MenuSelector
                    :menus="menus"
                    v-model="selectedMenuId"
                    :error="menuError"
                />
            </template>

            <p v-if="menuError" class="text-sm text-red-500 mt-1">
                {{ menuError }}
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
</template>
