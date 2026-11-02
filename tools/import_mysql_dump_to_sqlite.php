<?php

declare(strict_types=1);

/**
 * Imports the persistent application data from a MySQL dump into an existing
 * SQLite Laravel database. Runtime-only tables (cache, sessions, queues, and
 * migration history) are deliberately excluded.
 *
 * Usage:
 *   php tools/import_mysql_dump_to_sqlite.php /path/to/source.sql /path/to/database.sqlite [--replace]
 */

if ($argc !== 3 && $argc !== 4) {
    fwrite(STDERR, "Usage: php {$argv[0]} SOURCE.sql TARGET.sqlite [--replace]\n");
    exit(64);
}

[$script, $sourcePath, $databasePath] = $argv;
$replace = ($argv[3] ?? null) === '--replace';

if ($argc === 4 && !$replace) {
    fwrite(STDERR, "The only supported optional flag is --replace.\n");
    exit(64);
}

if (!is_readable($sourcePath)) {
    fwrite(STDERR, "Source dump is not readable: {$sourcePath}\n");
    exit(66);
}

if (!is_file($databasePath)) {
    fwrite(STDERR, "Target SQLite database does not exist: {$databasePath}\n");
    exit(66);
}

$allowedTables = [
    'classrooms',
    'modules',
    'sub_modules',
    'tasks',
    'users',
    'student_progress',
    'submissions',
];

/** @return iterable<string> */
function insertStatements(string $sql): iterable
{
    preg_match_all('/INSERT\s+INTO\s+`?[A-Za-z0-9_]+`?/i', $sql, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as $match) {
        $start = $match[1];
        $quote = null;
        $length = strlen($sql);

        for ($index = $start; $index < $length; $index++) {
            $character = $sql[$index];

            if ($quote === "'") {
                if ($character === '\\' && $index + 1 < $length) {
                    $index++;
                    continue;
                }

                if ($character === "'") {
                    if ($index + 1 < $length && $sql[$index + 1] === "'") {
                        $index++;
                        continue;
                    }

                    $quote = null;
                }

                continue;
            }

            if ($character === "'") {
                $quote = "'";
                continue;
            }

            if ($character === ';') {
                yield substr($sql, $start, $index - $start + 1);
                break;
            }
        }
    }
}

function sqliteCompatible(string $statement): string
{
    $output = '';
    $length = strlen($statement);
    $inString = false;

    for ($index = 0; $index < $length; $index++) {
        $character = $statement[$index];

        if (!$inString) {
            $output .= $character;
            if ($character === "'") {
                $inString = true;
            }
            continue;
        }

        if ($character === '\\' && $index + 1 < $length) {
            $escaped = $statement[++$index];
            $output .= match ($escaped) {
                '0' => "\0",
                'b' => "\x08",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'Z' => "\x1a",
                "'" => "''",
                default => $escaped,
            };
            continue;
        }

        $output .= $character;
        if ($character === "'") {
            if ($index + 1 < $length && $statement[$index + 1] === "'") {
                $output .= $statement[++$index];
                continue;
            }

            $inString = false;
        }
    }

    return $output;
}

$sql = file_get_contents($sourcePath);
if ($sql === false) {
    fwrite(STDERR, "Could not read source dump.\n");
    exit(74);
}

$pdo = new PDO("sqlite:{$databasePath}", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$availableTables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
$targetTables = array_values(array_intersect($allowedTables, $availableTables));

if (count($targetTables) !== count($allowedTables)) {
    $missing = implode(', ', array_diff($allowedTables, $targetTables));
    fwrite(STDERR, "Target database is missing required tables: {$missing}\n");
    exit(65);
}

foreach ($targetTables as $table) {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    if ($count > 0 && !$replace) {
        fwrite(STDERR, "Target table '{$table}' is not empty; import cancelled to prevent duplicate data.\n");
        exit(65);
    }
}

$inserted = array_fill_keys($allowedTables, 0);
$pdo->exec('PRAGMA foreign_keys = OFF');
$pdo->beginTransaction();

try {
    if ($replace) {
        foreach (['submissions', 'student_progress', 'users', 'tasks', 'sub_modules', 'modules', 'classrooms'] as $table) {
            $pdo->exec("DELETE FROM `{$table}`");
        }
    }

    foreach (insertStatements($sql) as $statement) {
        if (!preg_match('/^\s*INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?/i', $statement, $matches)) {
            continue;
        }

        $table = strtolower($matches[1]);
        if (!in_array($table, $allowedTables, true)) {
            continue;
        }

        $pdo->exec(sqliteCompatible($statement));
    }

    foreach ($allowedTables as $table) {
        $inserted[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    $foreignKeyProblems = $pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);

    if ($foreignKeyProblems !== []) {
        throw new RuntimeException('Foreign-key verification failed after import.');
    }

    $pdo->commit();
    $pdo->exec('PRAGMA foreign_keys = ON');

    fwrite(STDOUT, json_encode(['imported' => $inserted], JSON_THROW_ON_ERROR).PHP_EOL);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec('PRAGMA foreign_keys = ON');
    fwrite(STDERR, "Import failed: {$exception->getMessage()}\n");
    exit(1);
}
