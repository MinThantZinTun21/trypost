<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

import AppHeader from '@/components/AppHeader.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import HelpMenu from '@/components/HelpMenu.vue';
import Toast from '@/components/Toast.vue';
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';

const page = usePage();
const isOpen = page.props.sidebarOpen;

type Props = {
    fullWidth?: boolean;
};

withDefaults(defineProps<Props>(), {
    fullWidth: false,
});
</script>

<template>
    <SidebarProvider :default-open="isOpen">
        <AppSidebar />
        <SidebarInset class="overflow-x-hidden">
            <AppHeader v-if="$slots['header'] || $slots['header-actions']">
                <template v-if="$slots['header']" #left>
                    <slot name="header" />
                </template>
                <template v-if="$slots['header-actions']" #right>
                    <slot name="header-actions" />
                </template>
            </AppHeader>
            <SidebarTrigger
                v-else
                class="absolute top-3 left-4 z-30 size-10 rounded-md border-2 border-foreground bg-card text-foreground shadow-2xs md:hidden"
            />
            <div
                :class="
                    fullWidth
                        ? 'flex min-h-0 flex-1 flex-col overflow-y-auto'
                        : 'flex-1 overflow-y-auto'
                "
            >
                <div
                    :class="[
                        fullWidth
                            ? 'flex min-h-0 flex-1 flex-col'
                            : 'mx-auto w-full max-w-7xl',
                        !fullWidth &&
                        !$slots['header'] &&
                        !$slots['header-actions']
                            ? 'pt-14 md:pt-0'
                            : '',
                    ]"
                >
                    <slot />
                </div>
            </div>
        </SidebarInset>
    </SidebarProvider>
    <HelpMenu />
    <Toast />
</template>
