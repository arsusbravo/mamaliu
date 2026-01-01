<script setup lang="ts">
import { ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Copy, Check, Plus } from 'lucide-vue-next';

interface Token {
    id: number;
    token: string;
    valid_at: string;
    expires_at: string;
    is_valid: boolean;
    invite_url: string;
    created_at: string;
}

interface Props {
    tokens: Token[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Invite Links', href: '/admin/invites' },
];

const showCreateDialog = ref(false);
const validAt = ref(new Date().toISOString().split('T')[0]);
const copiedToken = ref<number | null>(null);

const createInvite = () => {
    router.post('/admin/invites', {
        valid_at: validAt.value,
    }, {
        onSuccess: () => {
            showCreateDialog.value = false;
            validAt.value = new Date().toISOString().split('T')[0];
        },
    });
};

const deleteInvite = (tokenId: number) => {
    if (confirm('Are you sure you want to delete this invite?')) {
        router.delete(`/admin/invites/${tokenId}`);
    }
};

const copyToClipboard = async (url: string, tokenId: number) => {
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(url);
            copiedToken.value = tokenId;
            setTimeout(() => {
                copiedToken.value = null;
            }, 2000);
        } else {
            // Fallback for browsers that don't support clipboard API
            const textArea = document.createElement('textarea');
            textArea.value = url;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            copiedToken.value = tokenId;
            setTimeout(() => {
                copiedToken.value = null;
            }, 2000);
        }
    } catch (error) {
        console.error('Failed to copy:', error);
        alert('Failed to copy to clipboard. Please copy manually: ' + url);
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusColor = (token: Token) => {
    if (token.is_valid) return 'bg-green-100 text-green-800';
    const now = new Date();
    const validAt = new Date(token.valid_at);
    if (validAt > now) return 'bg-blue-100 text-blue-800';
    return 'bg-red-100 text-red-800';
};

const getStatusText = (token: Token) => {
    if (token.is_valid) return 'Valid';
    const now = new Date();
    const validAt = new Date(token.valid_at);
    if (validAt > now) return 'Not yet valid';
    return 'Expired';
};
</script>

<template>
    <Head title="Invite Links" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Invite Links</h1>
                <Button @click="showCreateDialog = true">
                    <Plus class="h-4 w-4 mr-2" />
                    Create Invite
                </Button>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Token</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valid From</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="token in tokens" :key="token.id">
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono">
                                {{ token.token.substring(0, 16) }}...
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span 
                                    :class="getStatusColor(token)"
                                    class="px-2 py-1 text-xs rounded font-medium"
                                >
                                    {{ getStatusText(token) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                {{ formatDate(token.valid_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                {{ formatDate(token.expires_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex gap-2">
                                    <Button
                                        v-if="token.is_valid"
                                        size="sm"
                                        variant="outline"
                                        @click="copyToClipboard(token.invite_url, token.id)"
                                        title="Copy registration link"
                                    >
                                        <Check v-if="copiedToken === token.id" class="h-4 w-4" />
                                        <Copy v-else class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        @click="deleteInvite(token.id)"
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="showCreateDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create Invite Link</DialogTitle>
                    <DialogDescription>
                        Generate a new registration invite link. Token will be valid for 3 days.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label for="valid_at">Valid From</Label>
                        <Input
                            id="valid_at"
                            v-model="validAt"
                            type="date"
                        />
                        <p class="text-xs text-gray-500 mt-1">Token will be valid for 3 days from this date</p>
                    </div>
                    <Button @click="createInvite" class="w-full">Create Invite</Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>