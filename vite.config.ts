import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const vitePort = Number(env.VITE_PORT ?? process.env.VITE_PORT ?? 5173);

    return {
        server: {
            // Required when running Vite inside Docker (Sail) so the host browser can reach it.
            host: true,
            port: vitePort,
            strictPort: true,
            hmr: {
                // Browser connects from the host, not from inside the container.
                host: 'localhost',
                port: vitePort,
            },
        },
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                refresh: true,
            }),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            wayfinder({
                formVariants: true,
            }),
        ],
    };
});
