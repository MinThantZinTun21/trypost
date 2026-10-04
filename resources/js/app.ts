import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

import { bootLocale, i18nConfig } from './language';
import { syncContentTypeMediaRules } from './lib/contentTypeMediaRules';

const appName = import.meta.env.VITE_APP_NAME || 'TryPost.it';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const locale = bootLocale(props.initialPage.props);

        syncContentTypeMediaRules(props.initialPage);

        router.on('navigate', (event) => {
            syncContentTypeMediaRules(event.detail.page);
        });

        createApp({ render: () => h(App, props) })
            .use(i18nVue, i18nConfig(locale))
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
