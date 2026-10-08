<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import FormDiscount from '@/components/FormDiscount.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import Toast from '@/components/ui/toast/Toast.vue';
import { usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';

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

interface User {
    id: number;
    name: string;
}

interface Discount {
    id: number;
    code: string;
    description: string | null;
    type: 'percentage' | 'fixed';
    value: number;
    target_type: 'general' | 'user' | 'group' | 'menu';
    user: User | null;
    group: Group | null;
    menu: Menu | null;
    user_id: number | null;
    group_id: number | null;
    menu_id: number | null;
    min_order_total: number | null;
    valid_from: string | null;
    valid_until: string | null;
    usage_limit: number | null;
    usage_limit_per_user: number | null;
    redemptions_count: number;
    active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    discounts?: {
        data: Discount[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
    menus?: Menu[];
    groups?: Group[];
    filters?: {
        search?: string;
    };
}

const props = withDefaults(defineProps<Props>(), {
    discounts: () => ({
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        links: [],
    }),
    menus: () => [],
    groups: () => [],
    filters: () => ({
        search: '',
    }),
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Discounts', href: '/admin/discounts' },
];

const page = usePage();
const showToast = ref(false);
const toastMessage = ref('');
const toastVariant = ref<'success' | 'destructive'>('success');

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const showDeleteDialog = ref(false);
const selectedDiscount = ref<Discount | null>(null);

const searchQuery = ref(props.filters?.search || '');
let searchTimeout: ReturnType<typeof setTimeout>;

const emptyDiscount: Discount = {
    id: 0,
    code: '',
    description: null,
    type: 'percentage',
    value: null as unknown as number,
    target_type: 'general',
    user: null,
    group: null,
    menu: null,
    user_id: null,
    group_id: null,
    menu_id: null,
    min_order_total: null,
    valid_from: null,
    valid_until: null,
    usage_limit: null,
    usage_limit_per_user: null,
    redemptions_count: 0,
    active: true,
};

const deleteForm = useForm({});

const openAddDialog = () => { showAddDialog.value = true; };
const openEditDialog = (discount: Discount) => { selectedDiscount.value = discount; showEditDialog.value = true; };
const openDeleteDialog = (discount: Discount) => { selectedDiscount.value = discount; showDeleteDialog.value = true; };
const handleFormSuccess = () => { showAddDialog.value = false; showEditDialog.value = false; };

const submitDelete = () => {
    if (selectedDiscount.value) {
        deleteForm.delete(`/admin/discounts/${selectedDiscount.value.id}`, {
            onSuccess: () => { showDeleteDialog.value = false; },
        });
    }
};

const goToPage = (url: string | null) => {
    if (url) router.visit(url);
};

const handleSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/admin/discounts', { search: searchQuery.value }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);
};

const targetLabel = (discount: Discount): string => {
    switch (discount.target_type) {
        case 'user': return discount.user ? `Client: ${discount.user.name}` : 'A specific client';
        case 'group': return discount.group ? `Group: ${discount.group.name}` : 'A specific group';
        case 'menu': return discount.menu ? `Item: ${discount.menu.label}` : 'A specific item';
        default: return 'Anyone';
    }
};

const valueLabel = (discount: Discount): string => {
    return discount.type === 'percentage' ? `${discount.value}%` : `€${Number(discount.value).toFixed(2)}`;
};

const usageLabel = (discount: Discount): string => {
    return discount.usage_limit ? `${discount.redemptions_count} / ${discount.usage_limit}` : `${discount.redemptions_count} (unlimited)`;
};

const formatDate = (dateString: string | null): string => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString();
};

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
        toastVariant.value = 'success';
        toastMessage.value = flash.success;
        showToast.value = true;
        setTimeout(() => { showToast.value = false; }, 3000);
    }
    if (flash?.error) {
        toastVariant.value = 'destructive';
        toastMessage.value = flash.error;
        showToast.value = true;
        setTimeout(() => { showToast.value = false; }, 3000);
    }
}, { deep: true });
</script>

<template>
    <Head title="Discounts" />

    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
    >
        <Toast v-if="showToast" :variant="toastVariant" position="top-right">
            {{ toastMessage }}
        </Toast>
    </Transition>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold">Discounts &amp; Gifts</h2>
                <Button @click="openAddDialog">Add Discount</Button>
            </div>

            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    @input="handleSearch"
                    type="search"
                    placeholder="Search by code or description..."
                    class="pl-9"
                />
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Code</TableHead>
                            <TableHead>Value</TableHead>
                            <TableHead>Applies to</TableHead>
                            <TableHead>Min. Order</TableHead>
                            <TableHead>Valid</TableHead>
                            <TableHead>Usage</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="discounts.data.length === 0">
                            <TableCell colspan="8" class="text-center text-muted-foreground">
                                No discounts found. Add your first discount to get started.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="discount in discounts.data" :key="discount.id">
                            <TableCell class="font-mono font-medium">{{ discount.code }}</TableCell>
                            <TableCell>{{ valueLabel(discount) }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{ targetLabel(discount) }}</TableCell>
                            <TableCell class="text-sm">
                                <span v-if="discount.min_order_total">€{{ Number(discount.min_order_total).toFixed(2) }}</span>
                                <span v-else class="italic text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                <span v-if="discount.valid_from || discount.valid_until">
                                    {{ formatDate(discount.valid_from) }} – {{ formatDate(discount.valid_until) }}
                                </span>
                                <span v-else class="italic">Always</span>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{ usageLabel(discount) }}</TableCell>
                            <TableCell>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                        discount.active
                                            ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'
                                            : 'bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-500/10'
                                    ]"
                                >
                                    {{ discount.active ? 'Active' : 'Inactive' }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button variant="outline" size="sm" @click="openEditDialog(discount)">Edit</Button>
                                    <Button variant="destructive" size="sm" @click="openDeleteDialog(discount)">Delete</Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="discounts.last_page > 1" class="flex items-center justify-between">
                <div class="text-sm text-muted-foreground">
                    Showing {{ ((discounts.current_page - 1) * discounts.per_page) + 1 }} to
                    {{ Math.min(discounts.current_page * discounts.per_page, discounts.total) }} of
                    {{ discounts.total }} discounts
                </div>
                <div class="flex gap-2">
                    <Button
                        v-for="link in discounts.links"
                        :key="link.label"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>

            <Dialog v-model:open="showAddDialog">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Add New Discount</DialogTitle>
                        <DialogDescription>Create a new discount or gift code.</DialogDescription>
                    </DialogHeader>

                    <FormDiscount
                        :discount="emptyDiscount"
                        submit-url="/admin/discounts"
                        method="post"
                        :menus="menus"
                        :groups="groups"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showEditDialog">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Discount</DialogTitle>
                        <DialogDescription>Update the discount information.</DialogDescription>
                    </DialogHeader>

                    <FormDiscount
                        v-if="selectedDiscount"
                        :discount="selectedDiscount"
                        :submit-url="`/admin/discounts/${selectedDiscount.id}`"
                        method="put"
                        :menus="menus"
                        :groups="groups"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showDeleteDialog">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Delete Discount</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{{ selectedDiscount?.code }}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="flex justify-end gap-2 mt-6">
                        <Button type="button" variant="secondary" @click="showDeleteDialog = false">Cancel</Button>
                        <Button variant="destructive" :disabled="deleteForm.processing" @click="submitDelete">Delete Discount</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
