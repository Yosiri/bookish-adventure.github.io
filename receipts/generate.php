<?php
// receipts/generate.php
defined('UMDC_APP') or define('UMDC_APP', true);

function generateReceipt(PDO $pdo, int $donation_id): ?string {
    // Check if receipt already exists
    $check = $pdo->prepare("SELECT receipt_number FROM receipts WHERE donation_id = ?");
    $check->execute([$donation_id]);
    $existing = $check->fetchColumn();
    if ($existing) return $existing;

    // Generate unique receipt number
    $receiptNo = 'UMDC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

    $pdo->prepare("INSERT INTO receipts (donation_id, receipt_number) VALUES (?, ?)")
        ->execute([$donation_id, $receiptNo]);

    // Create ledger hash
    $hash = hash('sha256', $donation_id . $receiptNo . microtime(true) . random_bytes(16));
    $pdo->prepare("INSERT INTO ledgers (donation_id, public_hash) VALUES (?, ?)")
        ->execute([$donation_id, $hash]);

    return $receiptNo;
}
