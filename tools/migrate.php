<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;

$pdo = DB::connection();

$pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$applied = array_column($pdo->query('SELECT filename FROM schema_migrations')->fetchAll(), 'filename');

$dir = dirname(__DIR__) . '/database/migrations';
$files = glob($dir . '/*.sql') ?: [];
sort($files);

$fresh = in_array('--fresh', $argv, true);

if ($fresh) {
    echo "Dropping all tables...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $applied = [];
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    SQL);
}

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }
    echo "Applying $name ... ";
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        DB::insert('schema_migrations', ['filename' => $name]);
        echo "OK\n";
        $ran++;
    } catch (Throwable $e) {
        echo "FAILED\n";
        echo $e->getMessage() . "\n";
        exit(1);
    }
}

echo $ran === 0 ? "Nothing to migrate.\n" : "$ran migration(s) applied.\n";
