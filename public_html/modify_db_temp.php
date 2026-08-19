<?php
$pdo = new PDO("mysql:host=localhost;dbname=noodluis_resolve;charset=utf8mb4", "noodluis_DEV_resolve", "+wxM$&RkY^Ye");

try {
   $pdo->exec("ALTER TABLE companies ADD COLUMN logo_url VARCHAR(255) NULL AFTER name");
   echo "Companies logo_url added.<br>";
} catch(Exception $e) {
   echo "Err logo_url: " . $e->getMessage() . "<br>";
}

try {
   $pdo->exec("ALTER TABLE companies ADD COLUMN brand_color VARCHAR(50) DEFAULT '#2563eb' AFTER logo_url");
   echo "Companies brand_color added.<br>";
} catch(Exception $e) {
   echo "Err brand_color: " . $e->getMessage() . "<br>";
}

try {
   $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'servicedesk', 'client', 'client_admin') NOT NULL DEFAULT 'client'");
   echo "Users role altered.<br>";
} catch(Exception $e) {
   echo "Err users role: " . $e->getMessage() . "<br>";
}

