/* =============================================================
   UMDC – Auth Pages JS (login & register)
   ============================================================= */
'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ── URL param alerts ───────────────────────────────────────
    const params = new URLSearchParams(window.location.search);
    const error  = params.get('error');
    const ok     = params.get('registered') || params.get('logged_out');

    const errEl = document.getElementById('auth-error');
    const okEl  = document.getElementById('auth-success');

    const errorMessages = {
        invalid:          'Invalid email or password. Please try again.',
        suspended:        'Your account has been suspended. Contact support.',
        rate_limit:       'Too many attempts. Please wait a few minutes.',
        server:           'A server error occurred. Please try again.',
        email_exists:     'An account with that email already exists.',
        weak_password:    'Password must be at least 8 characters.',
        password_mismatch:'Passwords do not match.',
        invalid_email:    'Please enter a valid email address.',
        invalid_name:     'Please enter your full name.',
    };

    const successMessages = {
        success:   'Account created! Please sign in.',
        '1':       'You have been signed out.',
    };

    if (errEl && error && errorMessages[error]) {
        errEl.textContent = errorMessages[error];
        errEl.classList.add('show');
        setTimeout(() => errEl.classList.remove('show'), 6000);
    }

    if (okEl && ok && successMessages[ok]) {
        okEl.textContent = successMessages[ok];
        okEl.classList.add('show');
    }

    // ── Login form ─────────────────────────────────────────────
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        const btn = loginForm.querySelector('button[type="submit"]');
        loginForm.addEventListener('submit', function () {
            if (btn) { btn.disabled = true; btn.textContent = 'Signing in…'; }
        });
    }

    // ── Register form ──────────────────────────────────────────
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        const btn      = registerForm.querySelector('button[type="submit"]');
        const password = registerForm.querySelector('[name="password"]');
        const confirm  = registerForm.querySelector('[name="confirm_password"]');
        const strength = document.getElementById('password-strength');

        // Password strength meter
        if (password && strength) {
            password.addEventListener('input', function () {
                const v   = this.value;
                const len = v.length >= 8;
                const upper = /[A-Z]/.test(v);
                const lower = /[a-z]/.test(v);
                const digit = /\d/.test(v);
                const spec  = /[^a-zA-Z0-9]/.test(v);
                const score = [len, upper, lower, digit, spec].filter(Boolean).length;
                const labels = ['', 'Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
                const colors = ['', '#dc2626', '#f97316', '#f59e0b', '#10b981', '#059669'];
                strength.textContent = labels[score];
                strength.style.color = colors[score];
            });
        }

        // Confirm password check
        if (confirm) {
            confirm.addEventListener('input', function () {
                if (password && this.value && this.value !== password.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }

        // Role switcher
        const roleInputs  = registerForm.querySelectorAll('[name="role"]');
        const visualPanel = document.querySelector('.auth-visual');
        const roleTitle   = document.getElementById('role-title');
        const roleDesc    = document.getElementById('role-desc');
        const roleIcon    = document.getElementById('role-icon');

        const roleContent = {
            user:         { icon: '🙋', title: 'Become a Donor', desc: 'Support campaigns and make a direct impact in your community.' },
            organization: { icon: '🏢', title: 'Register Your Org', desc: 'Create campaigns and receive donations for your cause.' },
        };

        roleInputs.forEach(radio => {
            radio.addEventListener('change', function () {
                const content = roleContent[this.value];
                if (content) {
                    if (roleIcon)  roleIcon.textContent  = content.icon;
                    if (roleTitle) roleTitle.textContent = content.title;
                    if (roleDesc)  roleDesc.textContent  = content.desc;
                }
                if (visualPanel) {
                    visualPanel.classList.toggle('org-theme', this.value === 'organization');
                }
            });
        });

        registerForm.addEventListener('submit', function () {
            if (btn) { btn.disabled = true; btn.textContent = 'Creating account…'; }
        });
    }
});
