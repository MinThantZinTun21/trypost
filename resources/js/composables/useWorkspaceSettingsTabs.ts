import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import { members as membersRoute } from '@/routes/app';
import {
    brand as brandRoute,
    settings as workspaceSettings,
} from '@/routes/app/workspace';

export const useWorkspaceSettingsTabs = () => {
    const { isAdminOrAbove } = useWorkspaceRole();

    return computed(() => {
        const tabs = [
            {
                name: 'workspace',
                label: trans('settings.workspace.tabs.workspace'),
                href: workspaceSettings.url(),
            },
            {
                name: 'brand',
                label: trans('settings.workspace.tabs.brand'),
                href: brandRoute.url(),
            },
            {
                name: 'members',
                label: trans('settings.workspace.tabs.users'),
                href: membersRoute.url(),
            },
        ];

        return isAdminOrAbove.value ? tabs : [];
    });
};
