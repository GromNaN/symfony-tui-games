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
 * Renders a full-screen pseudo-3D racing game using Unicode sextant sub-pixel
 * rendering. Each terminal cell is treated as a 2×3 grid of sub-pixels, yielding
 * effectively double horizontal and triple vertical resolution for smooth curves,
 * road edges, and mountain silhouettes.
 *
 * render() returns ($rows-1) game lines + 1 help bar line.
 *
 * True-color ANSI codes are generated only through Palette; all other text
 * styling uses Style::apply().
 */
class RacerWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;
    use QuitableTrait;

    private readonly Style $styleHelp;

    /**
     * 5-row ASCII art glyphs for the countdown and crash overlays.
     * '#' → full-block character; ' ' → background fill.
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
     * Build the full game frame using Unicode sextant sub-pixel rendering.
     *
     * Each terminal cell covers a 2×3 block of sub-pixels (cols×2, rows×3).
     * All game geometry is computed in sub-pixel space, then each block is
     * downsampled to a sextant character, yielding smooth road edges, mountain
     * silhouettes, and tree contours.
     *
     * Pipeline:
     *   1. Road geometry in sub-pixel space (perspective projection)
     *   2. Sprite projection (trees, enemies, player car)
     *   3. Overlay buffer in terminal-cell space (HUD, countdown, crash)
     *   4. Per terminal row: rasterise 3 sub-rows → compose sextant cells
     *
     * @return list<string>
     */
    private function renderFrame(int $cols, int $rows): array
    {
        // Sub-pixel dimensions: 2× horizontal, 3× vertical.
        $sc = $cols * 2;
        $sr = $rows * 3;

        $reset = "\x1b[0m";
        $horizonSY = (int) ($sr * 0.22);
        $roadSubRows = $sr - $horizonSY;
        $roadMaxHalfW = (int) ($sc * 0.38);
        $roadMinHalfW = (int) ($sc * 0.06);

        $position = $this->game->getPosition();
        $parallaxX = $this->game->getParallaxX();
        $playerX = $this->game->getPlayerX();

        $camCurve = $this->track->curveAt((int) ($position / 3));
        $pOff = (int) ($parallaxX * 8); // ×2 for doubled column count

        // -- Road geometry in sub-pixel space --------------------------------

        $rd = [];
        $acc = 0.0;
        for ($r = 0; $r < $roadSubRows; ++$r) {
            $t = $r / max(1, $roadSubRows - 1);
            $t2 = $t * $t;
            $z = 1.0 / max(0.01, $t);
            $hw = (int) ($roadMinHalfW + ($roadMaxHalfW - $roadMinHalfW) * $t2);
            $acc += $this->track->curveAt((int) (($position + $z * 6.0) / 3)) * $t * 1.4;
            $c = (int) ($sc / 2 + $acc - $camCurve * $t * 60); // ×2 for sub-pixel cols
            $s = ((int) ($position * 0.1 + $z * 0.35)) % 2;
            $rd[$r] = ['t' => $t, 'z' => $z, 'hw' => $hw, 'c' => $c, 's' => $s];
        }

        // Convert relative world Z to a sub-pixel row (null = not visible).
        $zToSubRow = static function (float $relZ) use ($roadSubRows, $horizonSY): ?int {
            if ($relZ <= 0) {
                return null;
            }
            $t = 6.0 / $relZ;
            if ($t < 0.01 || $t > 1.0) {
                return null;
            }

            return $horizonSY + (int) ($t * $roadSubRows);
        };

        // -- Tree sprites in sub-pixel space ---------------------------------

        $treeSprites = [];
        foreach ($this->game->getTrees() as $tr) {
            $rz = $tr['z'] - $position;
            if ($rz < 3 || $rz > 500) {
                continue;
            }
            $row = $zToSubRow($rz);
            if (null === $row || $row <= $horizonSY || $row >= $horizonSY + $roadSubRows) {
                continue;
            }
            $srow = $row - $horizonSY;
            if (!isset($rd[$srow])) {
                continue;
            }
            $r = $rd[$srow];
            $edgeX = $r['c'] + $tr['side'] * ($r['hw'] + (int) ($tr['offset'] * $r['hw'] * 0.4));
            $scale = max(3, (int) ($tr['size'] * $r['t'] * 12)); // ×3 for sub-pixel height
            if ($edgeX >= 4 && $edgeX < $sc - 4) {
                $treeSprites[] = ['x' => $edgeX, 'y' => $row, 's' => $scale];
            }
        }

        // -- Enemy sprites in sub-pixel space --------------------------------

        $enemySprites = [];
        foreach ($this->game->getEnemies() as $en) {
            $rz = $en['z'] - $position;
            if ($rz < 3 || $rz > 500) {
                continue;
            }
            $row = $zToSubRow($rz);
            if (null === $row || $row <= $horizonSY || $row >= $horizonSY + $roadSubRows - 6) {
                continue;
            }
            $srow = $row - $horizonSY;
            if (!isset($rd[$srow])) {
                continue;
            }
            $r = $rd[$srow];
            $ex = (int) ($r['c'] + $en['x'] * $r['hw'] * 0.8);
            $hc = max(2, (int) ($r['t'] * 10));
            $enemySprites[] = [
                'x' => $ex,
                'y' => $row,
                'hw' => $hc,
                'col' => Palette::ENEMY_COLORS[$en['color']],
            ];
        }

        // Player car in sub-pixel space: ±6 sub-cols wide, 9 sub-rows tall.
        $psx = (int) ($sc / 2 + $playerX * $roadMaxHalfW * 0.8);
        $carY = ($rows - 4) * 3;

        // Sun position (precomputed, used in the inner loop).
        $sunX = (int) ($sc * 0.75 + $pOff * 0.5);
        $sunY = (int) ($horizonSY * 0.3);

        // Terminal-row horizon (for sky gradient quantisation).
        $termHorizonY = (int) ($horizonSY / 3);

        // -- Overlay buffer in terminal-cell space ---------------------------
        // Overlays (HUD, crash, countdown) bypass the sextant compositing.

        $ov = [];
        $stamp = static function (int $y, int $x, string $text, array $fg, array $bg) use (&$ov, $cols): void {
            for ($i = 0, $len = \strlen($text); $i < $len; ++$i) {
                $cx = $x + $i;
                if ($cx >= 0 && $cx < $cols) {
                    $ov[$y][$cx] = [$bg, $fg, $text[$i]];
                }
            }
        };

        $hb = [15, 15, 25];
        $stamp(0, 1, \sprintf(' %05d ', $this->game->getScore()), [255, 220, 50], $hb);
        $tt = \sprintf(' %s ', $this->game->formatTime());
        $stamp(0, $cols - \strlen($tt) - 1, $tt, [255, 220, 50], $hb);

        if ($this->game->isCounting() && !$this->game->isStarted()) {
            $midY = intdiv($rows, 2) - 3;
            $cv = $this->game->getCountdownValue();
            if ($cv > 0) {
                $this->stampBigText((string) $cv, $midY, [255, 220, 50], [20, 15, 40], $ov, $cols);
            } else {
                $this->stampBigText('GO!', $midY, [80, 255, 80], [15, 40, 15], $ov, $cols);
            }
        }

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

        // -- Rasterise: 3 sub-rows per terminal row, compose sextant cells --

        $lines = [];
        for ($y = 0; $y < $rows; ++$y) {
            // Fill three sub-rows for this terminal row.
            $subRows3 = [[], [], []];

            for ($dr = 0; $dr < 3; ++$dr) {
                $sy = $y * 3 + $dr;

                for ($sx = 0; $sx < $sc; ++$sx) {
                    $bg = null;

                    // Sky and mountains (above horizon).
                    if ($sy < $horizonSY) {
                        // Gradient quantised to terminal-row resolution → uniform sky cells.
                        $bg = Palette::lerp(Palette::SKY_TOP, Palette::SKY_BOT, $y / max(1, $termHorizonY - 1));
                        $my = $horizonSY - 1;
                        $px = $sx + $pOff;
                        $px2 = $sx + (int) ($pOff * 1.6);
                        // Halve sin frequencies (cols doubled) and triple heights.
                        $h1 = (int) ((5 + 4 * sin($px * 0.0175 + 1.5) + 2 * sin($px * 0.04 + 0.5)) * 3);
                        $h2 = (int) ((4 + 3 * sin($px2 * 0.025 + 3) + 2 * sin($px2 * 0.055)) * 3);
                        if ($sy >= $my - $h1) {
                            $bg = Palette::MTN_FAR;
                            if ($sy < $my - $h1 + 6) { // snow: 2 terminal rows → 6 sub-rows
                                $bg = Palette::MTN_SNOW;
                            }
                        }
                        if ($sy >= $my - $h2 + 9) { // +3 terminal rows → +9 sub-rows
                            $bg = Palette::MTN_NEAR;
                        }
                        // Sun disc: radius ×3 for sub-pixel space.
                        if (\sqrt(($sx - $sunX) ** 2 * 0.5 + ($sy - $sunY) ** 2) < 9) {
                            $bg = [255, 240, 180];
                        }
                    }

                    // Road and grass (at or below horizon).
                    if ($sy >= $horizonSY) {
                        $srow = $sy - $horizonSY;
                        if ($srow < $roadSubRows && isset($rd[$srow])) {
                            $seg = $rd[$srow];
                            $ad = abs($sx - $seg['c']);
                            $sw = max(1, (int) ($seg['hw'] * 0.1));
                            $lw = max(0, (int) ($seg['t'] * 4)); // ×2 for sub-pixel cols
                            $bg = match (true) {
                                $ad <= $lw && $lw > 0 => $seg['s'] ? Palette::LINE_WHITE : Palette::ROAD_2,
                                $ad < $seg['hw'] - $sw => $seg['s'] ? Palette::ROAD_1 : Palette::ROAD_2,
                                $ad < $seg['hw'] => $seg['s'] ? Palette::RUMBLE_1 : Palette::RUMBLE_2,
                                default => $seg['s'] ? Palette::GRASS_1 : Palette::GRASS_2,
                            };
                        } else {
                            $bg = Palette::GRASS_1;
                        }
                    }

                    $bg ??= Palette::SKY_TOP;

                    // Trees (triangular foliage, trunk at base).
                    foreach ($treeSprites as $ts) {
                        $tty = $ts['y'] - $ts['s'] * 2;
                        $tky = $ts['y'] - 3; // 1 terminal row trunk → 3 sub-rows
                        if ($sy < $tty || $sy > $ts['y'] + 2) {
                            continue;
                        }
                        if ($sy >= $tky && $sy <= $ts['y'] + 2 && $sx === $ts['x']) {
                            $bg = Palette::TREE_TRUNK;
                        } elseif ($sy >= $tty && $sy < $tky) {
                            $cr = max(1, $tky - $tty);
                            $hw = max(0, (int) ($ts['s'] * ($sy - $tty + 1) / $cr));
                            if (abs($sx - $ts['x']) <= $hw) {
                                $bg = (($sy - $tty) % 2 === 0) ? Palette::TREE_LEAF_1 : Palette::TREE_LEAF_2;
                            }
                        }
                    }

                    // Enemy cars: 2 terminal rows = 6 sub-rows (top 3 = window, bottom 3 = body).
                    foreach ($enemySprites as $es) {
                        $rx = $sx - $es['x'];
                        $ry = $sy - $es['y'];
                        if ($ry >= -3 && $ry <= 2 && abs($rx) <= $es['hw']) {
                            $bg = ($ry < 0 && abs($rx) < $es['hw'])
                                ? Palette::CAR_WINDOW
                                : $es['col'];
                            if (abs($rx) === $es['hw']) {
                                $bg = Palette::CAR_WHEEL;
                            }
                        }
                    }

                    // Player car: 9 sub-rows tall, ±6 sub-cols wide; blinks after crash.
                    $rsx = $sx - $psx;
                    $rsy = $sy - $carY;
                    if ($this->game->isAlive() || $this->game->isBlinkOn()) {
                        if ($rsy >= 0 && $rsy <= 2 && abs($rsx) <= 6) {
                            $bg = match (true) {
                                abs($rsx) <= 2 => Palette::CAR_WINDOW,
                                abs($rsx) <= 4 => Palette::CAR_TOP,
                                default => Palette::CAR_BODY,
                            };
                        } elseif ($rsy >= 3 && $rsy <= 5 && abs($rsx) <= 6) {
                            $bg = 6 === abs($rsx) ? Palette::CAR_WHEEL : Palette::CAR_BODY;
                            if (abs($rsx) <= 1) {
                                $bg = Palette::CAR_HIGH; // centre racing stripe
                            }
                        } elseif ($rsy >= 6 && $rsy <= 8 && abs($rsx) <= 6) {
                            $bg = match (true) {
                                6 === abs($rsx) => Palette::CAR_WHEEL,
                                abs($rsx) >= 4 => Palette::CAR_TAIL,
                                default => Palette::CAR_BODY,
                            };
                        }
                    }

                    $subRows3[$dr][$sx] = $bg;
                }
            }

            // Compose terminal cells from 6-sub-pixel groups.
            $line = '';
            for ($x = 0; $x < $cols; ++$x) {
                if (isset($ov[$y][$x])) {
                    // Overlay cells bypass sextant compositing entirely.
                    [$obg, $ofg, $och] = $ov[$y][$x];
                    $line .= Palette::bg($obg);
                    if (null !== $ofg) {
                        $line .= Palette::fg($ofg);
                    }
                    $line .= $och;
                } else {
                    $pixels = [
                        $subRows3[0][$x * 2],     $subRows3[0][$x * 2 + 1],
                        $subRows3[1][$x * 2],     $subRows3[1][$x * 2 + 1],
                        $subRows3[2][$x * 2],     $subRows3[2][$x * 2 + 1],
                    ];
                    [$char, $fg, $bg] = $this->composeSextantCell($pixels);
                    $line .= Palette::bg($bg);
                    if (null !== $fg) {
                        $line .= Palette::fg($fg);
                    }
                    $line .= $char;
                }
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

    // -------------------------------------------------------------------------
    // Sextant helpers
    // -------------------------------------------------------------------------

    /**
     * Compose a terminal cell from 6 sub-pixels ordered [top-left … bot-right].
     *
     * Finds the two most frequent colors: most common = background (fill),
     * second = foreground (active sextant bits).
     * Returns [char, fg_color|null, bg_color].
     *
     * @param list<array{int,int,int}> $pixels
     *
     * @return array{string, array{int,int,int}|null, array{int,int,int}}
     */
    private function composeSextantCell(array $pixels): array
    {
        $counts = [];
        $colors = [];
        foreach ($pixels as $px) {
            $k = ($px[0] << 16) | ($px[1] << 8) | $px[2];
            $counts[$k] = ($counts[$k] ?? 0) + 1;
            $colors[$k] ??= $px;
        }

        if (1 === \count($counts)) {
            return [' ', null, $pixels[0]];
        }

        \arsort($counts);
        $keys = \array_keys($counts);
        $bgKey = $keys[0];
        $fgKey = $keys[1];

        // Sextant mask: bit i set when sub-pixel i differs from the background.
        $mask = 0;
        foreach ($pixels as $i => $px) {
            $k = ($px[0] << 16) | ($px[1] << 8) | $px[2];
            if ($k !== $bgKey) {
                $mask |= (1 << $i);
            }
        }

        return [self::sextantChar($mask), $colors[$fgKey], $colors[$bgKey]];
    }

    /**
     * Return the UTF-8 character for a 6-bit sextant mask.
     *
     * Bit layout (positions 1–6 in Unicode naming):
     *   bit 0 (1)  = top-left     bit 1 (2)  = top-right
     *   bit 2 (4)  = mid-left     bit 3 (8)  = mid-right
     *   bit 4 (16) = bot-left     bit 5 (32) = bot-right
     *
     * Special cases reuse existing block elements:
     *   mask  0 → ' '   (empty)
     *   mask 21 → ▌ U+258C  (left column: bits 0,2,4)
     *   mask 42 → ▐ U+2590  (right column: bits 1,3,5)
     *   mask 63 → █ U+2588  (full block)
     *
     * The remaining 60 masks map to U+1FB00–U+1FB3B (all share UTF-8 prefix
     * F0 9F AC; 4th byte = 0x80 + index, skipping over masks 21 and 42):
     *   masks  1–20 → index = mask − 1    (U+1FB00–U+1FB13)
     *   masks 22–41 → index = mask − 2    (U+1FB14–U+1FB27)
     *   masks 43–62 → index = mask − 3    (U+1FB28–U+1FB3B)
     */
    private static function sextantChar(int $mask): string
    {
        return match ($mask) {
            0 => ' ',
            21 => "\xe2\x96\x8c",  // ▌ U+258C LEFT HALF BLOCK
            42 => "\xe2\x96\x90",  // ▐ U+2590 RIGHT HALF BLOCK
            63 => "\xe2\x96\x88",  // █ U+2588 FULL BLOCK
            default => "\xF0\x9F\xAC".\chr(0x80 + match (true) {
                $mask < 21 => $mask - 1,
                $mask < 42 => $mask - 2,
                default => $mask - 3,
            }),
        };
    }

    // -------------------------------------------------------------------------
    // Big-text overlay
    // -------------------------------------------------------------------------

    /**
     * Stamp 5-row big-pixel text into the overlay buffer (centred horizontally).
     *
     * '#' pixels → full-block █ in $fg on $bg.
     * Space pixels → $bg-on-$bg for a solid background box.
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
            $totalW += \strlen($glyph[0]) + 1;
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
                            ? [$bg, $fg, "\xe2\x96\x88"]  // █ filled pixel
                            : [$bg, $bg, ' '];              // background fill
                    }
                }
            }
            $cx += \strlen($glyph[0]) + 1;
        }
    }
}
