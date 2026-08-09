<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper. All queries use prepared statements.
 * Singleton connection reused across the request / cron run.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = Config::get('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            Logger::error('DB connection failed: ' . $e->getMessage());
            throw $e;
        }

        return self::$pdo;
    }

    /** Run a prepared statement and return it. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch a single row (or null). */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch all rows. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Fetch a single scalar column from the first row. */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $stmt = self::run($sql, $params);
        return $stmt->fetchColumn();
    }

    /** Insert a row into $table from an associative array, return last insert id. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(static fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn($c) => "`$c`", $cols)),
            implode(', ', $placeholders)
        );
        self::run($sql, self::bindable($data));
        return (int) self::connection()->lastInsertId();
    }

    /** Update rows in $table matching $where (assoc). Returns affected row count. */
    public static function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(static fn($c) => "`$c` = :set_$c", array_keys($data)));
        $cond = implode(' AND ', array_map(static fn($c) => "`$c` = :w_$c", array_keys($where)));

        $params = [];
        foreach ($data as $k => $v) {
            $params["set_$k"] = self::normalize($v);
        }
        foreach ($where as $k => $v) {
            $params["w_$k"] = self::normalize($v);
        }

        $sql = "UPDATE `$table` SET $set WHERE $cond";
        return self::run($sql, $params)->rowCount();
    }

    public static function delete(string $table, array $where): int
    {
        $cond = implode(' AND ', array_map(static fn($c) => "`$c` = :$c", array_keys($where)));
        return self::run("DELETE FROM `$table` WHERE $cond", self::bindable($where))->rowCount();
    }

    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::connection()->commit();
    }

    public static function rollBack(): void
    {
        if (self::connection()->inTransaction()) {
            self::connection()->rollBack();
        }
    }

    private static function bindable(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = self::normalize($v);
        }
        return $out;
    }

    private static function normalize(mixed $v): mixed
    {
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }
        if (is_array($v)) {
            return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return $v;
    }
}
