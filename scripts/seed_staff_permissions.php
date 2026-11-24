<?php
// Run from the terminal only; this is not a public setup endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    // Refuse to assign rights when the expected role IDs have changed.
    $roles = $pdo->query('SELECT ma_vai_tro, ten_vai_tro FROM vai_tro WHERE ma_vai_tro IN (1, 3, 5)')
        ->fetchAll(PDO::FETCH_KEY_PAIR);
    $expected = [1 => 'Admin', 3 => 'Nhan vien ban hang', 5 => 'Nhan vien ky thuat'];
    foreach ($expected as $id => $name) {
        if (($roles[$id] ?? null) !== $name) {
            throw new RuntimeException('Unexpected role at ID ' . $id . '. Check vai_tro before seeding.');
        }
    }

    $sql = file_get_contents(__DIR__ . '/../database/seeds/001_staff_permissions.sql');
    if ($sql === false) {
        throw new RuntimeException('Cannot read permission seed.');
    }
    $pdo->beginTransaction();
    // The seed contains only simple INSERT statements, no stored procedures.
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
    $pdo->commit();
    echo "Staff permissions applied successfully. Existing grants were preserved.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
