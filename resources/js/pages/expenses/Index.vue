<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm, usePage, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import FormExpense, { type ExpenseData } from '@/components/FormExpense.vue';
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
import { Camera, ChevronDown, ChevronRight, Download, Loader2 } from 'lucide-vue-next';

interface ExpenseRow extends ExpenseData {
    id: number;
    has_receipt: boolean;
    items_total: number;
}

interface Props {
    expenses: {
        data: ExpenseRow[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { from: string | null; to: string | null; category: string | null };
    categories: string[];
    totals: { overall: number; by_category: Array<{ category: string; total: number }> };
    scannerConfigured: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Expenses', href: '/admin/expenses' },
];

const money = (value: number | null | undefined) => `€${Number(value ?? 0).toFixed(2)}`;

// Toast
const page = usePage();
const showToast = ref(false);
const toastMessage = ref('');
const toastVariant = ref<'success' | 'destructive'>('success');
let toastTimer: ReturnType<typeof setTimeout> | undefined;

const toast = (message: string, variant: 'success' | 'destructive' = 'success') => {
    toastVariant.value = variant;
    toastMessage.value = message;
    showToast.value = true;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (showToast.value = false), variant === 'success' ? 3000 : 6000);
};

watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) toast(flash.success);
        else if (flash?.error) toast(flash.error, 'destructive');
    },
    { deep: true },
);

// Filters
const filterFrom = ref(props.filters.from ?? '');
const filterTo = ref(props.filters.to ?? '');
const filterCategory = ref(props.filters.category ?? '');

const filterParams = computed(() => {
    const params: Record<string, string> = {};
    if (filterFrom.value) params.from = filterFrom.value;
    if (filterTo.value) params.to = filterTo.value;
    if (filterCategory.value) params.category = filterCategory.value;
    return params;
});

const applyFilters = () => router.get('/admin/expenses', filterParams.value, { preserveState: true, preserveScroll: true });
const clearFilters = () => {
    filterFrom.value = '';
    filterTo.value = '';
    filterCategory.value = '';
    applyFilters();
};
const exportUrl = computed(() => `/admin/expenses/export?${new URLSearchParams(filterParams.value)}`);

const goToPage = (url: string | null | undefined) => {
    if (url) router.get(url, {}, { preserveScroll: true });
};

// Expand rows
const expanded = ref<Set<number>>(new Set());
const toggle = (id: number) => {
    const next = new Set(expanded.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expanded.value = next;
};

// Dialog states
const showAddDialog = ref(false);
const showEditDialog = ref(false);
const showDeleteDialog = ref(false);
const selected = ref<ExpenseRow | null>(null);
const deleteForm = useForm({});

const today = () => new Date().toISOString().slice(0, 10);
const blankExpense = (): ExpenseData => ({
    store: '',
    purchased_at: today(),
    total: null,
    notes: '',
    receipt_image: null,
    items: [{ name: '', quantity: 1, price: 0, category: null }],
});

// Receipts waiting for review. A scan can produce several (many photos, or one
// photo with several small receipts); they are reviewed and saved one by one.
interface Draft {
    expense: ExpenseData;
    receiptUrl: string | null;
}
const queue = ref<Draft[]>([]);
const queueIndex = ref(0);
const draft = computed(() => queue.value[queueIndex.value] ?? { expense: blankExpense(), receiptUrl: null });
const objectUrls: string[] = [];

const clearQueue = () => {
    objectUrls.splice(0).forEach((url) => URL.revokeObjectURL(url));
    queue.value = [];
    queueIndex.value = 0;
};

const openManual = () => {
    clearQueue();
    queue.value = [{ expense: blankExpense(), receiptUrl: null }];
    showAddDialog.value = true;
};

const openEdit = (expense: ExpenseRow) => {
    selected.value = expense;
    showEditDialog.value = true;
};

const openDelete = (expense: ExpenseRow) => {
    selected.value = expense;
    showDeleteDialog.value = true;
};

const submitDelete = () => {
    if (!selected.value) return;
    deleteForm.delete(`/admin/expenses/${selected.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (showDeleteDialog.value = false),
    });
};

// Move on to the next scanned receipt, or close when the queue is done
const nextDraft = () => {
    if (queueIndex.value + 1 < queue.value.length) {
        queueIndex.value++;
        return;
    }
    showAddDialog.value = false;
};

const handleFormSuccess = () => {
    showEditDialog.value = false;
    if (showAddDialog.value) nextDraft();
};

watch(showAddDialog, (open) => {
    if (!open) clearQueue();
});

// Scanning
const fileInput = ref<HTMLInputElement | null>(null);
const scanning = ref(false);

onBeforeUnmount(clearQueue);

const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const scanProgress = ref('');

const scanReceipts = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';
    if (files.length === 0) return;

    clearQueue();
    scanning.value = true;
    const failures: string[] = [];

    // One request per photo keeps each request short; a photo can hold several receipts
    for (const [index, file] of files.entries()) {
        scanProgress.value = files.length > 1 ? ` ${index + 1}/${files.length}` : '';
        try {
            const body = new FormData();
            body.append('receipt', file);

            const response = await fetch('/admin/expenses/scan', {
                method: 'POST',
                body,
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                failures.push(data.message ?? data.errors?.receipt?.[0] ?? `${file.name}: scanning failed.`);
                continue;
            }

            // Browsers can't render HEIC/HEIF, so those get no preview
            const isHeic = /\.(heic|heif)$/i.test(file.name) || /hei[cf]/i.test(file.type);
            const receiptUrl = isHeic ? null : URL.createObjectURL(file);
            if (receiptUrl) objectUrls.push(receiptUrl);

            for (const receipt of data.receipts as Array<any>) {
                queue.value.push({
                    receiptUrl,
                    expense: {
                        store: receipt.store ?? '',
                        purchased_at: receipt.date ?? today(),
                        total: receipt.total ?? null,
                        notes: '',
                        receipt_image: receipt.receipt_image,
                        items: receipt.items,
                    },
                });
            }
        } catch {
            failures.push('Could not reach the server. Please try again.');
        }
    }

    scanning.value = false;
    scanProgress.value = '';

    if (failures.length) toast(failures.join(' '), 'destructive');
    if (queue.value.length) {
        queueIndex.value = 0;
        showAddDialog.value = true;
    }
};
</script>

<template>
    <Head title="Expenses" />

    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
    >
        <Toast v-if="showToast" :variant="toastVariant" position="top-right" class="z-[100]">
            {{ toastMessage }}
        </Toast>
    </Transition>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-2xl font-bold">Expenses</h2>
                <div class="flex flex-wrap gap-2">
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        accept="image/*,.heic,.heif"
                        multiple
                        @change="scanReceipts"
                    />
                    <Button :disabled="scanning || !scannerConfigured" @click="fileInput?.click()">
                        <Loader2 v-if="scanning" class="h-4 w-4 animate-spin" />
                        <Camera v-else class="h-4 w-4" />
                        {{ scanning ? `Reading receipt${scanProgress}…` : 'Scan receipts' }}
                    </Button>
                    <Button variant="outline" @click="openManual">Add manually</Button>
                    <Button variant="outline" as-child>
                        <a :href="exportUrl">
                            <Download class="h-4 w-4" />
                            Export
                        </a>
                    </Button>
                </div>
            </div>

            <p v-if="!scannerConfigured" class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                Receipt scanning is not set up yet: add <code>OPENROUTER_API_KEY</code> to the server's <code>.env</code>.
                You can still add expenses manually.
            </p>

            <!-- Filters -->
            <div class="flex flex-wrap items-end gap-3">
                <label class="grid gap-1 text-sm">
                    From
                    <Input v-model="filterFrom" type="date" class="w-40" @change="applyFilters" />
                </label>
                <label class="grid gap-1 text-sm">
                    To
                    <Input v-model="filterTo" type="date" class="w-40" @change="applyFilters" />
                </label>
                <label class="grid gap-1 text-sm">
                    Category
                    <select
                        v-model="filterCategory"
                        class="h-10 w-44 rounded-md border border-input bg-background px-3 text-sm"
                        @change="applyFilters"
                    >
                        <option value="">All categories</option>
                        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                    </select>
                </label>
                <Button v-if="filterFrom || filterTo || filterCategory" variant="ghost" size="sm" @click="clearFilters">
                    Clear
                </Button>
            </div>

            <!-- Totals -->
            <div class="grid gap-3 sm:grid-cols-[14rem_1fr]">
                <div class="rounded-md border p-4">
                    <div class="text-sm text-muted-foreground">Total spent</div>
                    <div class="text-3xl font-bold">{{ money(totals.overall) }}</div>
                </div>
                <div class="rounded-md border p-4">
                    <div class="mb-2 text-sm text-muted-foreground">By category</div>
                    <div v-if="totals.by_category.length === 0" class="text-sm text-muted-foreground">No expenses in this range.</div>
                    <div v-else class="flex flex-wrap gap-x-6 gap-y-1">
                        <div v-for="row in totals.by_category" :key="row.category" class="flex gap-2 text-sm">
                            <span>{{ row.category }}</span>
                            <span class="font-semibold">{{ money(row.total) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-8"></TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Store</TableHead>
                            <TableHead class="text-right">Items</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="expenses.data.length === 0">
                            <TableCell colspan="6" class="text-center text-muted-foreground">
                                No expenses yet. Scan a receipt to get started.
                            </TableCell>
                        </TableRow>
                        <template v-for="expense in expenses.data" :key="expense.id">
                            <TableRow class="cursor-pointer" @click="toggle(expense.id)">
                                <TableCell>
                                    <component :is="expanded.has(expense.id) ? ChevronDown : ChevronRight" class="h-4 w-4" />
                                </TableCell>
                                <TableCell>{{ expense.purchased_at }}</TableCell>
                                <TableCell class="font-medium">{{ expense.store || '-' }}</TableCell>
                                <TableCell class="text-right">{{ expense.items.length }}</TableCell>
                                <TableCell class="text-right">{{ money(expense.total ?? expense.items_total) }}</TableCell>
                                <TableCell class="text-right" @click.stop>
                                    <div class="flex justify-end gap-2">
                                        <Button v-if="expense.has_receipt" variant="outline" size="sm" as-child>
                                            <a :href="`/admin/expenses/${expense.id}/receipt`" target="_blank">Receipt</a>
                                        </Button>
                                        <Button variant="outline" size="sm" @click="openEdit(expense)">Edit</Button>
                                        <Button variant="destructive" size="sm" @click="openDelete(expense)">Delete</Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="expanded.has(expense.id)">
                                <TableCell></TableCell>
                                <TableCell colspan="5" class="bg-muted/30">
                                    <div v-for="(item, i) in expense.items" :key="i" class="flex justify-between gap-4 py-0.5 text-sm">
                                        <span>
                                            {{ item.name }}
                                            <span v-if="item.quantity !== 1" class="text-muted-foreground">× {{ item.quantity }}</span>
                                            <span v-if="item.category" class="ml-2 rounded bg-muted px-1.5 py-0.5 text-xs">{{ item.category }}</span>
                                        </span>
                                        <span>{{ money(item.price) }}</span>
                                    </div>
                                    <p v-if="expense.notes" class="mt-1 text-xs text-muted-foreground">{{ expense.notes }}</p>
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
            </div>

            <div v-if="expenses.last_page > 1" class="flex items-center justify-between gap-2">
                <div class="text-sm text-muted-foreground">
                    Page {{ expenses.current_page }} of {{ expenses.last_page }} ({{ expenses.total }} receipts)
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" :disabled="expenses.current_page === 1" @click="goToPage(expenses.links[0]?.url)">←</Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="expenses.current_page === expenses.last_page"
                        @click="goToPage(expenses.links[expenses.links.length - 1]?.url)"
                    >
                        →
                    </Button>
                </div>
            </div>

            <!-- Add / review scan -->
            <Dialog v-model:open="showAddDialog">
                <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {{ draft.expense.receipt_image ? 'Review scanned receipt' : 'Add expense' }}
                            <span v-if="queue.length > 1" class="text-muted-foreground">
                                ({{ queueIndex + 1 }} of {{ queue.length }})
                            </span>
                        </DialogTitle>
                        <DialogDescription>
                            {{ draft.expense.receipt_image ? 'Check the details below and fix anything that was misread before saving.' : 'Enter the purchase details.' }}
                        </DialogDescription>
                    </DialogHeader>
                    <div v-if="queue.length > 1" class="flex items-center justify-between rounded-md bg-muted/50 p-2 text-sm">
                        <span>Save this receipt to continue with the next one.</span>
                        <Button type="button" variant="ghost" size="sm" @click="nextDraft">
                            {{ queueIndex + 1 < queue.length ? 'Skip this receipt' : 'Skip and finish' }}
                        </Button>
                    </div>
                    <FormExpense
                        v-if="showAddDialog"
                        :key="queueIndex"
                        :expense="draft.expense"
                        :categories="categories"
                        :receipt-url="draft.receiptUrl"
                        submit-url="/admin/expenses"
                        method="post"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Edit -->
            <Dialog v-model:open="showEditDialog">
                <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit expense</DialogTitle>
                        <DialogDescription>Update this purchase.</DialogDescription>
                    </DialogHeader>
                    <FormExpense
                        v-if="showEditDialog && selected"
                        :key="selected.id"
                        :expense="selected"
                        :categories="categories"
                        :receipt-url="selected.has_receipt ? `/admin/expenses/${selected.id}/receipt` : null"
                        :submit-url="`/admin/expenses/${selected.id}`"
                        method="put"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Delete -->
            <Dialog v-model:open="showDeleteDialog">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Delete expense</DialogTitle>
                        <DialogDescription>
                            Delete the {{ selected?.store || '' }} receipt of {{ selected?.purchased_at }}? This also removes its receipt photo and cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="mt-6 flex justify-end gap-2">
                        <Button type="button" variant="secondary" @click="showDeleteDialog = false">Cancel</Button>
                        <Button variant="destructive" :disabled="deleteForm.processing" @click="submitDelete">Delete</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
