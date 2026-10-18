<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import FormGift from '@/components/FormGift.vue';
import { Button } from '@/components/ui/button';
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

interface Menu {
    id: number;
    label: string;
    price: number;
    image_url: string | null;
    has_image: boolean;
}

interface Gift {
    id: number;
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

const emptyGift: Gift = {
    id: 0,
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

            <p class="text-sm text-muted-foreground">
                Clients with a membership ID assigned (in Clients) receive this gift automatically when they
                enter their ID at checkout and their order meets the threshold.
            </p>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Threshold</TableHead>
                            <TableHead>Reward item</TableHead>
                            <TableHead>Valid</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="gifts.data.length === 0">
                            <TableCell colspan="5" class="text-center text-muted-foreground">
                                No gifts found. Add one to get started.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="gift in gifts.data" :key="gift.id">
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
                        <DialogDescription>Create a new gift for members to redeem.</DialogDescription>
                    </DialogHeader>

                    <FormGift
                        :gift="emptyGift"
                        submit-url="/admin/gifts"
                        method="post"
                        :menus="menus"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showEditDialog">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Gift</DialogTitle>
                        <DialogDescription>Update the gift.</DialogDescription>
                    </DialogHeader>

                    <FormGift
                        v-if="selectedGift"
                        :gift="selectedGift"
                        :submit-url="`/admin/gifts/${selectedGift.id}`"
                        method="put"
                        :menus="menus"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showDeleteDialog">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Delete Gift</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete the gift rewarding
                            <strong>{{ selectedGift?.reward_menu?.label }}</strong>? This action cannot be undone.
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
