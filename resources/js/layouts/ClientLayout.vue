<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ShoppingCart, LogOut, User, Menu as MenuIcon, ChefHat, Settings } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Props {
    title?: string;
    cartCount?: number;
}

const emit = defineEmits(['showCart']);

const openCart = () => {
    emit('showCart');
};

const props = withDefaults(defineProps<Props>(), {
    title: '',
    cartCount: 0,
});

const mobileMenuOpen = ref(false);

const page = usePage();
const isAdmin = () => page.props?.auth?.user?.usertype === 'master';

const isActive = (path: string) => {
    const currentPath = page.url;
    if (!currentPath) return false;
    
    if (path === '/') {
        return currentPath === '/';
    }
    if (path === '/settings/profile') {
        return currentPath.startsWith('/settings');
    }
    return currentPath.startsWith(path);
};

const linkClass = (path: string) => {
    if (!page.url) {
        // Return default class if page not ready
        return 'px-4 py-2 text-gray-700 hover:text-red-600 transition-colors font-semibold rounded-lg hover:bg-red-50';
    }
    
    return isActive(path)
        ? 'px-4 py-2 bg-gradient-to-r from-red-600 to-orange-500 text-white transition-colors font-semibold rounded-lg shadow-md'
        : 'px-4 py-2 text-gray-700 hover:text-red-600 transition-colors font-semibold rounded-lg hover:bg-red-50';
};

const mobileLinkClass = (path: string) => {
    if (!page.url) {
        // Return default class if page not ready
        return 'block px-4 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 rounded-lg font-semibold';
    }
    
    return isActive(path)
        ? 'block px-4 py-3 bg-gradient-to-r from-red-600 to-orange-500 text-white rounded-lg font-semibold'
        : 'block px-4 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 rounded-lg font-semibold';
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen client-theme">
        <Head :title="title" />
        
        <div class="h-2 bg-gradient-to-r from-red-600 via-orange-500 to-red-600"></div>
        
        <header v-if="page.url" class="bg-white shadow-lg sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between py-3">
                    <Link href="/" class="flex items-center gap-3 group">
                        <div class="relative">
                            <img 
                                src="/images/logo.png" 
                                alt="Mama Liu" 
                                class="h-20 w-20 object-contain transition-transform group-hover:scale-110 group-hover:rotate-6"
                            />
                        </div>
                        <div class="hidden sm:block">
                            <h1 class="text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-orange-500 tracking-tight">
                                MAMA LIU
                            </h1>
                            <p class="text-sm text-gray-600 font-medium -mt-1 flex items-center gap-1">
                                <ChefHat class="h-3 w-3" />
                                Authentic Taiwanese Cuisine
                            </p>
                        </div>
                    </Link>
                    
                    <div class="hidden md:flex items-center gap-6">
                        <Link 
                            v-if="isAdmin()"
                            href="/admin/dashboard" 
                            class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-red-600 to-orange-500 hover:from-red-700 hover:to-orange-600 text-white transition-colors font-semibold rounded-lg shadow-md hover:shadow-lg"
                        >
                            <Settings class="h-4 w-4" />
                            Admin Panel
                        </Link>
                        
                        <Link href="/" :class="linkClass('/')">
                            Make Order
                        </Link>
                        
                        <Link href="/orders" :class="linkClass('/orders')">
                            My Orders
                        </Link>
                        
                        <Link href="/settings/profile" :class="linkClass('/settings/profile')">
                            My Account
                        </Link>
                        
                        <button 
                            v-if="cartCount > 0" 
                            @click="openCart"
                            class="relative hover:scale-110 transition-transform"
                        >
                            <div class="bg-red-100 p-2 rounded-full">
                                <ShoppingCart class="h-6 w-6 text-red-600" />
                            </div>
                            <span class="absolute -top-2 -right-2 bg-gradient-to-r from-red-600 to-orange-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center shadow-lg animate-pulse">
                                {{ cartCount }}
                            </span>
                        </button>
                        
                        <button 
                            @click="logout" 
                            class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-gray-100 to-gray-200 hover:from-gray-200 hover:to-gray-300 rounded-lg transition-all font-semibold text-gray-700 shadow-sm hover:shadow-md"
                        >
                            <LogOut class="h-4 w-4" />
                            <span>Logout</span>
                        </button>
                    </div>
                    
                    <button 
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="md:hidden p-2 rounded-lg hover:bg-red-50 text-red-600"
                    >
                        <MenuIcon class="h-6 w-6" />
                    </button>
                </div>
                
                <div v-if="mobileMenuOpen" class="md:hidden pb-4 border-t space-y-2 pt-4">
                    <Link 
                        v-if="isAdmin()"
                        href="/admin/dashboard" 
                        class="flex items-center gap-2 px-4 py-3 bg-gradient-to-r from-red-600 to-orange-500 text-white rounded-lg font-semibold"
                        @click="mobileMenuOpen = false"
                    >
                        <Settings class="h-4 w-4" />
                        Admin Panel
                    </Link>
                    
                    <Link 
                        href="/" 
                        :class="mobileLinkClass('/')"
                        @click="mobileMenuOpen = false"
                    >
                        Make Order
                    </Link>
                    
                    <Link 
                        href="/orders" 
                        :class="mobileLinkClass('/orders')"
                        @click="mobileMenuOpen = false"
                    >
                        My Orders
                    </Link>
                    
                    <Link 
                        href="/settings/profile" 
                        :class="mobileLinkClass('/settings/profile')"
                        @click="mobileMenuOpen = false"
                    >
                        My Account
                    </Link>
                    
                    <button 
                        @click="logout" 
                        class="w-full text-left px-4 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 rounded-lg font-semibold"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </header>
        
        <main class="min-h-screen bg-gradient-to-br from-orange-50 via-red-50 to-orange-50">
            <slot />
        </main>
        
        <footer class="bg-white border-t-4 border-red-600 shadow-inner">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <img src="/images/logo.png" alt="Mama Liu" class="h-16 w-16 object-contain" />
                        <div>
                            <p class="font-black text-xl text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-orange-500">
                                MAMA LIU
                            </p>
                            <p class="text-xs text-gray-600 font-medium">Authentic Taiwanese Cuisine</p>
                        </div>
                    </div>
                    <p class="text-gray-500 text-sm">
                        © {{ new Date().getFullYear() }} Mama Liu. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>