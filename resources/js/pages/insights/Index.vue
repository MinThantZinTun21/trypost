<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { IconAlertTriangle, IconChartLine } from '@tabler/icons-vue';
import { computed } from 'vue';

import { index as insightsIndex } from '@/actions/App/Http/Controllers/App/InsightsController';
import EmptyState from '@/components/EmptyState.vue';
import PageMetricCard from '@/components/insights/PageMetricCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import { accounts as accountsPage } from '@/routes/app';
import type {
    InsightsAccount,
    InsightsAccountOption,
    PageInsightsSummary,
} from '@/types/insights';

const props = defineProps<{
    accounts: InsightsAccountOption[];
    account: InsightsAccount | null;
    range: number;
    ranges: number[];
    summary: PageInsightsSummary | null;
}>();

const insightsUrl = (accountId: string | undefined, range: number) =>
    insightsIndex.url({ query: { account: accountId, range } });

const selectAccount = (accountId: unknown) => {
    if (typeof accountId === 'string') {
        router.visit(insightsUrl(accountId, props.range));
    }
};

const period = computed(() =>
    props.summary
        ? {
              from: date.formatDateOnly(props.summary.from),
              to: date.formatDateOnly(props.summary.to),
          }
        : null,
);
</script>

<template>
    <Head :title="$t('insights.title')" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <PageHeader
                :title="$t('insights.title')"
                :description="$t('insights.description')"
            />

            <EmptyState
                v-if="account === null"
                :icon="IconChartLine"
                :title="$t('insights.empty.title')"
                :description="$t('insights.empty.description')"
                data-testid="insights-empty"
            >
                <template #action>
                    <Button as-child>
                        <Link :href="accountsPage.url()">
                            {{ $t('insights.empty.action') }}
                        </Link>
                    </Button>
                </template>
            </EmptyState>

            <template v-else>
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Select
                        v-if="accounts.length > 1"
                        :model-value="account.id"
                        @update:model-value="selectAccount"
                    >
                        <SelectTrigger
                            class="w-full sm:w-64"
                            :aria-label="$t('insights.page_label')"
                            data-testid="insights-account"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in accounts"
                                :key="option.id"
                                :value="option.id"
                            >
                                {{ option.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-else
                        class="font-semibold text-foreground"
                        data-testid="insights-account-name"
                    >
                        {{ account.name }}
                    </p>

                    <Tabs :model-value="`${range}`">
                        <TabsList>
                            <TabsTrigger
                                v-for="option in ranges"
                                :key="option"
                                :value="`${option}`"
                                as-child
                            >
                                <Link
                                    :href="insightsUrl(account.id, option)"
                                    :data-testid="`insights-range-${option}`"
                                >
                                    {{
                                        $t('insights.range_days', {
                                            days: `${option}`,
                                        })
                                    }}
                                </Link>
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <div
                    class="flex flex-col gap-1 text-sm text-foreground/60 sm:flex-row sm:justify-between"
                >
                    <span v-if="period" data-testid="insights-period">
                        {{ $t('insights.period', period) }}
                    </span>
                    <span data-testid="insights-read-at">
                        {{
                            account.read_at
                                ? $t('insights.read_at', {
                                      time: date.diffForHumans(account.read_at),
                                  })
                                : $t('insights.never_read')
                        }}
                    </span>
                </div>

                <Alert
                    v-if="account.error"
                    variant="destructive"
                    data-testid="insights-error"
                >
                    <IconAlertTriangle class="size-4" />
                    <AlertTitle>{{ $t('insights.error.title') }}</AlertTitle>
                    <AlertDescription>
                        {{
                            $t('insights.error.description', {
                                message: account.error,
                            })
                        }}
                    </AlertDescription>
                </Alert>

                <EmptyState
                    v-if="!account.has_snapshots"
                    :icon="IconChartLine"
                    :title="$t('insights.not_read.title')"
                    :description="$t('insights.not_read.description')"
                    data-testid="insights-not-read"
                />

                <div
                    v-else-if="summary"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    data-testid="insights-metrics"
                >
                    <PageMetricCard
                        v-for="metric in summary.metrics"
                        :key="metric.key"
                        :metric="metric"
                        :days="summary.range"
                    />
                </div>
            </template>
        </div>
    </AppLayout>
</template>
