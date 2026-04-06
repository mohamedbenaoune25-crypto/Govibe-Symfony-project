<?php
try {
    $ports = [3306, 3307];
    foreach ($ports as $port) {
        echo "Checking port $port...\n";
        $pdo = new PDO("mysql:host=127.0.0.1;port=$port", "root", "");
        $stmt = $pdo->query("SHOW DATABASES");
        $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($dbs as $db) {
            if ($db === 'govibe_project') {
                echo "Found govibe_project on port $port!\n";
                $pdo->exec("USE govibe_project");
                $stmt2 = $pdo->query("SHOW TABLES");
                $tables = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                echo "Tables in govibe_project (port $port): " . count($tables) . "\n";
                foreach ($tables as $table) {
                    echo " - $table\n";
                }
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
