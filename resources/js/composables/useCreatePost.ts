import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import { create as createPostPage } from '@/routes/app/posts';

/**
 * "New post" opens the composer without saving anything (ADR 0011). When a
 * calendar day (YYYY-MM-DD) is given, the composer starts on that day.
 */
export const useCreatePost = () => {
    const creatingPost = ref(false);

    const createPost = (date: string | null = null): void => {
        if (creatingPost.value) {
            return;
        }

        creatingPost.value = true;

        router.visit(createPostPage.url(date ? { query: { date } } : {}), {
            onFinish: () => {
                creatingPost.value = false;
            },
        });
    };

    return { createPost, creatingPost };
};
