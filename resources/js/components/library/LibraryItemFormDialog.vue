<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    IconBrandFacebook,
    IconBrandTiktok,
    IconBrandYoutube,
    IconLoader2,
    IconPhotoPlus,
    IconX,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import {
    store as storeItem,
    update as updateItem,
} from '@/actions/App/Http/Controllers/App/LibraryItemController';
import InputError from '@/components/InputError.vue';
import LibraryMediaThumbnail from '@/components/library/LibraryMediaThumbnail.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useMediaUpload } from '@/composables/useMediaUpload';
import { errorForField } from '@/lib/formErrors';
import { LIBRARY_ITEM_MEDIA_MAX } from '@/lib/libraryItem';
import { ALLOWED_MIME_TYPES, MediaType } from '@/lib/mediaType';
import {
    parseYouTubeTags,
    YOUTUBE_TAGS_MAX_CHARACTERS,
    youtubeTagsCharacters,
} from '@/lib/youtubeTags';
import type { LibraryItem } from '@/types/library';
import type { MediaItem } from '@/types/media';

const props = defineProps<{
    item?: LibraryItem | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const media = ref<MediaItem[]>([]);
const tagsText = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const { uploading, uploadProgress, uploadFiles } = useMediaUpload(media);

const form = useForm({
    name: '',
    facebook_text: '',
    tiktok_caption: '',
    youtube_title: '',
    youtube_description: '',
});

const accept = [
    ...ALLOWED_MIME_TYPES[MediaType.Image],
    ...ALLOWED_MIME_TYPES[MediaType.Video],
].join(',');

const tags = computed(() => parseYouTubeTags(tagsText.value));
const tagsCharacters = computed(() => youtubeTagsCharacters(tags.value));

const errorFor = (field: string): string | undefined =>
    errorForField(form.errors as Record<string, string>, field);

watch(open, (isOpen) => {
    if (!isOpen) return;

    form.clearErrors();
    form.defaults({
        name: props.item?.name ?? '',
        facebook_text: props.item?.facebook_text ?? '',
        tiktok_caption: props.item?.tiktok_caption ?? '',
        youtube_title: props.item?.youtube_title ?? '',
        youtube_description: props.item?.youtube_description ?? '',
    });
    form.reset();
    media.value = [...(props.item?.media ?? [])];
    tagsText.value = (props.item?.youtube_tags ?? []).join(', ');
});

const onFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        void uploadFiles(Array.from(target.files));
        target.value = '';
    }
};

const removeMedia = (mediaId: string) => {
    media.value = media.value.filter((item) => item.id !== mediaId);
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    form.transform((data) => ({
        ...data,
        media_ids: media.value.map((item) => item.id),
        youtube_tags: tags.value,
    }));

    if (props.item) {
        form.put(updateItem.url(props.item.id), options);
    } else {
        form.post(storeItem.url(), {
            ...options,
            only: ['items'],
            reset: ['items'],
        });
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <form
                class="space-y-6"
                data-testid="library-form"
                @submit.prevent="submit"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            item
                                ? $t('library.form.edit_title')
                                : $t('library.form.create_title')
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ $t('library.description') }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="library_name">{{
                        $t('library.form.name')
                    }}</Label>
                    <Input
                        id="library_name"
                        v-model="form.name"
                        :placeholder="trans('library.form.name_placeholder')"
                        data-testid="library-name-input"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ $t('library.form.media') }}</Label>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                        <div
                            v-for="file in media"
                            :key="file.id"
                            class="group relative aspect-square overflow-hidden rounded-xl border border-border bg-muted"
                            :data-testid="`library-media-${file.id}`"
                        >
                            <LibraryMediaThumbnail :media="file" />
                            <button
                                type="button"
                                class="absolute top-1 right-1 inline-flex size-6 cursor-pointer items-center justify-center rounded-full bg-black/60 text-white transition-colors hover:bg-black/80"
                                :aria-label="trans('library.form.remove_media')"
                                :data-testid="`library-media-remove-${file.id}`"
                                @click="removeMedia(file.id)"
                            >
                                <IconX class="size-3.5" />
                            </button>
                        </div>
                        <button
                            v-if="media.length < LIBRARY_ITEM_MEDIA_MAX"
                            type="button"
                            data-testid="library-media-upload"
                            :disabled="uploading"
                            class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-foreground/25 text-foreground/60 transition-colors hover:border-primary hover:bg-primary/5 hover:text-primary disabled:cursor-wait"
                            @click="fileInput?.click()"
                        >
                            <template v-if="uploading">
                                <IconLoader2 class="size-5 animate-spin" />
                                <span class="text-[10px] font-bold tabular-nums"
                                    >{{ uploadProgress }}%</span
                                >
                            </template>
                            <template v-else>
                                <IconPhotoPlus class="size-5" />
                                <span class="text-[10px] font-bold uppercase">{{
                                    $t('library.form.upload')
                                }}</span>
                            </template>
                        </button>
                        <input
                            ref="fileInput"
                            type="file"
                            multiple
                            class="hidden"
                            data-testid="library-media-input"
                            :accept="accept"
                            @change="onFileSelect"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t('library.form.media_hint', {
                                max: String(LIBRARY_ITEM_MEDIA_MAX),
                            })
                        }}
                    </p>
                    <InputError :message="errorFor('media_ids')" />
                </div>

                <section class="grid gap-2 rounded-xl border border-border p-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold">
                        <IconBrandFacebook class="size-4 text-[#1877f2]" />
                        {{ $t('library.texts.facebook') }}
                    </h3>
                    <Label for="library_facebook_text" class="sr-only">{{
                        $t('library.form.facebook_text')
                    }}</Label>
                    <Textarea
                        id="library_facebook_text"
                        v-model="form.facebook_text"
                        class="min-h-24"
                        :placeholder="
                            trans('library.form.facebook_text_placeholder')
                        "
                        data-testid="library-facebook-text-input"
                    />
                    <InputError :message="form.errors.facebook_text" />
                </section>

                <section class="grid gap-2 rounded-xl border border-border p-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold">
                        <IconBrandTiktok class="size-4" />
                        {{ $t('library.texts.tiktok') }}
                    </h3>
                    <Label for="library_tiktok_caption" class="sr-only">{{
                        $t('library.form.tiktok_caption')
                    }}</Label>
                    <Textarea
                        id="library_tiktok_caption"
                        v-model="form.tiktok_caption"
                        class="min-h-20"
                        :placeholder="
                            trans('library.form.tiktok_caption_placeholder')
                        "
                        data-testid="library-tiktok-caption-input"
                    />
                    <InputError :message="form.errors.tiktok_caption" />
                </section>

                <section class="grid gap-3 rounded-xl border border-border p-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold">
                        <IconBrandYoutube class="size-4 text-[#ff0000]" />
                        {{ $t('library.texts.youtube') }}
                    </h3>
                    <div class="grid gap-2">
                        <Label for="library_youtube_title">{{
                            $t('library.form.youtube_title')
                        }}</Label>
                        <Input
                            id="library_youtube_title"
                            v-model="form.youtube_title"
                            :placeholder="
                                trans('library.form.youtube_title_placeholder')
                            "
                            data-testid="library-youtube-title-input"
                        />
                        <InputError :message="form.errors.youtube_title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="library_youtube_description">{{
                            $t('library.form.youtube_description')
                        }}</Label>
                        <Textarea
                            id="library_youtube_description"
                            v-model="form.youtube_description"
                            class="min-h-20"
                            :placeholder="
                                trans(
                                    'library.form.youtube_description_placeholder',
                                )
                            "
                            data-testid="library-youtube-description-input"
                        />
                        <InputError
                            :message="form.errors.youtube_description"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="library_youtube_tags">{{
                            $t('library.form.youtube_tags')
                        }}</Label>
                        <Input
                            id="library_youtube_tags"
                            v-model="tagsText"
                            :placeholder="
                                trans('library.form.youtube_tags_placeholder')
                            "
                            data-testid="library-youtube-tags-input"
                        />
                        <p
                            class="text-xs text-muted-foreground tabular-nums"
                            :class="{
                                'text-destructive':
                                    tagsCharacters >
                                    YOUTUBE_TAGS_MAX_CHARACTERS,
                            }"
                        >
                            {{
                                $t('posts.form.youtube.tags_characters', {
                                    used: String(tagsCharacters),
                                    limit: String(YOUTUBE_TAGS_MAX_CHARACTERS),
                                })
                            }}
                        </p>
                        <InputError :message="errorFor('youtube_tags')" />
                    </div>
                </section>

                <DialogFooter class="gap-2">
                    <Button
                        type="submit"
                        :disabled="form.processing || uploading"
                        data-testid="library-save"
                    >
                        {{ $t('library.form.save') }}
                    </Button>
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            {{ $t('library.form.cancel') }}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
