// ================================
// PREMIUM LOGIN SYSTEM - JAVASCRIPT
// Black & Gold Gradient 3D Dynamic
// ================================

document.addEventListener('DOMContentLoaded', function() {
    initParticles();
    initFormValidation();
    initPasswordToggle();
    initRememberMe();
    init3DEffects();
    initButtonAnimations();
});

// ================================
// PARTICLES ANIMATION SYSTEM
// ================================

function initParticles() {
    const particlesContainer = document.getElementById('particles');
    const particleCount = 80;
    
    for (let i = 0; i < particleCount; i++) {
        createParticle(particlesContainer);
    }
}

function createParticle(container) {
    const particle = document.createElement('div');
    const size = Math.random() * 3 + 1;
    const startX = Math.random() * window.innerWidth;
    const startY = Math.random() * window.innerHeight;
    const duration = Math.random() * 20 + 10;
    const delay = Math.random() * 5;
    
    particle.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        background: radial-gradient(circle, rgba(255, 215, 0, 0.8), transparent);
        border-radius: 50%;
        left: ${startX}px;
        top: ${startY}px;
        pointer-events: none;
        animation: float-particle ${duration}s ease-in-out ${delay}s infinite;
        box-shadow: 0 0 ${size * 3}px rgba(255, 215, 0, 0.5);
    `;
    
    container.appendChild(particle);
}

// Add particle animation styles dynamically
const style = document.createElement('style');
style.textContent = `
    @keyframes float-particle {
        0%, 100% {
            transform: translate(0, 0) scale(1);
            opacity: 0;
        }
        10% {
            opacity: 0.8;
        }
        90% {
            opacity: 0.8;
        }
        25% {
            transform: translate(${Math.random() * 100 - 50}px, ${Math.random() * 100 - 50}px) scale(${Math.random() + 0.5});
        }
        50% {
            transform: translate(${Math.random() * 150 - 75}px, ${Math.random() * 150 - 75}px) scale(${Math.random() + 1});
        }
        75% {
            transform: translate(${Math.random() * 100 - 50}px, ${Math.random() * 100 - 50}px) scale(${Math.random() + 0.5});
        }
    }
`;
document.head.appendChild(style);

// ================================
// 3D MOUSE TRACKING EFFECTS - DISABLED
// ================================

function init3DEffects() {
    // 3D effects disabled per user request
    return;
}

// ================================
// FORM VALIDATION SYSTEM
// ================================

function initFormValidation() {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');

    if (!loginForm) return;

    // Real-time validation
    usernameInput.addEventListener('blur', validateUsername);
    usernameInput.addEventListener('input', function() {
        clearError('usernameError');
        addInputAnimation(this);
    });

    passwordInput.addEventListener('blur', validatePassword);
    passwordInput.addEventListener('input', function() {
        clearError('passwordError');
        addInputAnimation(this);
    });

    // Form submission
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        clearAllErrors();
        
        if (!validateForm()) {
            shakeForm();
            return;
        }
        
        submitLoginForm();
    });
}

function validateUsername() {
    const username = document.getElementById('username').value.trim();
    const usernameError = document.getElementById('usernameError');

    if (username === '') {
        showError(usernameError, 'Tên đăng nhập không được để trống!');
        return false;
    }

    if (username.length < 3) {
        showError(usernameError, 'Tên đăng nhập phải có ít nhất 3 ký tự!');
        return false;
    }

    if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        showError(usernameError, 'Tên đăng nhập chỉ chứa chữ, số và dấu gạch dưới!');
        return false;
    }

    return true;
}

function validatePassword() {
    const password = document.getElementById('password').value;
    const passwordError = document.getElementById('passwordError');

    if (password === '') {
        showError(passwordError, 'Mật khẩu không được để trống!');
        return false;
    }

    if (password.length < 6) {
        showError(passwordError, 'Mật khẩu phải có ít nhất 6 ký tự!');
        return false;
    }

    return true;
}

function validateForm() {
    return validateUsername() && validatePassword();
}

function showError(element, message) {
    element.textContent = message;
    element.style.animation = 'none';
    setTimeout(() => {
        element.style.animation = 'error-shake 0.5s ease-out';
    }, 10);
}

function clearError(errorId) {
    const element = document.getElementById(errorId);
    if (element) {
        element.textContent = '';
    }
}

function clearAllErrors() {
    clearError('usernameError');
    clearError('passwordError');
}

// Add error shake animation
const errorStyle = document.createElement('style');
errorStyle.textContent = `
    @keyframes error-shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
`;
document.head.appendChild(errorStyle);

// ================================
// PASSWORD TOGGLE
// ================================

function initPasswordToggle() {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (!togglePassword || !passwordInput) return;

    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        this.classList.toggle('active');
        
        // Add ripple effect
        createRipple(this);
    });
}

// ================================
// REMEMBER ME FUNCTIONALITY
// ================================

function initRememberMe() {
    const rememberMe = document.getElementById('rememberMe');
    const usernameInput = document.getElementById('username');

    if (!rememberMe || !usernameInput) return;

    // Load remembered username
    const rememberedUsername = localStorage.getItem('rememberedUsername');
    if (rememberedUsername) {
        usernameInput.value = rememberedUsername;
        rememberMe.checked = true;
        
        // Add entrance animation
        usernameInput.style.animation = 'input-glow 0.6s ease-out';
    }

    // Save/remove username on checkbox change
    rememberMe.addEventListener('change', function() {
        if (this.checked) {
            localStorage.setItem('rememberedUsername', usernameInput.value);
            showNotification('Đã lưu thông tin đăng nhập', 'success');
        } else {
            localStorage.removeItem('rememberedUsername');
            showNotification('Đã xóa thông tin đã lưu', 'info');
        }
    });

    // Update saved username when typing
    usernameInput.addEventListener('input', function() {
        if (rememberMe.checked) {
            localStorage.setItem('rememberedUsername', this.value);
        }
    });
}

// ================================
// INPUT ANIMATIONS
// ================================

function addInputAnimation(input) {
    const wrapper = input.closest('.input-wrapper');
    if (!wrapper) return;
    
    wrapper.style.animation = 'none';
    setTimeout(() => {
        wrapper.style.animation = 'input-pulse 0.3s ease-out';
    }, 10);
}

const inputStyle = document.createElement('style');
inputStyle.textContent = `
    @keyframes input-pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.02); }
        100% { transform: scale(1); }
    }
    
    @keyframes input-glow {
        0%, 100% { box-shadow: none; }
        50% { box-shadow: 0 0 20px rgba(255, 215, 0, 0.5); }
    }
`;
document.head.appendChild(inputStyle);

// ================================
// FORM SUBMIT WITH LOADING STATE
// ================================

function submitLoginForm() {
    const form = document.getElementById('loginForm');
    const submitBtn = form.querySelector('.btn-login');
    
    if (!submitBtn) return;
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');
    
    // Create success ripple effect
    createSuccessRipple();
    
    // Vibrate if supported
    if (navigator.vibrate) {
        navigator.vibrate([50, 30, 50]);
    }
    
    // Submit after animation
    setTimeout(() => {
        form.submit();
    }, 800);
}

// ================================
// BUTTON ANIMATIONS
// ================================

function initButtonAnimations() {
    const loginBtn = document.querySelector('.btn-login');
    
    if (!loginBtn) return;
    
    loginBtn.addEventListener('mouseenter', function() {
        createButtonParticles(this);
    });
    
    loginBtn.addEventListener('click', function(e) {
        if (!this.disabled) {
            createRipple(this, e);
        }
    });
}

function createButtonParticles(button) {
    const rect = button.getBoundingClientRect();
    const particleCount = 6;
    
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        const size = Math.random() * 4 + 2;
        const angle = (Math.PI * 2 * i) / particleCount;
        const distance = 50;
        
        particle.style.cssText = `
            position: fixed;
            width: ${size}px;
            height: ${size}px;
            background: radial-gradient(circle, #FFD700, transparent);
            border-radius: 50%;
            left: ${rect.left + rect.width / 2}px;
            top: ${rect.top + rect.height / 2}px;
            pointer-events: none;
            z-index: 1000;
            animation: particle-burst 0.8s ease-out forwards;
            --tx: ${Math.cos(angle) * distance}px;
            --ty: ${Math.sin(angle) * distance}px;
        `;
        
        document.body.appendChild(particle);
        
        setTimeout(() => particle.remove(), 800);
    }
}

const particleStyle = document.createElement('style');
particleStyle.textContent = `
    @keyframes particle-burst {
        0% {
            transform: translate(0, 0) scale(1);
            opacity: 1;
        }
        100% {
            transform: translate(var(--tx), var(--ty)) scale(0);
            opacity: 0;
        }
    }
`;
document.head.appendChild(particleStyle);

// ================================
// RIPPLE EFFECT
// ================================

function createRipple(element, event = null) {
    const ripple = document.createElement('div');
    const rect = element.getBoundingClientRect();
    
    let x, y;
    if (event) {
        x = event.clientX - rect.left;
        y = event.clientY - rect.top;
    } else {
        x = rect.width / 2;
        y = rect.height / 2;
    }
    
    const size = Math.max(rect.width, rect.height) * 2;
    
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.4), transparent);
        left: ${x}px;
        top: ${y}px;
        transform: translate(-50%, -50%) scale(0);
        animation: ripple-expand 0.6s ease-out;
        pointer-events: none;
        z-index: 10;
    `;
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    setTimeout(() => ripple.remove(), 600);
}

const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
    @keyframes ripple-expand {
        to {
            transform: translate(-50%, -50%) scale(1);
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyle);

// ================================
// SUCCESS RIPPLE EFFECT
// ================================

function createSuccessRipple() {
    const loginBox = document.querySelector('.login-box');
    if (!loginBox) return;
    
    const ripple = document.createElement('div');
    ripple.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255, 215, 0, 0.3), transparent);
        transform: translate(-50%, -50%);
        animation: success-ripple 1s ease-out;
        pointer-events: none;
        z-index: 100;
    `;
    
    loginBox.appendChild(ripple);
    setTimeout(() => ripple.remove(), 1000);
}

const successStyle = document.createElement('style');
successStyle.textContent = `
    @keyframes success-ripple {
        to {
            width: 500px;
            height: 500px;
            opacity: 0;
        }
    }
`;
document.head.appendChild(successStyle);

// ================================
// SHAKE FORM ON ERROR
// ================================

function shakeForm() {
    const loginBox = document.querySelector('.login-box');
    if (!loginBox) return;
    
    loginBox.style.animation = 'none';
    setTimeout(() => {
        loginBox.style.animation = 'form-shake 0.5s ease-out';
    }, 10);
    
    if (navigator.vibrate) {
        navigator.vibrate([100, 50, 100]);
    }
}

const shakeStyle = document.createElement('style');
shakeStyle.textContent = `
    @keyframes form-shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
        20%, 40%, 60%, 80% { transform: translateX(10px); }
    }
`;
document.head.appendChild(shakeStyle);

// ================================
// NOTIFICATION SYSTEM
// ================================

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const colors = {
        success: 'rgba(0, 255, 136, 0.2)',
        error: 'rgba(255, 68, 68, 0.2)',
        info: 'rgba(255, 215, 0, 0.2)'
    };
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${colors[type] || colors.info};
        border: 2px solid ${type === 'success' ? '#00FF88' : type === 'error' ? '#FF4444' : '#FFD700'};
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        font-size: 14px;
        backdrop-filter: blur(10px);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        z-index: 10000;
        animation: notification-slide-in 0.5s ease-out;
    `;
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'notification-slide-out 0.5s ease-out';
        setTimeout(() => notification.remove(), 500);
    }, 3000);
}

const notificationStyle = document.createElement('style');
notificationStyle.textContent = `
    @keyframes notification-slide-in {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes notification-slide-out {
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(notificationStyle);

// ================================
// KEYBOARD SHORTCUTS
// ================================

document.addEventListener('keydown', function(e) {
    // Enter key for quick login
    if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
        const form = document.getElementById('loginForm');
        if (form) {
            form.dispatchEvent(new Event('submit'));
        }
    }
    
    // Escape key to clear form
    if (e.key === 'Escape') {
        clearForm();
    }
});

function clearForm() {
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    
    if (username) username.value = '';
    if (password) password.value = '';
    
    clearAllErrors();
    showNotification('Form đã được xóa', 'info');
}

// ================================
// PERFORMANCE OPTIMIZATION
// ================================

// Reduce animations on low-performance devices
if (navigator.hardwareConcurrency && navigator.hardwareConcurrency < 4) {
    document.documentElement.style.setProperty('--transition', 'all 0.2s ease');
    document.documentElement.style.setProperty('--transition-smooth', 'all 0.3s ease');
}

// Pause animations when tab is not visible
document.addEventListener('visibilitychange', function() {
    const orbs = document.querySelectorAll('.orb');
    orbs.forEach(orb => {
        if (document.hidden) {
            orb.style.animationPlayState = 'paused';
        } else {
            orb.style.animationPlayState = 'running';
        }
    });
});

// ================================
// CONSOLE MESSAGE
// ================================

console.log('%c🏋️ ELITE FITNESS GYM MANAGEMENT SYSTEM 🏋️', 'color: #FFD700; font-size: 20px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);');
console.log('%cPremium Black & Gold Design', 'color: #FFC107; font-size: 14px;');
console.log('%cVersion 1.0.0 - Developed with ❤️', 'color: #999; font-size: 12px;');