import './bootstrap';
import 'vue-sonner/style.css';

import { createApp, h } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Pajoh Distributor';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, window.Ziggy)
            .component('apexchart', VueApexCharts)
            .mount(el);
    },
    progress: {
        color: '#A03232',
    },
});
