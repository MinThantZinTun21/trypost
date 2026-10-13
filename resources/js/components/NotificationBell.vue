<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    IconArchive,
    IconBell,
    IconCheck,
    IconChecks,
    IconInbox,
    IconX,
} from '@tabler/icons-vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { useSidebar } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import dayjs from '@/dayjs';
import { accounts } from '@/routes/app';
import { archiveAll, index, read, readAll } from '@/routes/app/notifications';
import { edit as editPost } from '@/routes/app/posts';

interface Notification {
    id: string;
    type: string;
    title: string;
    body: string;
    data: Record<string, string> | null;
    read_at: string | null;
    archived_at: string | null;
    created_at: string;
}

const notifications = ref<Notification[]>([]);
const unreadCount = ref(0);
const loading = ref(false);
const show = ref(false);
const panel = ref<HTMLElement | null>(null);

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const fetchNotifications = async () => {
    loading.value = true;
    try {
        const response = await fetch(index.url(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) return;

        const data = await response.json();
        notifications.value = data.notifications;
        unreadCount.value = data.unread_count;
    } finally {
        loading.value = false;
    }
};

const handleMarkAsRead = async (notification: Notification) => {
    await fetch(read.url(notification.id), {
        method: 'PUT',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
    });

    notification.read_at = dayjs().toISOString();
    unreadCount.value = Math.max(0, unreadCount.value - 1);
};

const handleMarkAllAsRead = async () => {
    await fetch(readAll.url(), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
    });

    notifications.value = notifications.value.map((n) => ({
        ...n,
        read_at: n.read_at ?? dayjs().toISOString(),
    }));
    unreadCount.value = 0;
};

const handleArchiveAll = async () => {
    await fetch(archiveAll.url(), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
    });

    notifications.value = [];
    unreadCount.value = 0;
};

const handleNotificationClick = (notification: Notification) => {
    if (!notification.read_at) {
        handleMarkAsRead(notification);
    }

    close();

    if (notification.data?.post_id) {
        router.visit(editPost.url(notification.data.post_id));
    } else if (
        notification.data?.social_account_id ||
        notification.data?.workspace_id
    ) {
        router.visit(accounts.url());
    }
};

const open = () => {
    show.value = true;
    fetchNotifications();
};

const close = () => {
    show.value = false;
};

const toggle = () => {
    if (show.value) {
        close();
    } else {
        open();
    }
};

const onClickOutside = (event: MouseEvent) => {
    if (panel.value && !panel.value.contains(event.target as Node)) {
        close();
    }
};

const onEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        close();
    }
};

// The panel is teleported to the body with an offset anchored to the open
// sidebar — closing the sidebar would leave it floating mid-screen.
const { state: sidebarState } = useSidebar();

watch(sidebarState, () => close());

const formatTime = (date: string) => {
    return dayjs.utc(date).fromNow();
};

watch(show, (value) => {
    if (value) {
        setTimeout(() => {
            document.addEventListener('click', onClickOutside);
            document.addEventListener('keydown', onEscape);
        }, 0);
    } else {
        document.removeEventListener('click', onClickOutside);
        document.removeEventListener('keydown', onEscape);
    }
});

// No live updates: the layout remounts on every visit, so the bell refetches
// on each page load and again whenever it is opened.
onMounted(() => {
    fetchNotifications();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside);
    document.removeEventListener('keydown', onEscape);
});
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        class="relative size-8 shrink-0"
        @click.stop="toggle"
    >
        <IconBell class="size-4" />
        <span
            v-if="unreadCount > 0"
            class="absolute -top-1 -right-1 inline-flex size-4 items-center justify-center rounded-full border border-border bg-rose-100 text-[9px] font-bold text-rose-700 shadow-2xs"
        >
            {{ unreadCount > 9 ? '9+' : unreadCount }}
        </span>
    </Button>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-2 opacity-0 sm:-translate-x-2 sm:translate-y-0"
            enter-to-class="translate-x-0 translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-x-0 translate-y-0 opacity-100"
            leave-to-class="-translate-y-2 opacity-0 sm:-translate-x-2 sm:translate-y-0"
        >
            <div
                v-if="show"
                ref="panel"
                class="fixed inset-x-2 top-2 z-50 flex h-[32rem] max-h-[calc(100svh-1rem)] flex-col overflow-hidden rounded-xl border border-border bg-card shadow-md sm:inset-x-auto sm:top-4 sm:left-[17rem] sm:w-[22rem]"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between gap-2 border-b-2 border-foreground/10 px-4 py-3"
                >
                    <h3
                        class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                    >
                        {{ $t('sidebar.notifications') }}
                    </h3>
                    <div class="flex items-center gap-1.5">
                        <Tooltip v-if="notifications.length > 0">
                            <TooltipTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="icon-sm"
                                    class="size-7 rounded-md shadow-2xs max-sm:size-9"
                                    :aria-label="$t('sidebar.mark_all_read')"
                                    @click="handleMarkAllAsRead"
                                >
                                    <IconChecks
                                        class="size-3.5"
                                        stroke-width="2.5"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{
                                $t('sidebar.mark_all_read')
                            }}</TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="notifications.length > 0">
                            <TooltipTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="icon-sm"
                                    class="size-7 rounded-md shadow-2xs max-sm:size-9"
                                    :aria-label="$t('sidebar.archive_all')"
                                    @click="handleArchiveAll"
                                >
                                    <IconArchive
                                        class="size-3.5"
                                        stroke-width="2.5"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{
                                $t('sidebar.archive_all')
                            }}</TooltipContent>
                        </Tooltip>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            class="size-7 rounded-full shadow-2xs hover:-rotate-90 hover:bg-rose-100 max-sm:size-9"
                            :aria-label="$t('common.close')"
                            @click="close"
                        >
                            <IconX class="size-3.5" stroke-width="2.5" />
                        </Button>
                    </div>
                </div>

                <!-- Notification list -->
                <div class="flex-1 overflow-y-auto">
                    <div
                        v-if="notifications.length > 0"
                        class="divide-y-2 divide-dashed divide-foreground/15"
                    >
                        <button
                            v-for="notification in notifications"
                            :key="notification.id"
                            type="button"
                            class="flex w-full cursor-pointer items-start gap-2.5 px-3 py-3 text-left transition-colors hover:bg-foreground/5"
                            :class="
                                !notification.read_at ? 'bg-violet-100/40' : ''
                            "
                            @click="handleNotificationClick(notification)"
                        >
                            <span
                                class="mt-1.5 inline-block size-2 shrink-0 rounded-full"
                                :class="
                                    !notification.read_at
                                        ? 'bg-violet-500 ring-2 ring-violet-200'
                                        : 'bg-transparent'
                                "
                            />
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-bold text-foreground"
                                >
                                    {{ notification.title }}
                                </p>
                                <p class="truncate text-xs text-foreground/70">
                                    {{ notification.body }}
                                </p>
                                <p
                                    class="mt-0.5 text-[11px] font-medium text-foreground/50"
                                >
                                    {{ formatTime(notification.created_at) }}
                                </p>
                            </div>
                            <div class="shrink-0" @click.stop>
                                <Tooltip v-if="!notification.read_at">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            class="size-7 rounded-md text-foreground/60 hover:bg-foreground/10 hover:text-foreground max-sm:size-9"
                                            :aria-label="
                                                $t('sidebar.mark_as_read')
                                            "
                                            @click="
                                                handleMarkAsRead(notification)
                                            "
                                        >
                                            <IconCheck
                                                class="size-3.5"
                                                stroke-width="2.5"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        $t('sidebar.mark_as_read')
                                    }}</TooltipContent>
                                </Tooltip>
                            </div>
                        </button>
                    </div>

                    <!-- Empty state -->
                    <Empty
                        v-else-if="!loading"
                        class="gap-3 px-6 py-12 md:px-6 md:py-12"
                    >
                        <EmptyHeader class="gap-3">
                            <EmptyMedia
                                class="mb-0 size-12 rounded-2xl bg-brand text-white shadow-md shadow-primary/25"
                            >
                                <IconInbox
                                    class="size-6 text-white"
                                    stroke-width="2"
                                />
                            </EmptyMedia>
                            <EmptyTitle
                                class="text-base font-bold tracking-normal text-foreground"
                                style="font-family: var(--font-display)"
                            >
                                {{ $t('sidebar.no_notifications') }}
                            </EmptyTitle>
                        </EmptyHeader>
                    </Empty>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
