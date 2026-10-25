<?php

/**
 * Optional real MySQL behind the Table DB stubs. While $db is null every DB stub keeps its no-op
 * behaviour, so only tests that call start() and attach() run real SQL. See STUBS.md.
 */
class BgaTestDb {
    const MYSQLD = "/usr/sbin/mysqld";

    /** The connection the DB stubs use; set only between attach() and detach(). */
    public static ?mysqli $db = null;
    private static ?mysqli $connection = null;

    /** Framework tables a dbmodel.sql may rely on; an approximation of the real BGA schema. */
    const FRAMEWORK_TABLES = "
        CREATE TABLE `player` (
            `player_no` int(10) NOT NULL DEFAULT '0',
            `player_id` int(10) unsigned NOT NULL,
            `player_canal` varchar(32) NOT NULL DEFAULT '',
            `player_name` varchar(32) NOT NULL DEFAULT '',
            `player_avatar` varchar(10) NOT NULL DEFAULT '',
            `player_color` varchar(6) NOT NULL DEFAULT '',
            `player_score` int(10) NOT NULL DEFAULT '0',
            `player_score_aux` int(10) NOT NULL DEFAULT '0',
            `player_zombie` tinyint(1) NOT NULL DEFAULT '0',
            `player_ai` tinyint(1) NOT NULL DEFAULT '0',
            `player_eliminated` tinyint(1) NOT NULL DEFAULT '0',
            `player_is_multiactive` tinyint(1) NOT NULL DEFAULT '0',
            `player_state` int(10) DEFAULT NULL,
            PRIMARY KEY (`player_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE `global` (
            `global_id` int(10) unsigned NOT NULL,
            `global_value` int(10) DEFAULT NULL,
            PRIMARY KEY (`global_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    /**
     * Creates a fresh schema from $dbmodel for this process, starting the shared mysqld if needed.
     * Returns false when MySQL is not installed, so the caller can skip.
     */
    static function start(string $dbmodel): bool {
        if (self::$connection) {
            return true;
        }
        $connection = self::startServer();
        if (!$connection) {
            return false;
        }
        // one schema per process, so parallel runs against a shared server do not collide
        $name = "bgatest_" . getmypid();
        register_shutdown_function(function () use ($connection, $name) {
            $connection->query("DROP DATABASE IF EXISTS $name");
            // the last test process to leave stops the server
            $others = $connection->query("SELECT COUNT(*) FROM information_schema.processlist WHERE user = 'root' AND id <> CONNECTION_ID()");
            if (!$others->fetch_row()[0]) {
                $connection->query("SHUTDOWN");
            }
        });
        $connection->query("DROP DATABASE IF EXISTS $name");
        $connection->query("CREATE DATABASE $name CHARACTER SET utf8mb4");
        $connection->select_db($name);
        self::multiQuery($connection, self::FRAMEWORK_TABLES);
        self::multiQuery($connection, file_get_contents($dbmodel));
        self::$connection = $connection;
        return true;
    }

    /** Empties every table and points the DB stubs at the real database. */
    static function attach(): void {
        foreach (self::getTables() as $table) {
            self::$connection->query(str_starts_with($table, "zz_") ? "DROP TABLE `$table`" : "TRUNCATE TABLE `$table`");
        }
        self::$db = self::$connection;
    }

    static function detach(): void {
        self::$db = null;
    }

    /**
     * Starts mysqld unless it is already running. Socket only (no TCP), so the
     * passwordless root is reachable just by the owner of the 0700 dir. Returns null when MySQL is not installed.
     */
    static function startServer(): ?mysqli {
        if (!extension_loaded("mysqli") || !is_executable(self::MYSQLD)) {
            return null;
        }
        $dir = self::getDir();
        if (!is_dir("$dir/data")) {
            @mkdir($dir, 0700, true);
            exec(self::MYSQLD . " --no-defaults --initialize-insecure --datadir=$dir/data > $dir/init.log 2>&1", $output, $code);
            if ($code) {
                throw new RuntimeException("mysqld --initialize failed, see $dir/init.log");
            }
        }
        $connection = self::connect();
        if ($connection) {
            return $connection;
        }
        // closing the inherited descriptors stops mysqld holding the test runner's output pipe open
        $close = implode(" ", array_map(fn($fd) => "$fd>&-", array_filter(scandir("/proc/self/fd"), fn($fd) => is_numeric($fd) && $fd > 2)));
        $mysqld =
            self::MYSQLD .
            " --no-defaults --datadir=$dir/data --skip-networking --socket=$dir/s.sock --mysqlx=OFF --pid-file=$dir/pid --log-error=$dir/err.log";
        exec("bash -c " . escapeshellarg("$mysqld $close < /dev/null > /dev/null 2>&1 &"));
        for ($i = 0; $i < 100 && !$connection; $i++) {
            usleep(100000);
            $connection = self::connect();
        }
        if (!$connection) {
            throw new RuntimeException("mysqld did not start, see $dir/err.log");
        }
        return $connection;
    }

    static function stop(): void {
        self::connect()?->query("SHUTDOWN");
    }

    private static function getDir(): string {
        // the socket path is capped at 107 bytes, so keep this short
        return getenv("BGA_TESTDB_DIR") ?: sys_get_temp_dir() . "/bga-testdb";
    }

    private static function connect(): ?mysqli {
        try {
            return new mysqli("localhost", "root", "", "", 0, self::getDir() . "/s.sock");
        } catch (mysqli_sql_exception) {
            return null;
        }
    }

    private static function multiQuery(mysqli $connection, string $sql): void {
        $connection->multi_query($sql);
        do {
            $connection->store_result() ?: null;
        } while ($connection->more_results() && $connection->next_result());
    }

    static function getTables(): array {
        return array_column(self::$connection->query("SHOW TABLES")->fetch_all(), 0);
    }

    static function getRows(string $sql): array {
        return self::$db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    static function getObjectList(string $sql, bool $bUniqueValue): array {
        $rows = self::getRows($sql);
        return $bUniqueValue ? array_map(fn($row) => reset($row), $rows) : $rows;
    }

    static function getObject(string $sql): ?array {
        $rows = self::getRows($sql);
        if (count($rows) > 1) {
            throw new \Bga\GameFramework\SystemException("Too many rows: $sql");
        }
        return $rows[0] ?? null;
    }

    static function getUniqueValue(string $sql): mixed {
        $row = self::getObject($sql);
        return $row ? reset($row) : null;
    }

    static function getCollection(string $sql, bool $bSingleValue): array {
        $result = [];
        foreach (self::getRows($sql) as $row) {
            $values = array_values($row);
            $result[$values[0]] = $bSingleValue ? $values[1] : $row;
        }
        return $result;
    }

    static function getDoubleKeyCollection(string $sql, bool $bSingleValue): array {
        $result = [];
        foreach (self::getRows($sql) as $row) {
            $values = array_values($row);
            $result[$values[0]][$values[1]] = $bSingleValue ? $values[2] : $row;
        }
        return $result;
    }

    static function getNonEmpty(?array $result, string $sql): array {
        if (!$result) {
            throw new \Bga\GameFramework\SystemException("No rows: $sql");
        }
        return $result;
    }

    /** The real framework runs an upgrade on every copy of the tables: live, zz_savepoint_, zz_replayN_. */
    static function applyToAllPrefixes(string $sql): void {
        $prefixes = [""];
        foreach (self::getTables() as $table) {
            if (preg_match("/^(zz_[a-z]+[0-9]*_)/", $table, $match)) {
                $prefixes[$match[1]] = $match[1];
            }
        }
        foreach ($prefixes as $prefix) {
            self::$db->query(str_replace("DBPREFIX_", $prefix, $sql));
        }
    }

    /** Copies every game table into its $to twin, as doUndoSavePoint (to zz_savepoint_) and undoRestorePoint (back) do. */
    static function copyTables(string $from, string $to): void {
        foreach (self::getTables() as $table) {
            if (str_starts_with($table, "zz_")) {
                continue;
            }
            self::$db->query("CREATE TABLE IF NOT EXISTS `$to$table` LIKE `$from$table`");
            self::$db->query("DELETE FROM `$to$table`");
            self::$db->query("INSERT INTO `$to$table` SELECT * FROM `$from$table`");
        }
    }
}

if (PHP_SAPI === "cli" && realpath($_SERVER["argv"][0] ?? "") === __FILE__) {
    $command = $_SERVER["argv"][1] ?? "";
    if ($command === "start") {
        BgaTestDb::startServer() or exit("MySQL is not installed\n");
    } elseif ($command === "stop") {
        BgaTestDb::stop();
    } else {
        exit("Usage: php " . basename(__FILE__) . " start|stop\n");
    }
}
