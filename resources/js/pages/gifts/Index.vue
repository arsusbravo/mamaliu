<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import FormGift from '@/components/FormGift.vue';
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

interface Gift {
    id: number;
    name: string;
    code: string;
    description: string | null;
    target_type: 'general' | 'user' | 'group' | 'items';
    user: User | null;
    group: Group | null;
    user_id: number | null;
    group_id: number | null;
    qualifying_items: Menu[];
    threshold_amount: number;
    reward_menu_id: number | null;
    reward_menu: Menu | null;
    valid_from: string | null;
    valid_until: string | null;
    active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    gifts?: {
        data: Gift[];
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
    gifts: () => ({
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
    { title: 'Gifts', href: '/admin/gifts' },
];

const page = usePage();
const showToast = ref(false);
const toastMessage = ref('');
const toastVariant = ref<'success' | 'destructive'>('success');

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const showDeleteDialog = ref(false);
const selectedGift = ref<Gift | null>(null);

const searchQuery = ref(props.filters?.search || '');
let searchTimeout: ReturnType<typeof setTimeout>;

const emptyGift: Gift = {
    id: 0,
    name: '',
    code: '',
    description: null,
    target_type: 'general',
    user: null,
    group: null,
    user_id: null,
    group_id: null,
    qualifying_items: [],
    threshold_amount: null as unknown as number,
    reward_menu_id: null,
    reward_menu: null,
    valid_from: null,
    valid_until: null,
    active: true,
};

const deleteForm = useForm({});

const openAddDialog = () => { showAddDialog.value = true; };
const openEditDialog = (gift: Gift) => { selectedGift.value = gift; showEditDialog.value = true; };
const openDeleteDialog = (gift: Gift) => { selectedGift.value = gift; showDeleteDialog.value = true; };
const handleFormSuccess = () => { showAddDialog.value = false; showEditDialog.value = false; };

const submitDelete = () => {
    if (selectedGift.value) {
        deleteForm.delete(`/admin/gifts/${selectedGift.value.id}`, {
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
        router.get('/admin/gifts', { search: searchQuery.value }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);
};

const targetLabel = (gift: Gift): string => {
    switch (gift.target_type) {
        case 'user': return gift.user ? `Client: ${gift.user.name}` : 'A specific client';
        case 'group': return gift.group ? `Group: ${gift.group.name}` : 'A specific group';
        case 'items': return gift.qualifying_items?.length
            ? `Items: ${gift.qualifying_items.map(m => m.label).join(', ')}`
            : 'Specific item(s)';
        default: return 'Anyone';
    }
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
    <Head title="Gifts" />

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
                <h2 class="text-2xl font-bold">Gifts</h2>
                <Button @click="openAddDialog">Add Gift</Button>
            </div>

            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    @input="handleSearch"
                    type="search"
                    placeholder="Search by name or description..."
                    class="pl-9"
                />
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Code</TableHead>
                            <TableHead>Applies to</TableHead>
                            <TableHead>Threshold</TableHead>
                            <TableHead>Reward item</TableHead>
                            <TableHead>Valid</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="gifts.data.length === 0">
                            <TableCell colspan="8" class="text-center text-muted-foreground">
                                No gift rules found. Add your first one to get started.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="gift in gifts.data" :key="gift.id">
                            <TableCell class="font-medium">{{ gift.name }}</TableCell>
                            <TableCell class="font-mono">{{ gift.code }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground max-w-[220px] truncate" :title="targetLabel(gift)">
                                {{ targetLabel(gift) }}
                            </TableCell>
                            <TableCell class="text-sm">€{{ Number(gift.threshold_amount).toFixed(2) }}</TableCell>
                            <TableCell class="text-sm">{{ gift.reward_menu?.label ?? '—' }}</TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                <span v-if="gift.valid_from || gift.valid_until">
                                    {{ formatDate(gift.valid_from) }} – {{ formatDate(gift.valid_until) }}
                                </span>
                                <span v-else class="italic">Always</span>
                            </TableCell>
                            <TableCell>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                        gift.active
                                            ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'
                                            : 'bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-500/10'
                                    ]"
                                >
                                    {{ gift.active ? 'Active' : 'Inactive' }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button variant="outline" size="sm" @click="openEditDialog(gift)">Edit</Button>
                                    <Button variant="destructive" size="sm" @click="openDeleteDialog(gift)">Delete</Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="gifts.last_page > 1" class="flex items-center justify-between">
                <div class="text-sm text-muted-foreground">
                    Showing {{ ((gifts.current_page - 1) * gifts.per_page) + 1 }} to
                    {{ Math.min(gifts.current_page * gifts.per_page, gifts.total) }} of
                    {{ gifts.total }} gifts
                </div>
                <div class="flex gap-2">
                    <Button
                        v-for="link in gifts.links"
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
                        <DialogTitle>Add New Gift</DialogTitle>
                        <DialogDescription>Create a new automatic gift rule.</DialogDescription>
                    </DialogHeader>

                    <FormGift
                        :gift="emptyGift"
                        submit-url="/admin/gifts"
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
                        <DialogTitle>Edit Gift</DialogTitle>
                        <DialogDescription>Update the gift rule.</DialogDescription>
                    </DialogHeader>

                    <FormGift
                        v-if="selectedGift"
                        :gift="selectedGift"
                        :submit-url="`/admin/gifts/${selectedGift.id}`"
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
                        <DialogTitle>Delete Gift</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{{ selectedGift?.name }}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="flex justify-end gap-2 mt-6">
                        <Button type="button" variant="secondary" @click="showDeleteDialog = false">Cancel</Button>
                        <Button variant="destructive" :disabled="deleteForm.processing" @click="submitDelete">Delete Gift</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
