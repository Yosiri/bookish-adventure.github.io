<?php
// Auth/Middleware.php
// Bootstraps the correct role-specific session on every protected page.
// The _sess=UMDC_* query param (set by login redirect) tells us which
// session cookie to open, so multi-tab multi-role testing works.
defined('UMDC_APP') or define('UMDC_APP', true);
require_once __DIR__ . '/../Config/security.php';
umdc_session_start();   // auto-detects correct session via _sess or cookie
