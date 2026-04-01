<?php

namespace App\Snake;

use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\QuitableTrait;
use Symfony\Component\Tui\Widget\ScheduledTickTrait;
use Symfony\Component\Tui\Widget\WidgetContext;

/**
 * Snake game widget.
 *
 * render() returns the game grid + a thin separator + one status line.
 * The border and centering are handled by the Renderer via the StyleSheet.
 */
class SnakeWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;
    use QuitableTrait;
    use ScheduledTickTrait;

    // Cell / UI styles — initialised once, reused every frame.
    private readonly Style $styleHead;
    private readonly Style $styleBody;
    private readonly Style $styleFood;
    private readonly Style $styleOverlay;
    private readonly Style $styleSeparator;
    private readonly Style $styleStatus;
    private readonly Style $styleStatusHighlight;
    private readonly Style $styleError;

    public function __construct(private readonly SnakeGame $game)
    {
        $this->styleHead = new Style(color: 'bright_green', bold: true);
        $this->styleBody = new Style(color: 'green');
        $this->styleFood = new Style(color: 'bright_red');
        $this->styleOverlay = new Style(reverse: true, bold: true);
        $this->styleSeparator = new Style(dim: true);
        $this->styleStatus = new Style(dim: true);
        $this->styleStatusHighlight = new Style(color: 'yellow');
        $this->styleError = new Style(color: 'bright_red');
    }

    // -------------------------------------------------------------------------
    // Keybindings
    // -------------------------------------------------------------------------

    protected static function getDefaultKeybindings(): array
    {
        return [
            'move_up' => [Key::UP,    'w'],
            'move_down' => [Key::DOWN,  's'],
            'move_left' => [Key::LEFT,  'a'],
            'move_right' => [Key::RIGHT, 'd'],
            'pause' => ['p', Key::SPACE],
            'restart' => ['r'],
            'quit' => [Key::ctrl('c'), 'q'],
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
        } elseif ($kb->matches($data, 'quit')) {
            $this->dispatchQuit();
        }
    }

    // -------------------------------------------------------------------------
    // Scheduling
    // -------------------------------------------------------------------------

    protected function resolveScheduledTickContext(): ?WidgetContext
    {
        return $this->getContext();
    }

    protected function onScheduledTick(): void
    {
        if (GameState::Playing !== $this->game->getState()) {
            return;
        }

        // Reschedule if the speed changed.
        $this->startScheduledTick($this->game->getStepIntervalMs() / 1000.0);

        $this->game->step();
        $this->invalidate();
    }

    protected function onAttach(WidgetContext $context): void
    {
        $this->startScheduledTick($this->game->getStepIntervalMs() / 1000.0);
    }

    protected function onDetach(): void
    {
        $this->stopScheduledTick();
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(RenderContext $context): array
    {
        $cols = $this->game->getCols();
        $rows = $this->game->getRows();
        $innerWidth = $cols * 2;

        if ($context->getColumns() < $innerWidth) {
            return [$this->styleError->apply('Terminal too small!')];
        }

        // Build lookup structures.
        $snakeBody = $this->game->getSnake();
        $head = $snakeBody[0] ?? null;
        $bodySet = [];
        foreach (\array_slice($snakeBody, 1) as [$bx, $by]) {
            $bodySet["$bx,$by"] = true;
        }
        [$fx, $fy] = $this->game->getFood();

        // Game grid.
        $lines = [];
        for ($y = 0; $y < $rows; ++$y) {
            $row = '';
            for ($x = 0; $x < $cols; ++$x) {
                $row .= match (true) {
                    null !== $head && $head[0] === $x && $head[1] === $y => $this->styleHead->apply('██'),
                    isset($bodySet["$x,$y"]) => $this->styleBody->apply('██'),
                    $x === $fx && $y === $fy => $this->styleFood->apply('██'),
                    default => '  ',
                };
            }
            $lines[] = $row;
        }

        // Overlay for PAUSE / GAME OVER.
        if (GameState::Playing !== $this->game->getState()) {
            $lines = $this->applyOverlay($lines, $cols, $rows);
        }

        // Separator + status line.
        $lines[] = $this->styleSeparator->apply(str_repeat('─', $innerWidth));
        $lines[] = $this->buildStatusLine($innerWidth);

        return $lines;
    }

    // -------------------------------------------------------------------------
    // Status line
    // -------------------------------------------------------------------------

    private function buildStatusLine(int $width): string
    {
        $score = $this->game->getScore();
        $length = $this->game->getLength();
        $state = $this->game->getState();

        $left = \sprintf('Score: %d  Length: %d', $score, $length);
        if (GameState::Paused === $state) {
            $left .= '  '.$this->styleStatusHighlight->apply('[PAUSED]');
        } elseif (GameState::GameOver === $state) {
            $left .= '  '.$this->styleStatusHighlight->apply('[GAME OVER]');
        }

        $hint = '↑↓←→  P  R  Q';

        // Compute visible lengths (no ANSI codes).
        $leftLen = mb_strlen(\sprintf('Score: %d  Length: %d', $score, $length))
            + (GameState::Playing !== $state ? 2 + mb_strlen(GameState::Paused === $state ? '[PAUSED]' : '[GAME OVER]') : 0);
        $hintLen = mb_strlen($hint);
        $pad = max(1, $width - $leftLen - $hintLen);

        return $this->styleStatus->apply($left.str_repeat(' ', $pad).$hint);
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

            $padded = mb_str_pad($text, $overlayW);
            $styled = $this->styleOverlay->apply($padded);

            $plain = preg_replace('/\033\[[0-9;]*m/', '', $lines[$lineIdx]);
            $before = mb_substr((string) $plain, 0, $startCol);
            $after = mb_substr((string) $plain, $startCol + $overlayW);

            $lines[$lineIdx] = $before.$styled.$after;
        }

        return $lines;
    }
}
