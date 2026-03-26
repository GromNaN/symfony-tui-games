<?php

namespace App\Snake;

use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;

/**
 * Snake game widget — renders only the cell grid (no border, no status bar).
 *
 * Border and focus highlight are declared in the StyleSheet (see SnakeCommand).
 * The status bar lives in a sibling TextWidget managed by SnakeCommand.
 */
class SnakeWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    // Cell styles — initialised once, reused every render().
    private readonly Style $styleHead;
    private readonly Style $styleBody;
    private readonly Style $styleFood;
    private readonly Style $styleOverlay;
    private readonly Style $styleError;

    public function __construct(private readonly SnakeGame $game)
    {
        $this->styleHead    = new Style(color: 'bright_green', bold: true);
        $this->styleBody    = new Style(color: 'green');
        $this->styleFood    = new Style(color: 'bright_red');
        $this->styleOverlay = new Style(reverse: true, bold: true);
        $this->styleError   = new Style(color: 'bright_red');
    }

    // -------------------------------------------------------------------------
    // Keybindings
    // -------------------------------------------------------------------------

    protected static function getDefaultKeybindings(): array
    {
        return [
            'move_up'    => [Key::UP,    'w'],
            'move_down'  => [Key::DOWN,  's'],
            'move_left'  => [Key::LEFT,  'a'],
            'move_right' => [Key::RIGHT, 'd'],
            'pause'      => ['p', Key::SPACE],
            'restart'    => ['r'],
        ];
    }

    public function handleInput(string $data): void
    {
        $kb = $this->getKeybindings();

        if ($kb->matches($data, 'move_up')) {
            $this->game->changeDirection(Direction::Up);
        } elseif ($kb->matches($data, 'move_down')) {
            $this->game->changeDirection(Direction::Down);
        } elseif ($kb->matches($data, 'move_left')) {
            $this->game->changeDirection(Direction::Left);
        } elseif ($kb->matches($data, 'move_right')) {
            $this->game->changeDirection(Direction::Right);
        } elseif ($kb->matches($data, 'pause')) {
            $this->game->togglePause();
            $this->invalidate();
        } elseif ($kb->matches($data, 'restart') && GameState::GameOver === $this->game->getState()) {
            $this->game->reset();
            $this->invalidate();
        }
    }

    // -------------------------------------------------------------------------
    // Rendering  (inner content only — border handled by the Renderer)
    // -------------------------------------------------------------------------

    public function render(RenderContext $context): array
    {
        $cols = $this->game->getCols();
        $rows = $this->game->getRows();

        if ($context->getColumns() < $cols * 2) {
            return [$this->styleError->apply('Terminal too small!')];
        }

        // Build lookup structures for fast cell classification.
        $snakeBody = $this->game->getSnake();
        $head      = $snakeBody[0] ?? null;
        $bodySet   = [];
        foreach (\array_slice($snakeBody, 1) as [$bx, $by]) {
            $bodySet["$bx,$by"] = true;
        }
        [$fx, $fy] = $this->game->getFood();

        $lines = [];
        for ($y = 0; $y < $rows; ++$y) {
            $row = '';
            for ($x = 0; $x < $cols; ++$x) {
                $row .= match (true) {
                    null !== $head && $head[0] === $x && $head[1] === $y => $this->styleHead->apply('██'),
                    isset($bodySet["$x,$y"])                             => $this->styleBody->apply('██'),
                    $x === $fx && $y === $fy                             => $this->styleFood->apply('██'),
                    default                                               => '  ',
                };
            }
            $lines[] = $row;
        }

        // Overlay for PAUSE / GAME OVER states.
        if (GameState::Playing !== $this->game->getState()) {
            $lines = $this->applyOverlay($lines, $cols, $rows);
        }

        return $lines;
    }

    // -------------------------------------------------------------------------
    // Overlay
    // -------------------------------------------------------------------------

    /** @param string[] $lines */
    private function applyOverlay(array $lines, int $cols, int $rows): array
    {
        $texts = GameState::Paused === $this->game->getState()
            ? ['', '  PAUSE  ', '  P to resume  ', '']
            : ['', '  GAME OVER  ', \sprintf('  Score: %d  ', $this->game->getScore()), '  R to restart  ', ''];

        $overlayH = \count($texts);
        $overlayW = max(array_map('mb_strlen', $texts));
        $startRow = (int) (($rows - $overlayH) / 2);
        $startCol = (int) (($cols * 2 - $overlayW) / 2);

        foreach ($texts as $i => $text) {
            $lineIdx = $startRow + $i;
            if (!isset($lines[$lineIdx])) {
                continue;
            }

            // Pad the overlay text to a uniform width then style it.
            $padded = mb_str_pad($text, $overlayW);
            $styled = $this->styleOverlay->apply($padded);

            // Splice into the plain line at the correct column.
            $plain  = preg_replace('/\033\[[0-9;]*m/', '', $lines[$lineIdx]);
            $before = mb_substr((string) $plain, 0, $startCol);
            $after  = mb_substr((string) $plain, $startCol + $overlayW);

            $lines[$lineIdx] = $before.$styled.$after;
        }

        return $lines;
    }
}
