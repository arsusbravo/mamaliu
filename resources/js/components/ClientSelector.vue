<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Input } from '@/components/ui/input';
import { Check } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Props {
    modelValue?: number | null;
    error?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    error: '',
    disabled: false,
});

const emit = defineEmits(['update:modelValue']);

const searchQuery = ref('');
const isOpen = ref(false);
const clients = ref<User[]>([]);

const selectedClient = computed(() => {
    if (!props.modelValue || !clients.value) return null;
    return clients.value.find(c => c.id === props.modelValue);
});

const filteredClients = computed(() => {
    if (!searchQuery.value) return clients.value;
    
    const query = searchQuery.value.toLowerCase();
    return clients.value.filter(client => 
        client.name.toLowerCase().includes(query) ||
        client.email.toLowerCase().includes(query)
    );
});

const loadClients = async () => {
    try {
        const response = await fetch('/admin/clients/list');
        const data = await response.json();
        clients.value = data.clients || [];
    } catch (error) {
        console.error('Failed to load clients:', error);
    }
};

const selectClient = (clientId: number) => {
    emit('update:modelValue', clientId);
    isOpen.value = false;
    searchQuery.value = '';
};

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value && clients.value.length === 0) {
        loadClients();
    }
};

onMounted(() => {
    if (props.modelValue) {
        loadClients();
    }
});
</script>

<template>
    <div class="grid gap-2">
        <button
            type="button"
            @click="toggleDropdown"
            :disabled="disabled"
            :class="[
                'flex items-center justify-between gap-3 w-full px-3 py-2 text-left border rounded-md bg-background hover:bg-accent',
                error ? 'border-red-500' : 'border-input',
                disabled ? 'opacity-50 cursor-not-allowed' : ''
            ]"
        >
            <div v-if="selectedClient" class="flex flex-col">
                <span class="font-medium">{{ selectedClient.name }}</span>
                <span class="text-xs text-muted-foreground">{{ selectedClient.email }}</span>
            </div>
            <span v-else class="text-muted-foreground">Select a client</span>
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div v-if="isOpen" class="relative">
            <div class="absolute z-50 w-full mt-1 bg-background border rounded-md shadow-lg">
                <div class="p-2 border-b">
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by name or email..."
                        class="w-full"
                    />
                </div>

                <div class="max-h-64 overflow-y-auto">
                    <div class="p-1">
                        <button
                            v-for="client in filteredClients"
                            :key="client.id"
                            type="button"
                            @click="selectClient(client.id)"
                            class="flex items-center justify-between w-full px-3 py-2 rounded hover:bg-accent transition-colors"
                        >
                            <div class="flex flex-col text-left">
                                <span class="font-medium">{{ client.name }}</span>
                                <span class="text-xs text-muted-foreground">{{ client.email }}</span>
                            </div>
                            <Check v-if="client.id === modelValue" class="w-4 h-4 text-primary shrink-0" />
                        </button>

                        <div v-if="filteredClients.length === 0" class="p-4 text-center text-muted-foreground">
                            No clients found
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <span v-if="error" class="text-sm text-red-500">
            {{ error }}
        </span>
    </div>
</template>