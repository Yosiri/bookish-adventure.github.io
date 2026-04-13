/* =============================================================
   UMDC – Public Landing Page JS
   ============================================================= */
'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ── Animated counter ───────────────────────────────────────
    function animateCounter(el, target, prefix = '', suffix = '') {
        const duration = 1800;
        const start    = performance.now();
        const from     = 0;

        function step(now) {
            const elapsed  = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased    = 1 - Math.pow(1 - progress, 3);
            const value    = Math.floor(from + (target - from) * eased);
            el.textContent = prefix + value.toLocaleString() + suffix;
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // ── Intersection Observer for counters ─────────────────────
    const counters = document.querySelectorAll('[data-count]');
    if (counters.length && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    const el     = e.target;
                    const target = parseInt(el.dataset.count, 10);
                    const prefix = el.dataset.prefix || '';
                    const suffix = el.dataset.suffix || '';
                    animateCounter(el, target, prefix, suffix);
                    io.unobserve(el);
                }
            });
        }, { threshold: 0.3 });
        counters.forEach(c => io.observe(c));
    }

    // ── Navbar scroll effect ───────────────────────────────────
    const navbar = document.querySelector('.pub-navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.style.boxShadow = window.scrollY > 20
                ? '0 4px 20px rgba(0,0,0,.1)'
                : '';
        }, { passive: true });
    }

    // ── Load public campaigns ──────────────────────────────────
    const grid = document.getElementById('public-campaigns-grid');
    if (grid) loadPublicCampaigns(grid);

    function loadPublicCampaigns(container) {
        container.innerHTML = loadingHTML('Loading campaigns...');

        fetch('/api/public_campaigns.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.campaigns.length) {
                    container.innerHTML = emptyHTML('fa-hand-holding-heart', 'No campaigns yet', 'Check back soon!');
                    return;
                }
                container.innerHTML = '';
                data.campaigns.slice(0, 6).forEach(c => {
                    container.appendChild(buildCampaignCard(c));
                });
            })
            .catch(() => {
                container.innerHTML = emptyHTML('fa-exclamation-circle', 'Could not load campaigns');
            });
    }

    function buildCampaignCard(c) {
        const pct     = c.target_amount > 0 ? Math.min(100, (c.current_amount / c.target_amount) * 100) : 0;
        const emojis  = { education: '📚', health: '🏥', disaster: '🆘', environment: '🌿', community: '🤝' };
        const icon    = emojis[c.category?.toLowerCase()] || '🤝';

        const div = document.createElement('div');
        div.className = 'pub-campaign-card';
        div.innerHTML = `
            <div class="card-thumb">${icon}</div>
            <div class="card-body">
                <div class="category-tag">${escapeHtml(c.category || 'General')}</div>
                <h4>${escapeHtml(c.title)}</h4>
                <p class="desc">${escapeHtml(c.description || '')}</p>
                <div class="amounts">
                    <span class="raised">${formatCurrency(c.current_amount)}</span>
                    <span class="goal">of ${formatCurrency(c.target_amount)}</span>
                </div>
                ${progressBar(c.current_amount, c.target_amount)}
            </div>
            <div class="locked-overlay" style="position:relative;">
                <div class="lock-cta">
                    <i class="fas fa-lock"></i>
                    <strong>Sign in to donate</strong>
                    <p>Create a free account to support this campaign</p>
                    <a href="/public/register.html" class="btn-primary" style="font-size:.82rem;padding:8px 18px;">Get Started</a>
                </div>
            </div>
        `;
        return div;
    }

    // ── Smooth scroll for anchor links ─────────────────────────
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ── Mobile nav toggle ──────────────────────────────────────
    const mobileNavBtn = document.getElementById('mobile-nav-btn');
    const mobileNavMenu = document.getElementById('mobile-nav-menu');
    if (mobileNavBtn && mobileNavMenu) {
        mobileNavBtn.addEventListener('click', () => {
            mobileNavMenu.classList.toggle('open');
        });
    }
});
