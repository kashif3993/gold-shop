import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        // Mounted once, globally — any page can call toast(...) from
        // 'sonner' without wiring up its own notification UI.
        root.render(
            <>
                <App {...props} />
                <Toaster richColors position="top-right" />
            </>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Prevent mouse wheel from changing values on number inputs globally
document.addEventListener(
    'wheel',
    (e) => {
        if (document.activeElement?.tagName === 'INPUT' && (document.activeElement as HTMLInputElement).type === 'number') {
            (document.activeElement as HTMLInputElement).blur();
        }
    },
    { passive: false },
);
