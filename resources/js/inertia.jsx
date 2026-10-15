import "./bootstrap";
import React from "react";
import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { AuthProvider } from "./react/AuthContext";
import { I18nProvider } from "./react/I18nContext";

createInertiaApp({
    title: (title) => (title ? `${title} | АТУ HR` : "АТУ HR"),
    resolve: (name) =>
        resolvePageComponent(
            `./react/Pages/${name}.jsx`,
            import.meta.glob("./react/Pages/**/*.jsx"),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(
            <I18nProvider>
                <AuthProvider>
                    <App {...props} />
                </AuthProvider>
            </I18nProvider>,
        );
    },
    progress: {
        color: "#005eb8",
    },
});
