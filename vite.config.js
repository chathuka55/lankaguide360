import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/map.js', 'resources/js/admin.js', 'resources/js/builder.js', 'resources/js/home.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    // Serve on IPv4: on Windows "localhost" can resolve to [::1], which a Content-Security-Policy
    // cannot name (see App\Http\Middleware\SecurityHeaders).
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: { host: '127.0.0.1' },
    },
});
