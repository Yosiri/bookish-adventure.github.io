<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMDC – Unified Monetary Donation Center</title>
    <meta name="description" content="UMDC connects donors with verified organizations and transparent campaigns. Give cash, items, or services to causes that matter.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/public.css">
</head>
<body>

<!-- ── Navbar ─────────────────────────────────────────────── -->
<header class="pub-navbar">
    <div class="brand-name">UMDC <span style="font-weight:400;color:var(--color-muted);font-size:.85rem">Platform</span></div>
    <nav>
        <a href="#campaigns">Campaigns</a>
        <a href="#how-it-works">How It Works</a>
        <a href="#transparency">Transparency</a>
        <a href="#about">About</a>
    </nav>
    <div class="nav-cta">
        <a href="/public/login.html" class="btn-secondary" style="padding:8px 18px;font-size:.875rem;">Sign In</a>
        <a href="/public/register.html" class="btn-primary" style="padding:8px 18px;font-size:.875rem;">Get Started</a>
    </div>
</header>

<!-- ── Hero ──────────────────────────────────────────────── -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-shield-alt"></i>
            Verified &amp; Transparent Giving
        </div>
        <h1>Every Peso<br><span>Makes a Difference</span></h1>
        <p class="hero-desc">
            UMDC connects generous donors with verified organizations running real campaigns.
            Donate cash, items, or services — and track every peso with full transparency.
        </p>
        <div class="hero-actions">
            <a href="/public/register.html" class="btn-primary" style="padding:14px 28px;font-size:1rem;">
                <i class="fas fa-heart"></i> Start Donating
            </a>
            <a href="#campaigns" class="btn-secondary" style="padding:14px 28px;font-size:1rem;">
                <i class="fas fa-search"></i> Browse Campaigns
            </a>
        </div>
    </div>
    <div class="hero-visual">
        <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:24px;padding:32px;box-shadow:var(--shadow-lg);min-width:340px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                <div style="width:44px;height:44px;border-radius:50%;background:var(--brand-gradient);display:flex;align-items:center;justify-content:center;color:white;font-size:1.1rem;"><i class="fas fa-hand-holding-heart"></i></div>
                <div>
                    <div style="font-weight:700;font-size:.95rem;">Recent Donation</div>
                    <div style="font-size:.75rem;color:var(--color-muted);">Just now</div>
                </div>
                <div style="margin-left:auto;color:var(--color-success);font-weight:700;font-family:var(--font-mono);">+₱500</div>
            </div>
            <div style="background:var(--color-surface-2);border-radius:12px;padding:16px;margin-bottom:16px;">
                <div style="font-size:.75rem;color:var(--color-muted);margin-bottom:6px;">Typhoon Relief Fund</div>
                <div class="progress-wrap" style="margin-bottom:8px;"><div class="progress-fill" style="width:72%"></div></div>
                <div style="display:flex;justify-content:space-between;font-size:.78rem;">
                    <span style="color:var(--color-success);font-weight:700;font-family:var(--font-mono);">₱72,400</span>
                    <span style="color:var(--color-muted);">of ₱100,000</span>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;text-align:center;">
                <div style="background:rgba(59,111,240,.06);border-radius:10px;padding:12px;">
                    <div style="font-size:.7rem;color:var(--color-muted);margin-bottom:4px;">Donors</div>
                    <div style="font-weight:800;font-size:1.1rem;color:var(--brand-primary);">342</div>
                </div>
                <div style="background:rgba(5,150,105,.06);border-radius:10px;padding:12px;">
                    <div style="font-size:.7rem;color:var(--color-muted);margin-bottom:4px;">Goal %</div>
                    <div style="font-weight:800;font-size:1.1rem;color:var(--color-success);">72%</div>
                </div>
                <div style="background:rgba(217,119,6,.06);border-radius:10px;padding:12px;">
                    <div style="font-size:.7rem;color:var(--color-muted);margin-bottom:4px;">Days Left</div>
                    <div style="font-weight:800;font-size:1.1rem;color:var(--color-warning);">14</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Stats Bar ──────────────────────────────────────────── -->
<div class="stats-bar">
    <div class="stats-bar-item">
        <span class="stats-bar-value" data-count="2500000" data-prefix="₱">₱0</span>
        <span class="stats-bar-label">Total Raised</span>
    </div>
    <div class="stats-bar-item">
        <span class="stats-bar-value" data-count="150">0</span>
        <span class="stats-bar-label">Active Campaigns</span>
    </div>
    <div class="stats-bar-item">
        <span class="stats-bar-value" data-count="5000">0</span>
        <span class="stats-bar-label">Donors</span>
    </div>
    <div class="stats-bar-item">
        <span class="stats-bar-value" data-count="48">0</span>
        <span class="stats-bar-label">Verified Organizations</span>
    </div>
</div>

<!-- ── Active Campaigns ───────────────────────────────────── -->
<section class="section" id="campaigns">
    <div class="section-header">
        <div class="label">Open Campaigns</div>
        <h2>Support a Cause Today</h2>
        <p>Browse verified campaigns — sign up to donate and track your impact in real time.</p>
    </div>
    <div id="public-campaigns-grid" class="campaigns-grid">
        <!-- Populated by public.js -->
    </div>
    <div style="text-align:center;margin-top:40px;">
        <a href="/public/register.html" class="btn-primary" style="padding:13px 32px;">
            <i class="fas fa-plus"></i> See All Campaigns &amp; Donate
        </a>
    </div>
</section>

<!-- ── How It Works ──────────────────────────────────────── -->
<section class="section section-alt" id="how-it-works">
    <div class="section-header">
        <div class="label">Process</div>
        <h2>Simple, Transparent Giving</h2>
        <p>From registration to impact report — we make sure every donation counts.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:24px;position:relative;">
        <div style="position:absolute;top:36px;left:12%;right:12%;height:2px;background:linear-gradient(90deg,var(--brand-primary),var(--brand-secondary));opacity:.15;z-index:0;"></div>
        <?php
        $steps = [
            ['1','fa-user-plus','Create Account','Register as a donor or organization in seconds. No fees, no barriers.'],
            ['2','fa-search','Find a Campaign','Browse verified campaigns by category, organization, or urgency.'],
            ['3','fa-hand-holding-heart','Donate','Give cash, items, or services. Every type of help matters.'],
            ['4','fa-chart-line','Track Impact','Follow real-time updates and get receipts for every donation.'],
        ];
        foreach ($steps as $s): ?>
        <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:28px;text-align:center;position:relative;z-index:1;box-shadow:var(--shadow-sm);">
            <div style="width:56px;height:56px;background:var(--brand-gradient);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.2rem;margin:0 auto 16px;">
                <i class="fas <?= $s[1] ?>"></i>
            </div>
            <div style="position:absolute;top:16px;right:16px;font-size:.65rem;font-weight:800;color:var(--brand-primary);background:rgba(59,111,240,.1);border-radius:20px;padding:2px 8px;">Step <?= $s[0] ?></div>
            <h4 style="font-weight:700;margin-bottom:8px;"><?= $s[2] ?></h4>
            <p style="font-size:.855rem;color:var(--color-muted);line-height:1.6;"><?= $s[3] ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ── Features ───────────────────────────────────────────── -->
<section class="section" id="transparency">
    <div class="section-header">
        <div class="label">Features</div>
        <h2>Built for Trust</h2>
        <p>Every feature is designed to make your donation safe, trackable, and impactful.</p>
    </div>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
            <h4>Verified Organizations</h4>
            <p>Every organization goes through document verification before they can launch a campaign.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-receipt"></i></div>
            <h4>Digital Receipts</h4>
            <p>Automatically generated receipts for every cash donation — shareable and downloadable.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-link"></i></div>
            <h4>Blockchain Ledger</h4>
            <p>Every transaction is hashed and recorded on a public ledger for full auditability.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-box"></i></div>
            <h4>Item Donations</h4>
            <p>Donate physical goods — we arrange courier pickup and track delivery status in real time.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
            <h4>Live Analytics</h4>
            <p>See campaign progress, donor leaderboards, and organization impact dashboards.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-flag"></i></div>
            <h4>Fraud Detection</h4>
            <p>Our admin team actively monitors and flags suspicious activity to protect every donor.</p>
        </div>
    </div>
</section>

<!-- ── CTA Strip ──────────────────────────────────────────── -->
<section class="cta-strip" id="about">
    <h2>Ready to Make an Impact?</h2>
    <p>Join thousands of donors already changing lives through UMDC.</p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
        <a href="/public/register.html" class="btn-white">
            <i class="fas fa-heart"></i> Donate Now — It's Free
        </a>
        <a href="/public/register.html?role=organization" class="btn-white" style="background:transparent;color:white;border:2px solid rgba(255,255,255,.5);">
            <i class="fas fa-building"></i> Register Your Organization
        </a>
    </div>
</section>

<!-- ── Footer ────────────────────────────────────────────── -->
<footer class="pub-footer">
    <div class="footer-grid">
        <div class="footer-brand">
            <div class="brand-name">UMDC Platform</div>
            <p>A unified platform for transparent, verified, and impactful donations across the Philippines.</p>
        </div>
        <div class="footer-col">
            <h5>Platform</h5>
            <a href="#campaigns">Campaigns</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#transparency">Transparency</a>
        </div>
        <div class="footer-col">
            <h5>Account</h5>
            <a href="/public/login.html">Sign In</a>
            <a href="/public/register.html">Create Account</a>
            <a href="/public/register.html?role=organization">For Organizations</a>
        </div>
        <div class="footer-col">
            <h5>Legal</h5>
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Cookie Policy</a>
        </div>
    </div>
    <div class="footer-bottom">
        <span>&copy; <?= date('Y') ?> UMDC Platform. All rights reserved.</span>
        <span>Built with ❤️ for Filipino communities</span>
    </div>
</footer>

<script src="/assets/js/global.js"></script>
<script src="/assets/js/public.js"></script>
</body>
</html>
