<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { IconChevronLeft, IconChevronRight, IconPlus } from '@tabler/icons-vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import DatePicker from '@/components/DatePicker.vue';
import CalendarAccountFilter from '@/components/posts/CalendarAccountFilter.vue';
import CalendarPostAccounts from '@/components/posts/CalendarPostAccounts.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCreatePost } from '@/composables/useCreatePost';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import date from '@/date';
import dayjs from '@/dayjs';
import AppLayout from '@/layouts/AppLayout.vue';
import { calendar } from '@/routes/app';
import { edit as editPost, show as showPost } from '@/routes/app/posts';
import type {
    CalendarAccountOption,
    CalendarPostPlatform,
} from '@/types/calendar';
import { PostStatus } from '@/types/post';

interface Post {
    id: string;
    status: string;
    content: string | null;
    scheduled_at: string;
    post_platforms: CalendarPostPlatform[];
}

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    posts: Record<string, Post[]>;
    currentDay: string;
    currentWeekStart: string;
    currentMonth: string;
    view: 'day' | 'week' | 'month';
    socialAccounts: CalendarAccountOption[];
    filters: { account: string | null };
}

const props = defineProps<Props>();

// Mobile detection
const isMobile = ref(false);
const { createPost, creatingPost } = useCreatePost();

const checkMobile = () => {
    isMobile.value = window.innerWidth < 1024;
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
});

// Effective view (force day view on mobile)
const effectiveView = computed(() => {
    return isMobile.value ? 'day' : props.view;
});

const localized = (value?: dayjs.ConfigType) => dayjs(value);

// Generate weekday names based on dayjs locale (respects weekStart config)
const weekdayNames = computed(() => {
    const names = [];
    const start = localized().startOf('week');
    for (let i = 0; i < 7; i++) {
        names.push(start.add(i, 'day').format('dddd'));
    }
    return names;
});

const formatDayMonth = (day: dayjs.Dayjs): string => day.format('D MMMM');

// Day view computed
const currentDay = computed(() => localized(props.currentDay));

const dayHeaderTitle = computed(() => currentDay.value.format('LL'));

const dayPosts = computed(() => {
    const dateKey = currentDay.value.format('YYYY-MM-DD');
    return props.posts[dateKey] || [];
});

// Date picker model for day navigation
const selectedDate = ref(props.currentDay);

// Week view computed
const weekStart = computed(() => localized(props.currentWeekStart));

const weekDays = computed(() => {
    const days = [];
    for (let i = 0; i < 7; i++) {
        days.push(weekStart.value.add(i, 'day'));
    }
    return days;
});

const weekHeaderTitle = computed(() => {
    const start = weekStart.value;
    const end = weekStart.value.add(6, 'day');

    // Day-first tokens so locales like pt-BR stay "3–9 de agosto", not "August 3–9".
    if (start.isSame(end, 'month')) {
        return `${start.format('D')}–${end.format('D MMMM YYYY')}`;
    }

    if (start.isSame(end, 'year')) {
        return `${start.format('D MMM')} – ${end.format('D MMMM YYYY')}`;
    }

    return `${start.format('ll')} – ${end.format('ll')}`;
});

// Month view computed
const monthDate = computed(() => localized(props.currentMonth));

const monthHeaderTitle = computed(() => monthDate.value.format('MMMM YYYY'));

const calendarDays = computed(() => {
    const start = monthDate.value.startOf('month').startOf('week');
    const end = monthDate.value.endOf('month').endOf('week');
    const days = [];
    let current = start;

    while (current.isBefore(end) || current.isSame(end, 'day')) {
        days.push(current);
        current = current.add(1, 'day');
    }

    return days;
});

const calendarWeeks = computed(() => {
    const weeks = [];
    for (let i = 0; i < calendarDays.value.length; i += 7) {
        weeks.push(calendarDays.value.slice(i, i + 7));
    }
    return weeks;
});

// Header title based on view
const headerTitle = computed(() => {
    if (effectiveView.value === 'day') return dayHeaderTitle.value;
    if (effectiveView.value === 'month') return monthHeaderTitle.value;
    return weekHeaderTitle.value;
});

const getPostsForDay = (day: dayjs.Dayjs): Post[] => {
    const dateKey = day.format('YYYY-MM-DD');
    return props.posts[dateKey] || [];
};

type CalendarQuery = Record<string, string | number>;

/** Keeps the Social account filter on every calendar link. */
const withAccount = (
    query: CalendarQuery,
    account: string | null = props.filters.account,
): CalendarQuery => (account ? { ...query, account } : query);

/** The period on screen now, so changing the filter stays on it. */
const currentPeriod = (): CalendarQuery => {
    if (effectiveView.value === 'day') {
        return { view: 'day', day: props.currentDay };
    }

    if (effectiveView.value === 'month') {
        return { view: 'month', month: props.currentMonth };
    }

    return { view: 'week', week: props.currentWeekStart };
};

const filterByAccount = (account: string | null) => {
    router.get(
        calendar.url({ query: withAccount(currentPeriod(), account) }),
        {},
        {
            preserveState: true,
        },
    );
};

const navigateDay = (direction: number) => {
    const newDay = currentDay.value.add(direction, 'day');
    router.get(
        calendar.url({
            query: withAccount({
                view: 'day',
                day: newDay.format('YYYY-MM-DD'),
            }),
        }),
        {},
        {
            preserveState: true,
        },
    );
};

const navigateWeek = (direction: number) => {
    const newStart = weekStart.value.add(direction * 7, 'day');
    router.get(
        calendar.url({
            query: withAccount({
                view: 'week',
                week: newStart.format('YYYY-MM-DD'),
            }),
        }),
        {},
        {
            preserveState: true,
        },
    );
};

const navigateMonth = (direction: number) => {
    const newMonth = monthDate.value.add(direction, 'month');
    router.get(
        calendar.url({
            query: withAccount({
                view: 'month',
                month: newMonth.format('YYYY-MM-DD'),
            }),
        }),
        {},
        {
            preserveState: true,
        },
    );
};

const navigate = (direction: number) => {
    if (effectiveView.value === 'day') {
        navigateDay(direction);
    } else if (effectiveView.value === 'month') {
        navigateMonth(direction);
    } else {
        navigateWeek(direction);
    }
};

const goToToday = () => {
    router.get(
        calendar.url({ query: withAccount({ view: effectiveView.value }) }),
        {},
        {
            preserveState: true,
        },
    );
};

const goToDate = (dateStr: string) => {
    if (!dateStr) return;
    router.get(
        calendar.url({
            query: withAccount({ view: 'day', day: dateStr }),
        }),
        {},
        {
            preserveState: true,
        },
    );
};

const switchView = (view: string | number) => {
    router.get(
        calendar.url({ query: withAccount({ view }) }),
        {},
        {
            preserveState: true,
        },
    );
};

const isToday = (day: dayjs.Dayjs): boolean => {
    return day.isSame(dayjs(), 'day');
};

const isCurrentMonth = (day: dayjs.Dayjs): boolean => {
    return day.month() === monthDate.value.month();
};

const getStatusColor = (status: string): string => {
    const colors: Record<string, string> = {
        draft: 'bg-card text-foreground',
        scheduled: 'bg-primary/20 text-foreground',
        publishing: 'bg-amber-100 text-foreground',
        published: 'bg-emerald-100 text-foreground',
        partially_published: 'bg-amber-100 text-foreground',
        failed: 'bg-rose-100 text-foreground',
    };
    return colors[status] ?? colors.draft;
};

const EDITABLE_STATUSES: readonly string[] = [
    PostStatus.Draft,
    PostStatus.Scheduled,
];

const getPostUrl = (post: Post): string => {
    return EDITABLE_STATUSES.includes(post.status)
        ? editPost.url(post.id)
        : showPost.url(post.id);
};

const formatTime = (scheduledAt: string): string => {
    return date.formatTime(scheduledAt) || '';
};
</script>

<template>
    <Head :title="$t('calendar.title')" />

    <AppLayout :fullWidth="true">
        <div class="flex h-full flex-col">
            <!-- Mobile header: nav on top, date jump and New post below -->
            <header
                class="flex shrink-0 flex-col gap-2 border-b border-border bg-card px-4 py-3 lg:hidden"
            >
                <div class="flex items-center gap-2 pl-12">
                    <Button
                        variant="outline"
                        size="icon"
                        class="shrink-0"
                        @click="navigate(-1)"
                    >
                        <IconChevronLeft class="size-4" />
                    </Button>
                    <Button variant="outline" @click="goToToday">
                        {{ $t('calendar.today') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="shrink-0"
                        @click="navigate(1)"
                    >
                        <IconChevronRight class="size-4" />
                    </Button>
                </div>
                <div class="flex items-center gap-2">
                    <div class="min-w-0 flex-1">
                        <DatePicker
                            v-model="selectedDate"
                            :show-time="false"
                            @update:model-value="(v: any) => goToDate(v)"
                        />
                    </div>
                    <Button
                        class="shrink-0"
                        :disabled="creatingPost"
                        @click="createPost()"
                        >{{ $t('calendar.new_post') }}</Button
                    >
                </div>
                <CalendarAccountFilter
                    :accounts="socialAccounts"
                    :model-value="filters.account"
                    test-id="calendar-account-filter-mobile"
                    @update:model-value="filterByAccount"
                />
            </header>

            <!-- Desktop header: nav · title · view switcher + new post -->
            <header
                class="hidden shrink-0 grid-cols-[auto_1fr_auto] items-center gap-3 border-b border-border bg-card px-6 py-3 lg:grid"
            >
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="icon" @click="navigate(-1)">
                        <IconChevronLeft class="size-4" />
                    </Button>
                    <Button variant="outline" @click="goToToday">
                        {{ $t('calendar.today') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        data-testid="calendar-next"
                        @click="navigate(1)"
                    >
                        <IconChevronRight class="size-4" />
                    </Button>
                </div>
                <div class="flex items-center justify-center">
                    <span
                        class="truncate text-sm font-bold text-foreground capitalize"
                    >
                        {{ headerTitle }}
                    </span>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <CalendarAccountFilter
                        :accounts="socialAccounts"
                        :model-value="filters.account"
                        @update:model-value="filterByAccount"
                    />
                    <Tabs
                        :default-value="view"
                        @update:model-value="switchView"
                    >
                        <TabsList>
                            <TabsTrigger value="day">{{
                                $t('calendar.day')
                            }}</TabsTrigger>
                            <TabsTrigger value="week">{{
                                $t('calendar.week')
                            }}</TabsTrigger>
                            <TabsTrigger value="month">{{
                                $t('calendar.month')
                            }}</TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <Button :disabled="creatingPost" @click="createPost()">{{
                        $t('calendar.new_post')
                    }}</Button>
                </div>
            </header>

            <!-- Day View (mobile or when view=day) -->
            <div v-if="effectiveView === 'day'" class="flex-1 overflow-y-auto">
                <!-- Mobile Header Title -->
                <div class="border-b border-border bg-card px-4 py-3 lg:hidden">
                    <h2
                        class="text-center font-display text-base font-semibold text-foreground capitalize"
                    >
                        {{ dayHeaderTitle }}
                    </h2>
                </div>

                <div class="space-y-3 p-4">
                    <!-- Posts List -->
                    <div v-if="dayPosts.length > 0" class="space-y-3">
                        <Link
                            v-for="post in dayPosts"
                            :key="post.id"
                            :href="getPostUrl(post)"
                            class="block"
                        >
                            <div
                                class="rounded-none border border-border p-4 shadow-2xs transition-all hover:shadow-sm"
                                :class="getStatusColor(post.status)"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0 flex-1">
                                        <!-- Time -->
                                        <div class="mb-2 text-sm font-bold">
                                            {{ formatTime(post.scheduled_at) }}
                                        </div>

                                        <!-- Social accounts -->
                                        <CalendarPostAccounts
                                            :post-platforms="
                                                post.post_platforms
                                            "
                                            :limit="5"
                                            show-handle
                                            class="mb-2 text-sm"
                                        />

                                        <!-- Content Preview -->
                                        <p
                                            class="line-clamp-2 text-sm font-medium text-foreground/80"
                                        >
                                            {{
                                                post.content?.trim() ||
                                                $t('calendar.no_content')
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="py-12 text-center text-foreground/60">
                        <p class="font-medium">
                            {{ $t('calendar.no_content') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Week View -->
            <div
                v-else-if="effectiveView === 'week'"
                class="grid flex-1 grid-cols-7 divide-x divide-border overflow-hidden"
            >
                <div
                    v-for="day in weekDays"
                    :key="day.format('YYYY-MM-DD')"
                    class="flex min-h-0 flex-col"
                    :class="{ 'bg-primary/10': isToday(day) }"
                >
                    <!-- Day Header -->
                    <div
                        class="flex flex-col items-center border-b border-border bg-card py-3"
                    >
                        <span
                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            {{ day.format('dddd') }}
                        </span>
                        <span
                            class="mt-1 text-sm font-bold capitalize"
                            :class="
                                isToday(day)
                                    ? 'text-foreground'
                                    : 'text-foreground/80'
                            "
                        >
                            {{ formatDayMonth(day) }}
                        </span>
                    </div>

                    <!-- Day Content -->
                    <div class="flex-1 space-y-2 overflow-y-auto p-2">
                        <!-- Add Post Button -->
                        <button
                            type="button"
                            :disabled="creatingPost"
                            class="flex w-full cursor-pointer items-center justify-center rounded-md border border-dashed border-foreground/25 p-2 text-foreground/60 transition-colors hover:border-primary/40 hover:bg-foreground/5 hover:text-foreground"
                            @click="createPost(day.format('YYYY-MM-DD'))"
                        >
                            <IconPlus class="size-4" />
                        </button>

                        <!-- Posts -->
                        <Link
                            v-for="post in getPostsForDay(day)"
                            :key="post.id"
                            :href="getPostUrl(post)"
                            class="block"
                        >
                            <div
                                class="rounded-lg border border-border p-2 text-sm shadow-2xs transition-all hover:shadow-sm"
                                :class="getStatusColor(post.status)"
                            >
                                <!-- Time -->
                                <div class="mb-1 text-xs font-bold">
                                    {{ formatTime(post.scheduled_at) }}
                                </div>

                                <!-- Social accounts -->
                                <CalendarPostAccounts
                                    :post-platforms="post.post_platforms"
                                    :limit="4"
                                    class="mb-1.5 text-xs"
                                />

                                <!-- Content Preview -->
                                <p
                                    class="line-clamp-2 text-xs font-medium text-foreground/80"
                                >
                                    {{
                                        post.content?.trim() ||
                                        $t('calendar.no_content')
                                    }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Month View -->
            <div v-else class="flex flex-1 flex-col">
                <!-- Weekday Headers -->
                <div
                    class="grid grid-cols-7 divide-x divide-border border-b border-border bg-card"
                >
                    <div
                        v-for="day in weekdayNames"
                        :key="day"
                        class="py-3 text-center text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                    >
                        {{ day }}
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div
                    class="grid flex-1 divide-y divide-border"
                    :style="{
                        gridTemplateRows: `repeat(${calendarWeeks.length}, minmax(0, 1fr))`,
                    }"
                >
                    <div
                        v-for="(week, weekIndex) in calendarWeeks"
                        :key="weekIndex"
                        class="grid min-h-0 grid-cols-7 divide-x divide-border"
                    >
                        <div
                            v-for="day in week"
                            :key="day.format('YYYY-MM-DD')"
                            class="group flex min-h-0 flex-col overflow-hidden p-2"
                            :class="{
                                'bg-primary/10': isToday(day),
                                'bg-foreground/[0.03]': !isCurrentMonth(day),
                            }"
                        >
                            <!-- Day Header -->
                            <div class="mb-2 flex items-center justify-between">
                                <span
                                    class="inline-flex size-7 items-center justify-center text-sm font-bold"
                                    :class="{
                                        'border border-border bg-foreground text-background shadow-2xs':
                                            isToday(day),
                                        'text-foreground/40':
                                            !isCurrentMonth(day),
                                        'text-foreground':
                                            isCurrentMonth(day) &&
                                            !isToday(day),
                                    }"
                                >
                                    {{ day.format('D') }}
                                </span>
                                <button
                                    type="button"
                                    :disabled="creatingPost"
                                    class="inline-flex size-6 cursor-pointer items-center justify-center rounded-none border border-border bg-card text-foreground opacity-0 transition-all group-hover:opacity-100 hover:bg-muted focus:opacity-100"
                                    @click="
                                        createPost(day.format('YYYY-MM-DD'))
                                    "
                                >
                                    <IconPlus
                                        class="size-3.5"
                                        stroke-width="3"
                                    />
                                </button>
                            </div>

                            <!-- Posts -->
                            <div
                                class="min-h-0 flex-1 space-y-1 overflow-y-auto px-1"
                            >
                                <Link
                                    v-for="post in getPostsForDay(day).slice(
                                        0,
                                        3,
                                    )"
                                    :key="post.id"
                                    :href="getPostUrl(post)"
                                    class="block"
                                >
                                    <div
                                        class="flex flex-col gap-1 rounded-md border border-border px-2 py-1 text-xs shadow-2xs transition-all hover:shadow-sm"
                                        :class="getStatusColor(post.status)"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-1.5"
                                        >
                                            <span class="shrink-0 font-bold">{{
                                                formatTime(post.scheduled_at)
                                            }}</span>
                                            <div
                                                class="flex shrink-0 -space-x-1"
                                            >
                                                <TooltipProvider
                                                    v-for="pp in post.post_platforms.slice(
                                                        0,
                                                        post.post_platforms
                                                            .length > 4
                                                            ? 3
                                                            : 4,
                                                    )"
                                                    :key="pp.id"
                                                    :delay-duration="200"
                                                >
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            as-child
                                                        >
                                                            <span
                                                                class="inline-flex size-4 items-center justify-center overflow-hidden rounded-full border border-border bg-card"
                                                            >
                                                                <img
                                                                    :src="
                                                                        getPlatformLogo(
                                                                            pp.platform,
                                                                        )
                                                                    "
                                                                    :alt="
                                                                        pp.platform
                                                                    "
                                                                    class="size-full object-cover"
                                                                />
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <div
                                                                class="space-y-0.5 text-xs"
                                                            >
                                                                <p
                                                                    class="font-semibold"
                                                                >
                                                                    {{
                                                                        pp
                                                                            .social_account
                                                                            ?.display_label ??
                                                                        pp.platform
                                                                    }}<span
                                                                        v-if="
                                                                            pp
                                                                                .social_account
                                                                                ?.username
                                                                        "
                                                                        class="font-normal opacity-80"
                                                                        >&nbsp;·&nbsp;@{{
                                                                            pp
                                                                                .social_account
                                                                                .username
                                                                        }}</span
                                                                    >
                                                                </p>
                                                                <p
                                                                    class="opacity-70"
                                                                >
                                                                    {{
                                                                        getPlatformLabel(
                                                                            pp.platform,
                                                                        )
                                                                    }}
                                                                </p>
                                                            </div>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                                <span
                                                    v-if="
                                                        post.post_platforms
                                                            .length > 4
                                                    "
                                                    class="inline-flex size-4 items-center justify-center rounded-full border border-border bg-card text-[9px] font-bold"
                                                >
                                                    +{{
                                                        post.post_platforms
                                                            .length - 3
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                        <p
                                            class="line-clamp-1 font-medium text-foreground/80"
                                        >
                                            {{
                                                post.content?.trim() ||
                                                $t('calendar.no_content')
                                            }}
                                        </p>
                                    </div>
                                </Link>
                                <div
                                    v-if="getPostsForDay(day).length > 3"
                                    class="px-2 py-0.5 text-xs font-medium text-foreground/60"
                                >
                                    {{
                                        $t('calendar.more', {
                                            count: String(
                                                getPostsForDay(day).length - 3,
                                            ),
                                        })
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
