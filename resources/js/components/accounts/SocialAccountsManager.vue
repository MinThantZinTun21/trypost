<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import ConnectedAccountsByNetwork from '@/components/accounts/ConnectedAccountsByNetwork.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import { useNetworkConnect } from '@/composables/useNetworkConnect';
import { disconnect, toggle } from '@/routes/app/accounts';
import type {
    AvailablePlatform,
    ConnectedAccount,
} from '@/types/social-account';

withDefaults(
    defineProps<{
        platforms: AvailablePlatform[];
        connectedAccounts?: ConnectedAccount[];
    }>(),
    {
        connectedAccounts: () => [],
    },
);

const disconnectModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(
    null,
);

const { startConnect } = useNetworkConnect();

const reconnectAccount = (account: ConnectedAccount) =>
    startConnect(account.platform, account.id);

const disconnectAccount = (account: ConnectedAccount) => {
    disconnectModal.value?.open({
        url: disconnect.url(account.id),
        confirmText: account.handle_label,
    });
};

const toggleAccount = (account: ConnectedAccount) => {
    router.put(toggle.url(account.id), {}, { preserveScroll: true });
};

defineExpose({ startConnect });
</script>

<template>
    <div>
        <ConnectedAccountsByNetwork
            :platforms="platforms"
            :connected-accounts="connectedAccounts"
            @connect="startConnect"
            @reconnect="reconnectAccount"
            @disconnect="disconnectAccount"
            @toggle="toggleAccount"
        />

        <ConfirmDeleteModal
            ref="disconnectModal"
            :title="$t('accounts.disconnect_modal.title')"
            :description="$t('accounts.disconnect_modal.description')"
            :action="$t('accounts.disconnect_modal.confirm')"
            :cancel="$t('accounts.disconnect_modal.cancel')"
        />
    </div>
</template>
