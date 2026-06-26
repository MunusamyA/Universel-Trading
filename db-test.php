<?php
require_once __DIR__ . '/includes/db.php';

/** @var PDO $pdo */

echo "<h3>Database Connection Test</h3>";
// echo password_hash('Admin@7019', PASSWORD_BCRYPT);  
try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("PDO connection object not found.");
    }

    echo "<p style='color:green;font-weight:bold;'>✅ Database connected successfully</p>";

    $stmt = $pdo->query("SELECT DATABASE() AS db_name");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<p><strong>Connected Database:</strong> " . htmlspecialchars($row['db_name']) . "</p>";

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<p><strong>Total Tables:</strong> " . count($tables) . "</p>";

    echo "<h4>Tables:</h4>";
    echo "<ul>";

    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }

    echo "</ul>";

} catch (PDOException $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Database Error</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Error</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}