<?php
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    echo "Connected successfully";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
