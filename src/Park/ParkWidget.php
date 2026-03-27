<?php

namespace App\Park;

use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;

/**
 * Full-screen park management widget.
 *
 * Layout (visible widths):
 *   map area : MAP_COLS * 2 + 2 = 42 cols
 *   gap      : 1 col
 *   info panel : 24 cols
 *   total    : 67 cols
 *   height   : MAP_ROWS + 2 (map border) + 1 (status bar) = 17 rows
 */
class ParkWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    private const R    = "\033[0m";
    private const DIM  = "\033[2m";
    private const BOLD = "\033[1m";

    // Info panel inner width (between ║ borders)
    private const INFO_W = 22;

    public function __construct(private readonly ParkGame $game)
    {
    }

    // -------------------------------------------------------------------------
    // FocusableInterface
    // -------------------------------------------------------------------------

    protected static function getDefaultKeybindings(): array
    {
        return [
            'cursor_up'    => [Key::UP,    'w'],
            'cursor_down'  => [Key::DOWN,  's'],
            'cursor_left'  => [Key::LEFT,  'a'],
            'cursor_right' => [Key::RIGHT, 'd'],
            'build'        => [Key::ENTER, 'e'],
            'demolish'     => ['x'],
            'mode_path'    => ['1'],
            'mode_coaster' => ['2'],
            'mode_food'    => ['3'],
            'mode_toilet'  => ['4'],
            'mode_demolish'=> ['D', 'd'],  // 'd' also moves cursor left, but D (shift) = demolish mode
            'pause'        => ['p', Key::SPACE],
        ];
    }

    public function handleInput(string $data): void
    {
        $kb = $this->getKeybindings();

        if ($kb->matches($data, 'cursor_up')) {
            $this->game->moveCursor(0, -1);
        } elseif ($kb->matches($data, 'cursor_down')) {
            $this->game->moveCursor(0, 1);
        } elseif ($kb->matches($data, 'cursor_left')) {
            $this->game->moveCursor(-1, 0);
        } elseif ($kb->matches($data, 'cursor_right')) {
            $this->game->moveCursor(1, 0);
        } elseif ($kb->matches($data, 'build')) {
            $this->game->build();
        } elseif ($kb->matches($data, 'demolish')) {
            $this->game->demolish();
        } elseif ($kb->matches($data, 'mode_path')) {
            $this->game->setBuildMode(BuildMode::Path);
        } elseif ($kb->matches($data, 'mode_coaster')) {
            $this->game->setBuildMode(BuildMode::Coaster);
        } elseif ($kb->matches($data, 'mode_food')) {
            $this->game->setBuildMode(BuildMode::FoodStall);
        } elseif ($kb->matches($data, 'mode_toilet')) {
            $this->game->setBuildMode(BuildMode::Toilet);
        } elseif ($kb->matches($data, 'mode_demolish')) {
            $this->game->setBuildMode(BuildMode::Demolish);
        } elseif ($kb->matches($data, 'pause')) {
            $this->game->togglePause();
        }

        $this->invalidate();
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(RenderContext $context): array
    {
        $COLS = ParkGame::MAP_COLS;
        $ROWS = ParkGame::MAP_ROWS;
        $minWidth = $COLS * 2 + 2 + 1 + self::INFO_W + 2; // 67

        if ($context->getColumns() < $minWidth) {
            return ["\033[31mTerminal too small! ({$minWidth} columns minimum)\033[0m"];
        }

        // Visitor positions map [y][x] => count
        $vmap = [];
        foreach ($this->game->getVisitors() as $v) {
            $vmap[$v->y][$v->x] = ($vmap[$v->y][$v->x] ?? 0) + 1;
        }

        $infoLines = $this->buildInfoPanel();
        $cx = $this->game->getCursorX();
        $cy = $this->game->getCursorY();

        $lines = [];

        // Row 0: top borders
        $mapTop   = self::DIM.'╔'.str_repeat('══', $COLS).'╗'.self::R;
        $lines[]  = $mapTop.' '.$infoLines[0];

        // Rows 1..MAP_ROWS: map content + info content
        for ($y = 0; $y < $ROWS; ++$y) {
            $row = self::DIM.'║'.self::R;
            for ($x = 0; $x < $COLS; ++$x) {
                $tile     = $this->game->getTileAt($x, $y);
                $isCursor = ($x === $cx && $y === $cy);
                $vCount   = $vmap[$y][$x] ?? 0;
                $row     .= $this->renderCell($tile, $isCursor, $vCount);
            }
            $row    .= self::DIM.'║'.self::R;
            $lines[] = $row.' '.$infoLines[$y + 1];
        }

        // Row MAP_ROWS+1: bottom borders
        $mapBottom = self::DIM.'╚'.str_repeat('══', $COLS).'╝'.self::R;
        $lines[]   = $mapBottom.' '.$infoLines[$ROWS + 1];

        // Status bar
        $lines[] = $this->buildStatusBar($minWidth);

        return $lines;
    }

    private function renderCell(TileType $tile, bool $isCursor, int $visitorCount): string
    {
        $char = $visitorCount >= 2 ? '@@' : ($visitorCount === 1 ? '@ ' : $tile->chars());

        if ($isCursor) {
            $bg = BuildMode::Demolish === $this->game->getBuildMode()
                ? "\033[41;97m"   // red bg + white text = demolish cursor
                : "\033[47;30m";  // white bg + black text = build cursor

            return $bg.$char.self::R;
        }

        if ($visitorCount > 0) {
            return "\033[97;1m".$char.self::R;
        }

        return $tile->ansi().$char.self::R;
    }

    /**
     * Returns exactly MAP_ROWS + 2 strings, each with visible width INFO_W + 2 = 24.
     *
     * @return string[]
     */
    private function buildInfoPanel(): array
    {
        $W    = self::INFO_W;
        $game = $this->game;
        $mode = $game->getBuildMode();

        // Helpers
        $pad  = static fn (string $s): string => mb_str_pad($s, $W);
        $row  = static fn (string $text, string $ansi = ''): string => '║'.($ansi ? $ansi.$text."\033[0m" : $text).'║';

        $money    = '$'.number_format($game->getMoney());
        $visitors = $game->getVisitorCount();
        $happy    = $game->getAverageHappiness();
        $revenue  = '$'.number_format($game->getTotalRevenue());

        $happyAnsi = $happy >= 70 ? "\033[32m" : ($happy >= 40 ? "\033[33m" : "\033[31m");

        $pauseLabel = $game->isPaused() ? ' [PAUSE]' : '';

        $title     = 'TERMINAL PARK'.$pauseLabel;
        $titleLen  = mb_strlen($title);
        $leftPad   = (int) (($W - $titleLen) / 2);
        $rightPad  = $W - $titleLen - $leftPad;
        $titleRow  = '║'.self::BOLD.str_repeat(' ', $leftPad).$title.str_repeat(' ', $rightPad).self::R.'║';

        $lines   = [];
        $lines[] = '╔'.str_repeat('═', $W).'╗';          // row 0: top border
        $lines[] = $titleRow;                              // row 1: title
        $lines[] = $row($pad(''));                         // row 2: separator
        $lines[] = $row($pad(" \$ Money    : $money"),    "\033[93m");
        $lines[] = $row($pad(" @ Visitors : $visitors"),  "\033[96m");
        $lines[] = $row($pad(" ~ Happiness: {$happy}%"),  $happyAnsi);
        $lines[] = $row($pad(" + Revenue  : $revenue"),   "\033[32m");
        $lines[] = $row($pad(''));                         // separator
        $lines[] = $row($pad(' BUILD :'),                  self::BOLD);
        foreach (BuildMode::cases() as $m) {
            $cost   = null !== $m->cost() ? ' $'.$m->cost() : '';
            $label  = " [{$m->shortKey()}] {$m->label()}";
            $plain  = mb_str_pad($label, $W - mb_strlen($cost)).$cost;
            $isSelected = ($m === $mode);
            $lines[] = $row($plain, $isSelected ? "\033[1;32m" : '');
        }
        $lines[] = $row($pad(''));                         // separator
        $lines[] = '╚'.str_repeat('═', $W).'╝';          // bottom border

        // Exactly MAP_ROWS + 2 = 16 lines
        return $lines;
    }

    private function buildStatusBar(int $totalWidth): string
    {
        $cx    = $this->game->getCursorX();
        $cy    = $this->game->getCursorY();
        $tile  = $this->game->getTileAt($cx, $cy);
        $event = $this->game->getLastEvent();

        $left  = self::BOLD."({$cx},{$cy}) {$tile->label()}".self::R
            .'  '.self::DIM.$event.self::R;

        $hint  = self::DIM.'↑↓←→·WASD  [Entr] Place  [X] Demo  [1-4/D] Mode  [P] Pause  [Q] Quit'.self::R;

        // Visible lengths
        $leftVis = mb_strlen("({$cx},{$cy}) {$tile->label()}  $event");
        $hintVis = mb_strlen('↑↓←→·WASD  [Entr] Place  [X] Demo  [1-4/D] Mode  [P] Pause  [Q] Quit');

        $padding = max(1, $totalWidth - $leftVis - $hintVis);

        return $left.str_repeat(' ', $padding).$hint;
    }
}
