import { createInertiaApp } from "@inertiajs/vue3";
import type { DefineComponent } from "vue";
import { initializeTheme } from "@/composables/useAppearance";
import { initializeFlashToast } from "@/lib/flashToast";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const pages = import.meta.glob<DefineComponent>([
            "./pages/**/*.vue",
            "!./pages/auth/**/*.vue",
            "!./pages/settings/**/*.vue",
            "!./pages/Dashboard.vue",
        ]);
        const page = pages[`./pages/${name}.vue`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        const module = await page();

        return (module as { default?: DefineComponent }).default ?? module;
    },
    layout: () => null,
    withApp: (app) => {
        app.directive("focus", {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: "#4B5563",
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
