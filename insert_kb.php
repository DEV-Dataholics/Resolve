<?php
/**
 * Helper to programmatically load the new .md knowledgebase articles into resolve DB.
 */
header('Content-Type: application/json');

$host = '127.0.0.1'; 
$db = 'noodluis_resolve'; 
$user = 'noodluis_DEV_resolve'; 
$pass = '+wxM$&RkY^Ye';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $articles = [
        [
            'file' => 'Site5_Shared_Hosting_Deployment_Guideline.md',
            'title' => 'Guia General de Despliegue en Servidores Compartidos (Site5)',
            'slug' => 'guia-general-despliegue-site5',
            'category' => 'Procesos Internos'
        ],
        [
            'file' => 'SITE5_PLAYBOOK.md',
            'title' => 'Playbook de Despliegue en Site5 - Warhorse',
            'slug' => 'playbook-despliegue-site5-warhorse',
            'category' => 'Manuales de Proyecto'
        ]
    ];

    $results = [];

    foreach ($articles as $art) {
        if (!file_exists($art['file'])) {
            $results[] = ['file' => $art['file'], 'status' => 'error', 'message' => 'File not found'];
            continue;
        }

        $raw = file_get_contents($art['file']);
        $html = '<pre style="white-space:pre-wrap;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;">'
              . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8')
              . '</pre>';

        $stmt = $pdo->prepare("
            INSERT INTO kb_articles (title, slug, category, content, status, author_id, created_at, updated_at)
            VALUES (:title, :slug, :category, :content, 'published', NULL, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                category = VALUES(category),
                content = VALUES(content),
                status = VALUES(status),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':title' => $art['title'],
            ':slug' => $art['slug'],
            ':category' => $art['category'],
            ':content' => $html
        ]);

        $results[] = ['file' => $art['file'], 'status' => 'success'];
    }

    echo json_encode(['ok' => true, 'results' => $results]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
