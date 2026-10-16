<script setup lang="ts">
import {
    IconAlertTriangle,
    IconBrandTelegram,
    IconChevronDown,
    IconChevronUp,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar } from '@/components/ui/avatar';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { usePageErrors } from '@/composables/usePageErrors';
import { getPlatformLogo } from '@/composables/usePlatformLogo';
import { tiktokViaTelegram } from '@/lib/tiktokDelivery';
import {
    TIKTOK_CAPTION_MAX,
    TIKTOK_PHOTO_DESCRIPTION_MAX,
    TIKTOK_PHOTO_TITLE_MAX,
    tiktokTextLength,
} from '@/lib/tiktokText';
import { toNullableText } from '@/lib/utils';
import { ContentType } from '@/types/content-type';
import {
    isTikTokPrivacyLevel,
    tiktokPrivacyLabelKey,
    TikTokPrivacyLevel,
    type TikTokPrivacyLevelValue,
} from '@/types/tiktok-privacy';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    display_label: string;
    avatar_url: string | null;
}

interface CreatorInfo {
    creator_nickname: string | null;
    creator_username: string | null;
    creator_avatar_url: string | null;
    privacy_level_options: TikTokPrivacyLevelValue[];
    comment_disabled: boolean;
    duet_disabled: boolean;
    stitch_disabled: boolean;
    max_video_post_duration_sec: number | null;
}

interface Props {
    socialAccount: SocialAccount | null;
    platformIndex: number;
    publishConfig: Record<string, any> | null;
    creatorInfo?: CreatorInfo | null;
    videoDurationSec?: number | null;
    contentType: string;
    contentTypeError?: string;
    meta: Record<string, any>;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    creatorInfo: null,
    videoDurationSec: null,
    contentTypeError: undefined,
    disabled: false,
});

const emit = defineEmits<{
    'update:meta': [value: Record<string, any>];
    'update:contentType': [value: string];
}>();

const variants = [
    {
        value: ContentType.TikTokVideo,
        labelKey: 'posts.form.tiktok.variant.video',
    },
    {
        value: ContentType.TikTokPhoto,
        labelKey: 'posts.form.tiktok.variant.photo',
    },
] as const;

const pickVariant = (value: string) => {
    if (props.disabled) return;
    emit('update:contentType', value);
};

const open = ref(false);

const viaTelegram = computed(() => tiktokViaTelegram());

const errors = usePageErrors();
const privacyError = computed<string | undefined>(() => {
    if (isTikTokPrivacyLevel(props.meta?.privacy_level)) {
        return undefined;
    }

    return Object.entries(errors.value).find(([key]) =>
        key.endsWith('.meta.privacy_level'),
    )?.[1];
});

const updateMeta = (patch: Record<string, any>) => {
    emit('update:meta', { ...props.meta, ...patch });
};

const metaText = (key: 'caption' | 'title' | 'description') =>
    computed({
        get: () => toNullableText(props.meta?.[key]) ?? '',
        set: (value: string) => updateMeta({ [key]: toNullableText(value) }),
    });
const caption = metaText('caption');
const photoTitle = metaText('title');
const photoDescription = metaText('description');
const fieldError = (key: string) =>
    computed(
        () => errors.value[`platforms.${props.platformIndex}.meta.${key}`],
    );
const captionError = fieldError('caption');
const photoTitleError = fieldError('title');
const photoDescriptionError = fieldError('description');
const fieldId = (key: string) => `tiktok-${key}-${props.platformIndex}`;

const privacyLevel = computed({
    get: () => props.meta?.privacy_level ?? '',
    set: (value: string) => updateMeta({ privacy_level: value }),
});

const autoAddMusic = computed({
    get: () => ((props.meta?.auto_add_music ?? false) ? 'yes' : 'no'),
    set: (value: string) => updateMeta({ auto_add_music: value === 'yes' }),
});

const allowComments = computed({
    get: () => props.meta?.allow_comments ?? false,
    set: (value: boolean) => updateMeta({ allow_comments: value }),
});

const allowDuet = computed({
    get: () => props.meta?.allow_duet ?? false,
    set: (value: boolean) => updateMeta({ allow_duet: value }),
});

const allowStitch = computed({
    get: () => props.meta?.allow_stitch ?? false,
    set: (value: boolean) => updateMeta({ allow_stitch: value }),
});

const isAigc = computed({
    get: () => props.meta?.is_aigc ?? false,
    set: (value: boolean) => updateMeta({ is_aigc: value }),
});

const discloseOpen = computed({
    get: () => props.meta?.disclose ?? false,
    set: (value: boolean) => {
        if (value) {
            updateMeta({ disclose: true });
            return;
        }
        // turning disclosure off clears its sub-toggles
        updateMeta({
            disclose: false,
            brand_organic_toggle: false,
            brand_content_toggle: false,
        });
    },
});

const brandOrganicToggle = computed({
    get: () => props.meta?.brand_organic_toggle ?? false,
    set: (value: boolean) => updateMeta({ brand_organic_toggle: value }),
});

const brandContentToggle = computed({
    get: () => props.meta?.brand_content_toggle ?? false,
    set: (value: boolean) => updateMeta({ brand_content_toggle: value }),
});

// Prefer the creator_info API response; fall back to the static list from the Platform enum.
// Every option is rendered. SelfOnly is shown but disabled when Branded Content is
// checked (TikTok UX Guideline Point 3b — must show interaction, not hide it).
const privacyOptions = computed<TikTokPrivacyLevelValue[]>(() => {
    const fromApi = (props.creatorInfo?.privacy_level_options ?? []).filter(
        isTikTokPrivacyLevel,
    );
    const fallback = (props.publishConfig?.privacyLevelOptions ?? []).filter(
        isTikTokPrivacyLevel,
    );

    return fromApi.length > 0 ? fromApi : fallback;
});

const isSelfOnlyDisabled = (option: TikTokPrivacyLevelValue): boolean =>
    option === TikTokPrivacyLevel.SelfOnly && brandContentToggle.value;

const commentDisabled = computed(() =>
    Boolean(props.creatorInfo?.comment_disabled),
);
const duetDisabled = computed(() => Boolean(props.creatorInfo?.duet_disabled));
const stitchDisabled = computed(() =>
    Boolean(props.creatorInfo?.stitch_disabled),
);

// Derive from the user's explicit variant choice (contentType), not from attached
// media. The variant selector is the single source of truth — user picks it, the
// gating responds immediately, and validation enforces media↔content_type matching.
const isPhotoPost = computed(
    () => props.contentType === ContentType.TikTokPhoto,
);
const isVideoPost = computed(
    () => props.contentType === ContentType.TikTokVideo,
);

// Max video duration check (when creator_info is available and we have duration).
const maxDurationSec = computed(
    () => props.creatorInfo?.max_video_post_duration_sec ?? null,
);
const exceedsMaxDuration = computed(() => {
    if (maxDurationSec.value === null || props.videoDurationSec === null)
        return false;
    return props.videoDurationSec > maxDurationSec.value;
});

const hasAnyBrandToggle = computed(
    () => brandOrganicToggle.value || brandContentToggle.value,
);

// Label required by TikTok: "Paid partnership" when branded content (with or without organic),
// otherwise "Promotional content".
const promotionalTitleKey = computed(() =>
    brandContentToggle.value
        ? 'posts.form.tiktok.promotional_paid_title'
        : 'posts.form.tiktok.promotional_organic_title',
);

// If user flips branded content ON while privacy is SELF_ONLY, clear it so they must re-pick.
// Surface a toast so the user understands why the field reset (TikTok UX Guideline Point 3b
// requires informing the user when an auto-switch happens).
watch(brandContentToggle, (value) => {
    if (value && privacyLevel.value === TikTokPrivacyLevel.SelfOnly) {
        privacyLevel.value = '';
        toast.warning(trans('posts.form.tiktok.branded_cleared_private'));
    }
});

// When creator_info reveals that an interaction is disabled for this account, force the meta
// flag off so we never submit a disallowed value.
watch(
    () =>
        [
            commentDisabled.value,
            duetDisabled.value,
            stitchDisabled.value,
        ] as const,
    ([comment, duet, stitch]) => {
        const patch: Record<string, any> = {};
        if (comment && allowComments.value) patch.allow_comments = false;
        if (duet && allowDuet.value) patch.allow_duet = false;
        if (stitch && allowStitch.value) patch.allow_stitch = false;
        if (Object.keys(patch).length > 0) updateMeta(patch);
    },
);
</script>

<template>
    <Card class="gap-0 py-0 shadow-2xs">
        <Collapsible v-model:open="open">
            <CollapsibleTrigger as-child>
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between gap-3 p-4 text-sm"
                    :data-testid="fieldId('settings-toggle')"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <span
                            class="inline-flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-card shadow-2xs"
                        >
                            <img
                                :src="getPlatformLogo('tiktok')"
                                alt="TikTok"
                                class="size-full object-cover"
                            />
                        </span>
                        <span class="truncate font-bold text-foreground">{{
                            $t('posts.form.tiktok.settings')
                        }}</span>
                        <span
                            v-if="socialAccount?.username"
                            class="truncate font-medium text-foreground/60"
                            >·&nbsp;@{{ socialAccount.username }}</span
                        >
                    </span>
                    <IconChevronUp
                        v-if="open"
                        class="size-4 shrink-0 text-foreground/60"
                    />
                    <IconChevronDown
                        v-else
                        class="size-4 shrink-0 text-foreground/60"
                    />
                </button>
            </CollapsibleTrigger>

            <CollapsibleContent>
                <CardContent
                    class="space-y-5 border-t border-border px-4 pt-4 pb-4"
                >
                    <!-- Variant: Video / Photo carousel -->
                    <Field class="gap-2">
                        <p
                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            {{ $t('posts.form.tiktok.variant_label') }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="variant in variants"
                                :key="variant.value"
                                type="button"
                                class="cursor-pointer rounded-none border px-3 py-1 text-xs font-semibold tracking-widest uppercase transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                :class="
                                    contentType === variant.value
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground'
                                "
                                :disabled="props.disabled"
                                @click="pickVariant(variant.value)"
                            >
                                {{ $t(variant.labelKey) }}
                            </button>
                        </div>
                        <FieldError :errors="[contentTypeError]" />
                    </Field>

                    <!-- Creator identity -->
                    <div
                        v-if="socialAccount"
                        class="flex items-center gap-3 rounded-lg bg-foreground/5 p-3"
                    >
                        <Avatar
                            :src="socialAccount.avatar_url"
                            :name="socialAccount.display_label"
                            class="size-9 shrink-0 rounded-full border border-border shadow-2xs"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                            >
                                {{ $t('posts.form.tiktok.posting_to') }}
                            </p>
                            <p class="truncate text-sm">
                                <span class="font-bold text-foreground">{{
                                    socialAccount.display_label
                                }}</span>
                                <span
                                    v-if="socialAccount?.username"
                                    class="font-medium text-foreground/60"
                                    >&nbsp;@{{ socialAccount.username }}</span
                                >
                            </p>
                        </div>
                    </div>

                    <!-- Caption (videos) -->
                    <Field v-if="isVideoPost" class="gap-2">
                        <FieldLabel
                            :for="fieldId('caption')"
                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                            >{{ $t('posts.form.tiktok.caption') }}</FieldLabel
                        >
                        <Textarea
                            :id="fieldId('caption')"
                            v-model="caption"
                            :data-testid="fieldId('caption')"
                            :disabled="props.disabled"
                            :aria-invalid="captionError ? true : undefined"
                            :placeholder="
                                $t('posts.form.tiktok.caption_placeholder')
                            "
                            class="field-sizing-fixed min-h-24 w-full resize-y"
                        />
                        <p class="text-xs text-foreground/60 tabular-nums">
                            {{
                                $t('posts.form.tiktok.text_characters', {
                                    used: tiktokTextLength(caption).toString(),
                                    limit: TIKTOK_CAPTION_MAX.toString(),
                                })
                            }}
                        </p>
                        <FieldError :errors="[captionError]" />
                    </Field>

                    <!-- Title and description (photos) -->
                    <template v-if="isPhotoPost">
                        <Field class="gap-2">
                            <FieldLabel
                                :for="fieldId('title')"
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >{{
                                    $t('posts.form.tiktok.photo_title')
                                }}</FieldLabel
                            >
                            <Input
                                :id="fieldId('title')"
                                v-model="photoTitle"
                                :data-testid="fieldId('title')"
                                :disabled="props.disabled"
                                :aria-invalid="
                                    photoTitleError ? true : undefined
                                "
                                :placeholder="
                                    $t(
                                        'posts.form.tiktok.photo_title_placeholder',
                                    )
                                "
                            />
                            <p class="text-xs text-foreground/60 tabular-nums">
                                {{
                                    $t('posts.form.tiktok.text_characters', {
                                        used: tiktokTextLength(
                                            photoTitle,
                                        ).toString(),
                                        limit: TIKTOK_PHOTO_TITLE_MAX.toString(),
                                    })
                                }}
                            </p>
                            <FieldError :errors="[photoTitleError]" />
                        </Field>
                        <Field class="gap-2">
                            <FieldLabel
                                :for="fieldId('description')"
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >{{
                                    $t('posts.form.tiktok.photo_description')
                                }}</FieldLabel
                            >
                            <Textarea
                                :id="fieldId('description')"
                                v-model="photoDescription"
                                :data-testid="fieldId('description')"
                                :disabled="props.disabled"
                                :aria-invalid="
                                    photoDescriptionError ? true : undefined
                                "
                                :placeholder="
                                    $t(
                                        'posts.form.tiktok.photo_description_placeholder',
                                    )
                                "
                                class="field-sizing-fixed min-h-24 w-full resize-y"
                            />
                            <p class="text-xs text-foreground/60 tabular-nums">
                                {{
                                    $t('posts.form.tiktok.text_characters', {
                                        used: tiktokTextLength(
                                            photoDescription,
                                        ).toString(),
                                        limit: TIKTOK_PHOTO_DESCRIPTION_MAX.toString(),
                                    })
                                }}
                            </p>
                            <FieldError :errors="[photoDescriptionError]" />
                        </Field>
                    </template>

                    <Alert
                        v-if="viaTelegram"
                        class="px-3 py-2"
                        data-testid="tiktok-via-telegram"
                    >
                        <IconBrandTelegram />
                        <AlertDescription class="text-xs font-semibold">
                            {{ $t('posts.form.tiktok.via_telegram') }}
                        </AlertDescription>
                    </Alert>

                    <template v-else>
                        <!-- Privacy Level -->
                        <Field class="gap-2">
                            <FieldLabel
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >{{
                                    $t('posts.form.tiktok.privacy_level')
                                }}</FieldLabel
                            >
                            <Select
                                v-model="privacyLevel"
                                :disabled="props.disabled"
                            >
                                <SelectTrigger
                                    class="w-full"
                                    :data-testid="fieldId('privacy-level')"
                                    :aria-invalid="
                                        privacyError ? true : undefined
                                    "
                                >
                                    <SelectValue
                                        :placeholder="
                                            $t(
                                                'posts.form.tiktok.privacy_placeholder',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in privacyOptions"
                                        :key="option"
                                        :value="option"
                                        :disabled="isSelfOnlyDisabled(option)"
                                        :title="
                                            isSelfOnlyDisabled(option)
                                                ? $t(
                                                      'posts.form.tiktok.privacy.private_disabled_branded',
                                                  )
                                                : undefined
                                        "
                                    >
                                        {{ $t(tiktokPrivacyLabelKey[option]) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FieldError :errors="[privacyError]" />
                            <p class="text-xs font-medium text-foreground/60">
                                {{ $t('posts.form.tiktok.privacy_hint') }}
                            </p>
                            <Alert
                                v-if="brandContentToggle"
                                variant="warning"
                                class="px-3 py-2"
                            >
                                <IconAlertTriangle />
                                <AlertDescription class="text-xs font-semibold">
                                    {{
                                        $t(
                                            'posts.form.tiktok.privacy.private_disabled_branded',
                                        )
                                    }}
                                </AlertDescription>
                            </Alert>
                        </Field>

                        <!-- Max duration warning -->
                        <Alert
                            v-if="isVideoPost && exceedsMaxDuration"
                            variant="destructive"
                            class="px-3 py-2"
                        >
                            <IconAlertTriangle />
                            <AlertDescription class="text-xs font-semibold">
                                {{
                                    $t(
                                        'posts.form.tiktok.max_duration_exceeded',
                                        {
                                            duration: String(
                                                videoDurationSec ?? 0,
                                            ),
                                            max: String(maxDurationSec ?? 0),
                                        },
                                    )
                                }}
                            </AlertDescription>
                        </Alert>

                        <!-- Auto Add Music (photos only) -->
                        <div v-if="isPhotoPost" class="space-y-2">
                            <Label
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >{{
                                    $t('posts.form.tiktok.auto_add_music')
                                }}</Label
                            >
                            <Select
                                v-model="autoAddMusic"
                                :disabled="props.disabled"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="yes">{{
                                        $t('posts.form.tiktok.yes')
                                    }}</SelectItem>
                                    <SelectItem value="no">{{
                                        $t('posts.form.tiktok.no')
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs font-medium text-foreground/60">
                                {{
                                    $t('posts.form.tiktok.auto_add_music_hint')
                                }}
                            </p>
                        </div>

                        <!-- Allow User To -->
                        <div class="space-y-2">
                            <Label
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >{{
                                    $t('posts.form.tiktok.allow_users')
                                }}</Label
                            >
                            <div
                                class="flex flex-wrap items-center gap-x-6 gap-y-2"
                            >
                                <Field
                                    orientation="horizontal"
                                    class="w-auto gap-2"
                                    :title="
                                        commentDisabled
                                            ? $t(
                                                  'posts.form.tiktok.interaction_disabled_by_creator',
                                              )
                                            : ''
                                    "
                                >
                                    <Checkbox
                                        :id="fieldId('allow-comments')"
                                        v-model="allowComments"
                                        :disabled="
                                            props.disabled || commentDisabled
                                        "
                                    />
                                    <FieldLabel
                                        :for="fieldId('allow-comments')"
                                        class="text-sm font-normal"
                                    >
                                        {{ $t('posts.form.tiktok.comments') }}
                                    </FieldLabel>
                                </Field>
                                <template v-if="!isPhotoPost">
                                    <Field
                                        orientation="horizontal"
                                        class="w-auto gap-2"
                                        :title="
                                            duetDisabled
                                                ? $t(
                                                      'posts.form.tiktok.interaction_disabled_by_creator',
                                                  )
                                                : ''
                                        "
                                    >
                                        <Checkbox
                                            :id="fieldId('allow-duet')"
                                            v-model="allowDuet"
                                            :disabled="
                                                props.disabled || duetDisabled
                                            "
                                        />
                                        <FieldLabel
                                            :for="fieldId('allow-duet')"
                                            class="text-sm font-normal"
                                        >
                                            {{ $t('posts.form.tiktok.duet') }}
                                        </FieldLabel>
                                    </Field>
                                    <Field
                                        orientation="horizontal"
                                        class="w-auto gap-2"
                                        :title="
                                            stitchDisabled
                                                ? $t(
                                                      'posts.form.tiktok.interaction_disabled_by_creator',
                                                  )
                                                : ''
                                        "
                                    >
                                        <Checkbox
                                            :id="fieldId('allow-stitch')"
                                            v-model="allowStitch"
                                            :disabled="
                                                props.disabled || stitchDisabled
                                            "
                                        />
                                        <FieldLabel
                                            :for="fieldId('allow-stitch')"
                                            class="text-sm font-normal"
                                        >
                                            {{ $t('posts.form.tiktok.stitch') }}
                                        </FieldLabel>
                                    </Field>
                                </template>
                            </div>
                        </div>

                        <Separator />

                        <!-- Video made with AI (independent) -->
                        <Field
                            v-if="!isPhotoPost"
                            orientation="horizontal"
                            class="w-auto gap-2"
                        >
                            <Checkbox
                                :id="fieldId('is-aigc')"
                                v-model="isAigc"
                                :disabled="props.disabled"
                            />
                            <FieldLabel
                                :for="fieldId('is-aigc')"
                                class="text-sm font-normal"
                            >
                                {{ $t('posts.form.tiktok.is_aigc') }}
                            </FieldLabel>
                        </Field>

                        <!-- Disclose video content (parent toggle) -->
                        <div class="space-y-3">
                            <Field
                                orientation="horizontal"
                                class="w-auto gap-2"
                            >
                                <Checkbox
                                    :id="fieldId('disclose')"
                                    v-model="discloseOpen"
                                    :disabled="props.disabled"
                                />
                                <FieldLabel
                                    :for="fieldId('disclose')"
                                    class="text-sm"
                                >
                                    {{ $t('posts.form.tiktok.disclose') }}
                                </FieldLabel>
                            </Field>
                            <p
                                class="ml-6 text-xs font-medium text-foreground/60"
                            >
                                {{ $t('posts.form.tiktok.disclose_hint') }}
                            </p>

                            <!-- Promotional Content warning (shown once a sub-toggle is picked) -->
                            <Alert
                                v-if="hasAnyBrandToggle"
                                variant="warning"
                                class="ml-6 w-auto p-3"
                            >
                                <IconAlertTriangle />
                                <AlertTitle
                                    class="line-clamp-none text-xs font-bold"
                                >
                                    {{ $t(promotionalTitleKey) }}
                                </AlertTitle>
                                <AlertDescription class="text-xs font-medium">
                                    {{
                                        $t(
                                            'posts.form.tiktok.promotional_description',
                                        )
                                    }}
                                </AlertDescription>
                            </Alert>

                            <!-- Compliance incomplete hint (disclose ON but no sub-toggle) -->
                            <p
                                v-else-if="discloseOpen"
                                class="ml-6 text-xs font-semibold text-amber-700"
                            >
                                {{
                                    $t(
                                        'posts.form.tiktok.compliance_incomplete',
                                    )
                                }}
                            </p>

                            <!-- Sub-toggles (only visible when disclose is on) -->
                            <div v-if="discloseOpen" class="ml-6 space-y-3">
                                <div class="space-y-1">
                                    <Field
                                        orientation="horizontal"
                                        class="w-auto gap-2"
                                    >
                                        <Checkbox
                                            :id="fieldId('brand-organic')"
                                            v-model="brandOrganicToggle"
                                            :disabled="props.disabled"
                                        />
                                        <FieldLabel
                                            :for="fieldId('brand-organic')"
                                            class="text-sm font-normal"
                                        >
                                            {{
                                                $t(
                                                    'posts.form.tiktok.brand_organic',
                                                )
                                            }}
                                        </FieldLabel>
                                    </Field>
                                    <p
                                        class="ml-6 text-xs font-medium text-foreground/60"
                                    >
                                        {{
                                            $t(
                                                'posts.form.tiktok.brand_organic_hint',
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <Field
                                        orientation="horizontal"
                                        class="w-auto gap-2"
                                    >
                                        <Checkbox
                                            :id="fieldId('brand-content')"
                                            v-model="brandContentToggle"
                                            :disabled="props.disabled"
                                        />
                                        <FieldLabel
                                            :for="fieldId('brand-content')"
                                            class="text-sm font-normal"
                                        >
                                            {{
                                                $t(
                                                    'posts.form.tiktok.brand_content',
                                                )
                                            }}
                                        </FieldLabel>
                                    </Field>
                                    <p
                                        class="ml-6 text-xs font-medium text-foreground/60"
                                    >
                                        {{
                                            $t(
                                                'posts.form.tiktok.brand_content_hint',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Compliance declaration — always visible per TikTok UX guideline Point 2/4 -->
                        <p class="text-xs text-muted-foreground">
                            {{ $t('posts.form.tiktok.compliance.agree') }}
                            <template v-if="brandContentToggle">
                                <a
                                    :href="
                                        publishConfig?.brandedContentPolicyUrl
                                    "
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-bold text-primary-text underline-offset-2 hover:underline"
                                >
                                    {{
                                        $t(
                                            'posts.form.tiktok.compliance.branded_policy',
                                        )
                                    }}
                                </a>
                                {{
                                    ' ' +
                                    $t('posts.form.tiktok.compliance.and') +
                                    ' '
                                }}
                            </template>
                            <a
                                :href="publishConfig?.musicUsageConfirmationUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-bold text-primary-text underline-offset-2 hover:underline"
                            >
                                {{
                                    $t(
                                        'posts.form.tiktok.compliance.music_usage',
                                    )
                                }}
                            </a>
                        </p>

                        <!-- Post-publish notice required by TikTok Content Sharing Guidelines -->
                        <p class="mt-3 text-xs text-muted-foreground">
                            {{ $t('posts.form.tiktok.processing_hint') }}
                        </p>
                    </template>
                </CardContent>
            </CollapsibleContent>
        </Collapsible>
    </Card>
</template>
