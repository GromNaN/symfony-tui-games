<?php

namespace App\Snake;

use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;

class SnakeWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    // ANSI color codes
    private const RESET      = "\033[0m";
    private const GREEN      = "\033[32m";
    private const GREEN_BOLD = "\033[1;32m";
    private const RED        = "\033[31m";
    private const YELLOW     = "\033[33m";
    private const CYAN       = "\033[36m";
    private const BOLD       = "\033[1m";
    private const DIM        = "\033[2m";

    // Border characters
    private const TL = '╔';
    private const TR = '╗';
    private const BL = '╚';
    private const BR = '╝';
    private const H  = '═';
    private const V  = '║';

    public function __construct(private readonly SnakeGame $game)
    {
    }

    protected static function getDefaultKeybindings(): array
    {
        return [
            'move_up'    => [Key::UP, 'w'],
            'move_down'  => [Key::DOWN, 's'],
            'move_left'  => [Key::LEFT, 'a'],
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

    public function render(RenderContext $context): array
    {
        $cols = $this->game->getCols();
        $rows = $this->game->getRows();

        // Build a lookup map for the snake and food
        $snakeBody = $this->game->getSnake();
        $head = $snakeBody[0] ?? null;
        $bodySet = [];
        foreach (\array_slice($snakeBody, 1) as [$bx, $by]) {
            $bodySet["$bx,$by"] = true;
        }
        [$fx, $fy] = $this->game->getFood();

        $lines = [];

        // Top border
        $lines[] = self::DIM.self::TL.str_repeat(self::H, $cols * 2).self::TR.self::RESET;

        // Game rows
        for ($y = 0; $y < $rows; ++$y) {
            $row = self::DIM.self::V.self::RESET;

            for ($x = 0; $x < $cols; ++$x) {
                if (null !== $head && $head[0] === $x && $head[1] === $y) {
                    $row .= self::GREEN_BOLD.'██'.self::RESET;
                } elseif (isset($bodySet["$x,$y"])) {
                    $row .= self::GREEN.'██'.self::RESET;
                } elseif ($x === $fx && $y === $fy) {
                    $row .= self::RED.'██'.self::RESET;
                } else {
                    $row .= '  ';
                }
            }

            $row .= self::DIM.self::V.self::RESET;
            $lines[] = $row;
        }

        // Bottom border
        $lines[] = self::DIM.self::BL.str_repeat(self::H, $cols * 2).self::BR.self::RESET;

        // Status bar
        $lines[] = $this->buildStatusLine($cols * 2 + 2);

        // Overlay for Paused / Game Over
        if (GameState::Playing !== $this->game->getState()) {
            $lines = $this->applyOverlay($lines, $rows);
        }

        return $lines;
    }

    /** @param string[] $lines */
    private function applyOverlay(array $lines, int $rows): array
    {
        $state = $this->game->getState();
        $cols = $this->game->getCols();

        if (GameState::Paused === $state) {
            $overlayLines = [
                '┌──────────────────┐',
                '│      PAUSE       │',
                '│  P pour reprendre│',
                '└──────────────────┘',
            ];
        } else {
            $overlayLines = [
                '┌──────────────────┐',
                '│    GAME  OVER    │',
                '│  Score: '.str_pad((string) $this->game->getScore(), 9).'│',
                '│  R pour rejouer  │',
                '└──────────────────┘',
            ];
        }

        $overlayH = \count($overlayLines);
        $overlayW = mb_strlen($overlayLines[0]);

        // Center position in the grid area (rows 1..rows, cols 1..cols*2)
        $startRow = 1 + (int) (($rows - $overlayH) / 2);
        $startCol = 1 + (int) (($cols * 2 - $overlayW) / 2);

        foreach ($overlayLines as $i => $overlayLine) {
            $lineIdx = $startRow + $i;
            if (!isset($lines[$lineIdx])) {
                continue;
            }

            $line = $lines[$lineIdx];
            // Strip all ANSI from the line to manipulate it as plain text
            $plain = preg_replace('/\033\[[0-9;]*m/', '', $line);
            $before = mb_substr($plain, 0, $startCol);
            $after = mb_substr($plain, $startCol + $overlayW);

            $lines[$lineIdx] = self::CYAN.$before.self::BOLD.$overlayLine.self::RESET.self::CYAN.$after.self::RESET;
        }

        return $lines;
    }

    private function buildStatusLine(int $totalWidth): string
    {
        $score = $this->game->getScore();
        $length = $this->game->getLength();
        $state = $this->game->getState();

        $stateLabel = match ($state) {
            GameState::Playing => '',
            GameState::Paused => self::YELLOW.' [PAUSE]'.self::RESET,
            GameState::GameOver => self::RED.' [GAME OVER]'.self::RESET,
        };

        $left = self::BOLD.'Score: '.$score.self::RESET
            .'  '.self::DIM.'Longueur: '.$length.self::RESET
            .$stateLabel;

        $hint = self::DIM.'↑↓←→ ou WASD · P pause · Q quitter'.self::RESET;

        // Visible widths
        $leftVisible = 'Score: '.$score.'  Longueur: '.$length;
        if (GameState::Playing !== $state) {
            $leftVisible .= match ($state) {
                GameState::Paused => ' [PAUSE]',
                GameState::GameOver => ' [GAME OVER]',
                default => '',
            };
        }
        $hintVisible = '↑↓←→ ou WASD · P pause · Q quitter';

        $padding = $totalWidth - mb_strlen($leftVisible) - mb_strlen($hintVisible);
        $padding = max(1, $padding);

        return $left.str_repeat(' ', $padding).$hint;
    }
}
