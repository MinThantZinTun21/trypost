<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { IconLogout, IconUser } from '@tabler/icons-vue';

import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/app/profile';
import type { User } from '@/types';

defineProps<{
    user: User;
}>();

const handleLogout = (): void => {
    router.flushAll();
};
</script>

<template>
    <DropdownMenuLabel
        class="px-2 py-1.5 text-xs font-medium tracking-normal text-muted-foreground normal-case"
        data-testid="sidebar-menu-greeting"
    >
        {{ user.first_name }}
    </DropdownMenuLabel>

    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="profileEdit.url()"
                prefetch
                data-testid="sidebar-menu-my-account"
            >
                <IconUser class="size-4" />
                {{ $t('sidebar.my_account') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>

    <DropdownMenuSeparator />

    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            as="button"
            data-test="logout-button"
            data-testid="logout-button"
            @click="handleLogout"
        >
            <IconLogout class="size-4" />
            {{ $t('sidebar.log_out') }}
        </Link>
    </DropdownMenuItem>
</template>
