import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/pantalla-cliente.js', 'resources/js/llamador.js', 'resources/js/consultor-precios.js'],
            refresh: true,
        }),
    ],
});
