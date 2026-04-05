<?php
$host = '127.0.0.1';
$port = '3306';
$user = 'root';
$pass = '';
$db = 'govibe_project';

try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
    echo "Connected to MySQL successfully!\n";
    $stmt = $pdo->query("SHOW DATABASES LIKE '$db'");
    if ($stmt->fetch()) {
        echo "Database '$db' exists.\n";
        $pdo->query("USE $db");
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll();
        if (count($tables) > 0) {
            echo "Tables in '$db':\n";
            foreach ($tables as $row) {
                echo "- " . $row[0] . "\n";
            }
        } else {
            echo "Database '$db' is EMPTY (no tables).\n";
        }
    } else {
        echo "Database '$db' DOES NOT exist.\n";
        $stmt = $pdo->query("SHOW DATABASES");
        echo "Available databases:\n";
        while ($row = $stmt->fetch()) {
            echo "- " . $row[0] . "\n";
        }
    }
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}

$port = '3307';
try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
    echo "Connected to MySQL successfully on port $port!\n";
    $stmt = $pdo->query("SHOW DATABASES LIKE '$db'");
    if ($stmt->fetch()) {
        echo "Database '$db' exists on port $port.\n";
    } else {
        echo "Database '$db' DOES NOT exist on port $port.\n";
        $stmt = $pdo->query("SHOW DATABASES");
        echo "Available databases on port $port:\n";
        while ($row = $stmt->fetch()) {
            echo "- " . $row[0] . "\n";
        }
    }
} catch (PDOException $e) {
    echo "Connection failed on port $port: " . $e->getMessage() . "\n";
}
