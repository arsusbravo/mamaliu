<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DialogFooter } from '@/components/ui/dialog';
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

interface Weekmenu {
    id: number;
    week: number;
    year: number;
    group_id: number | null;
    menu_id: number | null;
    quantity: number;
}

interface Props {
    weekmenu?: Weekmenu;
    menus: Menu[];
    groups: Group[];
    currentWeek?: number;
    currentYear?: number;
    defaultWeek?: number;
    defaultYear?: number;
    submitUrl: string;
    method: 'post' | 'put';
}

const props = withDefaults(defineProps<Props>(), {
    weekmenu: undefined,
    currentWeek: undefined,
    currentYear: undefined,
    defaultWeek: undefined,
    defaultYear: undefined,
});

const emit = defineEmits(['success', 'cancel']);

const getWeekNumber = () => {
    const now = new Date();
    const start = new Date(now.getFullYear(), 0, 1);
    const diff = now.getTime() - start.getTime();
    const oneWeek = 1000 * 60 * 60 * 24 * 7;
    return Math.ceil(diff / oneWeek);
};

const form = useForm({
    week: props.weekmenu?.week || props.defaultWeek || props.currentWeek || getWeekNumber(),
    year: props.weekmenu?.year || props.defaultYear || props.currentYear || new Date().getFullYear(),
    group_id: props.weekmenu?.group_id ? String(props.weekmenu.group_id) : 'none',
    menu_id: props.weekmenu?.menu_id ? String(props.weekmenu.menu_id) : '',
    quantity: props.weekmenu?.quantity || 1,
});

const submit = () => {
    const submitMethod = props.method === 'post' ? 'post' : 'put';
    
    const submitData = {
        ...form.data(),
        group_id: form.group_id && form.group_id !== 'none' ? Number(form.group_id) : null,
        menu_id: form.menu_id ? Number(form.menu_id) : null,
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
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="week">Week *</Label>
                    <Input
                        id="week"
                        v-model.number="form.week"
                        type="number"
                        min="1"
                        max="53"
                        required
                        :class="{ 'border-red-500': form.errors.week }"
                    />
                    <span v-if="form.errors.week" class="text-sm text-red-500">
                        {{ form.errors.week }}
                    </span>
                </div>

                <div class="grid gap-2">
                    <Label for="year">Year *</Label>
                    <Input
                        id="year"
                        v-model.number="form.year"
                        type="number"
                        min="2020"
                        max="2100"
                        required
                        :class="{ 'border-red-500': form.errors.year }"
                    />
                    <span v-if="form.errors.year" class="text-sm text-red-500">
                        {{ form.errors.year }}
                    </span>
                </div>
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
                        <SelectItem value="none">
                            No location
                        </SelectItem>
                    </SelectContent>
                </Select>
                <span v-if="form.errors.group_id" class="text-sm text-red-500">
                    {{ form.errors.group_id }}
                </span>
            </div>

            <MenuSelector
                :menus="menus"
                v-model="form.menu_id"
                :error="form.errors.menu_id"
            />

            <div class="grid gap-2">
                <Label for="quantity">Quantity *</Label>
                <Input
                    id="quantity"
                    v-model.number="form.quantity"
                    type="number"
                    min="1"
                    required
                    :class="{ 'border-red-500': form.errors.quantity }"
                />
                <span v-if="form.errors.quantity" class="text-sm text-red-500">
                    {{ form.errors.quantity }}
                </span>
            </div>
        </div>

        <DialogFooter class="gap-2">
            <Button type="button" variant="secondary" @click="emit('cancel')">
                Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ method === 'post' ? 'Create Week Menu' : 'Update Week Menu' }}
            </Button>
        </DialogFooter>
    </form>
</template>