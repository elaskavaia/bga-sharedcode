
# BGA Framework Stubs

Re-usable in-memory stubs of the BGA framework for running PHPUnit tests
without a database or the real framework.

## File Structure

- **BgaFrameworkStubs.php** — Single file with all stub implementations, organized by namespace.
  Follows the structure of `_ide_helper.php` with concrete in-memory implementations
  for testable classes (Notify, GamestateMachine, Legacy, Table, Deck, Globals, PlayerCounter).

- **table.game.php, deck.game.php, game.view.php** — One-line shims that `require_once` BgaFrameworkStubs.php.
  Existing game autoloaders that reference these files continue to work unchanged.

- **sync_check.php / reflect_signatures.php** — Tooling to compare
  BgaFrameworkStubs.php signatures against `_ide_helper.php` using PHP Reflection API.

- **BgaTestDb.php** - Optional real MySQL behind the DB stubs, see below.

## Real MySQL (optional)

By default the DB stubs run no SQL: `DbQuery` records the query, `get*FromDB` return empty
results. A test that needs real SQL (an `upgradeTableDb` migration, raw queries) can attach a
throwaway MySQL 8:

```php
abstract class TestWithRealDb extends TestCase {
    protected function setUp(): void {
        if (!BgaTestDb::start(__DIR__ . "/../../dbmodel.sql")) {
            $this->markTestSkipped("needs mysql-server and php8.4-mysql");
        }
        BgaTestDb::attach(); // empties every table
    }

    protected function tearDown(): void {
        BgaTestDb::detach();
    }
}
```

- Needs `sudo apt install mysql-server php8.4-mysql`; the system service can stay disabled.
  Without them `start()` returns false and the test skips.
- `start()` launches a socket-only `mysqld` (no TCP, so only the owner of the 0700 data dir can
  reach its passwordless root) in `/tmp/bga-testdb` (or `BGA_TESTDB_DIR`, keep it short: the socket
  path is capped at 107 bytes), or reuses one already running there, and builds a per-process
  database `bgatest_<pid>` from the framework tables (`player`, `global`, approximated) plus
  `dbmodel.sql`. At exit it drops the database, and the last process still connected shuts the
  server down (a cold start takes about 0.7s).
- Manual control: `php misc/php/stubs/BgaTestDb.php start|stop`, or `npm run testdb:start` /
  `npm run testdb:stop` from a game. Stop is needed only after a run killed with Ctrl-C, and a
  manual start lasts until the next test run ends.
- While attached: every `Table` and legacy `APP_DbObject` DB method runs on that connection,
  `applyDbUpgradeToAllDB` runs on the live tables and every existing `zz_` copy, and
  `doUndoSavePoint` / `undoRestorePoint` copy the tables to and from `zz_savepoint_`.
- BGA prod runs MySQL 5.7 (studio 8.0): keep test SQL in the 5.7 subset.
- Reference user: bga-tapestry `tests/UpgradeTableDbTest.php`.

## How Games Consume the Stubs

Set the `APP_GAMEMODULE_PATH` env var to point at the `misc/` directory, then
include `BgaFrameworkStubs.php` in your test bootstrap (e.g. `_autoload.php`):

```bash
# In package.json test script:
"tests": "APP_GAMEMODULE_PATH=~/git/bga-sharedcode/misc/ phpunit ..."
```

```php
// In _autoload.php:
define("APP_GAMEMODULE_PATH", getenv("APP_GAMEMODULE_PATH"));
require_once APP_GAMEMODULE_PATH . "/php/stubs/BgaFrameworkStubs.php";
```

This single include provides all framework classes, constants, and global functions
needed for PHPUnit tests. Legacy shim files (`table.game.php`, `deck.game.php`,
`game.view.php`) still work for backward compatibility.

## Syncing with _ide_helper.php

When `_ide_helper.php` is updated from the BGA framework:

1. Run the sync check:
   ```bash
   php misc/php/stubs/sync_check.php
   ```

2. Review the output:
   - **Missing classes/methods**: Need to be added to BgaFrameworkStubs.php
   - **Signature mismatches**: May need updating (note: stubs intentionally omit
     `final` and some type annotations to allow test overrides)
   - **Extra in stubs**: Test helpers like `_getCurrentPlayerId` — expected, not errors

3. For each new/changed item, update the corresponding section in BgaFrameworkStubs.php:
   - Copy the method signature from `_ide_helper.php`
   - Add an empty body (return default value) or in-memory implementation as needed
   - Classes that are `abstract` in `_ide_helper.php` are concrete in stubs

4. Run all game tests to verify

## Current Status
```
=== MISSING CLASSES (in _ide_helper but not in stubs) ===
  - Bga\GameFramework\Components\Deck

=== MISSING METHODS (in _ide_helper but not in stubs) ===
  - APP_GameAction::__default
  - APP_GameAction::getCurrentPlayerId
  - game_view::getCurrentPlayerId
  - game_view::raw

=== SIGNATURE MISMATCHES ===
  Bga\GameFramework\GamestateMachine::getPrivateState:
    helper: public function getPrivateState(int $playerId): array
    stubs:  public function getPrivateState(int $playerId): ?array
  Bga\GameFramework\Table::createNextPlayerTable:
    helper: public function createNextPlayerTable(array $players, bool $bLoop = true): void
    stubs:  public function createNextPlayerTable(array $players, bool $bLoop = true)
  Bga\GameFramework\Table::getObjectFromDB:
    helper: public static function getObjectFromDB(string $sql): array
    stubs:  public static function getObjectFromDB(string $sql): ?array
```