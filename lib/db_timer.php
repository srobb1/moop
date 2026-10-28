<?php
/**
 * SQLite time per request, for the System Monitor (notes/SYSTEM_MONITOR_PLAN.md).
 *
 * getDbConnection() (lib/functions_database.php) — the single door for every SQLite connection —
 * opens a MoopTimedPDO, whose statements are MoopTimedStatement. Together they add up, per
 * request, the time spent executing statements AND fetching their rows, and count statements.
 * The request log records both (db_ms, db_n), so the monitor can say which pages are slow
 * because of the database rather than PHP.
 *
 * Fetching is timed on purpose: on this site a cold query's cost is in reading the rows, not in
 * the index lookup (notes/QUERY_PERFORMANCE.md), so timing execute() alone would miss most of it.
 *
 * Not covered: iterating a statement with foreach (PHP's internal iterator bypasses fetch()).
 * MOOP's helpers use fetchAll()/fetch(); a page that iterates instead is under-counted, not
 * mis-counted. Cost: one hrtime() pair per call, tens of nanoseconds.
 *
 * Method signatures match PHP 8.1+'s tentative return types, so 8.3 raises no deprecation
 * (the php-next CI job fails on any).
 */

final class MoopDbTimer {
    public static float $ms = 0.0;
    public static int $n = 0;

    public static function add(int $start_ns, int $statements = 0): void {
        self::$ms += (hrtime(true) - $start_ns) / 1e6;
        self::$n += $statements;
    }
}

class MoopTimedPDO extends PDO {
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null) {
        parent::__construct($dsn, $username, $password, $options);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [MoopTimedStatement::class]);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $t = hrtime(true);
        try {
            return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
        } finally {
            MoopDbTimer::add($t, 1);
        }
    }

    public function exec(string $statement): int|false {
        $t = hrtime(true);
        try {
            return parent::exec($statement);
        } finally {
            MoopDbTimer::add($t, 1);
        }
    }
}

class MoopTimedStatement extends PDOStatement {
    protected function __construct() {}

    public function execute(?array $params = null): bool {
        $t = hrtime(true);
        try {
            return parent::execute($params);
        } finally {
            MoopDbTimer::add($t, 1);
        }
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        $t = hrtime(true);
        try {
            return parent::fetch($mode, $cursorOrientation, $cursorOffset);
        } finally {
            MoopDbTimer::add($t);
        }
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        $t = hrtime(true);
        try {
            return parent::fetchAll($mode, ...$args);
        } finally {
            MoopDbTimer::add($t);
        }
    }

    public function fetchColumn(int $column = 0): mixed {
        $t = hrtime(true);
        try {
            return parent::fetchColumn($column);
        } finally {
            MoopDbTimer::add($t);
        }
    }
}
