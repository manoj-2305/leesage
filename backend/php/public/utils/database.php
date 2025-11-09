<?php
// Database utility functions for the public website

require_once __DIR__ . '/../../../database/config.php';

/**
 * Get a database connection
 * @return PDO Database connection
 */
function getConnection() {
    return getDBConnection();
}

/**
 * Execute a query with parameters and return the statement
 * @param string $query SQL query
 * @param array $params Parameters for the query
 * @return PDOStatement The executed statement
 */
function executeQuery($query, $params = []) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare($query);
        
        // Bind parameters with proper types
        foreach ($params as $key => $value) {
            $paramType = PDO::PARAM_STR;
            
            // Determine parameter type
            if (is_int($value)) {
                $paramType = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $paramType = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $paramType = PDO::PARAM_NULL;
            }
            
            // Handle named parameters (e.g., :limit) and positional parameters (e.g., ?)
            if (is_string($key)) {
                $stmt->bindValue($key, $value, $paramType);
            } else {
                $stmt->bindValue($key + 1, $value, $paramType);
            }
        }
        
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        logError('Database query error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Fetch all results from a query
 * @param string $query SQL query
 * @param array $params Parameters for the query
 * @return array Results as associative array
 */
function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch a single row from a query
 * @param string $query SQL query
 * @param array $params Parameters for the query
 * @return array|null Result as associative array or null if not found
 */
function fetchOne($query, $params = []) {
    $stmt = executeQuery($query, $params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result !== false ? $result : null;
}

/**
 * Insert data into a table
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int|string Last insert ID
 */
function insert($table, $data) {
    $columns = array_keys($data);
    $placeholders = array_map(function($col) { return ":$col"; }, $columns);
    
    $query = "INSERT INTO $table (" . implode(', ', $columns) . ") "
           . "VALUES (" . implode(', ', $placeholders) . ")";
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare($query);
        
        // Prepare data for execution with named parameters
        $boundData = [];
        foreach ($data as $key => $value) {
            $boundData[':' . $key] = $value;
        }
        
        $stmt->execute($boundData);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logError('Database insert error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Update data in a table
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @param string $whereClause WHERE clause
 * @param array $whereParams Parameters for WHERE clause
 * @return int Number of affected rows
 */
function update($table, $data, $whereClause, $whereParams = []) {
    $setClauses = array_map(function($col) { return "$col = :$col"; }, array_keys($data));
    
    $query = "UPDATE $table SET " . implode(', ', $setClauses) . " WHERE $whereClause";
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute(array_merge($data, $whereParams));
        return $stmt->rowCount();
    } catch (PDOException $e) {
        logError('Database update error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Delete data from a table
 * @param string $table Table name
 * @param string $whereClause WHERE clause
 * @param array $params Parameters for WHERE clause
 * @return int Number of affected rows
 */
function delete($table, $whereClause, $params = []) {
    $query = "DELETE FROM $table WHERE $whereClause";
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        logError('Database delete error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Begin a transaction
 * @return PDO Database connection
 */
function beginTransaction() {
    $pdo = getConnection();
    $pdo->beginTransaction();
    return $pdo;
}

/**
 * Commit a transaction
 * @param PDO $pdo Database connection
 */
function commitTransaction($pdo) {
    $pdo->commit();
}

/**
 * Rollback a transaction
 * @param PDO $pdo Database connection
 */
function rollbackTransaction($pdo) {
    $pdo->rollBack();
}
?>