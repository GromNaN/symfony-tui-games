<?php

namespace App\Pong;

use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\QuitableTrait;

/**
 * Pong game widget — two-player.
 *
 * render() returns the game grid + a thin separator + one status line.
 * The border and centering are handled by the Renderer via the StyleSheet.
 */
class PongWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;
    use QuitableTrait;

    private readonly Style $stylePaddle1;
    private readonly Style $stylePaddle2;
    private readonly Style $styleBall;
    private readonly Style $styleNet;
    private readonly Style $styleOverlay;
    private readonly Style $styleSeparator;
    private readonly Style $styleStatus;
    private readonly Style $styleP1Highlight;
    private readonly Style $styleP2Highlight;
    private readonly Style $styleError;

    public function __construct(private readonly PongGame $game)
    {
        $this->stylePaddle1    = new Style(color: 'bright_cyan', bold: true);
        $this->stylePaddle2    = new Style(color: 'bright_magenta', bold: true);
        $this->styleBall       = new Style(color: 'bright_yellow', bold: true);
        $this->styleNet        = new Style(dim: true);
        $this->styleOverlay    = new Style(reverse: true, bold: true);
        $this->styleSeparator  = new Style(dim: true);
        $this->styleStatus     = new Style(dim: true);
        $this->styleP1Highlight = new Style(color: 'bright_cyan');
        $this->styleP2Highlight = new Style(color: 'bright_magenta');
        $this->styleError      = new Style(color: 'bright_red');
    }

    // -------------------------------------------------------------------------
    // Keybindings
    // -------------------------------------------------------------------------

    protected static function getDefaultKeybindings(): array
    {
        return [
            'p1_up'   => ['w'],
            'p1_down' => ['s'],
            'p2_up'   => [Key::UP],
            'p2_down' => [Key::DOWN],
            'pause'   => ['p', Key::SPACE],
            'restart' => ['r'],
            'quit'    => [Key::ctrl('c'), 'q'],
        ];
    }

    public function handleInput(string $data): void
    {
        $kb = $this->getKeybindings();

        if ($kb->matches($data, 'p1_up')) {
            $this->game->movePaddle1(1);
            $this->invalidate();
        } elseif ($kb->matches($data, 'p1_down')) {
            $this->game->movePaddle1(-1);
            $this->invalidate();
        } elseif ($kb->matches($data, 'p2_up')) {
            $this->game->movePaddle2(-1);
            $this->invalidate();
        } elseif ($kb->matches($data, 'p2_down')) {
            $this->game->movePaddle2(1);
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
    // Rendering
    // -------------------------------------------------------------------------

    public function render(RenderContext $context): array
    {
        $cols       = $this->game->getCols();
        $rows       = $this->game->getRows();
        $innerWidth = $cols * 2;

        if ($context->getColumns() < $innerWidth) {
            return [$this->styleError->apply('Terminal too small!')];
        }

        $ballX   = $this->game->getBallX();
        $ballY   = $this->game->getBallY();
        $p1Y     = $this->game->getPaddle1Y();
        $p2Y     = $this->game->getPaddle2Y();
        $paddleH = $this->game->getPaddleHeight();
        $midX    = (int) ($cols / 2);

        $lines = [];
        for ($y = 0; $y < $rows; ++$y) {
            $row = '';
            for ($x = 0; $x < $cols; ++$x) {
                $row .= match (true) {
                    $x === $ballX && $y === $ballY
                        => $this->styleBall->apply('██'),
                    $x === 0 && $y >= $p1Y && $y < $p1Y + $paddleH
                        => $this->stylePaddle1->apply('██'),
                    $x === $cols - 1 && $y >= $p2Y && $y < $p2Y + $paddleH
                        => $this->stylePaddle2->apply('██'),
                    $x === $midX && $y % 2 === 0
                        => $this->styleNet->apply('│ '),
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
        $s1 = $this->game->getScore1();
        $s2 = $this->game->getScore2();

        $left = $this->styleP1Highlight->apply(\sprintf('P1: %d', $s1))
            .' │ '
            .$this->styleP2Highlight->apply(\sprintf('P2: %d', $s2));

        $state = $this->game->getState();
        if (GameState::Paused === $state) {
            $left .= '  '.$this->styleP1Highlight->apply('[PAUSED]');
        } elseif (GameState::GameOver === $state) {
            $winner = $this->game->getWinner();
            $style  = 1 === $winner ? $this->styleP1Highlight : $this->styleP2Highlight;
            $left  .= '  '.$style->apply(\sprintf('[P%d WINS]', $winner));
        }

        $hint = 'W/S ↑↓  P R Q';

        $leftVisible = \sprintf('P1: %d │ P2: %d', $s1, $s2)
            .match ($state) {
                GameState::Paused => '  [PAUSED]',
                GameState::GameOver => \sprintf('  [P%d WINS]', $this->game->getWinner()),
                default => '',
            };
        $pad = max(1, $width - mb_strlen($leftVisible) - mb_strlen($hint));

        return $this->styleStatus->apply($left.str_repeat(' ', $pad).$hint);
    }

    // -------------------------------------------------------------------------
    // Overlay
    // -------------------------------------------------------------------------

    /** @param string[] $lines */
    private function applyOverlay(array $lines, int $cols, int $rows): array
    {
        $state = $this->game->getState();

        if (GameState::Paused === $state) {
            $texts = ['', '  PAUSE  ', '  P to resume  ', ''];
        } else {
            $winner = $this->game->getWinner();
            $texts  = [
                '',
                \sprintf('  PLAYER %d WINS!  ', $winner),
                \sprintf('  %d — %d  ', $this->game->getScore1(), $this->game->getScore2()),
                '  R to restart  ',
                '',
            ];
        }

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
