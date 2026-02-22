import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import RootLayout from './layouts/RootLayout.vue';

const appName = (import.meta.env.VITE_APP_NAME && import.meta.env.VITE_APP_NAME !== 'Laravel')
    ? import.meta.env.VITE_APP_NAME
    : 'BoardGamePlays';

createInertiaApp({
    title: (title) => {
        if (!title) return appName;
        if (title === appName) return title;
        return `${title} - ${appName}`;
    },
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({
            render() {
                return h(RootLayout, null, { default: () => h(App, props) });
            },
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
