<?php
/**
 * Quick diagnostic for /api/kb/categories
 * Token: ?token=DIAG_KB_2026
 */
if (($_GET['token'] ?? '') !== 'DIAG_KB_2026') { http_response_code(403); die('forbidden'); }

$host = 'localhost'; $db = 'noodluis_resolve'; $user = 'noodluis_DEV_resolve'; $pass = '+wxM$&RkY^Ye';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $rows = $pdo->query("SELECT DISTINCT category FROM kb_articles WHERE category IS NOT NULL AND category != '' AND status = 'published' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'categories' => $rows, 'count' => count($rows)]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
