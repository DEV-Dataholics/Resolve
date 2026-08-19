<?php
/**
 * Read latest CI4 log entry
 * Token: ?token=DIAG_LOG_2026
 */
if (($_GET['token'] ?? '') !== 'DIAG_LOG_2026') { http_response_code(403); die('forbidden'); }

$logDir = '/home1/noodluis/api_resolve/writable/logs/';
$files = glob($logDir . 'log-*.log');
if (empty($files)) { echo json_encode(['ok' => false, 'msg' => 'No log files found', 'dir' => $logDir]); exit; }

usort($files, fn($a,$b) => filemtime($b) - filemtime($a));
$latest = $files[0];
$content = file_get_contents($latest);
// Get last 3000 chars
$snippet = substr($content, -3000);
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'file' => basename($latest), 'tail' => $snippet]);
