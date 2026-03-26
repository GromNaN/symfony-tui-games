# Contributing

## Architecture

Each game is structured in three layers:

```
src/
├── <Game>/
│   ├── <Game>Game.php     — pure logic (state, rules, no TUI dependency)
│   ├── <Game>Widget.php   — ANSI rendering + keyboard handling
│   └── *.php              — enums, entities (Direction, TileType…)
└── Command/
    └── <Game>Command.php  — Symfony command, tick loop
```

The `Game` / `Widget` separation keeps the logic testable independently of rendering.

## Adding a game

1. Create a `src/MyGame/` directory.
2. Implement `MyGameGame` (pure logic, no TUI dependency).
3. Create `MyGameWidget extends AbstractWidget implements FocusableInterface`:
   - `getDefaultKeybindings()` — declare key bindings
   - `handleInput(string $data)` — react to key presses
   - `render(RenderContext $context): array` — return an array of ANSI lines
     (visible width ≤ `$context->getColumns()`, no `\n`)
4. Create `src/Command/MyGameCommand.php`:
   - Annotate with `#[AsCommand(name: 'app:my-game')]`
   - Set up the tick loop with `$tui->onTick(...)` and `$event->setBusy()`
5. Document the game in [README.md](README.md).

## Conventions

- **Rendering**: use ANSI escape codes directly (`\033[32m`…); reset with `\033[0m`.
  Use `mb_str_pad()` (PHP 8.3+) for correct padding of multibyte strings.
- **Width**: check `$context->getColumns()` at the top of `render()` and return an error
  message if the terminal is too small.
- **Tick loop**: accumulate `$event->getDeltaTime()` manually for a fixed time step;
  always call `$event->setBusy()` to keep the loop running.
- **Unicode sprites**: prefer block-drawing characters (`▀▄█▌▐░▒▓`) for pixel-art sprites.

## TUI component dependency

The `symfony/tui` component is bundled locally under `vendor-src/` (see [README.md](README.md)).
It is **not** available on Packagist. Do not run `composer update symfony/tui` without first
updating the source repository in `vendor-src/symfony`.
