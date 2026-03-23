import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './app/**/*.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Manrope', 'Segoe UI', 'Helvetica Neue', 'Arial', ...defaultTheme.fontFamily.sans],
                headline: ['Epilogue', 'Segoe UI', 'sans-serif'],
                body: ['Manrope', 'Segoe UI', 'Helvetica Neue', 'Arial', ...defaultTheme.fontFamily.sans],
                label: ['Inter', 'Segoe UI', 'Helvetica Neue', 'Arial', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
