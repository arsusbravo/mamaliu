<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import FormClient from '@/components/FormClient.vue';
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
    name: string;
}

interface Client {
    id: number;
    name: string | null;
    phone: string | null;
    username: string | null;
    group_id: number | null;
    active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    clients?: {
        data: Client[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
    groups: Group[];
    filters?: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
}

const props = withDefaults(defineProps<Props>(), {
    clients: () => ({
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        links: [],
    }),
    groups: () => [],
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
        title: 'Clients',
        href: '/admin/clients',
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
const selectedClient = ref<Client | null>(null);

// Search
const searchQuery = ref(props.filters?.search || '');
let searchTimeout: ReturnType<typeof setTimeout>;

// Empty client for add form
const emptyClient: Client = {
    id: 0,
    name: null,
    phone: null,
    username: null,
    group_id: null,
    active: true,
};

const deleteForm = useForm({});

// Actions
const openAddDialog = () => {
    showAddDialog.value = true;
};

const openEditDialog = (client: Client) => {
    selectedClient.value = client;
    showEditDialog.value = true;
};

const openDeleteDialog = (client: Client) => {
    selectedClient.value = client;
    showDeleteDialog.value = true;
};

const handleFormSuccess = () => {
    showAddDialog.value = false;
    showEditDialog.value = false;
};

const submitDelete = () => {
    if (selectedClient.value) {
        deleteForm.delete(`/admin/clients/${selectedClient.value.id}`, {
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
        router.get('/admin/clients', {
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
    
    // Toggle direction if clicking the same field
    if (props.filters?.sort === field && props.filters?.direction === 'asc') {
        direction = 'desc';
    }
    
    router.get('/admin/clients', {
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
    <Head title="Clients" />

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
                <h2 class="text-2xl font-bold">Clients</h2>
                <Button @click="openAddDialog">Add Client</Button>
            </div>

            <!-- Search Bar -->
            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    @input="handleSearch"
                    type="search"
                    placeholder="Search clients..."
                    class="pl-9"
                />
            </div>

            <!-- Clients Table -->
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
                            <TableHeadSortable
                                sort-key="username"
                                :current-sort="filters?.sort"
                                :current-direction="filters?.direction"
                                @sort="handleSort"
                            >
                                Email
                            </TableHeadSortable>
                            <TableHead>Phone</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="clients.data.length === 0">
                            <TableCell colspan="5" class="text-center text-muted-foreground">
                                No clients found. Add your first client to get started.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="client in clients.data" :key="client.id">
                            <TableCell class="font-medium">{{ client.name ?? '-' }}</TableCell>
                            <TableCell>{{ client.username ?? '-' }}</TableCell>
                            <TableCell>{{ client.phone ?? '-' }}</TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditDialog(client)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        @click="openDeleteDialog(client)"
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
            <div v-if="clients.last_page > 1" class="flex items-center justify-between gap-2">
                <div class="text-sm text-muted-foreground hidden sm:block">
                    Showing {{ ((clients.current_page - 1) * clients.per_page) + 1 }} to 
                    {{ Math.min(clients.current_page * clients.per_page, clients.total) }} of 
                    {{ clients.total }} clients
                </div>
                
                <!-- Mobile pagination -->
                <div class="flex sm:hidden items-center gap-2 ml-auto">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="clients.current_page === 1"
                        @click="goToPage(clients.links[0]?.url)"
                    >
                        ←
                    </Button>
                    <span class="text-sm font-medium px-2">
                        {{ clients.current_page }} / {{ clients.last_page }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="clients.current_page === clients.last_page"
                        @click="goToPage(clients.links[clients.links.length - 1]?.url)"
                    >
                        →
                    </Button>
                </div>
                
                <!-- Desktop pagination -->
                <div class="hidden sm:flex gap-2">
                    <Button
                        v-for="link in clients.links"
                        :key="link.label"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>

            <!-- Add Client Dialog -->
            <Dialog v-model:open="showAddDialog">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Add New Client</DialogTitle>
                        <DialogDescription>
                            Fill in the client information below.
                        </DialogDescription>
                    </DialogHeader>

                    <FormClient
                        :client="emptyClient"
                        :groups="groups"
                        submit-url="/admin/clients"
                        method="post"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Edit Client Dialog -->
            <Dialog v-model:open="showEditDialog">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Client</DialogTitle>
                        <DialogDescription>
                            Update the client information below.
                        </DialogDescription>
                    </DialogHeader>

                    <FormClient
                        v-if="selectedClient"
                        :client="selectedClient"
                        :groups="groups"
                        :submit-url="`/admin/clients/${selectedClient.id}`"
                        method="put"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Delete Client Dialog -->
            <Dialog v-model:open="showDeleteDialog">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Delete Client</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{{ selectedClient?.name }}</strong>? This action cannot be undone.
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
                            Delete Client
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>