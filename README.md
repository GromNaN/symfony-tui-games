# Symfony TUI Games

Terminal games built as a **showcase for the `symfony/tui` component**.

Each game deliberately exercises a different slice of the TUI API — styling,
borders, compositing, keybindings, tick loops — so the project doubles as a
living, playable reference for the component.

> [!WARNING]
> This project depends on two experimental Symfony components that are not yet on Packagist:
>
> - **`symfony/tui`** — pending review, tracked via `vendor-src/symfony/tui` (submodule of `fabpot/symfony` branch `tui`)
> - **`symfony/console` + `symfony/dependency-injection`** with `ConsoleBundle` and pure DI kernel — from [symfony/symfony#63715](https://github.com/symfony/symfony/pull/63715), tracked via `vendor-src/symfony/nicolas-grekas`
>
> Follow the setup instructions below to get started.

---

## Requirements

- PHP ≥ 8.4
- Composer
- Git

## Installation

```bash
# Clone this repository with its submodules
git clone --recurse-submodules https://github.com/GromNaN/symfony-tui-games.git symfony-tui-games
cd symfony-tui-games

# Install dependencies (TUI and ConsoleBundle are loaded from vendor-src/ via path repositories)
composer install
```

> If you cloned without `--recurse-submodules`, run
> `git submodule update --init` before `composer install`.
> The `composer install` / `composer update` scripts run this automatically.

---

## Games

| Command | Description |
|---------|-------------|
| `php bin/console app:snake` | **Snake** — eat the apples, avoid the walls and your own tail. Speed increases over time. |
| `php bin/console app:park` | **Terminal Park** — RollerCoaster Tycoon-style park management. Build paths and attractions, manage money and visitor happiness. |
| `php bin/console app:space` | **Space Invaders** — defend Earth against waves of emoji invaders. |
| `php bin/console app:tetris` | **Tetris** — classic falling pieces with ghost preview, soft/hard drop, and increasing speed. |
| `php bin/console app:pong` | **Pong** — two-player classic. Player 1 uses W/S, Player 2 uses arrow keys. First to 11 wins. |
| `php bin/console app:racer` | **Racer** — pseudo-3D racing with curves, roadside trees and enemy cars. Rendered with Unicode sextant sub-pixels. |

### Common controls

| Key | Action |
|-----|--------|
| `Q` / `Ctrl+C` | Quit |
| `P` | Pause / resume |
| `R` | Restart |

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).
