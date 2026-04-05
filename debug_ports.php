<?php
$host = '127.0.0.1';
$ports = ['3306', '3307'];
$user = 'root';
$pass = '';

foreach ($ports as $port) {
    try {
        echo "Port: $port\n";
        $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
        $stmt = $pdo->query("SHOW DATABASES");
        while ($db = $stmt->fetchColumn()) {
            if (in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys'])) continue;
            echo "  Database: $db\n";
            try {
                $pdo->query("USE `$db` ");
                $tstmt = $pdo->query("SHOW TABLES");
                while ($table = $tstmt->fetchColumn()) {
                    echo "    - Table: $table\n";
                }
            } catch (Exception $ee) {}
        }
    } catch (PDOException $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
