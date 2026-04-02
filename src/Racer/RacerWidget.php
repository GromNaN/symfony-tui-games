<?php

namespace App\Racer;

use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\QuitableTrait;

/**
 * Racer TUI widget.
 *
 * Renders a full-screen pseudo-3D racing game: per-pixel RGB frame buffer,
 * perspective road with curves, tree sprites, enemy cars, and overlay text.
 * render() returns $rows-1 game lines + 1 help bar line.
 *
 * True-color ANSI codes are used for pixel rendering via Palette; all other
 * text styling uses Style::apply().
 */
class RacerWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;
    use QuitableTrait;

    private readonly Style $styleHelp;

    /**
     * 5-row ASCII art glyphs for the countdown and crash overlays.
     * Each '#' becomes a full-block character; spaces become background fill.
     */
    private const GLYPHS = [
        '3' => [' ##### ', '     # ', '  ###  ', '     # ', ' ##### '],
        '2' => [' ##### ', '     # ', ' ##### ', ' #     ', ' ##### '],
        '1' => ['   #   ', '  ##   ', '   #   ', '   #   ', '  ###  '],
        'G' => ['  #### ', ' #     ', ' # ### ', ' #   # ', '  #### '],
        'O' => ['  ###  ', ' #   # ', ' #   # ', ' #   # ', '  ###  '],
        '!' => ['   #   ', '   #   ', '   #   ', '       ', '   #   '],
        'C' => ['  #### ', ' #     ', ' #     ', ' #     ', '  #### '],
        'R' => [' ####  ', ' #   # ', ' ####  ', ' #  #  ', ' #   # '],
        'A' => ['  ###  ', ' #   # ', ' ##### ', ' #   # ', ' #   # '],
        'S' => ['  #### ', ' #     ', '  ###  ', '     # ', ' ####  '],
        'H' => [' #   # ', ' #   # ', ' ##### ', ' #   # ', ' #   # '],
        ' ' => ['   ', '   ', '   ', '   ', '   '],
    ];

    public function __construct(
        private readonly RacerGame $game,
        private readonly RacerTrack $track,
    ) {
        $this->styleHelp = new Style(dim: true);
    }

    // -------------------------------------------------------------------------
    // Keybindings
    // -------------------------------------------------------------------------

    protected static function getDefaultKeybindings(): array
    {
        return [
            'steer_left' => [Key::LEFT,  'a'],
            'steer_right' => [Key::RIGHT, 'd'],
            'stop_steer' => [Key::UP,    Key::DOWN],
            'start' => [Key::SPACE],
            'restart' => [Key::ENTER],
            'quit' => [Key::ctrl('c'), 'q', Key::ESCAPE],
        ];
    }

    public function handleInput(string $data): void
    {
        $kb = $this->getKeybindings();

        if ($kb->matches($data, 'quit')) {
            $this->dispatchQuit();
        } elseif ($kb->matches($data, 'restart') && !$this->game->isAlive()) {
            $this->game->reset();
        } elseif ($kb->matches($data, 'start')) {
            $this->game->startCountdown();
        } elseif ($kb->matches($data, 'steer_left')) {
            $this->game->steer(-1);
        } elseif ($kb->matches($data, 'steer_right')) {
            $this->game->steer(1);
        } elseif ($kb->matches($data, 'stop_steer')) {
            $this->game->steer(0);
        }
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(RenderContext $context): array
    {
        $cols = $context->getColumns();
        $rows = max(4, $context->getRows() - 1); // reserve 1 row for the help bar

        return [...$this->renderFrame($cols, $rows), $this->renderHelpBar($cols)];
    }

    /**
     * Build the full game frame as an array of ANSI-colored strings.
     *
     * Pipeline:
     *   1. Pre-compute road segment geometry (perspective projection)
     *   2. Project tree and enemy world positions to screen coordinates
     *   3. Build the overlay buffer (HUD, countdown, crash text)
     *   4. Rasterise every cell: sky → road → trees → enemies → player → overlay
     *
     * @return list<string>
     */
    private function renderFrame(int $cols, int $rows): array
    {
        $reset = "\x1b[0m";
        $horizonY = (int) ($rows * 0.22);
        $roadRows = $rows - $horizonY;
        $roadMaxHalfW = (int) ($cols * 0.38);
        $roadMinHalfW = (int) ($cols * 0.06);

        $position = $this->game->getPosition();
        $parallaxX = $this->game->getParallaxX();
        $playerX = $this->game->getPlayerX();

        $camCurve = $this->track->curveAt((int) ($position / 3));
        $pOff = (int) ($parallaxX * 4);

        // -- Road geometry -------------------------------------------------

        $rd = [];
        $acc = 0.0;
        for ($sr = 0; $sr < $roadRows; ++$sr) {
            $t = $sr / max(1, $roadRows - 1);
            $t2 = $t * $t;
            $z = 1.0 / max(0.01, $t);
            $hw = (int) ($roadMinHalfW + ($roadMaxHalfW - $roadMinHalfW) * $t2);
            $acc += $this->track->curveAt((int) (($position + $z * 6.0) / 3)) * $t * 1.4;
            $c = (int) ($cols / 2 + $acc - $camCurve * $t * 30);
            $s = ((int) ($position * 0.1 + $z * 0.35)) % 2;
            $rd[$sr] = ['t' => $t, 'z' => $z, 'hw' => $hw, 'c' => $c, 's' => $s];
        }

        // Convert a relative world Z-depth to a screen row (null = not visible).
        $zToRow = static function (float $relZ) use ($roadRows, $horizonY): ?int {
            if ($relZ <= 0) {
                return null;
            }
            $t = 6.0 / $relZ;
            if ($t < 0.01 || $t > 1.0) {
                return null;
            }

            return $horizonY + (int) ($t * $roadRows);
        };

        // -- Tree sprites --------------------------------------------------

        $treeSprites = [];
        foreach ($this->game->getTrees() as $tr) {
            $rz = $tr['z'] - $position;
            if ($rz < 3 || $rz > 500) {
                continue;
            }
            $row = $zToRow($rz);
            if (null === $row || $row <= $horizonY || $row >= $horizonY + $roadRows) {
                continue;
            }
            $sr = $row - $horizonY;
            if (!isset($rd[$sr])) {
                continue;
            }
            $r = $rd[$sr];
            $edgeX = $r['c'] + $tr['side'] * ($r['hw'] + (int) ($tr['offset'] * $r['hw'] * 0.4));
            $scale = max(1, (int) ($tr['size'] * $r['t'] * 4));
            if ($edgeX >= 2 && $edgeX < $cols - 2) {
                $treeSprites[] = ['x' => $edgeX, 'y' => $row, 's' => $scale];
            }
        }

        // -- Enemy sprites -------------------------------------------------

        $enemySprites = [];
        foreach ($this->game->getEnemies() as $en) {
            $rz = $en['z'] - $position;
            if ($rz < 3 || $rz > 500) {
                continue;
            }
            $row = $zToRow($rz);
            if (null === $row || $row <= $horizonY || $row >= $horizonY + $roadRows - 2) {
                continue;
            }
            $sr = $row - $horizonY;
            if (!isset($rd[$sr])) {
                continue;
            }
            $r = $rd[$sr];
            $ex = (int) ($r['c'] + $en['x'] * $r['hw'] * 0.8);
            $hc = max(1, (int) ($r['t'] * 5));
            $enemySprites[] = [
                'x' => $ex,
                'y' => $row,
                'hw' => $hc,
                'col' => Palette::ENEMY_COLORS[$en['color']],
            ];
        }

        // Player car centre-X on screen.
        $psx = (int) ($cols / 2 + $playerX * $roadMaxHalfW * 0.8);

        // -- Overlay buffer [$bg, $fg|null, $char] -------------------------

        $ov = [];
        $stamp = static function (int $y, int $x, string $text, array $fg, array $bg) use (&$ov, $cols): void {
            for ($i = 0, $len = \strlen($text); $i < $len; ++$i) {
                $cx = $x + $i;
                if ($cx >= 0 && $cx < $cols) {
                    $ov[$y][$cx] = [$bg, $fg, $text[$i]];
                }
            }
        };

        // Score / time HUD (top-left and top-right).
        $hb = [15, 15, 25];
        $stamp(0, 1, \sprintf(' %05d ', $this->game->getScore()), [255, 220, 50], $hb);
        $tt = \sprintf(' %s ', $this->game->formatTime());
        $stamp(0, $cols - \strlen($tt) - 1, $tt, [255, 220, 50], $hb);

        // Countdown big text.
        if ($this->game->isCounting() && !$this->game->isStarted()) {
            $midY = intdiv($rows, 2) - 3;
            $cv = $this->game->getCountdownValue();
            if ($cv > 0) {
                $this->stampBigText((string) $cv, $midY, [255, 220, 50], [20, 15, 40], $ov, $cols);
            } else {
                $this->stampBigText('GO!', $midY, [80, 255, 80], [15, 40, 15], $ov, $cols);
            }
        }

        // Crash big text + score box.
        if (!$this->game->isAlive() && $this->game->getDeathTimer() > 0.4) {
            $midY = intdiv($rows, 2) - 5;
            $crashBg = [40, 10, 10];
            $this->stampBigText('CRASH', $midY, [255, 50, 50], $crashBg, $ov, $cols);

            $infoY = $midY + 7;
            $infoBg = [30, 8, 8];
            $s1 = \sprintf(
                '  Score: %d  Best: %d  Time: %s  ',
                $this->game->getScore(),
                $this->game->getHighScore(),
                $this->game->formatTime(),
            );
            $stamp($infoY, intdiv($cols - \strlen($s1), 2), $s1, [200, 200, 210], $infoBg);

            if ($this->game->isBlinkOn()) {
                $coin = '  >>> INSERT COIN <<<  ';
                $stamp($infoY + 2, intdiv($cols - \strlen($coin), 2), $coin, [255, 220, 50], $infoBg);
            }
        }

        // -- Rasterise -----------------------------------------------------

        $lines = [];
        for ($y = 0; $y < $rows; ++$y) {
            $line = '';
            for ($x = 0; $x < $cols; ++$x) {
                $bg = null;
                $fg = null;
                $ch = ' ';

                // Sky and mountains (above horizon).
                if ($y < $horizonY) {
                    $bg = Palette::lerp(Palette::SKY_TOP, Palette::SKY_BOT, $y / max(1, $horizonY - 1));
                    $my = $horizonY - 1;
                    $px = $x + $pOff;
                    $px2 = $x + (int) ($pOff * 1.6);
                    $h1 = (int) (5 + 4 * sin($px * 0.035 + 1.5) + 2 * sin($px * 0.08 + 0.5));
                    $h2 = (int) (4 + 3 * sin($px2 * 0.05 + 3) + 2 * sin($px2 * 0.11));
                    if ($y >= $my - $h1) {
                        $bg = Palette::MTN_FAR;
                        if ($y < $my - $h1 + 2) {
                            $bg = Palette::MTN_SNOW;
                        }
                    }
                    if ($y >= $my - $h2 + 3) {
                        $bg = Palette::MTN_NEAR;
                    }
                    // Sun disc.
                    $sx = (int) ($cols * 0.75 + $pOff * 0.5);
                    if (sqrt(($x - $sx) ** 2 * 0.5 + ($y - (int) ($horizonY * 0.3)) ** 2) < 3) {
                        $bg = [255, 240, 180];
                    }
                }

                // Road and grass (at and below horizon).
                if ($y >= $horizonY) {
                    $sr = $y - $horizonY;
                    if ($sr < $roadRows && isset($rd[$sr])) {
                        $r = $rd[$sr];
                        $ad = abs($x - $r['c']);
                        $sw = max(1, (int) ($r['hw'] * 0.1));
                        $lw = max(0, (int) ($r['t'] * 2));
                        $bg = match (true) {
                            $ad <= $lw && $lw > 0 => $r['s'] ? Palette::LINE_WHITE : Palette::ROAD_2,
                            $ad < $r['hw'] - $sw => $r['s'] ? Palette::ROAD_1 : Palette::ROAD_2,
                            $ad < $r['hw'] => $r['s'] ? Palette::RUMBLE_1 : Palette::RUMBLE_2,
                            default => $r['s'] ? Palette::GRASS_1 : Palette::GRASS_2,
                        };
                    } else {
                        $bg = Palette::GRASS_1;
                    }
                }

                $bg ??= Palette::SKY_TOP;

                // Trees (foliage tapers toward top, trunk at base).
                foreach ($treeSprites as $ts) {
                    $tty = $ts['y'] - $ts['s'] * 2;
                    $tky = $ts['y'] - 1;
                    if ($y >= $tky && $y <= $ts['y'] && $x === $ts['x']) {
                        $bg = Palette::TREE_TRUNK;
                    }
                    if ($y >= $tty && $y < $tky) {
                        $cr = max(1, $tky - $tty);
                        $hw = max(0, (int) ($ts['s'] * ($y - $tty + 1) / $cr));
                        if (abs($x - $ts['x']) <= $hw) {
                            $bg = (($y - $tty) % 2 === 0) ? Palette::TREE_LEAF_1 : Palette::TREE_LEAF_2;
                        }
                    }
                }

                // Enemy cars (2-row sprite: window row + body row).
                foreach ($enemySprites as $es) {
                    $rx = $x - $es['x'];
                    $ry = $y - $es['y'];
                    if ($ry >= -1 && $ry <= 0 && abs($rx) <= $es['hw']) {
                        $bg = (-1 === $ry && abs($rx) < $es['hw'])
                            ? Palette::CAR_WINDOW
                            : $es['col'];
                        if (abs($rx) === $es['hw']) {
                            $bg = Palette::CAR_WHEEL;
                        }
                    }
                }

                // Player car (3-row sprite; blinks after crash).
                $cy = $rows - 4;
                $rx = $x - $psx;
                $ry = $y - $cy;
                if ($this->game->isAlive() || $this->game->isBlinkOn()) {
                    if (0 === $ry && abs($rx) <= 3) {
                        $bg = match (true) {
                            abs($rx) <= 1 => Palette::CAR_WINDOW,
                            2 === abs($rx) => Palette::CAR_TOP,
                            default => Palette::CAR_BODY,
                        };
                    } elseif (1 === $ry && abs($rx) <= 3) {
                        $bg = 3 === abs($rx) ? Palette::CAR_WHEEL : Palette::CAR_BODY;
                        if (0 === $rx) {
                            $bg = Palette::CAR_HIGH;
                            $fg = [255, 255, 255];
                            $ch = '|';
                        }
                    } elseif (2 === $ry && abs($rx) <= 3) {
                        $bg = match (true) {
                            3 === abs($rx) => Palette::CAR_WHEEL,
                            2 === abs($rx) => Palette::CAR_TAIL,
                            default => Palette::CAR_BODY,
                        };
                    }
                }

                // Overlay (HUD, countdown, crash) always wins.
                if (isset($ov[$y][$x])) {
                    [$bg, $fg, $ch] = $ov[$y][$x];
                }

                $line .= Palette::bg($bg);
                if (null !== $fg) {
                    $line .= Palette::fg($fg);
                }
                $line .= $ch;
            }
            $line .= $reset;
            $lines[] = $line;
        }

        return $lines;
    }

    /** One-line help bar below the game frame. */
    private function renderHelpBar(int $cols): string
    {
        $hint = match (true) {
            !$this->game->isAlive() => '[Enter] restart    [A/D ←/→] steer    [Q] quit',
            !$this->game->isStarted() && !$this->game->isCounting() => '[Space] start    [A/D ←/→] steer    [Q] quit',
            default => '[A/D ←/→] steer    [Q] quit',
        };

        return $this->styleHelp->apply(\str_pad($hint, $cols));
    }

    /**
     * Stamp 5-row big-pixel text into the overlay buffer (centred horizontally).
     *
     * '#' pixels become full-block characters (█) in $fg on $bg.
     * Space pixels become $bg-on-$bg to create a solid background box.
     *
     * @param array<int, array<int, array{0: array, 1: array|null, 2: string}>> $ov
     */
    private function stampBigText(
        string $text,
        int $centerY,
        array $fg,
        array $bg,
        array &$ov,
        int $cols,
    ): void {
        $text = strtoupper($text);
        $totalW = 0;
        for ($i = 0; $i < \strlen($text); ++$i) {
            $glyph = self::GLYPHS[$text[$i]] ?? self::GLYPHS[' '];
            $totalW += \strlen($glyph[0]) + 1; // +1 gap between characters
        }
        $totalW = max(0, $totalW - 1);
        $cx = intdiv($cols - $totalW, 2);

        for ($i = 0; $i < \strlen($text); ++$i) {
            $glyph = self::GLYPHS[$text[$i]] ?? self::GLYPHS[' '];
            foreach ($glyph as $row => $rowStr) {
                for ($gi = 0, $gLen = \strlen($rowStr); $gi < $gLen; ++$gi) {
                    $px = $cx + $gi;
                    $py = $centerY + $row;
                    if ($px >= 0 && $px < $cols) {
                        $ov[$py][$px] = '#' === $rowStr[$gi]
                            ? [$bg, $fg, "\xe2\x96\x88"]  // filled pixel: █
                            : [$bg, $bg, ' '];              // background fill
                    }
                }
            }
            $cx += \strlen($glyph[0]) + 1;
        }
    }
}
