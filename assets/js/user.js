/* =============================================================
   UMDC – User Dashboard JS
   ============================================================= */
'use strict';

let currentSection = null;

function loadSection(section) {
    currentSection = section;

    // Update active sidebar link
    document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
    const activeLink = Array.from(document.querySelectorAll('.sidebar-link'))
        .find(l => l.textContent.trim().toLowerCase().includes(section.replace('-', ' ')));
    if (activeLink) activeLink.classList.add('active');

    const content = document.getElementById('dynamic-content');
    if (!content) return;
    content.innerHTML = loadingHTML();

    switch (section) {
        case 'campaigns':    loadCampaigns(content); break;
        case 'my-donations': loadMyDonations(content); break;
        case 'leaderboard':  loadLeaderboard(content); break;
        case 'transparency': loadTransparency(content); break;
        case 'profile':      loadProfile(content); break;
        default:             loadCampaigns(content);
    }
}

// ── Campaigns ──────────────────────────────────────────────────
function loadCampaigns(el) {
    apiFetch('../api/campaigns.php')
        .then(data => {
            if (!data.success) throw new Error(data.message);

            if (!data.campaigns.length) {
                el.innerHTML = emptyHTML('fa-hand-holding-heart', 'No active campaigns', 'Check back soon!');
                return;
            }

            const categoryIcons = { education:'📚', health:'🏥', disaster:'🆘', environment:'🌿', community:'🤝', food:'🍱' };
            const grid = document.createElement('div');
            grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:20px;';

            data.campaigns.forEach(c => {
                const icon = categoryIcons[c.category?.toLowerCase()] || '🤝';
                const pct  = c.target_amount > 0 ? Math.min(100, (c.current_amount / c.target_amount) * 100) : 0;
                const card = document.createElement('div');
                card.className = 'dash-campaign-card';
                card.innerHTML = `
                    <div class="thumb">${icon}</div>
                    <div class="c-body">
                        <div class="c-cat">${escapeHtml(c.category || 'General')} · ${escapeHtml(c.org_name || '')}</div>
                        <div class="c-title">${escapeHtml(c.title)}</div>
                        <div class="c-desc">${escapeHtml(c.description || '')}</div>
                        <div class="c-amounts">
                            <span class="c-raised">${formatCurrency(c.current_amount)}</span>
                            <span class="c-goal">Goal: ${formatCurrency(c.target_amount)}</span>
                        </div>
                        ${progressBar(c.current_amount, c.target_amount)}
                        <div style="font-size:.72rem;color:var(--color-muted);margin-top:6px;">
                            ${pct.toFixed(0)}% funded · ${c.donor_count} donor${c.donor_count !== 1 ? 's' : ''}
                        </div>
                    </div>
                    <div class="c-actions">
                        <button class="btn-primary" style="font-size:.8rem;padding:8px 14px;flex:1;justify-content:center;"
                                onclick="DonationForm.open(${c.campaign_id}, '${escapeHtml(c.title).replace(/'/g, "\\'")}')">
                            <i class="fas fa-heart"></i> Donate
                        </button>
                    </div>
                `;
                grid.appendChild(card);
            });

            el.innerHTML = `
                <div class="card-header" style="padding:0 0 16px;">
                    <h5 style="margin:0;">Active Campaigns</h5>
                    <span style="font-size:.82rem;color:var(--color-muted);">${data.campaigns.length} campaign${data.campaigns.length !== 1 ? 's' : ''}</span>
                </div>`;
            el.appendChild(grid);
        })
        .catch(err => {
            el.innerHTML = emptyHTML('fa-exclamation-circle', 'Failed to load campaigns', err.message);
        });
}

// ── My Donations ───────────────────────────────────────────────
window.loadMyDonations = function (el) {
    if (!el) el = document.getElementById('dynamic-content');
    if (!el) return;
    el.innerHTML = loadingHTML('Loading your donations...');

    apiFetch('../api/my_donation.php')
        .then(data => {
            if (!data.success) throw new Error(data.message);

            if (!data.donations.length) {
                el.innerHTML = emptyHTML('fa-receipt', 'No donations yet', 'Browse campaigns and make your first donation!');
                return;
            }

            const typeIcons = { cash:'fa-peso-sign', item:'fa-box', service:'fa-screwdriver-wrench' };
            let rows = data.donations.map(d => `
                <tr>
                    <td><i class="fas ${typeIcons[d.donation_type] || 'fa-gift'}" style="margin-right:6px;color:var(--brand-primary)"></i>${d.donation_type}</td>
                    <td>${escapeHtml(d.campaign_title)}</td>
                    <td class="amount-mono">${d.amount ? formatCurrency(d.amount) : '—'}</td>
                    <td><span class="badge-pill badge-${d.status}">${d.status}</span></td>
                    <td>${formatDate(d.created_at)}</td>
                    <td>
                        ${d.status === 'pending' && d.donation_type === 'cash'
                            ? `<a href="../donations/cash.php?id=${d.donation_id}" class="btn-primary" style="font-size:.75rem;padding:5px 12px;">Complete</a>`
                            : ''}
                        ${d.receipt_number
                            ? `<button class="btn-secondary" style="font-size:.75rem;padding:5px 12px;" onclick="window.open('../receipts/view.php?id=${d.donation_id}','_blank')"><i class="fas fa-receipt"></i> Receipt</button>`
                            : ''}
                        ${d.public_hash
                            ? `<button class="btn-secondary" style="font-size:.75rem;padding:5px 12px;" onclick="window.open('../transactions/verify.php?hash=${escapeHtml(d.public_hash)}','_blank')"><i class="fas fa-link"></i> Verify</button>`
                            : ''}
                    </td>
                </tr>`).join('');

            el.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h5>My Donations</h5>
                        <span style="font-size:.82rem;color:var(--color-muted);">${data.donations.length} record${data.donations.length !== 1 ? 's' : ''}</span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="table-custom">
                            <thead><tr>
                                <th>Type</th><th>Campaign</th><th>Amount</th>
                                <th>Status</th><th>Date</th><th>Actions</th>
                            </tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                </div>`;
        })
        .catch(err => {
            el.innerHTML = emptyHTML('fa-exclamation-circle', 'Failed to load', err.message);
        });
};

// ── Leaderboard ────────────────────────────────────────────────
function loadLeaderboard(el) {
    apiFetch('../analytics/leaderboard.php')
        .then(data => {
            if (!data.success) throw new Error(data.message);
            const rankClass = ['gold','silver','bronze'];
            let items = (data.leaderboard || []).map((u, i) => `
                <div class="leaderboard-item">
                    <div class="rank ${rankClass[i] || ''}">${i < 3 ? ['🥇','🥈','🥉'][i] : `#${i+1}`}</div>
                    <div class="lb-info">
                        <div class="lb-name">${escapeHtml(u.donor_name)}</div>
                        <div class="lb-sub">${u.donation_count} donation${u.donation_count !== 1 ? 's' : ''}</div>
                    </div>
                    <div class="lb-amount">${formatCurrency(u.total_donated)}</div>
                </div>`).join('');

            el.innerHTML = `
                <div class="card">
                    <div class="card-header"><h5>🏆 Top Donors</h5></div>
                    ${items || emptyHTML('fa-trophy', 'No donors yet')}
                </div>`;
        })
        .catch(() => {
            el.innerHTML = emptyHTML('fa-exclamation-circle', 'Failed to load leaderboard');
        });
}

// ── Transparency ────────────────────────────────────────────────
function loadTransparency(el) {
    apiFetch('../api/transparency.php')
        .then(data => {
            if (!data.success) throw new Error(data.message);
            let rows = (data.records || []).map(r => `
                <tr>
                    <td class="amount-mono" style="font-size:.75rem;">${escapeHtml(r.public_hash || '—')}</td>
                    <td>${escapeHtml(r.campaign_title)}</td>
                    <td class="amount-mono">${formatCurrency(r.amount)}</td>
                    <td>${formatDate(r.created_at)}</td>
                </tr>`).join('');

            el.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-shield-alt" style="color:var(--color-success)"></i> Public Transaction Ledger</h5>
                    </div>
                    <div class="alert alert-info" style="margin:16px 20px 0;">
                        <i class="fas fa-info-circle"></i>
                        <span>All completed donations are hashed and recorded publicly for full transparency.</span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="table-custom">
                            <thead><tr><th>Hash</th><th>Campaign</th><th>Amount</th><th>Date</th></tr></thead>
                            <tbody>${rows || '<tr><td colspan="4" style="text-align:center;color:var(--color-muted);padding:30px;">No records yet</td></tr>'}</tbody>
                        </table>
                    </div>
                </div>`;
        })
        .catch(() => {
            el.innerHTML = emptyHTML('fa-exclamation-circle', 'Failed to load transparency data');
        });
}

// ── Profile ────────────────────────────────────────────────────
function loadProfile(el) {
    apiFetch('../api/profile.php')
        .then(data => {
            if (!data.success) throw new Error(data.message);
            const u = data.user;
            const s = data.stats;

            el.innerHTML = `
                <div class="two-col">
                    <div class="card">
                        <div class="card-header"><h5>Edit Profile</h5></div>
                        <div class="card-body">
                            <form id="profileForm" novalidate>
                                <input type="hidden" name="csrf_token" value="${escapeHtml(document.querySelector('meta[name="csrf-token"]')?.content || '')}">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                                    <div class="form-group">
                                        <label class="form-label">First Name</label>
                                        <input class="form-input" type="text" name="first_name" value="${escapeHtml(u.first_name)}" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Last Name</label>
                                        <input class="form-input" type="text" name="last_name" value="${escapeHtml(u.last_name)}" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email Address</label>
                                    <input class="form-input" type="email" name="email" value="${escapeHtml(u.email)}" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone (optional)</label>
                                    <input class="form-input" type="tel" name="phone" value="${escapeHtml(u.phone || '')}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Address (optional)</label>
                                    <textarea class="form-textarea" name="address" rows="2">${escapeHtml(u.address || '')}</textarea>
                                </div>
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h5>Account Statistics</h5></div>
                        <div class="card-body">
                            <div style="text-align:center;margin-bottom:24px;">
                                <div class="profile-avatar-lg" style="margin:0 auto;">${escapeHtml((u.first_name[0]+u.last_name[0]).toUpperCase())}</div>
                                <h5 style="margin-top:12px;font-weight:700;">${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)}</h5>
                                <div style="color:var(--color-muted);font-size:.85rem;">${escapeHtml(u.email)}</div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div style="background:var(--color-surface-2);border-radius:10px;padding:16px;text-align:center;">
                                    <div style="font-size:.7rem;color:var(--color-muted);text-transform:uppercase;letter-spacing:.5px;">Total Donated</div>
                                    <div style="font-size:1.4rem;font-weight:800;color:var(--color-success);font-family:var(--font-mono);">${formatCurrency(s.total_donated)}</div>
                                </div>
                                <div style="background:var(--color-surface-2);border-radius:10px;padding:16px;text-align:center;">
                                    <div style="font-size:.7rem;color:var(--color-muted);text-transform:uppercase;letter-spacing:.5px;">Donations</div>
                                    <div style="font-size:1.4rem;font-weight:800;color:var(--brand-primary);">${s.donation_count}</div>
                                </div>
                                <div style="background:var(--color-surface-2);border-radius:10px;padding:16px;text-align:center;">
                                    <div style="font-size:.7rem;color:var(--color-muted);text-transform:uppercase;letter-spacing:.5px;">Campaigns</div>
                                    <div style="font-size:1.4rem;font-weight:800;color:var(--brand-primary);">${s.campaigns_supported}</div>
                                </div>
                                <div style="background:var(--color-surface-2);border-radius:10px;padding:16px;text-align:center;">
                                    <div style="font-size:.7rem;color:var(--color-muted);text-transform:uppercase;letter-spacing:.5px;">Member Since</div>
                                    <div style="font-size:.95rem;font-weight:700;">${formatDate(u.created_at)}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;

            document.getElementById('profileForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = 'Saving...';
                try {
                    const result = await apiFetch('../api/update_profile.php', { method: 'POST', body: new FormData(this) });
                    if (result.success) showToast('Profile updated!');
                    else showToast(result.message || 'Update failed', 'error');
                } catch { showToast('Network error', 'error'); }
                finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Changes'; }
            });
        })
        .catch(() => {
            el.innerHTML = emptyHTML('fa-exclamation-circle', 'Failed to load profile');
        });
}
