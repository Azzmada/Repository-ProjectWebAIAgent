<?php
/**
 * PDO singleton connection.
 *
 * Usage:
 *   $pdo   = db();
 *   $rows  = Database::fetchAll('SELECT * FROM cities WHERE country = ?', ['Indonesia']);
 *   $one   = Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
 *   $id    = Database::insert('INSERT INTO users (name) VALUES (?)', ['Budi']);
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class Database
{
    private static ?PDO $pdo = null;

    private function __construct()
    {
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements — never let PDO emulate them.
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8');
                exit("Koneksi database gagal:\n" . $e->getMessage() . "\n\n" .
                    "Pastikan MySQL berjalan dan import database/schema.sql + database/seed.sql.");
            }
            http_response_code(500);
            exit('Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.');
        }

        return self::$pdo;
    }

    /**
     * Run a prepared statement and return it.
     *
     * @param array<int|string,mixed> $params
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param array<int|string,mixed> $params
     * @return array<string,mixed>|false
     */
    public static function fetch(string $sql, array $params = []): array|false
    {
        return self::query($sql, $params)->fetch();
    }

    /**
     * @param array<int|string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * @param array<int|string,mixed> $params
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * @param array<int|string,mixed> $params
     */
    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function begin(): void
    {
        self::pdo()->beginTransaction();
    }

    public static function commit(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }
}

/**
 * Short helper used across the application.
 */
function db(): PDO
{
    return Database::pdo();
}
