<?php

namespace App\SpaceInvaders;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;

/**
 * Space Invaders TUI widget.
 *
 * Display: GAME_W * 2 + 2 border = 62 cols, GAME_H + 2 border + 1 score = 23 rows.
 */
class SpaceWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    private const R    = "\033[0m";
    private const DIM  = "\033[2m";
    private const BOLD = "\033[1m";

    // Colours
    private const C_RED    = "\033[91m";   // bright red   — row 0 invaders + enemy bullets
    private const C_YELLOW = "\033[93m";   // bright yellow — row 1 + explosions
    private const C_CYAN   = "\033[96m";   // bright cyan   — row 2
    private const C_GREEN  = "\033[92m";   // bright green  — row 3
    private const C_WHITE  = "\033[97m";   // bright white  — player + bullets

    public function __construct(private readonly SpaceGame $game)
    {
    }

    // -------------------------------------------------------------------------
    // Keybindings
    // -------------------------------------------------------------------------

    protected static function getDefaultKeybindings(): array
    {
        return [
            'left'    => [Key::LEFT,  'a'],
            'right'   => [Key::RIGHT, 'd'],
            'shoot'   => [Key::SPACE, Key::UP, 'w'],
            'pause'   => ['p'],
            'restart' => ['r'],
        ];
    }

    public function handleInput(string $data): void
    {
        $kb = $this->getKeybindings();

        if ($kb->matches($data, 'left')) {
            $this->game->movePlayer(-1);
        } elseif ($kb->matches($data, 'right')) {
            $this->game->movePlayer(1);
        } elseif ($kb->matches($data, 'shoot')) {
            $this->game->shoot();
        } elseif ($kb->matches($data, 'pause')) {
            $this->game->togglePause();
        } elseif ($kb->matches($data, 'restart')) {
            $this->game->reset();
        }

        $this->invalidate();
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(RenderContext $context): array
    {
        $W         = SpaceGame::GAME_W;
        $H         = SpaceGame::GAME_H;
        $minWidth  = $W * 2 + 2; // 62
        $minHeight = $H + 3;     // 23

        if ($context->getColumns() < $minWidth) {
            return ["\033[31mTerminal trop petit ! ({$minWidth} colonnes minimum)\033[0m"];
        }

        // Build a cell grid: index [y][x] => 2-char ANSI string
        $grid = [];
        for ($y = 0; $y < $H; ++$y) {
            for ($x = 0; $x < $W; ++$x) {
                $grid[$y][$x] = '  '; // empty
            }
        }

        // Invaders — one emoji per cell (2 terminal columns wide)
        $invaders = $this->game->getInvaders();

        for ($r = 0; $r < SpaceGame::ROWS; ++$r) {
            for ($c = 0; $c < SpaceGame::COLS; ++$c) {
                if (!($invaders[$r][$c] ?? false)) {
                    continue;
                }
                $ix = $this->game->invaderX($c);
                $iy = $this->game->invaderY($r);

                if ($ix >= 0 && $ix < $W && $iy >= 0 && $iy < $H) {
                    $grid[$iy][$ix] = $this->invaderGlyph($r);
                }
            }
        }

        // Explosions
        foreach ($this->game->getExplosions() as $exp) {
            if ($exp['x'] >= 0 && $exp['x'] < $W && $exp['y'] >= 0 && $exp['y'] < $H) {
                $grid[$exp['y']][$exp['x']] = '💥';
            }
        }

        // Player bullets
        foreach ($this->game->getPlayerBullets() as $b) {
            if ($b['x'] >= 0 && $b['x'] < $W && $b['y'] >= 0 && $b['y'] < $H) {
                $grid[$b['y']][$b['x']] = self::C_WHITE.'| '.self::R;
            }
        }

        // Invader bullets
        foreach ($this->game->getInvaderBullets() as $b) {
            if ($b['x'] >= 0 && $b['x'] < $W && $b['y'] >= 0 && $b['y'] < $H) {
                $grid[$b['y']][$b['x']] = self::C_RED.'. '.self::R;
            }
        }

        // Player  (dim during invincibility frames)
        $px = $this->game->getPlayerX();
        if ($px >= 0 && $px < $W) {
            $grid[SpaceGame::PLAYER_Y][$px] = $this->game->isInvincible()
                ? self::DIM.'🚀'.self::R
                : '🚀';
        }

        // Compose lines
        $lines   = [];
        $border  = self::DIM;
        $topLine = $border.'╔'.str_repeat('══', $W).'╗'.self::R;
        $lines[] = $this->buildScoreRow($W * 2);

        $lines[] = $topLine;

        for ($y = 0; $y < $H; ++$y) {
            $row = $border.'║'.self::R;
            for ($x = 0; $x < $W; ++$x) {
                $row .= $grid[$y][$x];
            }
            $row    .= $border.'║'.self::R;
            $lines[] = $row;
        }

        $lines[] = $border.'╚'.str_repeat('══', $W).'╝'.self::R;

        // Overlays
        $state = $this->game->getState();
        if ($state === GameState::Paused) {
            $lines = $this->overlay($lines, ['', '  [ PAUSE ]  ', '  [P] Reprendre  [R] Restart  ', '']);
        } elseif ($state === GameState::GameOver) {
            $lines = $this->overlay($lines, ['', '  GAME  OVER  ', \sprintf('  Score: %d  ', $this->game->getScore()), '  [R] Rejouer  ', '']);
        } elseif ($state === GameState::WaveCleared) {
            $lines = $this->overlay($lines, ['', \sprintf('  VAGUE %d TERMINEE !  ', $this->game->getWave()), '  Prochaine vague...  ', '']);
        }

        return $lines;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the emoji for an invader row.
     * Each emoji is exactly 2 terminal columns wide — one grid cell.
     */
    private function invaderGlyph(int $row): string
    {
        return match ($row) {
            0       => '👾',  // alien monster  (30 pts)
            1       => '🦑',  // squid          (20 pts)
            2       => '🦀',  // crab           (10 pts)
            default => '🐙',  // octopus        (10 pts)
        };
    }

    private function buildScoreRow(int $innerWidth): string
    {
        $score = 'SCORE: '.$this->game->getScore();
        $wave  = 'VAGUE '.$this->game->getWave();
        $lives = 'VIES: '.str_repeat('🚀', $this->game->getLives());

        $waveLen   = AnsiUtils::visibleWidth($wave);
        $leftLen   = AnsiUtils::visibleWidth($score);
        $rightLen  = AnsiUtils::visibleWidth($lives);
        $midLeft   = (int) (($innerWidth - $waveLen) / 2);
        $midRight  = $innerWidth - $waveLen - $midLeft;
        $leftPad   = $midLeft - $leftLen;
        $rightPad  = $midRight - $rightLen;

        return self::BOLD.$score.self::R
            .str_repeat(' ', max(0, $leftPad))
            .self::C_YELLOW.self::BOLD.$wave.self::R
            .str_repeat(' ', max(0, $rightPad))
            .self::C_WHITE.$lives.self::R;
    }

    /**
     * Renders overlay text centred horizontally in the play area.
     *
     * @param  string[] $lines  Full line array (includes score row + borders)
     * @param  string[] $texts  Lines of overlay text
     * @return string[]
     */
    private function overlay(array $lines, array $texts): array
    {
        $W         = SpaceGame::GAME_W;
        $innerWidth = $W * 2;  // each cell = 2 chars
        $startRow   = (int) ((\count($lines) - \count($texts)) / 2);

        foreach ($texts as $i => $text) {
            $lineIdx = $startRow + $i;
            if (!isset($lines[$lineIdx])) {
                continue;
            }
            $visible = $W * 2; // inner width in terminal columns (no ANSI, no emoji ambiguity)
            $textLen = AnsiUtils::visibleWidth($text);
            $pad     = (int) (($visible - $textLen) / 2);
            $padded  = str_repeat(' ', max(0, $pad))
                ."\033[7m".$text.self::R
                .str_repeat(' ', max(0, $visible - $textLen - $pad));

            // Replace the content between borders (keep first char '║' and last '║')
            $lines[$lineIdx] = self::DIM.'║'.self::R.$padded.self::DIM.'║'.self::R;
        }

        return $lines;
    }
}
