<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1', 'root', '40500273');
    $pdo->exec('CREATE DATABASE IF NOT EXISTS bcn_pymes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "✓ Base de datos 'bcn_pymes' creada exitosamente\n";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
