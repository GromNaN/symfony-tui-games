# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

Terminal games showcase for the experimental `symfony/tui` component (PHP 8.4+, Symfony 8.0). Each game exercises different TUI API features — styling, borders, compositing, keybindings, tick loops. The TUI component is **not on Packagist**; it's bundled locally in `vendor-src/` via a `path` repository.

## Commands

```bash
composer install                    # Install dependencies (includes TUI from vendor-src/)
php bin/console app:snake           # Play Snake
php bin/console app:tetris          # Play Tetris
php bin/console app:space           # Play Space Invaders
php bin/console app:park            # Play Theme Park
```

No test suite, linter, or static analysis is configured yet.

## Architecture

Three-layer separation per game:

- **`src/<Game>/<Game>Game.php`** — Pure game logic (state, rules, collision, scoring). No TUI dependency. Testable in isolation.
- **`src/<Game>/<Game>Widget.php`** — Rendering + input handling. Extends `AbstractWidget`, implements `FocusableInterface`, uses `KeybindingsTrait`.
- **`src/Command/<Game>Command.php`** — Symfony command (invokable, no `extends Command`). Builds `StyleSheet`, initializes `Tui`, wires tick loop via `$tui->onTick()`.

Supporting enums/classes live alongside the game (e.g., `Direction`, `GameState`, `Tetromino`, `TileType`, `Visitor`).

## Adding a new game

1. Create `src/MyGame/` with `MyGameGame.php` (pure logic) and `MyGameWidget.php` (rendering).
2. Create `src/Command/MyGameCommand.php` — invokable class with `#[AsCommand(name: 'app:my-game')]`.
3. Widget must: use `KeybindingsTrait`, implement `getDefaultKeybindings()`, return lines from `render()` with visible width <= `$context->getColumns()` and no trailing `\n`.
4. Command must: build a `StyleSheet`, call `$event->setBusy()` in the tick callback.

## TUI API conventions

- **No raw ANSI escape codes.** Use `Style::apply()` for text styling, initialized once in the constructor and reused per frame.
- **Borders and sizing** go in the `StyleSheet`, not in `render()`. `render()` uses `RenderContext::getColumns()` for inner content width.
- **Overlays** use `Compositor::composite()` with transparent `Layer` objects.
- **String widths:** Always use `AnsiUtils::visibleWidth()` instead of `mb_strlen()`/`strlen()` for terminal-destined strings.

## TUI component dependency

The `symfony/tui` source lives in `vendor-src/symfony/` (cloned from `fabpot/symfony` branch `tui`). Do **not** run `composer update symfony/tui` without first updating `vendor-src/symfony`.
