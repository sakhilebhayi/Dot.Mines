/**
 * Micro-interaction layer: button hover/press scaling, ripple feedback, and
 * the btn-animate/input-animate utility classes (defined in app.css).
 *
 * R7 dead-code note: this file previously also carried card stagger/hover
 * effects keyed to the legacy `.bg-gray-800` palette (zero matches since the
 * ink/gold re-theme), `.fade-in-on-scroll` observers, and window helpers
 * (smoothScrollTo/showSkeleton/showToast/showModal/...) with zero callers.
 * All deleted -- only behavior that reaches today's DOM remains.
 */

// R-52: the shared dot-ui controls (header, search pill, switcher, drawer), the sidebar, the notification panel and the dashboard
// (alert rows and the Acknowledge button: no motion on alert states) keep their own look and behaviour. This layer used to
// rewrite their inline position/overflow and scale them on hover, which moved the search pill's arrow out of its field.
const OWN_BEHAVIOUR = '.dh, .dh-drawer, .dsr, .dsw, [data-dsw], .mines-sidebar, .mines-bell, .dm-stack, [data-landing]';
const enhanceable = (selector) => Array.from(document.querySelectorAll(selector)).filter((el) => !el.closest(OWN_BEHAVIOUR));

document.addEventListener('DOMContentLoaded', enhanceInteractions);
document.addEventListener('livewire:navigated', enhanceInteractions);

function enhanceInteractions() {
    // Transition utility classes for buttons and inputs
    const buttons = enhanceable('button:not(.animated), a[class*="btn"]:not(.animated)');
    buttons.forEach(button => {
        button.classList.add('animated', 'btn-animate');
    });

    const inputs = enhanceable('input:not([type="checkbox"]):not([type="radio"]):not(.animated), textarea:not(.animated), select:not(.animated)');
    inputs.forEach(input => {
        input.classList.add('animated', 'input-animate');
    });

    setupButtonHoverEffects();
    setupRippleEffects();
}

function setupButtonHoverEffects() {
    const hoverButtons = enhanceable('button:not(.hover-enhanced), a[class*="bg-"]:not(.hover-enhanced)');
    hoverButtons.forEach(button => {
        button.classList.add('hover-enhanced');

        button.addEventListener('mouseenter', function () {
            if (!this.disabled && !this.classList.contains('disabled')) {
                this.style.transform = 'scale(1.05)';
            }
        });

        button.addEventListener('mouseleave', function () {
            this.style.transform = 'scale(1)';
        });

        button.addEventListener('mousedown', function () {
            if (!this.disabled && !this.classList.contains('disabled')) {
                this.style.transform = 'scale(0.95)';
            }
        });

        button.addEventListener('mouseup', function () {
            if (!this.disabled && !this.classList.contains('disabled')) {
                this.style.transform = 'scale(1.05)';
            }
        });
    });
}

function setupRippleEffects() {
    const rippleButtons = enhanceable('button:not(.no-ripple):not(.ripple-added), a[class*="btn"]:not(.no-ripple):not(.ripple-added)');

    rippleButtons.forEach(button => {
        button.classList.add('ripple-added');
        button.style.position = 'relative';
        button.style.overflow = 'hidden';

        button.addEventListener('click', function (e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple-effect');

            ripple.style.position = 'absolute';
            ripple.style.borderRadius = '50%';
            ripple.style.backgroundColor = 'rgba(255, 255, 255, 0.3)';
            ripple.style.transform = 'scale(0)';
            ripple.style.animation = 'rippleAnimation 0.6s ease-out';
            ripple.style.pointerEvents = 'none';

            this.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
}

// Keyframes for the ripple span (injected once; CSP style-src allows
// first-party inline styles -- see SecurityHeaders).
const style = document.createElement('style');
style.textContent = `
    @keyframes rippleAnimation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
