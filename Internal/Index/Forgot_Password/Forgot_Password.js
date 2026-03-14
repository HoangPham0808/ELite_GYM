// ================================
// PREMIUM FORGOT PASSWORD SYSTEM - JAVASCRIPT
// Black & Gold Gradient 3D Dynamic
// ================================

document.addEventListener('DOMContentLoaded', function() {
    initParticles();
    
    const forgotForm = document.getElementById('forgotPasswordForm');
    const resetForm = document.getElementById('resetPasswordForm');
    
    if (forgotForm) {
        initForgotPasswordForm();
    }
    
    if (resetForm) {
        initResetPasswordForm();
    }
    
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
// FORGOT PASSWORD FORM VALIDATION
// ================================

function initForgotPasswordForm() {
    const form = document.getElementById('forgotPasswordForm');
    const username = document.getElementById('username');
    const email = document.getElementById('email');

    if (!form) return;

    username.addEventListener('blur', validateUsername);
    username.addEventListener('input', function() {
        clearError('usernameError');
        addInputAnimation(this);
    });

    email.addEventListener('blur', validateEmail);
    email.addEventListener('input', function() {
        clearError('emailError');
        addInputAnimation(this);
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        clearAllErrors();
        
        if (!validateUsername() || !validateEmail()) {
            shakeForm();
            return;
        }
        
        submitForgotForm();
    });
}

// ================================
// RESET PASSWORD FORM VALIDATION
// ================================

function initResetPasswordForm() {
    const form = document.getElementById('resetPasswordForm');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const togglePassword1 = document.getElementById('togglePassword1');
    const togglePassword2 = document.getElementById('togglePassword2');

    if (!form) return;

    // Password toggles
    if (togglePassword1 && newPassword) {
        togglePassword1.addEventListener('click', function() {
            togglePasswordVisibility(newPassword, togglePassword1);
        });
    }

    if (togglePassword2 && confirmPassword) {
        togglePassword2.addEventListener('click', function() {
            togglePasswordVisibility(confirmPassword, togglePassword2);
        });
    }

    // Validation
    newPassword.addEventListener('input', function() {
        checkPasswordStrength();
        clearError('passwordError');
        addInputAnimation(this);
    });
    newPassword.addEventListener('blur', validatePassword);

    confirmPassword.addEventListener('input', function() {
        clearError('confirmPasswordError');
        addInputAnimation(this);
    });
    confirmPassword.addEventListener('blur', validateConfirmPassword);

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        clearAllErrors();
        
        if (!validatePassword() || !validateConfirmPassword()) {
            shakeForm();
            return;
        }
        
        submitResetForm();
    });
}

// ================================
// VALIDATION FUNCTIONS
// ================================

function validateUsername() {
    const username = document.getElementById('username');
    if (!username) return true;
    
    const value = username.value.trim();
    const usernameError = document.getElementById('usernameError');

    if (value === '') {
        showError(usernameError, 'Tên đăng nhập không được để trống!');
        return false;
    }

    if (value.length < 3) {
        showError(usernameError, 'Tên đăng nhập phải có ít nhất 3 ký tự!');
        return false;
    }

    return true;
}

function validateEmail() {
    const email = document.getElementById('email');
    if (!email) return true;
    
    const value = email.value.trim();
    const emailError = document.getElementById('emailError');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (value === '') {
        showError(emailError, 'Email không được để trống!');
        return false;
    }

    if (!emailRegex.test(value)) {
        showError(emailError, 'Email không hợp lệ!');
        return false;
    }

    return true;
}

function validatePassword() {
    const password = document.getElementById('new_password');
    if (!password) return true;
    
    const value = password.value;
    const passwordError = document.getElementById('passwordError');

    if (value === '') {
        showError(passwordError, 'Mật khẩu không được để trống!');
        return false;
    }

    if (value.length < 6) {
        showError(passwordError, 'Mật khẩu phải có ít nhất 6 ký tự!');
        return false;
    }

    return true;
}

function validateConfirmPassword() {
    const password = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    if (!password || !confirmPassword) return true;
    
    const confirmPasswordError = document.getElementById('confirmPasswordError');

    if (confirmPassword.value === '') {
        showError(confirmPasswordError, 'Vui lòng xác nhận mật khẩu!');
        return false;
    }

    if (password.value !== confirmPassword.value) {
        showError(confirmPasswordError, 'Mật khẩu nhập lại không khớp!');
        return false;
    }

    return true;
}

// ================================
// ERROR HANDLING
// ================================

function showError(element, message) {
    if (!element) return;
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
    const errorIds = ['usernameError', 'emailError', 'passwordError', 'confirmPasswordError'];
    errorIds.forEach(id => clearError(id));
}

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
// PASSWORD STRENGTH CHECKER
// ================================

function checkPasswordStrength() {
    const password = document.getElementById('new_password');
    if (!password) return;
    
    const value = password.value;
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('strengthText');

    if (!strengthBar) return;

    strengthBar.className = 'password-strength-bar';
    if (strengthText) strengthText.className = 'strength-text';

    if (value.length === 0) {
        strengthBar.classList.remove('show');
        if (strengthText) {
            strengthText.classList.remove('show');
            strengthText.textContent = '';
        }
        return;
    }

    strengthBar.classList.add('show');
    if (strengthText) strengthText.classList.add('show');

    let strength = 0;

    // Length checks
    if (value.length >= 6) strength++;
    if (value.length >= 10) strength++;

    // Character type checks
    if (/[A-Z]/.test(value)) strength++;
    if (/[a-z]/.test(value)) strength++;
    if (/[0-9]/.test(value)) strength++;
    if (/[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(value)) strength++;

    if (strength <= 2) {
        strengthBar.classList.add('weak');
        if (strengthText) {
            strengthText.classList.add('weak');
            strengthText.textContent = 'Yếu';
        }
    } else if (strength <= 4) {
        strengthBar.classList.add('medium');
        if (strengthText) {
            strengthText.classList.add('medium');
            strengthText.textContent = 'Trung bình';
        }
    } else {
        strengthBar.classList.add('strong');
        if (strengthText) {
            strengthText.classList.add('strong');
            strengthText.textContent = 'Mạnh';
        }
    }
}

// ================================
// PASSWORD TOGGLE
// ================================

function togglePasswordVisibility(input, toggle) {
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    toggle.classList.toggle('active');
    createRipple(toggle);
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
        50% { transform: scale(1.01); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(inputStyle);

// ================================
// FORM SUBMIT
// ================================

function submitForgotForm() {
    const form = document.getElementById('forgotPasswordForm');
    if (!form) return;
    
    const submitBtn = form.querySelector('.btn-submit');
    if (!submitBtn) return;
    
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');
    
    createSuccessRipple();
    
    if (navigator.vibrate) {
        navigator.vibrate([50, 30, 50]);
    }
    
    setTimeout(() => {
        form.submit();
    }, 800);
}

function submitResetForm() {
    const form = document.getElementById('resetPasswordForm');
    if (!form) return;
    
    const submitBtn = form.querySelector('.btn-submit');
    if (!submitBtn) return;
    
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');
    
    createSuccessRipple();
    
    if (navigator.vibrate) {
        navigator.vibrate([50, 30, 50]);
    }
    
    setTimeout(() => {
        form.submit();
    }, 800);
}

// ================================
// BUTTON ANIMATIONS
// ================================

function initButtonAnimations() {
    const submitBtn = document.querySelector('.btn-submit');
    
    if (!submitBtn) return;
    
    submitBtn.addEventListener('mouseenter', function() {
        createButtonParticles(this);
    });
    
    submitBtn.addEventListener('click', function(e) {
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
// SUCCESS RIPPLE
// ================================

function createSuccessRipple() {
    const registerBox = document.querySelector('.register-box');
    if (!registerBox) return;
    
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
    
    registerBox.appendChild(ripple);
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
    const forgotBox = document.querySelector('.forgot-box');
    if (!forgotBox) return;
    
    forgotBox.style.animation = 'none';
    setTimeout(() => {
        forgotBox.style.animation = 'form-shake 0.5s ease-out';
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
// KEYBOARD SHORTCUTS
// ================================

document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
        const forgotForm = document.getElementById('forgotPasswordForm');
        const resetForm = document.getElementById('resetPasswordForm');
        const activeForm = forgotForm || resetForm;
        
        if (activeForm && document.activeElement.form === activeForm) {
            activeForm.dispatchEvent(new Event('submit'));
        }
    }
    
    if (e.key === 'Escape') {
        clearAllFields();
    }
});

function clearAllFields() {
    // Clear forgot password form
    const username = document.getElementById('username');
    const email = document.getElementById('email');
    if (username) username.value = '';
    if (email) email.value = '';
    
    // Clear reset password form
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    if (newPassword) newPassword.value = '';
    if (confirmPassword) confirmPassword.value = '';
    
    clearAllErrors();
    
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('strengthText');
    if (strengthBar) strengthBar.className = 'password-strength-bar';
    if (strengthText) strengthText.className = 'strength-text';
    
    showNotification('Form đã được xóa', 'info');
}

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
// PERFORMANCE OPTIMIZATION
// ================================

if (navigator.hardwareConcurrency && navigator.hardwareConcurrency < 4) {
    document.documentElement.style.setProperty('--transition', 'all 0.2s ease');
    document.documentElement.style.setProperty('--transition-smooth', 'all 0.3s ease');
}

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

console.log('%c🔐 ELITE FITNESS GYM - PASSWORD RECOVERY 🔐', 'color: #FFD700; font-size: 20px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);');
console.log('%cPremium Black & Gold Design', 'color: #FFC107; font-size: 14px;');
console.log('%cVersion 1.0.0 - Secure & Safe', 'color: #999; font-size: 12px;');
