# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

Terminal games showcase for the experimental `symfony/tui` component (PHP 8.4+). Each game exercises different TUI API features — styling, borders, compositing, keybindings, tick loops.

Two components are **not on Packagist** and are bundled locally in `vendor-src/` via `path` repositories:
- `symfony/tui` — in `vendor-src/symfony/tui/` (fabpot/symfony, branch `tui`)
- `symfony/console` + `symfony/dependency-injection` with `ConsoleBundle` — in `vendor-src/symfony/nicolas-grekas/` (symfony/symfony PR #63715)

## Commands

```bash
composer install  # Install dependencies (includes TUI from vendor-src/)
bin/console       # List all commands
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

## Submodule dependencies

- `vendor-src/symfony/tui/` — cloned from `fabpot/symfony` branch `tui`. Do **not** run `composer update symfony/tui` without first updating this submodule.
- `vendor-src/symfony/nicolas-grekas/` — tracks the `console-kernel` branch from symfony/symfony PR #63715. Contains updated `Console` and `DependencyInjection` components including `ConsoleBundle`, `AbstractKernel`, and `KernelTrait`.

## Kernel architecture

`src/Kernel.php` extends `AbstractKernel` and uses `KernelTrait` (from `symfony/dependency-injection`) — a pure DI kernel with no HTTP stack. Bundle registration and service autowiring are inlined directly in `Kernel.php`; there is no `config/bundles.php` or `config/services.yaml`.
