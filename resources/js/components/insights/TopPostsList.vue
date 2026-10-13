<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';

import { show as showPost } from '@/actions/App/Http/Controllers/App/PostController';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import date from '@/date';
import { formatCount } from '@/lib/utils';
import type { TopPost } from '@/types/insights';

defineProps<{
    posts: TopPost[];
}>();

const views = (count: number | null): string =>
    transChoice('insights.top_posts.views', count ?? 0, {
        count: formatCount(count ?? 0),
    });

const excerpt = (post: TopPost): string =>
    post.content.trim() || trans('insights.top_posts.untitled');
</script>

<template>
    <Card class="gap-3 py-4" data-testid="insights-top-posts">
        <CardHeader class="gap-0 px-4">
            <CardTitle class="leading-normal text-foreground">
                {{ $t('insights.top_posts.title') }}
            </CardTitle>
            <CardDescription class="text-foreground/60">
                {{ $t('insights.top_posts.description') }}
            </CardDescription>
        </CardHeader>

        <CardContent class="px-4">
            <p
                v-if="posts.length === 0"
                class="text-sm text-foreground/60"
                data-testid="insights-top-posts-empty"
            >
                {{ $t('insights.top_posts.empty') }}
            </p>

            <ol v-else class="divide-y-2 divide-foreground/10">
                <li v-for="post in posts" :key="post.post_id">
                    <Link
                        :href="showPost.url(post.post_id)"
                        class="flex items-center justify-between gap-4 py-3 hover:text-primary"
                        :data-testid="`insights-top-post-${post.post_id}`"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">
                                {{ excerpt(post) }}
                            </span>
                            <span class="text-xs text-foreground/60">
                                {{ date.formatDate(post.published_at) }}
                            </span>
                        </span>
                        <span class="shrink-0 text-sm font-bold tabular-nums">
                            {{ views(post.insights.views) }}
                        </span>
                    </Link>
                </li>
            </ol>
        </CardContent>
    </Card>
</template>
