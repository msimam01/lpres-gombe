import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'react-hot-toast';
import { route as routeFn } from 'ziggy-js';

declare global {
    const route: typeof routeFn;
}

const appName = 'Gombe State L-PRES';

createInertiaApp({
    title: (title) => `${title} | ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        // Shared top-right Toaster: one instance, mounted outside any page
        // layout so Inertia page swaps keep it alive.
        root.render(
            <>
                <App {...props} />
                <Toaster
                    position="top-right"
                    containerStyle={{ top: 80 }}
                    toastOptions={{
                        className: 'font-lpres',
                        style: {
                            maxWidth: 420,
                            fontFamily: 'Manrope, sans-serif',
                        },
                    }}
                />
            </>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
