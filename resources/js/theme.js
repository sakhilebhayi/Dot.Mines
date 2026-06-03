/**
 * Theme Controller
 * ----------------
 * Manages Light / Dark / System theme switching with localStorage persistence.
 *
 * Storage keys:
 *   theme-mode  → 'light' | 'dark' | 'system'  (user-selected mode)
 *   theme       → 'light' | 'dark'              (effective/resolved theme)
 *
 * Usage:
 *   import { toggleTheme, setMode, getCurrentMode, getCurrentTheme } from './theme';
 *   toggleTheme();           // flip between light and dark
 *   setMode('system');       // follow OS preference
 *   getCurrentMode();        // 'light' | 'dark' | 'system'
 *   getCurrentTheme();       // 'light' | 'dark'  (resolved)
 *
 * Alpine / window event:
 *   window.addEventListener('theme-changed', (e) => e.detail.theme)
 */

const MODE_KEY  = 'theme-mode'; // 'light' | 'dark' | 'system'
const THEME_KEY = 'theme';      // 'light' | 'dark'  (resolved, for legacy compat)

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Returns the OS dark-mode preference. */
function getSystemPreference() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/** Returns the saved mode from localStorage ('light' | 'dark' | 'system' | null). */
function getSavedMode() {
    return localStorage.getItem(MODE_KEY);
}

/**
 * Resolves the effective theme ('light' | 'dark') from a mode string.
 * Falls back to getSavedMode() → system preference when called with no arg.
 */
function getEffectiveTheme(mode) {
    const m = mode ?? getSavedMode();
    if (!m || m === 'system') {
        return getSystemPreference();
    }
    return m; // 'light' | 'dark'
}

// ---------------------------------------------------------------------------
// Core API
// ---------------------------------------------------------------------------

/**
 * Apply a resolved theme ('light' | 'dark') to the document.
 * Adds/removes the 'dark' class and updates data-theme on <html>.
 */
function applyTheme(theme) {
    const html = document.documentElement;

    if (theme === 'dark') {
        html.classList.add('dark');
        html.setAttribute('data-theme', 'dark');
    } else {
        html.classList.remove('dark');
        html.setAttribute('data-theme', 'light');
    }

    // Keep legacy 'theme' key for any code that reads it directly.
    localStorage.setItem(THEME_KEY, theme);

    // Notify Alpine components and other listeners.
    window.dispatchEvent(new CustomEvent('theme-changed', {
        detail: { theme, mode: getSavedMode() || 'system' },
        bubbles: false,
    }));
}

/**
 * Persist a mode ('light' | 'dark' | 'system') and immediately apply it.
 */
function setMode(mode) {
    localStorage.setItem(MODE_KEY, mode);
    applyTheme(getEffectiveTheme(mode));
}

/**
 * Toggle between light and dark (ignores system mode — becomes explicit).
 */
function toggleTheme() {
    const current = getEffectiveTheme();
    setMode(current === 'dark' ? 'light' : 'dark');
}

/** Returns the saved mode string: 'light' | 'dark' | 'system'. */
function getCurrentMode() {
    return getSavedMode() || 'system';
}

/** Returns the resolved theme: 'light' | 'dark'. */
function getCurrentTheme() {
    return getEffectiveTheme();
}

// ---------------------------------------------------------------------------
// Initialisation
// ---------------------------------------------------------------------------

function init() {
    // Resolve and apply theme immediately to avoid flash of wrong theme.
    applyTheme(getEffectiveTheme());

    // Re-apply when the OS preference changes (only when mode is 'system').
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        const saved = getSavedMode();
        if (!saved || saved === 'system') {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });
}

init();

// Expose globally for Blade/inline scripts that cannot import ES modules.
window.ThemeController = { setMode, toggleTheme, getCurrentMode, getCurrentTheme };

export { setMode, toggleTheme, getCurrentMode, getCurrentTheme, getEffectiveTheme };
