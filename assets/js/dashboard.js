/* =============================================================
   UMDC – Dashboard Shared JS
   ============================================================= */
'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ── Active nav link ────────────────────────────────────────
    const currentPath = window.location.pathname;
    document.querySelectorAll('.sidebar-link[href]').forEach(link => {
        if (link.getAttribute('href') === currentPath ||
            currentPath.endsWith(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });

    // ── Mobile sidebar toggle ──────────────────────────────────
    const toggleBtn = document.querySelector('.mobile-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }

    // ── Confirm logout ─────────────────────────────────────────
    document.querySelectorAll('[data-logout]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to sign out?')) {
                const sess = window._SESS ? window._SESS : '';
                window.location.href = '/Auth/logout.php' + sess;
            }
        });
    });

    // ── Auto-dismiss alerts ────────────────────────────────────
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });

    // ── Tooltip simple ────────────────────────────────────────
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.setAttribute('title', el.dataset.tooltip);
    });
});

// ── Donation form handler ──────────────────────────────────────
window.DonationForm = {
    currentModal: null,

    open(campaignId, campaignTitle) {
        const idEl    = document.getElementById('campaign_id');
        const infoEl  = document.getElementById('donation-campaign-info');
        const form    = document.getElementById('donationForm');

        if (idEl) idEl.value = campaignId;
        if (infoEl) infoEl.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span>Donating to: <strong>${escapeHtml(campaignTitle)}</strong></span>
            </div>`;
        if (form) form.reset();

        // Show/hide type fields
        const typeSelect = document.getElementById('donation_type');
        if (typeSelect) {
            DonationForm.toggleTypeFields(typeSelect.value);
            typeSelect.onchange = () => DonationForm.toggleTypeFields(typeSelect.value);
        }

        openModal('donationModal');
    },

    toggleTypeFields(type) {
        ['cash-fields', 'item-fields', 'service-fields'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
        const active = document.getElementById(`${type}-fields`);
        if (active) active.style.display = 'block';

        // Manage required attrs
        const amountEl  = document.getElementById('amount');
        const itemEl    = document.getElementById('item_name');
        const serviceEl = document.getElementById('service_description');
        if (amountEl)  amountEl.required  = (type === 'cash');
        if (itemEl)    itemEl.required     = (type === 'item');
        if (serviceEl) serviceEl.required  = (type === 'service');
    },

    async submit(btn) {
        const form = document.getElementById('donationForm');
        if (!form) return;

        const type = form.querySelector('[name="donation_type"]')?.value;

        // Client-side validation
        if (type === 'cash') {
            const amount = parseFloat(form.querySelector('[name="amount"]')?.value || '0');
            if (isNaN(amount) || amount < 10) {
                showToast('Minimum donation amount is ₱10.00', 'error'); return;
            }
        } else if (type === 'item') {
            if (!form.querySelector('[name="item_name"]')?.value.trim()) {
                showToast('Please enter the item name', 'error'); return;
            }
        } else if (type === 'service') {
            if (!form.querySelector('[name="service_description"]')?.value.trim()) {
                showToast('Please describe the service', 'error'); return;
            }
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const data = await apiFetch('../api/donate.php', { method: 'POST', body: new FormData(form) });
            if (data.success) {
                showToast(data.message || 'Donation submitted!');
                closeModal('donationModal');
                if (data.redirect) {
                    const sessName = (window._SESS || '').replace('?_sess=', '');
                    const sep      = data.redirect.includes('?') ? '&' : '?';
                    const sessUrl  = sessName ? data.redirect + sep + '_sess=' + sessName : data.redirect;
                    setTimeout(() => { window.location.href = sessUrl; }, 1200);
                } else if (typeof loadMyDonations === 'function') {
                    loadMyDonations();
                }
            } else {
                showToast(data.message || 'Donation failed', 'error');
            }
        } catch {
            showToast('Network error. Please try again.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Confirm Donation';
        }
    }
};
