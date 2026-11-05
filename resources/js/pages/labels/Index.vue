<script setup lang="ts">
import { ref, watch } from 'vue';
import { router, useForm, usePage, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import FormLabel, { type LabelItem } from '@/components/FormLabel.vue';
import LabelPrintDialog from '@/components/LabelPrintDialog.vue';
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
import { ImageOff, Printer } from 'lucide-vue-next';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    labels: {
        data: LabelItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
    labelFormats: Array<{ id: string; name: string; size: string; count: number }>;
    defaultLabelFormat: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Label maker', href: '/admin/labels' },
];

const emptyLabel: LabelItem = {
    id: 0,
    name: '',
    text: '',
    show_logo: true,
    text_color: '#000000',
    background_url: null,
    has_background: false,
};

// Toast state
const page = usePage();
const showToast = ref(false);
const toastMessage = ref('');
const toastVariant = ref<'success' | 'destructive'>('success');

// Dialog states
const showAddDialog = ref(false);
const showEditDialog = ref(false);
const showDeleteDialog = ref(false);
const showPrintDialog = ref(false);
const selectedLabel = ref<LabelItem | null>(null);
const deleteForm = useForm({});

const openEditDialog = (label: LabelItem) => {
    selectedLabel.value = label;
    showEditDialog.value = true;
};

const openDeleteDialog = (label: LabelItem) => {
    selectedLabel.value = label;
    showDeleteDialog.value = true;
};

const openPrintDialog = (label: LabelItem) => {
    selectedLabel.value = label;
    showPrintDialog.value = true;
};

const handleFormSuccess = () => {
    showAddDialog.value = false;
    showEditDialog.value = false;
};

const submitDelete = () => {
    if (!selectedLabel.value) return;
    deleteForm.delete(`/admin/labels/${selectedLabel.value.id}`, {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
    });
};

const goToPage = (url: string | null | undefined) => {
    if (url) router.get(url, {}, { preserveScroll: true });
};

watch(
    () => page.props.flash,
    (flash: any) => {
        const message = flash?.success ?? flash?.error;
        if (!message) return;
        toastVariant.value = flash.success ? 'success' : 'destructive';
        toastMessage.value = message;
        showToast.value = true;
        setTimeout(() => {
            showToast.value = false;
        }, 3000);
    },
    { deep: true },
);
</script>

<template>
    <Head title="Label maker" />

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
                <h2 class="text-2xl font-bold">Label maker</h2>
                <Button @click="showAddDialog = true">Add Label</Button>
            </div>

            <p class="text-sm text-muted-foreground">
                Design labels with a name, text and background image, then print a full sheet on your label paper.
            </p>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-20">Background</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Text</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="labels.data.length === 0">
                            <TableCell colspan="4" class="text-center text-muted-foreground">
                                No labels yet. Add one to get started.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="label in labels.data" :key="label.id">
                            <TableCell>
                                <img
                                    v-if="label.background_url"
                                    :src="label.background_url"
                                    :alt="label.name"
                                    class="h-12 w-12 rounded object-cover"
                                />
                                <div v-else class="flex h-12 w-12 items-center justify-center rounded bg-gray-100 dark:bg-gray-800">
                                    <ImageOff class="h-5 w-5 text-gray-400" />
                                </div>
                            </TableCell>
                            <TableCell class="font-medium">{{ label.name }}</TableCell>
                            <TableCell class="max-w-xs truncate">{{ label.text || '-' }}</TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button variant="outline" size="sm" @click="openPrintDialog(label)">
                                        <Printer class="h-4 w-4" />
                                        Print
                                    </Button>
                                    <Button variant="outline" size="sm" @click="openEditDialog(label)">Edit</Button>
                                    <Button variant="destructive" size="sm" @click="openDeleteDialog(label)">Delete</Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="labels.last_page > 1" class="flex items-center justify-between gap-2">
                <div class="hidden text-sm text-muted-foreground sm:block">
                    Page {{ labels.current_page }} of {{ labels.last_page }} ({{ labels.total }} labels)
                </div>
                <div class="ml-auto flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="labels.current_page === 1"
                        @click="goToPage(labels.links[0]?.url)"
                    >
                        ←
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="labels.current_page === labels.last_page"
                        @click="goToPage(labels.links[labels.links.length - 1]?.url)"
                    >
                        →
                    </Button>
                </div>
            </div>

            <!-- Add -->
            <Dialog v-model:open="showAddDialog">
                <DialogContent class="max-h-[90vh] max-w-xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Add Label</DialogTitle>
                        <DialogDescription>Create a new printable label.</DialogDescription>
                    </DialogHeader>
                    <FormLabel
                        v-if="showAddDialog"
                        :label="emptyLabel"
                        submit-url="/admin/labels"
                        method="post"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Edit -->
            <Dialog v-model:open="showEditDialog">
                <DialogContent class="max-h-[90vh] max-w-xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Label</DialogTitle>
                        <DialogDescription>Update this label.</DialogDescription>
                    </DialogHeader>
                    <FormLabel
                        v-if="showEditDialog && selectedLabel"
                        :key="selectedLabel.id"
                        :label="selectedLabel"
                        :submit-url="`/admin/labels/${selectedLabel.id}`"
                        method="put"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Delete -->
            <Dialog v-model:open="showDeleteDialog">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Delete Label</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{{ selectedLabel?.name }}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="mt-6 flex justify-end gap-2">
                        <Button type="button" variant="secondary" @click="showDeleteDialog = false">Cancel</Button>
                        <Button variant="destructive" :disabled="deleteForm.processing" @click="submitDelete">
                            Delete Label
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Print -->
            <LabelPrintDialog
                v-model:open="showPrintDialog"
                :formats="props.labelFormats"
                :default-format="props.defaultLabelFormat"
                :description="`A sheet filled with &quot;${selectedLabel?.name ?? ''}&quot; labels.`"
                :build-url="(format) => `/admin/labels/${selectedLabel?.id}/pdf?format=${format}`"
            />
        </div>
    </AppLayout>
</template>
