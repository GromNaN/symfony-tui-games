# CLAUDE.md

Terminal games showcase for the experimental `symfony/tui` component (PHP 8.4+).

`symfony/tui` is **not on Packagist** — bundled in `vendor-src/symfony/tui/` (fabpot/symfony, branch `tui`). Do **not** run `composer update symfony/tui` without first updating the submodule. All other Symfony components are `8.1.*` from Packagist.

## Architecture

Three layers per game:

- **`src/<Game>/<Game>Game.php`** — pure logic, no TUI dependency.
- **`src/<Game>/<Game>Widget.php`** — rendering + input. Extends `AbstractWidget`, implements `FocusableInterface`, uses `KeybindingsTrait`.
- **`src/Command/<Game>Command.php`** — invokable class (no `extends Command`), `#[AsCommand]`, builds `StyleSheet`, wires tick loop via `$tui->onTick()`.

`src/Kernel.php` extends `AbstractKernel` (pure DI, no HTTP). No `config/bundles.php` or `config/services.yaml`.

## Adding a new game

1. `src/MyGame/MyGameGame.php` (logic) + `MyGameWidget.php` (rendering).
2. `src/Command/MyGameCommand.php` — invokable, `#[AsCommand(name: 'app:my-game')]`.
3. Widget: `render()` lines must have visible width <= `$context->getColumns()`, no trailing `\n`.
4. Command: call `$event->setBusy()` in the tick callback.
5. The game appears automatically in the menu (auto-discovered via the `console.command` tag).
6. Add a snapshot test in `tests/MyGame/MyGameWidgetTest.php` (see existing tests for the pattern).

## TUI conventions

- No raw ANSI. Use `Style::apply()`, initialized once in the constructor.
- Borders/sizing in `StyleSheet`, not in `render()`.
- Overlays: `Compositor::composite()` with transparent `Layer` objects.
- String widths: always `AnsiUtils::visibleWidth()`, never `mb_strlen()`/`strlen()`.

## Tests

Snapshot tests use `VirtualTerminal` + `AnsiUtils::stripAnsiCodes()` and store plain-text renders in `tests/<Game>/snapshots/`.

```bash
php vendor/bin/phpunit          # run all tests
UPDATE_SNAPSHOTS=1 php vendor/bin/phpunit tests/Menu/  # regenerate snapshots
```
