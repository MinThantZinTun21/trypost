<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { formatCount } from '@/lib/utils';
import type { PageMetricTotal } from '@/types/insights';

const props = defineProps<{
    metric: PageMetricTotal;
    days: number;
}>();

const HINTED_METRICS = ['followers', 'reach'];

const comparison = computed(() => {
    const { current, previous } = props.metric;
    const days = props.days;

    if (current === null || previous === null) {
        return {
            text: trans('insights.comparison.none', { days: `${days}` }),
            tone: 'muted',
        };
    }

    if (previous === 0) {
        return current === 0
            ? {
                  text: trans('insights.comparison.same', { days: `${days}` }),
                  tone: 'muted',
              }
            : {
                  text: trans('insights.comparison.new', { days: `${days}` }),
                  tone: 'muted',
              };
    }

    const percent = Math.round(((current - previous) / previous) * 100);

    if (percent === 0) {
        return {
            text: trans('insights.comparison.same', { days: `${days}` }),
            tone: 'muted',
        };
    }

    const better = props.metric.key === 'unfollows' ? percent < 0 : percent > 0;

    return {
        text: trans(
            percent > 0 ? 'insights.comparison.up' : 'insights.comparison.down',
            {
                percent: `${Math.abs(percent)}`,
                days: `${days}`,
            },
        ),
        tone: better ? 'good' : 'bad',
    };
});
</script>

<template>
    <div
        class="flex flex-col gap-2 rounded-xl border-2 border-foreground bg-card p-4 shadow-2xs"
        :data-testid="`insights-metric-${metric.key}`"
    >
        <p class="text-sm font-medium text-foreground/70">
            {{ $t(`insights.metrics.${metric.key}`) }}
        </p>
        <p
            class="text-3xl text-foreground tabular-nums"
            style="font-family: var(--font-display)"
            :data-testid="`insights-metric-${metric.key}-value`"
        >
            {{
                metric.current === null
                    ? $t('insights.no_value')
                    : formatCount(metric.current)
            }}
        </p>
        <p
            class="text-xs"
            :class="{
                'text-foreground/60': comparison.tone === 'muted',
                'text-emerald-700 dark:text-emerald-400':
                    comparison.tone === 'good',
                'text-red-700 dark:text-red-400': comparison.tone === 'bad',
            }"
        >
            {{ comparison.text }}
        </p>
        <p
            v-if="HINTED_METRICS.includes(metric.key)"
            class="text-xs text-foreground/50"
        >
            {{ $t(`insights.hints.${metric.key}`) }}
        </p>
    </div>
</template>
