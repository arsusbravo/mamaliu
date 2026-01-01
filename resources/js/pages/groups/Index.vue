<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import FormGroup from '@/components/FormGroup.vue';
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
    TableHeadSortable,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import Toast from '@/components/ui/toast/Toast.vue';
import { usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';

interface Group {
    id: number;
    name: string | null;
    slug: string | null;
    active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    groups?: {
        data: Group[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
    filters?: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
}

const props = withDefaults(defineProps<Props>(), {
    groups: () => ({
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        links: [],
    }),
    filters: () => ({
        search: '',
        sort: 'created_at',
        direction: 'desc',
    }),
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
    {
        title: 'Groups',
        href: '/admin/groups',
    },
];

// Toast state
const page = usePage();
const showToast = ref(false);
const toastMessage = ref('');
const toastVariant = ref<'success' | 'destructive'>('success');

// Dialog states
const showAddDialog = ref(false);
const showEditDialog = ref(false);
const showDeleteDialog = ref(false);
const selectedGroup = ref<Group | null>(null);

// Search
const searchQuery = ref(props.filters?.search || '');
let searchTimeout: ReturnType<typeof setTimeout>;

// Empty group for add form
const emptyGroup: Group = {
    id: 0,
    name: null,
    slug: null,
    active: true,
};

const deleteForm = useForm({});

// Actions
const openAddDialog = () => {
    showAddDialog.value = true;
};

const openEditDialog = (group: Group) => {
    selectedGroup.value = group;
    showEditDialog.value = true;
};

const openDeleteDialog = (group: Group) => {
    selectedGroup.value = group;
    showDeleteDialog.value = true;
};

const handleFormSuccess = () => {
    showAddDialog.value = false;
    showEditDialog.value = false;
};

const submitDelete = () => {
    if (selectedGroup.value) {
        deleteForm.delete(`/admin/groups/${selectedGroup.value.id}`, {
            onSuccess: () => {
                showDeleteDialog.value = false;
            },
        });
    }
};

const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url);
    }
};

// Search handler with debounce
const handleSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/admin/groups', {
            search: searchQuery.value,
            sort: props.filters?.sort,
            direction: props.filters?.direction,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);
};

// Sort handler
const handleSort = (field: string) => {
    let direction: 'asc' | 'desc' = 'asc';
    
    if (props.filters?.sort === field && props.filters?.direction === 'asc') {
        direction = 'desc';
    }
    
    router.get('/admin/groups', {
        search: searchQuery.value,
        sort: field,
        direction: direction,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Watch for flash messages
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
        toastVariant.value = 'success';
        toastMessage.value = flash.success;
        showToast.value = true;
        setTimeout(() => {
            showToast.value = false;
        }, 3000);
    }
    if (flash?.error) {
        toastVariant.value = 'destructive';
        toastMessage.value = flash.error;
        showToast.value = true;
        setTimeout(() => {
            showToast.value = false;
        }, 3000);
    }
}, { deep: true });
</script>

<template>
    <Head title="Groups" />

    <!-- Toast Notification -->
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
    >
        <Toast
            v-if="showToast"
            :variant="toastVariant"
            position="top-right"
        >
            {{ toastMessage }}
        </Toast>
    </Transition>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold">Client Groups</h2>
                <Button @click="openAddDialog">Add Group</Button>
            </div>

            <!-- Search Bar -->
            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    @input="handleSearch"
                    type="search"
                    placeholder="Search groups..."
                    class="pl-9"
                />
            </div>

            <!-- Groups Table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHeadSortable
                                sort-key="name"
                                :current-sort="filters?.sort"
                                :current-direction="filters?.direction"
                                @sort="handleSort"
                            >
                                Name
                            </TableHeadSortable>
                            <TableHead>Slug</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="groups.data.length === 0">
                            <TableCell colspan="4" class="text-center text-muted-foreground">
                                No groups found. Add your first group to get started.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="group in groups.data" :key="group.id">
                            <TableCell class="font-medium">{{ group.name ?? '-' }}</TableCell>
                            <TableCell class="font-mono text-sm">{{ group.slug ?? '-' }}</TableCell>
                            <TableCell>
                                <span 
                                    :class="[
                                        'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                        group.active 
                                            ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' 
                                            : 'bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-500/10'
                                    ]"
                                >
                                    {{ group.active ? 'Active' : 'Inactive' }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditDialog(group)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        @click="openDeleteDialog(group)"
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <div v-if="groups.last_page > 1" class="flex items-center justify-between">
                <div class="text-sm text-muted-foreground">
                    Showing {{ ((groups.current_page - 1) * groups.per_page) + 1 }} to 
                    {{ Math.min(groups.current_page * groups.per_page, groups.total) }} of 
                    {{ groups.total }} groups
                </div>
                <div class="flex gap-2">
                    <Button
                        v-for="link in groups.links"
                        :key="link.label"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>

            <!-- Add Group Dialog -->
            <Dialog v-model:open="showAddDialog">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Add New Group</DialogTitle>
                        <DialogDescription>
                            Create a new client group.
                        </DialogDescription>
                    </DialogHeader>

                    <FormGroup
                        :group="emptyGroup"
                        submit-url="/admin/groups"
                        method="post"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Edit Group Dialog -->
            <Dialog v-model:open="showEditDialog">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Edit Group</DialogTitle>
                        <DialogDescription>
                            Update the group information.
                        </DialogDescription>
                    </DialogHeader>

                    <FormGroup
                        v-if="selectedGroup"
                        :group="selectedGroup"
                        :submit-url="`/admin/groups/${selectedGroup.id}`"
                        method="put"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Delete Group Dialog -->
            <Dialog v-model:open="showDeleteDialog">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Delete Group</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{{ selectedGroup?.name }}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="flex justify-end gap-2 mt-6">
                        <Button
                            type="button"
                            variant="secondary"
                            @click="showDeleteDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            :disabled="deleteForm.processing"
                            @click="submitDelete"
                        >
                            Delete Group
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>