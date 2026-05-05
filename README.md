# Symfony TUI Games

Terminal games built as a **showcase for the `symfony/tui` component**.

Each game deliberately exercises a different slice of the TUI API — styling,
borders, compositing, keybindings, tick loops — so the project doubles as a
living, playable reference for the component.

> [!NOTE]
> This project depends on **`symfony/tui`** from Symfony `8.1-dev`.

---

## Requirements

- PHP ≥ 8.4.1
- Composer

## Installation

```bash
# Clone this repository
git clone https://github.com/GromNaN/symfony-tui-games.git symfony-tui-games
cd symfony-tui-games

# Install dependencies
composer install
```

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
