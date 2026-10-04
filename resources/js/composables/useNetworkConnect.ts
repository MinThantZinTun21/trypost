import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

import { oauthConnectUrl, useOAuthPopup } from '@/composables/useOAuthPopup';

export const useNetworkConnect = () => {
    const { openOAuthPopup } = useOAuthPopup((result) => {
        if (result.success) {
            toast.success(result.message);
            router.reload();
            return;
        }

        toast.error(result.message);
    });

    const startConnect = (platform: string, reconnectId?: string) => {
        const url = oauthConnectUrl(platform, reconnectId);

        if (url) {
            openOAuthPopup(url);
        }
    };

    return { startConnect };
};
