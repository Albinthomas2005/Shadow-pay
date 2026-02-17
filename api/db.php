<?php
// db.php - shared PDO connection (moved to api/)
// Update the credentials below if your MySQL root has a password or you want a different DB name.
$host = '127.0.0.1';
$port = 3306; // default MySQL port used by XAMPP
$db   = 'shadowpay';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// initial DSN (create_pdo will rebuild with fallback hosts/ports as needed)
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Optional: set a reasonable timeout
    PDO::ATTR_TIMEOUT => 5,
];

function create_pdo($tryHost = null, $tryPort = null)
{
    global $dsn, $user, $pass, $options, $host, $port, $db, $charset;
    $h = $tryHost ?? $host;
    $p = $tryPort ?? $port;
    $localDsn = "mysql:host={$h};port={$p};dbname={$db};charset={$charset}";

    try {
        return new PDO($localDsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Try a sensible fallback once if the initial host is different from the alternate
        $alternate = ($h === '127.0.0.1') ? 'localhost' : '127.0.0.1';
        if ($tryHost === null) {
            try {
                $altDsn = "mysql:host={$alternate};port={$p};dbname={$db};charset={$charset}";
                return new PDO($altDsn, $user, $pass, $options);
            } catch (PDOException $e2) {
                // fall through to show a helpful message
            }
        }

        // Helpful guidance for local dev when connection fails
        $msg = 'Database connection failed: ' . $e->getMessage();
        $msg .= "\n\nSuggestions:\n";
        $msg .= " - Make sure MySQL is running in the XAMPP Control Panel (Start MySQL).\n";
        $msg .= " - Confirm MySQL is listening on port {$p} (default 3306).\n";
        $msg .= " - If you use a non-default port or host, update 'db.php' accordingly.\n";
        $msg .= " - Check MySQL error log (eg. C:\\xampp\\mysql\\data\\mysql_error.log) and Apache/PHP logs.\n";
        $msg .= " - Firewall/local security software may block connections - try disabling temporarily.\n";

        // For local development it's useful to see the exception, but don't leak this in production.
        exit(nl2br(htmlentities($msg)));
    }
}

// Create initial connection
$pdo = create_pdo();
