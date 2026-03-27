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
> This project embeds the component locally via a `path` repository in `composer.json`.
> Follow the setup instructions below to get started.

---

## Requirements

- PHP ≥ 8.4
- Composer
- Git

## Installation

```bash
# 1. Clone this repository
git clone https://github.com/GromNaN/symfony-tui-games.git symfony-tui-games
cd symfony-tui-games

# 2. Fetch the TUI component from the pending PR branch
git clone --branch tui --single-branch https://github.com/fabpot/symfony.git vendor-src/symfony

# 3. Install dependencies (the TUI component is loaded from vendor-src/ via a path repository)
composer install
```

> `composer.json` references `vendor-src/symfony/src/Symfony/Component/Tui`
> as a `path` repository, so no further configuration is needed.

---

## Games

| Command | Description |
|---------|-------------|
| `php bin/console app:snake` | **Snake** — eat the apples, avoid the walls and your own tail. Speed increases over time. |
| `php bin/console app:park` | **Terminal Park** — RollerCoaster Tycoon-style park management. Build paths and attractions, manage money and visitor happiness. |
| `php bin/console app:space` | **Space Invaders** — defend Earth against waves of emoji invaders. |
| `php bin/console app:tetris` | **Tetris** — classic falling pieces with ghost preview, soft/hard drop, and increasing speed. |

### Common controls

| Key | Action |
|-----|--------|
| `Q` / `Ctrl+C` | Quit |
| `P` | Pause / resume |
| `R` | Restart |

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).
