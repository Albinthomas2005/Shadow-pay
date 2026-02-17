<?php
session_start();

require_once __DIR__ . '/db.php';

/**
 * Executes a prepared statement with a single reconnect retry on "MySQL server has gone away" errors.
 *
 * @param PDO $pdo The PDO instance.
 * @param string $sql The SQL query to prepare and execute.
 * @param array $params The parameters to bind to the statement.
 * @return PDOStatement The resulting PDOStatement object.
 * @throws PDOException If the query fails after a retry attempt.
 */
function execute_with_retry(PDO $pdo, string $sql, array $params = []): PDOStatement {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        // SQLSTATE HY000 with 'MySQL server has gone away' is a common error for lost connections.
        if ($e->getCode() === 'HY000' && strpos($e->getMessage(), 'MySQL server has gone away') !== false) {
            $GLOBALS['pdo'] = create_pdo(); // Re-create the global PDO connection from db.php
            $stmt = $GLOBALS['pdo']->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        }
        throw $e; // Re-throw other errors
    }
}