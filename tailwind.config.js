import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import aspectRatio from '@tailwindcss/aspect-ratio';
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                slate: {
                    950: '#0f172a',
                },
                amber: {
                    500: '#f59e0b',
                },
                emerald: {
                    500: '#10b981',
                },
                red: {
                    500: '#ef4444',
                },
            },
            animation: {
                'fadeIn': 'fadeIn 0.5s ease-out forwards',
                'fadeInUp': 'fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                'fadeInDown': 'fadeInDown 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                'slideInRight': 'slideInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
                'modalSlideIn': 'modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
                'pageSlideIn': 'pageSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
                'pulseGlow': 'pulseGlow 3s ease-in-out infinite',
                'bounceAttention': 'bounceAttention 2s ease-in-out infinite',
                'shimmer': 'shimmer 2s linear infinite',
                'spin-slow': 'spin 3s linear infinite',
                'spin-fast': 'spin 0.5s linear infinite',
            },
            keyframes: {
                fadeIn: {
                    'from': { opacity: '0' },
                    'to': { opacity: '1' },
                },
                fadeInUp: {
                    'from': {
                        opacity: '0',
                        transform: 'translateY(20px)',
                    },
                    'to': {
                        opacity: '1',
                        transform: 'translateY(0)',
                    },
                },
                fadeInDown: {
                    'from': {
                        opacity: '0',
                        transform: 'translateY(-20px)',
                    },
                    'to': {
                        opacity: '1',
                        transform: 'translateY(0)',
                    },
                },
                slideInRight: {
                    'from': {
                        opacity: '0',
                        transform: 'translateX(100px)',
                    },
                    'to': {
                        opacity: '1',
                        transform: 'translateX(0)',
                    },
                },
                modalSlideIn: {
                    'from': {
                        opacity: '0',
                        transform: 'translateY(-50px) scale(0.95)',
                    },
                    'to': {
                        opacity: '1',
                        transform: 'translateY(0) scale(1)',
                    },
                },
                pageSlideIn: {
                    'from': {
                        opacity: '0',
                        transform: 'translateX(-10px)',
                    },
                    'to': {
                        opacity: '1',
                        transform: 'translateX(0)',
                    },
                },
                pulseGlow: {
                    '0%, 100%': {
                        opacity: '1',
                        boxShadow: '0 0 20px rgba(59, 130, 246, 0.3)',
                    },
                    '50%': {
                        opacity: '0.8',
                        boxShadow: '0 0 40px rgba(59, 130, 246, 0.6)',
                    },
                },
                bounceAttention: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-10px)' },
                },
                shimmer: {
                    '0%': { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
            },
            transitionTimingFunction: {
                'smooth': 'cubic-bezier(0.16, 1, 0.3, 1)',
                'bounce-in': 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
            },
            transitionDuration: {
                '400': '400ms',
                '600': '600ms',
            },
        },
    },

    plugins: [forms, typography, aspectRatio, daisyui],
    
    daisyui: {
        themes: [
            {
                // Matches the brand tokens in resources/css/app.css (--ink, --gold, etc.)
                // used across the guest/marketing pages and the dashboard shell, so
                // daisyui components (card, stat, badge, alert, btn) read as the same
                // product instead of a generic slate-blue admin theme.
                mines: {
                    "primary": "#d99e2b",
                    "primary-content": "#211a14",
                    "secondary": "#6b4226",
                    "secondary-content": "#f4efe4",
                    "accent": "#f0c669",
                    "accent-content": "#211a14",
                    "neutral": "#211a14",
                    "neutral-content": "#f4efe4",
                    "base-100": "#211a14",
                    "base-200": "#2c2319",
                    "base-300": "#3a2f22",
                    "base-content": "#f4efe4",
                    "info": "#3b82f6",
                    "success": "#10b981",
                    "warning": "#f59e0b",
                    "error": "#ef4444",
                },
            },
        ],
    },
};
