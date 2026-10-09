<script setup lang="ts">
import { computed } from 'vue';

import date from '@/date';
import { formatCount } from '@/lib/utils';
import type { PostInsights } from '@/types/insights';

const props = defineProps<{
    insights: PostInsights | null;
}>();

type PostMetricKey = Exclude<keyof PostInsights, 'read_at'>;

const DURATION_METRICS: PostMetricKey[] = [
    'avg_watch_time_ms',
    'watch_time_ms',
];

const METRICS: PostMetricKey[] = [
    'views',
    'reach',
    'reactions',
    'clicks',
    'video_views',
    'reel_plays',
    'avg_watch_time_ms',
    'watch_time_ms',
];

const formatDuration = (milliseconds: number): string => {
    const seconds = Math.round(milliseconds / 1000);

    if (seconds < 60) {
        return `${seconds}s`;
    }

    const minutes = Math.floor(seconds / 60);

    return minutes < 60
        ? `${minutes}m ${seconds % 60}s`
        : `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
};

const shownMetrics = computed(() =>
    METRICS.filter((key) => props.insights?.[key] != null).map((key) => {
        const value = props.insights?.[key] as number;

        return {
            key,
            value: DURATION_METRICS.includes(key)
                ? formatDuration(value)
                : formatCount(value),
        };
    }),
);
</script>

<template>
    <div
        class="border-t-2 border-foreground/10 px-4 py-3"
        data-testid="post-insights"
    >
        <p
            v-if="insights === null || insights.read_at === null"
            class="text-xs font-medium text-foreground/60"
            data-testid="post-insights-not-read"
        >
            {{ $t('insights.post.not_read') }}
        </p>
        <template v-else>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-4">
                <div
                    v-for="metric in shownMetrics"
                    :key="metric.key"
                    :data-testid="`post-insights-${metric.key}`"
                >
                    <dt class="text-[11px] font-semibold text-foreground/60">
                        {{ $t(`insights.post.metrics.${metric.key}`) }}
                    </dt>
                    <dd class="text-sm font-bold text-foreground tabular-nums">
                        {{ metric.value }}
                    </dd>
                </div>
            </dl>
            <p class="mt-2 text-[11px] text-foreground/50">
                {{
                    $t('insights.post.read_at', {
                        time: date.diffForHumans(insights.read_at),
                    })
                }}
            </p>
        </template>
    </div>
</template>
