import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import { store as storePost } from '@/routes/app/posts';

/**
 * "New post" creates a Draft and lands on the editor. When a calendar day
 * (YYYY-MM-DD) is given, the Draft is pre-scheduled on that day.
 */
export const useCreatePost = () => {
    const creatingPost = ref(false);

    const createPost = (date: string | null = null): void => {
        if (creatingPost.value) {
            return;
        }

        creatingPost.value = true;

        router.post(storePost.url(), date ? { date } : {}, {
            onFinish: () => {
                creatingPost.value = false;
            },
        });
    };

    return { createPost, creatingPost };
};
