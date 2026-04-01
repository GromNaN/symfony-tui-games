<?php

namespace App\Tetris;

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
 * Tetris game widget.
 *
 * render() returns the game board + side panel + a thin separator + one status line.
 * The border and centering are handled by the Renderer via the StyleSheet.
 */
class TetrisWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;
    use QuitableTrait;
    use ScheduledTickTrait;

    private const BOARD_CHARS = 20; // 10 cols × 2
    private const GAP = 2;
    private const SIDE_WIDTH = 12;

    /** @var array<string, Style> */
    private readonly array $pieceStyles;
    private readonly Style $styleGhost;
    private readonly Style $styleOverlay;
    private readonly Style $styleSeparator;
    private readonly Style $styleStatus;
    private readonly Style $styleLabel;
    private readonly Style $styleValue;
    private readonly Style $styleError;

    public function __construct(private readonly TetrisGame $game)
    {
        $styles = [];
        foreach (Tetromino::cases() as $piece) {
            $styles[$piece->value] = new Style(color: $piece->color());
        }
        $this->pieceStyles = $styles;

        $this->styleGhost     = new Style(dim: true);
        $this->styleOverlay   = new Style(reverse: true, bold: true);
        $this->styleSeparator = new Style(dim: true);
        $this->styleStatus    = new Style(dim: true);
        $this->styleLabel     = new Style(dim: true);
        $this->styleValue     = new Style(color: 'yellow', bold: true);
        $this->styleError     = new Style(color: 'bright_red');
    }

    // -------------------------------------------------------------------------
    // Keybindings
    // -------------------------------------------------------------------------

    protected static function getDefaultKeybindings(): array
    {
        return [
            'move_left'  => [Key::LEFT,  'a'],
            'move_right' => [Key::RIGHT, 'd'],
            'rotate'     => [Key::UP,    'w'],
            'soft_drop'  => [Key::DOWN,  's'],
            'hard_drop'  => [Key::SPACE],
            'pause'      => ['p'],
            'restart'    => ['r'],
            'quit'       => [Key::ctrl('c'), 'q'],
        ];
    }

    public function handleInput(string $data): void
    {
        $kb = $this->getKeybindings();

        if ($kb->matches($data, 'move_left')) {
            $this->game->moveLeft();
            $this->invalidate();
        } elseif ($kb->matches($data, 'move_right')) {
            $this->game->moveRight();
            $this->invalidate();
        } elseif ($kb->matches($data, 'rotate')) {
            $this->game->rotateCW();
            $this->invalidate();
        } elseif ($kb->matches($data, 'soft_drop')) {
            $this->game->softDrop();
            $this->invalidate();
        } elseif ($kb->matches($data, 'hard_drop')) {
            $this->game->hardDrop();
            $this->invalidate();
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

        // Reschedule if the speed changed (level up).
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
        $totalWidth = self::BOARD_CHARS + self::GAP + self::SIDE_WIDTH;

        if ($context->getColumns() < $totalWidth) {
            return [$this->styleError->apply('Terminal too small!')];
        }

        $rows  = $this->game->getRows();
        $cols  = $this->game->getCols();
        $board = $this->game->getBoard();

        // Build lookup sets for the falling piece and its ghost.
        $currentCells = [];
        $ghostCells   = [];

        if (GameState::GameOver !== $this->game->getState()) {
            foreach ($this->game->getCurrentCells() as [$r, $c]) {
                $currentCells["$r,$c"] = true;
            }
            foreach ($this->game->getGhostCells() as [$r, $c]) {
                if (!isset($currentCells["$r,$c"])) {
                    $ghostCells["$r,$c"] = true;
                }
            }
        }

        $currentPiece = $this->game->getCurrentPiece();

        // ---- Board lines (without side panel) ----
        $boardLines = [];
        for ($y = 0; $y < $rows; ++$y) {
            $row = '';
            for ($x = 0; $x < $cols; ++$x) {
                $key = "$y,$x";
                $row .= match (true) {
                    isset($currentCells[$key]) => $this->pieceStyles[$currentPiece->value]->apply('██'),
                    isset($ghostCells[$key])   => $this->styleGhost->apply('░░'),
                    null !== $board[$y][$x]    => $this->pieceStyles[$board[$y][$x]->value]->apply('██'),
                    default                    => '  ',
                };
            }
            $boardLines[] = $row;
        }

        // Overlay for PAUSE / GAME OVER (applied to board area only).
        if (GameState::Playing !== $this->game->getState()) {
            $boardLines = $this->applyOverlay($boardLines, $cols, $rows);
        }

        // ---- Combine board + side panel ----
        $sideLines = $this->buildSidePanel();
        $lines = [];
        for ($y = 0; $y < $rows; ++$y) {
            $side = $sideLines[$y] ?? '';
            $lines[] = $boardLines[$y].str_repeat(' ', self::GAP).$side;
        }

        // Separator + status line.
        $lines[] = $this->styleSeparator->apply(str_repeat('─', $totalWidth));
        $lines[] = $this->buildStatusLine($totalWidth);

        return $lines;
    }

    // -------------------------------------------------------------------------
    // Side panel
    // -------------------------------------------------------------------------

    /** @return array<int, string> keyed by board row index */
    private function buildSidePanel(): array
    {
        $lines = [];

        // Next-piece preview.
        $lines[0] = $this->styleLabel->apply('NEXT');

        $next      = $this->game->getNextPiece();
        $nextCells = $next->cells(0);
        $nextSize  = $next->size();
        $nextStyle = $this->pieceStyles[$next->value];

        for ($r = 0; $r < $nextSize; ++$r) {
            $rowStr = '';
            for ($c = 0; $c < $nextSize; ++$c) {
                $found = false;
                foreach ($nextCells as [$cr, $cc]) {
                    if ($cr === $r && $cc === $c) {
                        $found = true;
                        break;
                    }
                }
                $rowStr .= $found ? $nextStyle->apply('██') : '  ';
            }
            $lines[1 + $r] = $rowStr;
        }

        // Stats — fixed row positions (max next-piece height = 4 rows + header).
        $lines[6]  = $this->styleLabel->apply('SCORE');
        $lines[7]  = $this->styleValue->apply((string) $this->game->getScore());

        $lines[9]  = $this->styleLabel->apply('LEVEL');
        $lines[10] = $this->styleValue->apply((string) $this->game->getLevel());

        $lines[12] = $this->styleLabel->apply('LINES');
        $lines[13] = $this->styleValue->apply((string) $this->game->getLinesCleared());

        return $lines;
    }

    // -------------------------------------------------------------------------
    // Status line
    // -------------------------------------------------------------------------

    private function buildStatusLine(int $width): string
    {
        $hint = match ($this->game->getState()) {
            GameState::Playing  => '←→ ↑Rot ↓ Space  P Q',
            GameState::Paused   => 'P Resume  Q Quit',
            GameState::GameOver => 'R Restart  Q Quit',
        };

        return $this->styleStatus->apply(str_pad($hint, $width));
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

            $plain  = preg_replace('/\033\[[0-9;]*m/', '', $lines[$lineIdx]);
            $before = mb_substr((string) $plain, 0, $startCol);
            $after  = mb_substr((string) $plain, $startCol + $overlayW);

            $lines[$lineIdx] = $before.$styled.$after;
        }

        return $lines;
    }
}
