/**
 * Toast Notification Service
 * Handles real-time alert notifications with toast messages
 * 
 * Features:
 * - Toast notifications for alerts
 * - Priority-based styling (critical, high, medium, low)
 * - Auto-dismiss timers
 * - Sound alerts for critical issues
 * - Notification queue
 * - Click to dismiss
 */

class ToastNotificationService {
    constructor() {
        this.toasts = new Map(); // id -> toast element
        this.notificationQueue = [];
        this.isPlayingSound = false;
        this.sounds = {};
        this.defaultDuration = {
            critical: 10000, // 10 seconds
            high: 8000,      // 8 seconds
            medium: 6000,    // 6 seconds
            low: 5000        // 5 seconds
        };
        this.soundEnabled = true;
        this.container = null;
    }

    /**
     * Initialize toast service
     */
    init() {
        // Create container if it doesn't exist
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                pointer-events: none;
            `;
            document.body.appendChild(this.container);
        }

        // Preload sound
        this.loadSound('critical', '/audio/alert-critical.mp3');

    }

    /**
     * Load sound file. Idempotent: init() can run more than once per page
     * (each realtime:init dispatch re-enters it), and re-creating the Audio
     * element re-downloaded the sound file on every call.
     * @private
     */
    loadSound(type, path) {
        if (this.sounds[type]) {
            return;
        }

        const audio = new Audio(path);
        audio.preload = 'auto';
        this.sounds[type] = audio;
    }

    /**
     * Show alert toast
     * @param {object} data - Alert data
     */
    showAlert(data) {
        const {
            id = this.generateId(),
            title,
            description,
            priority = 'medium',
            type = 'alert',
            duration = this.defaultDuration[priority],
            action = null
        } = data;

        const toast = this.createToastElement(
            id,
            title,
            description,
            priority,
            type,
            action
        );

        this.container.appendChild(toast);
        this.toasts.set(id, toast);

        // Trigger animation
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        // Play sound for critical alerts
        if (priority === 'critical' && this.soundEnabled) {
            this.playSound('critical');
        }

        // Auto-dismiss
        if (duration > 0) {
            setTimeout(() => {
                this.dismiss(id);
            }, duration);
        }

        return id;
    }

    /**
     * Create toast DOM element
     * @private
     */
    createToastElement(id, title, description, priority, type, action) {
        const toast = document.createElement('div');
        toast.id = `toast-${id}`;
        toast.className = `toast toast-${priority}`;

        const colors = {
            critical: { bg: '#fee2e2', border: '#dc2626', text: '#991b1b', icon: '🚨' },
            high: { bg: '#fef3c7', border: '#f59e0b', text: '#92400e', icon: '⚠️' },
            medium: { bg: '#dbeafe', border: '#3b82f6', text: '#1e40af', icon: 'ℹ️' },
            low: { bg: '#dcfce7', border: '#16a34a', text: '#15803d', icon: '✓' }
        };

        const style = colors[priority];

        toast.style.cssText = `
            background-color: ${style.bg};
            border-left: 4px solid ${style.border};
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            pointer-events: auto;
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateX(400px);
            min-width: 350px;
            max-width: 500px;
        `;

        let actionHTML = '';
        if (action) {
            actionHTML = `
                <button class="toast-action" style="
                    background: ${style.border};
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    margin-top: 8px;
                    transition: opacity 0.2s;
                ">
                    ${action.label}
                </button>
            `;
        }

        toast.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div style="flex: 1;">
                    <div style="
                        font-weight: 600;
                        color: ${style.text};
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        font-size: 14px;
                    ">
                        <span style="font-size: 18px;">${style.icon}</span>
                        ${title}
                    </div>
                    <div style="
                        color: ${style.text};
                        opacity: 0.8;
                        font-size: 13px;
                        margin-top: 4px;
                    ">
                        ${description || ''}
                    </div>
                    ${actionHTML}
                </div>
                <button class="toast-close" style="
                    background: none;
                    border: none;
                    color: ${style.text};
                    font-size: 18px;
                    cursor: pointer;
                    opacity: 0.6;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: opacity 0.2s;
                ">
                    ✕
                </button>
            </div>
        `;

        // Close button handler
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.dismiss(id);
        });

        // Action button handler
        if (action) {
            const actionBtn = toast.querySelector('.toast-action');
            actionBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (action.callback) {
                    action.callback();
                }
                this.dismiss(id);
            });

            // Hover effects
            actionBtn.addEventListener('mouseenter', () => {
                actionBtn.style.opacity = '0.9';
            });
            actionBtn.addEventListener('mouseleave', () => {
                actionBtn.style.opacity = '1';
            });
        }

        // Click to dismiss
        toast.addEventListener('click', () => {
            this.dismiss(id);
        });

        // Hover effects
        closeBtn.addEventListener('mouseenter', () => {
            closeBtn.style.opacity = '1';
        });
        closeBtn.addEventListener('mouseleave', () => {
            closeBtn.style.opacity = '0.6';
        });

        return toast;
    }

    /**
     * Dismiss toast
     * @param {string} id
     */
    dismiss(id) {
        const toast = this.toasts.get(id);
        if (toast) {
            toast.classList.remove('show');
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(400px)';

            setTimeout(() => {
                if (this.container && toast.parentNode) {
                    this.container.removeChild(toast);
                }
                this.toasts.delete(id);
            }, 300);
        }
    }

    /**
     * Dismiss all toasts
     */
    dismissAll() {
        for (const [id] of this.toasts) {
            this.dismiss(id);
        }
    }

    /**
     * Play alert sound
     * @private
     */
    playSound(type) {
        if (!this.soundEnabled) return;

        const sound = this.sounds[type];
        if (sound) {
            sound.currentTime = 0;
            sound.play().catch(err => {
                console.warn('Could not play sound:', err);
            });
        }
    }

    /**
     * Toggle sound
     */
    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        return this.soundEnabled;
    }

    /**
     * Generate unique ID
     * @private
     */
    generateId() {
        return `toast-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    }

    /**
     * Get active toast count
     */
    getActiveCount() {
        return this.toasts.size;
    }

    /**
     * Show success toast
     */
    success(title, description = '', duration = 3000) {
        return this.showAlert({
            title,
            description,
            priority: 'low',
            type: 'success',
            duration
        });
    }

    /**
     * Show info toast
     */
    info(title, description = '', duration = 5000) {
        return this.showAlert({
            title,
            description,
            priority: 'medium',
            type: 'info',
            duration
        });
    }

    /**
     * Show warning toast
     */
    warning(title, description = '', duration = 6000) {
        return this.showAlert({
            title,
            description,
            priority: 'high',
            type: 'warning',
            duration
        });
    }

    /**
     * Show error toast
     */
    error(title, description = '', duration = 8000) {
        return this.showAlert({
            title,
            description,
            priority: 'critical',
            type: 'error',
            duration
        });
    }

    /**
     * Dispose service
     */
    dispose() {
        this.dismissAll();
        if (this.container && this.container.parentNode) {
            document.body.removeChild(this.container);
        }
        this.toasts.clear();
        this.container = null;
    }
}

// Export singleton instance
export default new ToastNotificationService();
