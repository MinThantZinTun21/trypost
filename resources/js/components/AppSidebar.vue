<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    IconAffiliate,
    IconBulb,
    IconCalendar,
    IconChevronRight,
    IconClock,
    IconFileCheck,
    IconFileText,
    IconPencil,
    IconSelector,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { index as ideasIndex } from '@/actions/App/Http/Controllers/App/ContentIdeaController';
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
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCreatePost } from '@/composables/useCreatePost';
import { accounts, calendar } from '@/routes/app';
import type { NavItem, User } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const hasWorkspace = computed(() => page.props.auth.currentWorkspace !== null);

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
    {
        title: trans('sidebar.posts.ideas'),
        href: ideasIndex.url(),
        icon: IconBulb,
    },
]);

const workspaceNavItems = computed<NavItem[]>(() => [
    {
        title: trans('sidebar.workspace.connections'),
        href: accounts.url(),
        icon: IconAffiliate,
    },
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
                                    data-testid="sidebar-user-menu"
                                >
                                    <Avatar
                                        :src="user.photo_url"
                                        :name="user.name"
                                        class="h-8 w-8 shrink-0 rounded-md border-2 border-foreground"
                                        fallback-class="bg-violet-100 text-violet-700 font-bold"
                                    />
                                    <div
                                        class="grid min-w-0 flex-1 text-left text-sm leading-tight"
                                    >
                                        <span class="truncate font-semibold">
                                            {{ user.name }}
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
                                <UserMenuContent :user="user" />
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <NotificationBell v-if="hasWorkspace" />
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-px">
            <div v-if="hasWorkspace" class="px-2 py-2">
                <Button
                    class="w-full"
                    :disabled="creatingPost"
                    @click="createPost()"
                >
                    {{ $t('sidebar.create_post') }}
                </Button>
            </div>

            <NavMain v-if="hasWorkspace" :items="mainNavItems" />
            <NavMain
                v-if="hasWorkspace"
                :items="postsNavItems"
                :label="$t('sidebar.groups.posts')"
            />
            <NavMain
                v-if="hasWorkspace"
                :items="workspaceNavItems"
                :label="$t('sidebar.groups.workspace')"
            />
        </SidebarContent>
    </Sidebar>
</template>
