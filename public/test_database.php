
<?php

require_once __DIR__ . '/../config/database.php';

echo '<h2>HP COMPUTER</h2>';

echo '<p style="color: green;">Ket noi MySQL thanh cong!</p>';

// Kiem tra database dang ket noi
$stmt = $pdo->query("SELECT DATABASE() AS ten_database");

$result = $stmt->fetch();

echo '<p>Database: ' . htmlspecialchars(
    $result['ten_database'],
    ENT_QUOTES,
    'UTF-8'
) . '</p>';

// Dem so bang trong database
$stmt = $pdo->query("
    SELECT COUNT(*) AS tong_so_bang
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_TYPE = 'BASE TABLE'
");

$result = $stmt->fetch();

echo '<p>Tong so bang: ' . $result['tong_so_bang'] . '</p>';

?>
