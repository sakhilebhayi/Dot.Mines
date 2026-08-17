/**
 * Enhanced UX/UI Animations
 * Smooth micro-interactions and transitions
 */

// Initialize animations when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeAnimations();
    setupHoverEffects();
    setupRippleEffects();
    setupScrollAnimations();
});

// Re-initialize on Livewire navigation
document.addEventListener('livewire:navigated', function() {
    initializeAnimations();
    setupHoverEffects();
    setupRippleEffects();
    setupScrollAnimations();
});

/**
 * Initialize stagger animations for list items
 */
function initializeAnimations() {
    // Add stagger animation to cards and list items
    const cards = document.querySelectorAll('.bg-gray-800:not(.animated)');
    cards.forEach((card, index) => {
        card.classList.add('animated', 'fade-in-up');
        card.style.animationDelay = `${index * 0.05}s`;
    });

    // Add smooth transitions to buttons
    const buttons = document.querySelectorAll('button:not(.animated), a[class*="btn"]:not(.animated)');
    buttons.forEach(button => {
        button.classList.add('animated', 'btn-animate');
    });

    // Add smooth transitions to inputs
    const inputs = document.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]):not(.animated), textarea:not(.animated), select:not(.animated)');
    inputs.forEach(input => {
        input.classList.add('animated', 'input-animate');
    });
}

/**
 * Setup enhanced hover effects
 */
function setupHoverEffects() {
    // Card hover effects
    const hoverCards = document.querySelectorAll('.bg-gray-800.border');
    hoverCards.forEach(card => {
        // Skip if already has hover effect
        if (card.classList.contains('hover-enhanced')) return;
        
        card.classList.add('hover-enhanced', 'transition-all', 'duration-300');
        
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
            this.style.boxShadow = '0 20px 25px -5px rgba(59, 130, 246, 0.1), 0 10px 10px -5px rgba(59, 130, 246, 0.04)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });

    // Button hover effects
    const hoverButtons = document.querySelectorAll('button:not(.hover-enhanced), a[class*="bg-"]:not(.hover-enhanced)');
    hoverButtons.forEach(button => {
        button.classList.add('hover-enhanced');
        
        button.addEventListener('mouseenter', function() {
            if (!this.disabled && !this.classList.contains('disabled')) {
                this.style.transform = 'scale(1.05)';
            }
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
        
        button.addEventListener('mousedown', function() {
            if (!this.disabled && !this.classList.contains('disabled')) {
                this.style.transform = 'scale(0.95)';
            }
        });
        
        button.addEventListener('mouseup', function() {
            if (!this.disabled && !this.classList.contains('disabled')) {
                this.style.transform = 'scale(1.05)';
            }
        });
    });
}

/**
 * Setup ripple effect for buttons
 */
function setupRippleEffects() {
    const rippleButtons = document.querySelectorAll('button:not(.no-ripple):not(.ripple-added), a[class*="btn"]:not(.no-ripple):not(.ripple-added)');
    
    rippleButtons.forEach(button => {
        button.classList.add('ripple-added');
        button.style.position = 'relative';
        button.style.overflow = 'hidden';
        
        button.addEventListener('click', function(e) {
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

/**
 * Setup scroll-based animations
 */
function setupScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe elements with scroll animation
    const scrollElements = document.querySelectorAll('.fade-in-on-scroll:not(.observed)');
    scrollElements.forEach(el => {
        el.classList.add('observed');
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
        observer.observe(el);
    });
}

/**
 * Add ripple animation keyframe
 */
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

/**
 * Smooth scroll to element
 */
window.smoothScrollTo = function(element, offset = 0) {
    const targetPosition = element.getBoundingClientRect().top + window.pageYOffset - offset;
    window.scrollTo({
        top: targetPosition,
        behavior: 'smooth'
    });
};

/**
 * Add loading skeleton effect
 */
window.showSkeleton = function(element) {
    element.classList.add('skeleton-loading');
    element.style.pointerEvents = 'none';
};

window.hideSkeleton = function(element) {
    element.classList.remove('skeleton-loading');
    element.style.pointerEvents = 'auto';
};

/**
 * Toast notification with animation
 */
window.showToast = function(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-amber-500',
        info: 'bg-blue-500'
    };
    
    toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-4 rounded-lg shadow-xl z-[10000] animate-slideInRight flex items-center gap-3`;
    toast.innerHTML = `
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease-out forwards';
        setTimeout(() => toast.remove(), 300);
    }, duration);
};

/**
 * Enhanced modal animations
 */
window.showModal = function(modalElement) {
    modalElement.classList.add('modal-backdrop');
    modalElement.style.display = 'flex';
    modalElement.style.animation = 'fadeIn 0.3s ease-out';
    
    const modalContent = modalElement.querySelector('.modal-content, [class*="bg-gray-8"]');
    if (modalContent) {
        modalContent.style.animation = 'modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1)';
    }
};

window.hideModal = function(modalElement) {
    modalElement.style.animation = 'fadeOut 0.2s ease-out';
    const modalContent = modalElement.querySelector('.modal-content, [class*="bg-gray-8"]');
    if (modalContent) {
        modalContent.style.animation = 'none';
    }
    
    setTimeout(() => {
        modalElement.style.display = 'none';
    }, 200);
};

