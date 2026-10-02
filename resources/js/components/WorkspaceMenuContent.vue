<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    IconCheck,
    IconCreditCard,
    IconLogout,
    IconPlus,
    IconSettings,
    IconUser,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import { Avatar } from '@/components/ui/avatar';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { useWorkspaceLimit } from '@/composables/useWorkspaceLimit';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import posthog from '@/posthog';
import { logout } from '@/routes';
import { edit as accountEdit } from '@/routes/app/account';
import { edit as profileEdit } from '@/routes/app/profile';
import { settings as workspaceSettings } from '@/routes/app/workspace';
import { switchMethod } from '@/routes/app/workspaces';
import type { User } from '@/types';

interface Workspace {
    id: string;
    name: string;
    logo_url: string | null;
}

const props = defineProps<{
    user: User;
    currentWorkspace: Workspace | null;
    workspaces: Workspace[];
    canCreateWorkspace: boolean;
}>();

const emit = defineEmits<{
    upgradeRequired: [];
}>();

const page = usePage();
const { canManageBilling, canManageWorkspace } = useWorkspaceRole();
const { createOrUpgrade } = useWorkspaceLimit(() => props.workspaces.length);
const selfHosted = computed(() => Boolean(page.props.selfHosted));
const showAccountSettings = computed(
    () => canManageBilling.value && !selfHosted.value,
);
const showWorkspaceSettings = computed(() => canManageWorkspace.value);
const switchWorkspace = (workspaceId: string): void => {
    if (workspaceId === props.currentWorkspace?.id) {
        return;
    }

    router.post(
        switchMethod.url(workspaceId),
        {},
        {
            preserveScroll: true,
        },
    );
};

const handleCreateWorkspace = (): void => {
    createOrUpgrade(() => emit('upgradeRequired'));
};

const handleLogout = (): void => {
    posthog.reset();
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
        <DropdownMenuItem v-if="showAccountSettings" :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="accountEdit.url()"
                prefetch
                data-testid="sidebar-menu-account-settings"
            >
                <IconCreditCard class="size-4" />
                {{ $t('sidebar.account_settings') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="showWorkspaceSettings" :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="workspaceSettings.url()"
                prefetch
                data-testid="sidebar-menu-workspace-settings"
            >
                <IconSettings class="size-4" />
                {{ $t('sidebar.workspace_settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>

    <DropdownMenuSeparator />

    <DropdownMenuLabel
        class="px-2 py-1.5 text-xs font-medium tracking-normal text-muted-foreground normal-case"
    >
        {{ $t('sidebar.workspaces') }}
    </DropdownMenuLabel>

    <DropdownMenuGroup>
        <DropdownMenuItem
            v-for="workspace in workspaces"
            :key="workspace.id"
            class="gap-2"
            @click="switchWorkspace(workspace.id)"
        >
            <Avatar
                :src="workspace.logo_url"
                :name="workspace.name"
                class="h-6 w-6 shrink-0 rounded-md border-2 border-foreground"
                fallback-class="text-[10px] bg-violet-100 text-violet-700 font-bold"
            />
            <span class="min-w-0 flex-1 truncate">{{ workspace.name }}</span>
            <IconCheck
                v-if="workspace.id === currentWorkspace?.id"
                class="size-4 shrink-0 text-foreground"
                stroke-width="2.5"
            />
        </DropdownMenuItem>
        <DropdownMenuItem
            v-if="canCreateWorkspace"
            data-testid="sidebar-create-workspace"
            @click="handleCreateWorkspace"
        >
            <IconPlus class="size-4" />
            {{ $t('sidebar.create_workspace') }}
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
