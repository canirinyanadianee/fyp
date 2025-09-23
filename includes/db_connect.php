<?php
// Database configuration
$db_host = 'localhost';
$db_username = 'root';     // Default XAMPP username
$db_password = '';         // Default XAMPP password is empty
$db_name = 'blood_management';

// Set timezone
date_default_timezone_set('UTC');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create PDO instance
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $db_username, $db_password, $options);
    
    // For backward compatibility, keep $conn variable
    $conn = $pdo;
    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper function to execute queries with parameters
function executeQuery($query, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        throw $e;
    }
}

// Helper function to fetch a single row
function fetchOne($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetch();
}

// Helper function to fetch all rows
function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetchAll();
}

// Helper function to insert data and return last insert ID
function insert($table, $data) {
    global $pdo;
    
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    
    $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($data);
    
    return $pdo->lastInsertId();
}

// Helper function to update data
function update($table, $data, $where, $whereParams = []) {
    global $pdo;
    
    $setParts = [];
    foreach (array_keys($data) as $key) {
        $setParts[] = "$key = :$key";
    }
    
    $query = "UPDATE $table SET " . implode(', ', $setParts) . " WHERE $where";
    
    // Merge data and where params
    $params = array_merge($data, $whereParams);
    
    $stmt = $pdo->prepare($query);
    return $stmt->execute($params);
}
?>
