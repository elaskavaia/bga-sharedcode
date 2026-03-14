
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