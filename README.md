# Symfony TUI Games

Terminal games built as a **showcase for the `symfony/tui` component**.

Each game deliberately exercises a different slice of the TUI API — styling,
borders, compositing, keybindings, tick loops — so the project doubles as a
living, playable reference for the component.

> [!WARNING]
> **`symfony/tui` is experimental and its PR is pending review.**
> It has not been merged into Symfony yet.
> See: https://github.com/symfony/symfony-docs/pull/22201
>
> This project embeds the component via a Git submodule pointing to the
> `tui` branch of `fabpot/symfony`, locked to a specific commit.
> Follow the setup instructions below to get started.

---

## Requirements

- PHP ≥ 8.4
- Composer
- Git

## Installation

```bash
# Clone this repository with its submodule
git clone --recurse-submodules https://github.com/GromNaN/symfony-tui-games.git symfony-tui-games
cd symfony-tui-games

# Install dependencies (the TUI component is loaded from vendor-src/ via a path repository)
composer install
```

> If you cloned without `--recurse-submodules`, run
> `git submodule update --init` before `composer install`.
> The `composer install` / `composer update` scripts also run this automatically.

---

## Games

| Command | Description |
|---------|-------------|
| `php bin/console app:snake` | **Snake** — eat the apples, avoid the walls and your own tail. Speed increases over time. |
| `php bin/console app:park` | **Terminal Park** — RollerCoaster Tycoon-style park management. Build paths and attractions, manage money and visitor happiness. |
| `php bin/console app:space` | **Space Invaders** — defend Earth against waves of emoji invaders. |
| `php bin/console app:tetris` | **Tetris** — classic falling pieces with ghost preview, soft/hard drop, and increasing speed. |
| `php bin/console app:pong` | **Pong** — two-player classic. Player 1 uses W/S, Player 2 uses arrow keys. First to 11 wins. |

### Common controls

| Key | Action |
|-----|--------|
| `Q` / `Ctrl+C` | Quit |
| `P` | Pause / resume |
| `R` | Restart |

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).
