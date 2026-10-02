<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    IconAffiliate,
    IconCalendar,
    IconChevronRight,
    IconClock,
    IconFileCheck,
    IconFileText,
    IconHash,
    IconPencil,
    IconPhoto,
    IconSelector,
    IconTag,
    IconWebhook,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { index as postsIndex } from '@/actions/App/Http/Controllers/App/PostController';
import NavMain from '@/components/NavMain.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import WorkspaceMenuContent from '@/components/WorkspaceMenuContent.vue';
import { useCreatePost } from '@/composables/useCreatePost';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import { accounts, calendar } from '@/routes/app';
import { index as assets } from '@/routes/app/assets';
import { index as labels } from '@/routes/app/labels';
import { index as signatures } from '@/routes/app/signatures';
import { index as webhooks } from '@/routes/app/webhooks';
import type { NavItem, User } from '@/types';

interface Workspace {
    id: string;
    name: string;
    logo_url: string | null;
}

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const currentWorkspace = computed<Workspace | null>(
    () => page.props.auth.currentWorkspace as Workspace | null,
);
const workspaces = computed<Workspace[]>(
    () => page.props.auth.workspaces as Workspace[],
);

const {
    canCreatePost,
    canManageAccounts,
    canManageWebhooks,
    canCreateWorkspace,
} = useWorkspaceRole();
const { isMobile } = useSidebar();
const { createPost, creatingPost } = useCreatePost();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: trans('sidebar.posts.calendar'),
        href: calendar.url(),
        icon: IconCalendar,
    },
]);

const postsNavItems = computed<NavItem[]>(() => [
    {
        title: trans('sidebar.posts.all'),
        href: postsIndex.url(),
        icon: IconFileText,
        excludeActive: [
            postsIndex.url('scheduled'),
            postsIndex.url('published'),
            postsIndex.url('draft'),
        ],
    },
    {
        title: trans('sidebar.posts.scheduled'),
        href: postsIndex.url('scheduled'),
        icon: IconClock,
    },
    {
        title: trans('sidebar.posts.posted'),
        href: postsIndex.url('published'),
        icon: IconFileCheck,
    },
    {
        title: trans('sidebar.posts.drafts'),
        href: postsIndex.url('draft'),
        icon: IconPencil,
    },
]);

const workspaceNavItems = computed<NavItem[]>(() => [
    ...(canManageAccounts.value
        ? [
              {
                  title: trans('sidebar.workspace.connections'),
                  href: accounts.url(),
                  icon: IconAffiliate,
              },
          ]
        : []),
    ...(canCreatePost.value
        ? [
              {
                  title: trans('sidebar.workspace.signatures'),
                  href: signatures.url(),
                  icon: IconHash,
              },
              {
                  title: trans('sidebar.workspace.labels'),
                  href: labels.url(),
                  icon: IconTag,
              },
              {
                  title: trans('sidebar.workspace.assets'),
                  href: assets.url(),
                  icon: IconPhoto,
              },
          ]
        : []),
    ...(canManageWebhooks.value
        ? [
              {
                  title: trans('sidebar.workspace.webhooks'),
                  href: webhooks.url(),
                  icon: IconWebhook,
              },
          ]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="offcanvas">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <div class="flex items-center gap-1">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <SidebarMenuButton
                                    size="lg"
                                    class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                    data-test="sidebar-menu-button"
                                    data-testid="sidebar-workspace-menu"
                                >
                                    <Avatar
                                        :src="currentWorkspace?.logo_url"
                                        :name="currentWorkspace?.name ?? '?'"
                                        class="h-8 w-8 shrink-0 rounded-md border-2 border-foreground"
                                        fallback-class="bg-violet-100 text-violet-700 font-bold"
                                    />
                                    <div
                                        class="grid min-w-0 flex-1 text-left text-sm leading-tight"
                                    >
                                        <span class="truncate font-semibold">
                                            {{
                                                currentWorkspace?.name ??
                                                $t('sidebar.select_workspace')
                                            }}
                                        </span>
                                    </div>
                                    <component
                                        :is="
                                            isMobile
                                                ? IconSelector
                                                : IconChevronRight
                                        "
                                        class="ml-auto size-4"
                                    />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                class="w-(--reka-dropdown-menu-trigger-width) min-w-64"
                                align="start"
                                :side="isMobile ? 'bottom' : 'right'"
                                :side-offset="4"
                            >
                                <WorkspaceMenuContent
                                    :user="user"
                                    :current-workspace="currentWorkspace"
                                    :workspaces="workspaces"
                                    :can-create-workspace="canCreateWorkspace"
                                />
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <NotificationBell v-if="currentWorkspace" />
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-px">
            <div v-if="currentWorkspace && canCreatePost" class="px-2 py-2">
                <Button
                    class="w-full"
                    :disabled="creatingPost"
                    @click="createPost()"
                >
                    {{ $t('sidebar.create_post') }}
                </Button>
            </div>

            <NavMain v-if="currentWorkspace" :items="mainNavItems" />
            <NavMain
                v-if="currentWorkspace"
                :items="postsNavItems"
                :label="$t('sidebar.groups.posts')"
            />
            <NavMain
                v-if="currentWorkspace && workspaceNavItems.length"
                :items="workspaceNavItems"
                :label="$t('sidebar.groups.workspace')"
            />
        </SidebarContent>
    </Sidebar>
</template>
