<?php
/**
 * ============================================================
 * DATAHOLICS RESOLVE — Setup Script (Browser Version)
 *
 * USO:
 *   Visita: https://resolve.dataholics.com.mx/setup_admin.php?token=DH2024setup
 *
 * ELIMINA este archivo del servidor después de usarlo.
 * ============================================================
 */

// ---- TOKEN DE SEGURIDAD ----
// Visita la URL con ?token=DH2024setup para ejecutar el script
define('SECRET_TOKEN', 'DH2024setup');

if (!isset($_GET['token']) || $_GET['token'] !== SECRET_TOKEN) {
    http_response_code(403);
    die('<h2 style="color:red;font-family:sans-serif">403 — Acceso denegado.<br><small>Agrega ?token=DH2024setup a la URL.</small></h2>');
}

// ---- CONFIGURACIÓN DB ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'noodluis_resolve');
define('DB_USER', 'noodluis_DEV_resolve');
define('DB_PASS', '+wxM$&RkY^Ye');

// ---- DATOS DEL ADMIN ----
define('ADMIN_NAME',  'Luis Chihuahua');
define('ADMIN_EMAIL', 'admin@dataholics.com.mx');
define('ADMIN_PASS',  'Dataholics2024!');

header('Content-Type: text/html; charset=utf-8');
echo '<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;line-height:2}
      .ok{color:#4ade80} .err{color:#f87171} .warn{color:#fb923c}</style>';
echo '<h2 style="color:#60a5fa">🔧 Dataholics Resolve — Setup Admin</h2>';

// ---- CONEXIÓN BD ----
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo '<p class="ok">✅ Conexión a la base de datos exitosa.</p>';
} catch (PDOException $e) {
    echo '<p class="err">❌ Error de conexión: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// ---- VERIFICAR SI YA EXISTE UN ADMIN ----
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
if ($stmt->fetchColumn() > 0) {
    echo '<p class="warn">⚠️ Ya existe un usuario Admin. El script NO creará duplicados.</p>';
    echo '<p>→ Ve a <a href="/index.html" style="color:#60a5fa">resolve.dataholics.com.mx</a> e inicia sesión.</p>';
    exit;
}

// ---- OBTENER / CREAR COMPANY INTERNA ----
$stmt = $pdo->query("SELECT id FROM companies WHERE is_internal = 1 LIMIT 1");
$companyId = $stmt->fetchColumn();

if (!$companyId) {
    $pdo->exec("INSERT INTO companies (name, is_internal, status) VALUES ('Dataholics', 1, 'active')");
    $companyId = $pdo->lastInsertId();
    echo '<p class="ok">✅ Empresa "Dataholics" creada (ID: ' . $companyId . ')</p>';
} else {
    echo '<p class="ok">✅ Empresa interna encontrada (ID: ' . $companyId . ')</p>';
}

// ---- CREAR USUARIO ADMIN ----
$hashedPassword = password_hash(ADMIN_PASS, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (company_id, name, email, password, role, status)
    VALUES (?, ?, ?, ?, 'admin', 'active')
");
$stmt->execute([$companyId, ADMIN_NAME, ADMIN_EMAIL, $hashedPassword]);
$userId = $pdo->lastInsertId();

echo '<p class="ok">✅ Usuario Admin creado exitosamente.</p>';
echo '<hr style="border-color:#334155;margin:1rem 0">';
echo '<p><b style="color:#60a5fa">Credenciales de acceso:</b></p>';
echo '<p>📧 Email: <b>' . ADMIN_EMAIL . '</b></p>';
echo '<p>🔑 Password: <b>' . ADMIN_PASS . '</b></p>';
echo '<p>🆔 ID de usuario: ' . $userId . '</p>';
echo '<hr style="border-color:#334155;margin:1rem 0">';
echo '<p class="warn">⚠️ <b>IMPORTANTE:</b> Elimina este archivo del servidor ahora.</p>';
echo '<p>→ <a href="/index.html" style="color:#60a5fa;font-size:1.1em">Ir al Login →</a></p>';
