import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'bcn': {
                    'primary': '#FFAF22',    // Naranja/Amarillo principal
                    'secondary': '#222036',  // Azul oscuro/Púrpura oscuro
                    'light': '#F7F7F7',      // Gris muy claro
                    'white': '#FFFFFF',      // Blanco
                },
            },
        },
    },

    plugins: [forms],
};
