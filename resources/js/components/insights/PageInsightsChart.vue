<script setup lang="ts">
import { CurveType } from '@unovis/ts';
import {
    VisAxis,
    VisCrosshair,
    VisLine,
    VisTooltip,
    VisXYContainer,
} from '@unovis/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import date from '@/date';
import { formatCount } from '@/lib/utils';
import type { PageInsightsDay, PageMetricKey } from '@/types/insights';

const props = defineProps<{
    days: PageInsightsDay[];
}>();

const CHARTED_METRICS: PageMetricKey[] = [
    'views',
    'reach',
    'engagements',
    'video_views',
    'new_follows',
];

const metric = ref<PageMetricKey>('views');

const points = computed(() =>
    props.days.map((day, index) => ({
        index,
        date: day.date,
        value: day[metric.value],
    })),
);

type Point = (typeof points.value)[number];

const x = (point: Point) => point.index;
const y = (point: Point) => point.value ?? undefined;

const dayLabel = (index: number) => {
    const day = props.days[Math.round(index)];

    return day ? date.formatMonthDay(day.date) : '';
};

const tooltip = (point: Point) =>
    `${dayLabel(point.index)}: ${
        point.value === null
            ? trans('insights.no_value')
            : formatCount(point.value)
    }`;

const selectMetric = (value: string | number) => {
    metric.value = value as PageMetricKey;
};
</script>

<template>
    <div
        class="flex flex-col gap-4 rounded-xl border-2 border-foreground bg-card p-4 shadow-2xs"
        data-testid="insights-chart"
    >
        <Tabs :model-value="metric" @update:model-value="selectMetric">
            <TabsList class="flex-wrap">
                <TabsTrigger
                    v-for="option in CHARTED_METRICS"
                    :key="option"
                    :value="option"
                    :data-testid="`insights-chart-metric-${option}`"
                >
                    {{ $t(`insights.metrics.${option}`) }}
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <div
            role="img"
            :aria-label="
                $t('insights.chart_label', {
                    metric: $t(`insights.metrics.${metric}`),
                })
            "
            class="text-primary"
        >
            <VisXYContainer
                :data="points"
                :height="260"
                :margin="{ top: 8, right: 8 }"
            >
                <VisLine
                    :x="x"
                    :y="y"
                    :curve-type="CurveType.Linear"
                    color="var(--chart-1)"
                />
                <VisAxis
                    type="x"
                    :tick-format="dayLabel"
                    :num-ticks="6"
                    :grid-line="false"
                />
                <VisAxis type="y" :tick-format="formatCount" :num-ticks="4" />
                <VisCrosshair :template="tooltip" color="var(--chart-1)" />
                <VisTooltip />
            </VisXYContainer>
        </div>
    </div>
</template>
