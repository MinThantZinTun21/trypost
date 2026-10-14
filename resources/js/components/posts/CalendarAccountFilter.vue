<script setup lang="ts">
import { computed } from 'vue';

import PlatformLogo from '@/components/PlatformLogo.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CalendarAccountOption } from '@/types/calendar';

/** reka-ui reserves the empty string, so "all accounts" needs its own value. */
const ALL_ACCOUNTS = 'all';

const props = withDefaults(
    defineProps<{
        accounts: CalendarAccountOption[];
        modelValue: string | null;
        testId?: string;
    }>(),
    { testId: 'calendar-account-filter' },
);

const emit = defineEmits<{
    'update:modelValue': [accountId: string | null];
}>();

const selected = computed(() => props.modelValue ?? ALL_ACCOUNTS);

const select = (value: unknown) => {
    const accountId =
        typeof value === 'string' && value !== ALL_ACCOUNTS ? value : null;

    if (accountId !== props.modelValue) {
        emit('update:modelValue', accountId);
    }
};
</script>

<template>
    <Select :model-value="selected" @update:model-value="select">
        <SelectTrigger
            class="w-full lg:w-56"
            :aria-label="$t('calendar.filter_label')"
            :data-testid="testId"
        >
            <SelectValue />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                :value="ALL_ACCOUNTS"
                data-testid="calendar-account-option-all"
            >
                {{ $t('calendar.all_accounts') }}
            </SelectItem>
            <SelectItem
                v-for="account in accounts"
                :key="account.id"
                :value="account.id"
                :data-testid="`calendar-account-option-${account.id}`"
            >
                <PlatformLogo :platform="account.platform" size="xs" plain />
                <span class="truncate">{{ account.display_label }}</span>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
