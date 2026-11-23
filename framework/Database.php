<?php

namespace Mini;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(bool $withoutDatabase = false): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }

        $path = (string) env('DB_PATH', BASE_PATH . '/storage/database/app.sqlite');
        if (!preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path) && $path !== ':memory:') $path = BASE_PATH . '/' . $path;
        if ($path !== ':memory:' && !is_dir(dirname($path))) mkdir(dirname($path), 0770, true);
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        self::$pdo = $pdo;
        return $pdo;
    }

    public static function statement(string $sql, array $bindings = []): \PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($bindings);
        return $statement;
    }

    public static function fetch(string $sql, array $bindings = []): ?array
    {
        $row = self::statement($sql, $bindings)->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $bindings = []): array
    {
        return self::statement($sql, $bindings)->fetchAll();
    }

    public static function fetchValue(string $sql, array $bindings = []): mixed
    {
        return self::statement($sql, $bindings)->fetchColumn();
    }

    public static function fetchPairs(string $sql, array $bindings = []): array
    {
        return self::statement($sql, $bindings)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function insert(string $table, array $data): int
    {
        self::identifier($table);
        if ($data === []) {
            throw new \InvalidArgumentException('Insert data cannot be empty.');
        }
        $columns = array_keys($data);
        foreach ($columns as $column) {
            self::identifier($column);
        }
        $quoted = array_map(fn ($column) => "`{$column}`", $columns);
        $sql = "INSERT INTO `{$table}` (" . implode(', ', $quoted) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        self::statement($sql, array_values($data));
        return (int) self::connection()->lastInsertId();
    }

    public static function update(string $table, int $id, array $data): void
    {
        self::identifier($table);
        if ($data === []) {
            throw new \InvalidArgumentException('Update data cannot be empty.');
        }
        foreach (array_keys($data) as $column) {
            self::identifier($column);
        }
        $sets = array_map(fn ($column) => "`{$column}` = ?", array_keys($data));
        self::statement("UPDATE `{$table}` SET " . implode(', ', $sets) . ' WHERE id = ?', [...array_values($data), $id]);
    }

    private static function identifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier.');
        }
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $callback($pdo);
            $pdo->exec('COMMIT');
            return $result;
        } catch (\Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }
}

