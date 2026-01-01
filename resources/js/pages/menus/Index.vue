<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import FormMenu from '@/components/FormMenu.vue';
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
import { Search, ImageOff, X } from 'lucide-vue-next';

interface Menu {
    id: number;
    label: string | null;
    description: string | null;
    price: number | null;
    menutype: string;
    image_url: string | null;
    has_image: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    menus?: {
        data: Menu[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
    filters?: {
        search?: string;
        menutype?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
}

const props = withDefaults(defineProps<Props>(), {
    menus: () => ({
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        links: [],
    }),
    filters: () => ({
        search: '',
        menutype: '',
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
        title: "Menu's",
        href: '/admin/menus',
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
const showImageDialog = ref(false);
const selectedMenu = ref<Menu | null>(null);
const selectedImageMenu = ref<Menu | null>(null);

// Search & Filters
const searchQuery = ref(props.filters?.search || '');
const menutypeFilter = ref(props.filters?.menutype || '');
let searchTimeout: ReturnType<typeof setTimeout>;

// Empty menu for add form
const emptyMenu: Menu = {
    id: 0,
    label: null,
    description: null,
    price: null,
    menutype: 'normal',
    image_url: null,
    has_image: false,
};

const deleteForm = useForm({});

// Actions
const openAddDialog = () => {
    showAddDialog.value = true;
};

const openEditDialog = (menu: Menu) => {
    selectedMenu.value = menu;
    showEditDialog.value = true;
};

const openDeleteDialog = (menu: Menu) => {
    selectedMenu.value = menu;
    showDeleteDialog.value = true;
};

const openImageDialog = (menu: Menu) => {
    selectedImageMenu.value = menu;
    showImageDialog.value = true;
};

const handleFormSuccess = () => {
    showAddDialog.value = false;
    showEditDialog.value = false;
};

const submitDelete = () => {
    if (selectedMenu.value) {
        deleteForm.delete(`/admin/menus/${selectedMenu.value.id}`, {
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
        router.get('/admin/menus', {
            search: searchQuery.value,
            menutype: menutypeFilter.value,
            sort: props.filters?.sort,
            direction: props.filters?.direction,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);
};

// Filter handler
const handleFilter = () => {
    router.get('/admin/menus', {
        search: searchQuery.value,
        menutype: menutypeFilter.value,
        sort: props.filters?.sort,
        direction: props.filters?.direction,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Sort handler
const handleSort = (field: string) => {
    let direction: 'asc' | 'desc' = 'asc';
    
    if (props.filters?.sort === field && props.filters?.direction === 'asc') {
        direction = 'desc';
    }
    
    router.get('/admin/menus', {
        search: searchQuery.value,
        menutype: menutypeFilter.value,
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
    <Head title="Menus" />

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
                <h2 class="text-2xl font-bold">Menu List</h2>
                <Button @click="openAddDialog">Add Menu</Button>
            </div>

            <!-- Search & Filter Bar -->
            <div class="flex gap-4">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        @input="handleSearch"
                        type="search"
                        placeholder="Search menus..."
                        class="pl-9"
                    />
                </div>
                <select
                    v-model="menutypeFilter"
                    @change="handleFilter"
                    class="h-10 w-[180px] rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <option value="">All Types</option>
                    <option value="normal">Normal</option>
                    <option value="menuset">Menu Set</option>
                </select>
            </div>

            <!-- Menus Table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-20">Image</TableHead>
                            <TableHeadSortable
                                sort-key="label"
                                :current-sort="filters?.sort"
                                :current-direction="filters?.direction"
                                @sort="handleSort"
                            >
                                Label
                            </TableHeadSortable>
                            <TableHead>Description</TableHead>
                            <TableHeadSortable
                                sort-key="price"
                                :current-sort="filters?.sort"
                                :current-direction="filters?.direction"
                                @sort="handleSort"
                            >
                                Price
                            </TableHeadSortable>
                            <TableHead>Type</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="menus.data.length === 0">
                            <TableCell colspan="6" class="text-center text-muted-foreground">
                                No menus found. Add your first menu to get started.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="menu in menus.data" :key="menu.id">
                            <TableCell>
                                <div 
                                    v-if="menu.has_image && menu.image_url" 
                                    class="w-16 h-16 rounded overflow-hidden cursor-pointer hover:opacity-80 transition-opacity"
                                    @click="openImageDialog(menu)"
                                >
                                    <img
                                        :src="menu.image_url"
                                        :alt="menu.label || 'Menu'"
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                                <div v-else class="w-16 h-16 rounded bg-gray-100 flex items-center justify-center">
                                    <ImageOff class="h-6 w-6 text-gray-400" />
                                </div>
                            </TableCell>
                            <TableCell class="font-medium">{{ menu.label ?? '-' }}</TableCell>
                            <TableCell class="max-w-xs truncate">{{ menu.description ?? '-' }}</TableCell>
                            <TableCell>€{{ Number(menu.price).toFixed(2) }}</TableCell>
                            <TableCell>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                        menu.menutype === 'menuset'
                                            ? 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20'
                                            : 'bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-500/10'
                                    ]"
                                >
                                    {{ menu.menutype === 'menuset' ? 'Menu Set' : 'Normal' }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditDialog(menu)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        @click="openDeleteDialog(menu)"
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
            <div v-if="menus.last_page > 1" class="flex items-center justify-between">
                <div class="text-sm text-muted-foreground">
                    Showing {{ ((menus.current_page - 1) * menus.per_page) + 1 }} to 
                    {{ Math.min(menus.current_page * menus.per_page, menus.total) }} of 
                    {{ menus.total }} menus
                </div>
                <div class="flex gap-2">
                    <Button
                        v-for="link in menus.links"
                        :key="link.label"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>

            <!-- Image Preview Dialog -->
            <Dialog v-model:open="showImageDialog">
                <DialogContent class="max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>{{ selectedImageMenu?.label }}</DialogTitle>
                        <DialogDescription v-if="selectedImageMenu?.description">
                            {{ selectedImageMenu.description }}
                        </DialogDescription>
                    </DialogHeader>
                    <div class="flex items-center justify-center">
                        <img
                            v-if="selectedImageMenu?.image_url"
                            :src="selectedImageMenu.image_url"
                            :alt="selectedImageMenu.label || 'Menu'"
                            class="max-w-full max-h-[70vh] rounded-lg"
                        />
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Add Menu Dialog -->
            <Dialog v-model:open="showAddDialog">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Add New Menu</DialogTitle>
                        <DialogDescription>
                            Create a new menu item.
                        </DialogDescription>
                    </DialogHeader>

                    <FormMenu
                        :menu="emptyMenu"
                        submit-url="/admin/menus"
                        method="post"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Edit Menu Dialog -->
            <Dialog v-model:open="showEditDialog">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Menu</DialogTitle>
                        <DialogDescription>
                            Update the menu information.
                        </DialogDescription>
                    </DialogHeader>

                    <FormMenu
                        v-if="selectedMenu"
                        :menu="selectedMenu"
                        :submit-url="`/admin/menus/${selectedMenu.id}`"
                        method="put"
                        @success="handleFormSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Delete Menu Dialog -->
            <Dialog v-model:open="showDeleteDialog">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Delete Menu</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{{ selectedMenu?.label }}</strong>? This action cannot be undone.
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
                            Delete Menu
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>