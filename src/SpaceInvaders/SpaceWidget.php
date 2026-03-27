<?php

namespace App\SpaceInvaders;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\Compositor;
use Symfony\Component\Tui\Render\Layer;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Style;
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
    private const C_RED    = "\033[91m";   // bright red   — enemy bullets
    private const C_YELLOW = "\033[93m";   // bright yellow — wave label
    private const C_WHITE  = "\033[97m";   // bright white  — player bullets + lives

    private readonly Style  $styleOverlay;
    private readonly Border $overlayBorder;

    public function __construct(private readonly SpaceGame $game)
    {
        $this->styleOverlay  = new Style(reverse: true);
        $this->overlayBorder = Border::from([1], BorderPattern::ROUNDED, 'bright_white');
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
        $minWidth  = $W * 2; // 60 inner cols (border drawn by Renderer)
        $minHeight = $H + 3; // 23

        if ($context->getColumns() < $minWidth) {
            return ["\033[31mTerminal too small! ({$minWidth} columns minimum)\033[0m"];
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

        // Flatten grid into lines
        $gridLines = [];
        for ($y = 0; $y < $H; ++$y) {
            $row = '';
            for ($x = 0; $x < $W; ++$x) {
                $row .= $grid[$y][$x];
            }
            $gridLines[] = $row;
        }

        // Overlay via Compositor
        $state = $this->game->getState();
        if ($state !== GameState::Playing) {
            $texts = match ($state) {
                GameState::Paused      => ['', '  [ PAUSE ]  ', '  [P] Resume  [R] Restart  ', ''],
                GameState::GameOver    => ['', '  GAME  OVER  ', \sprintf('  Score: %d  ', $this->game->getScore()), '  [R] Play again  ', ''],
                GameState::WaveCleared => ['', \sprintf('  WAVE %d CLEARED!  ', $this->game->getWave()), '  Next wave...  ', ''],
                default                => [],
            };

            if ([] !== $texts) {
                $overlayW = max(array_map(fn ($t) => AnsiUtils::visibleWidth($t), $texts));
                $overlayH = \count($texts);

                // Build content lines (uniform width, reverse-video)
                $contentLines = [];
                foreach ($texts as $text) {
                    $textLen        = AnsiUtils::visibleWidth($text);
                    $padded         = $text.str_repeat(' ', $overlayW - $textLen);
                    $contentLines[] = $this->styleOverlay->apply($padded);
                }

                // Wrap with a rounded border (+1 row top/bottom, +1 col left/right)
                $overlayLines = $this->overlayBorder->wrapLines($contentLines, $overlayW, $this->styleOverlay);
                $borderedW    = $overlayW + 2;
                $borderedH    = $overlayH + 2;

                $overlayRow = (int) (($H - $borderedH) / 2);
                $overlayCol = (int) (($W * 2 - $borderedW) / 2);

                $gridLines = Compositor::composite(
                    new Layer($gridLines, width: $W * 2, height: $H),
                    new Layer($overlayLines, row: $overlayRow, col: $overlayCol, transparent: true),
                );
            }
        }

        // Assemble final output: score row + game grid (border drawn by Renderer via StyleSheet)
        return [$this->buildScoreRow($W * 2), ...$gridLines];
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
        $wave  = 'WAVE '.$this->game->getWave();
        $lives = 'LIVES: '.str_repeat('🚀', $this->game->getLives());

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
}
