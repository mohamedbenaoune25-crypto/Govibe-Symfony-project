<?php
$host = '127.0.0.1';
$port = '3306';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
    $stmt = $pdo->query("SHOW DATABASES");
    while ($db = $stmt->fetchColumn()) {
        if (in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys'])) continue;
        echo "Database: $db\n";
        $pdo->query("USE `$db` ");
        $tstmt = $pdo->query("SHOW TABLES");
        while ($table = $tstmt->fetchColumn()) {
            echo "  - Table: $table\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
