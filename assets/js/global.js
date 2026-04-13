/* =============================================================
   UMDC – Global JS Utilities
   ============================================================= */
'use strict';

// ── Toast Notifications ────────────────────────────────────────
(function () {
    const container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);

    window.showToast = function (message, type = 'success', duration = 4000) {
        const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<i class="fas ${icons[type] || icons.success}" style="color:var(--color-${type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'success'})"></i><span>${escapeHtml(message)}</span>`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'toastOut .3s ease forwards';
            toast.addEventListener('animationend', () => toast.remove());
        }, duration);
    };
})();

// ── Escape HTML ────────────────────────────────────────────────
window.escapeHtml = function (str) {
    if (str == null) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
};

// ── Format Currency ────────────────────────────────────────────
window.formatCurrency = function (amount, currency = '₱') {
    const num = parseFloat(amount) || 0;
    return currency + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// ── Format Date ────────────────────────────────────────────────
window.formatDate = function (dateStr) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch { return dateStr; }
};

// ── Modal Helpers ──────────────────────────────────────────────
window.openModal = function (id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('active'); document.body.style.overflow = 'hidden'; }
};
window.closeModal = function (id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('active'); document.body.style.overflow = ''; }
};

// Close modal on overlay click
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// Close modal on ESC
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
            m.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
});

// ── Sidebar Toggle (Mobile) ────────────────────────────────────
window.toggleSidebar = function () {
    const sb = document.querySelector('.sidebar');
    if (sb) sb.classList.toggle('open');
};

document.addEventListener('click', function (e) {
    const sb = document.querySelector('.sidebar');
    const toggle = document.querySelector('.mobile-toggle');
    if (sb && sb.classList.contains('open') && !sb.contains(e.target) && e.target !== toggle) {
        sb.classList.remove('open');
    }
});

// ── CSRF helper for fetch ──────────────────────────────────────
window.csrfFetch = function (url, options = {}) {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        options.headers = options.headers || {};
        options.headers['X-CSRF-Token'] = csrfMeta.content;
    }
    return fetch(url, options);
};

// ── Confirm Dialog ─────────────────────────────────────────────
window.confirmAction = function (message, callback) {
    if (window.confirm(message)) callback();
};

// ── Progress Bar Renderer ──────────────────────────────────────
window.progressBar = function (current, target) {
    const pct = target > 0 ? Math.min(100, (current / target) * 100) : 0;
    return `<div class="progress-wrap"><div class="progress-fill" style="width:${pct.toFixed(1)}%"></div></div>`;
};

// ── Loading Placeholder ────────────────────────────────────────
window.loadingHTML = function (msg = 'Loading...') {
    return `<div class="loading-state"><div class="spinner"></div><span>${escapeHtml(msg)}</span></div>`;
};

// ── Empty State ────────────────────────────────────────────────
window.emptyHTML = function (icon = 'fa-inbox', title = 'No data', sub = '') {
    return `<div class="empty-state"><i class="fas ${icon}"></i><h6>${escapeHtml(title)}</h6>${sub ? `<p>${escapeHtml(sub)}</p>` : ''}</div>`;
};

// ── API fetch wrapper ──────────────────────────────────────────
// Automatically appends _sess= to every API call so PHP opens the
// correct role session for this tab (multi-account support).
window._SESS = window._SESS || '';   // set by each dashboard page

window.apiFetch = async function (url, options = {}) {
    try {
        // Append _sess param if we know the session name for this tab
        let fullUrl = url;
        if (window._SESS) {
            const sep = url.includes('?') ? '&' : '?';
            fullUrl = url + sep + '_sess=' + window._SESS.replace('?_sess=', '');
        }
        const res = await fetch(fullUrl, options);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return await res.json();
    } catch (err) {
        console.error('API error:', err);
        throw err;
    }
};
