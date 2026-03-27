# Contributing

## Architecture

Each game is structured in three layers:

```
src/
├── <Game>/
│   ├── <Game>Game.php     — pure logic (state, rules, no TUI dependency)
│   ├── <Game>Widget.php   — rendering + keyboard handling via TUI APIs
│   └── *.php              — enums, entities (Direction, TileType…)
└── Command/
    └── <Game>Command.php  — Symfony command: StyleSheet, Tui setup, tick loop
```

The `Game` / `Widget` separation keeps the logic testable independently of rendering.

---

## Adding a game

1. Create a `src/MyGame/` directory.
2. Implement `MyGameGame` (pure logic, no TUI dependency).
3. Create `MyGameWidget extends AbstractWidget implements FocusableInterface`:
   - Use `KeybindingsTrait` and declare `getDefaultKeybindings(): array`
   - Implement `handleInput(string $data)` using `$this->getKeybindings()->matches()`
   - Implement `render(RenderContext $context): array` — return lines whose visible
     width ≤ `$context->getColumns()`, with no trailing `\n`
4. Create `src/Command/MyGameCommand.php` (invokable, no `extends Command`):
   - Annotate with `#[AsCommand(name: 'app:my-game')]`
   - Build a `StyleSheet` to declare the widget's border, size, and focus style
   - Set up the tick loop with `$tui->onTick(...)` and call `$event->setBusy()`
5. Document the game in [README.md](README.md).

---

## TUI API conventions

This project uses the `symfony/tui` API throughout. Avoid raw ANSI escape codes
in new code; use the component abstractions instead.

### Styling text

Use `Style::apply()` with named colors and boolean flags:

```php
$style = new Style(color: 'bright_green', bold: true);
echo $style->apply('Hello');  // wraps in correct ANSI codes
```

Prefer `Style` objects initialised once in the constructor and reused every frame.

### Borders and layout

Declare borders and sizing in the `StyleSheet` passed to `Tui`, not in `render()`.
This keeps widgets free of layout concerns and lets the Renderer handle chrome:

```php
$stylesheet = new StyleSheet([
    MyWidget::class => new Style(
        maxColumns: 62,
        border: Border::from([1], BorderPattern::ROUNDED, 'green'),
        dim: true,
    ),
    MyWidget::class.':focus' => new Style(
        border: Border::from([1], BorderPattern::ROUNDED, 'bright_green'),
        dim: false,
    ),
]);
```

`maxColumns` pins the outer width so that `:root`'s `Align::Center` / `VerticalAlign::Center`
can compute the correct centering offset.

`render()` receives a `RenderContext` whose `getColumns()` already accounts for the
border and padding — compare against the **inner** content width, not the outer one.

### Overlays

Use `Compositor::composite()` with a transparent `Layer` rather than patching lines
manually:

```php
$overlay = $this->overlayBorder->wrapLines($contentLines, $overlayW, $this->styleOverlay);

$lines = Compositor::composite(
    new Layer($baseLines, width: $W, height: $H),
    new Layer($overlay, row: $centerRow, col: $centerCol, transparent: true),
);
```

### Terminal-aware widths

Always use `AnsiUtils::visibleWidth()` instead of `mb_strlen()` or `strlen()` when
measuring strings destined for the terminal. It strips ANSI escape codes and counts
the actual terminal columns occupied, handling multi-byte characters, wide characters,
and emoji correctly:

```php
$w = AnsiUtils::visibleWidth($style->apply($text));  // strips codes, counts columns
```

### Tick loop

Accumulate `$event->getDeltaTime()` for a fixed time step and always call
`$event->setBusy()` to keep the loop running at full speed:

```php
$tui->onTick(function (TickEvent $event) use ($game, $widget, &$elapsed): void {
    $elapsed += $event->getDeltaTime();
    if ($elapsed >= $stepInterval) {
        $elapsed -= $stepInterval;
        $game->step();
        $widget->invalidate();
    }
    $event->setBusy();
});
```

---

## TUI component dependency

The `symfony/tui` component is bundled locally under `vendor-src/` (see [README.md](README.md)).
It is **not** available on Packagist. Do not run `composer update symfony/tui` without first
updating the source repository in `vendor-src/symfony`.
