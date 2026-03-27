<?php

namespace App\Park;

class ParkGame
{
    public const MAP_COLS = 20;
    public const MAP_ROWS = 14;
    public const ENTRANCE_X = 10;
    public const ENTRANCE_Y = 13;
    private const MAX_VISITORS = 15;
    private const VISIT_COOLDOWN = 8; // ticks before re-using same attraction

    /** @var array<int, array<int, TileType>> [y][x] */
    private array $map;

    /** @var list<Visitor> */
    private array $visitors = [];

    private int $money;
    private int $tick = 0;
    private int $totalVisitors = 0;
    private int $totalRevenue = 0;
    private BuildMode $buildMode = BuildMode::Path;
    private int $cursorX;
    private int $cursorY;
    private bool $paused = false;

    /** Most recent event message for the status bar. */
    private string $lastEvent = 'Build a path from >> to attract visitors!';

    public function __construct(int $startingMoney = 2000)
    {
        $this->money = $startingMoney;
        $this->cursorX = self::ENTRANCE_X;
        $this->cursorY = self::ENTRANCE_Y - 1;
        $this->initMap();
    }

    private function initMap(): void
    {
        for ($y = 0; $y < self::MAP_ROWS; ++$y) {
            for ($x = 0; $x < self::MAP_COLS; ++$x) {
                $this->map[$y][$x] = TileType::Grass;
            }
        }
        $this->map[self::ENTRANCE_Y][self::ENTRANCE_X] = TileType::Entrance;
    }

    public function tick(): void
    {
        if ($this->paused) {
            return;
        }

        ++$this->tick;
        $this->spawnVisitors();
        $this->moveVisitors();
    }

    // -------------------------------------------------------------------------
    // Visitor lifecycle
    // -------------------------------------------------------------------------

    private function spawnVisitors(): void
    {
        if (\count($this->visitors) >= self::MAX_VISITORS) {
            return;
        }
        if (0 !== $this->tick % 4) {
            return;
        }
        if (!$this->hasPathAdjacentToEntrance()) {
            return;
        }
        if (random_int(0, 2) === 0) {
            return; // ~33% skip
        }

        $this->visitors[] = new Visitor(self::ENTRANCE_X, self::ENTRANCE_Y);
        ++$this->totalVisitors;
        $this->lastEvent = 'Nouveau visiteur arrive !';
    }

    private function hasPathAdjacentToEntrance(): bool
    {
        foreach ([[-1, 0], [1, 0], [0, -1]] as [$dx, $dy]) {
            $nx = self::ENTRANCE_X + $dx;
            $ny = self::ENTRANCE_Y + $dy;
            if ($this->inBounds($nx, $ny) && $this->map[$ny][$nx] !== TileType::Grass) {
                return true;
            }
        }

        return false;
    }

    private function moveVisitors(): void
    {
        foreach ($this->visitors as $key => $visitor) {
            $this->stepVisitor($visitor);

            // Check for attraction at new position
            $tile = $this->map[$visitor->y][$visitor->x];
            if (\in_array($tile, [TileType::Coaster, TileType::FoodStall, TileType::Toilet], true)) {
                $tileKey = "{$visitor->x},{$visitor->y}";
                $lastTick = $visitor->lastVisit[$tileKey] ?? -999;
                if ($this->tick - $lastTick >= self::VISIT_COOLDOWN) {
                    $visitor->lastVisit[$tileKey] = $this->tick;
                    $earned = $tile->income();
                    $this->money += $earned;
                    $this->totalRevenue += $earned;
                    $visitor->happiness = min(100, $visitor->happiness + $tile->happinessBoost());
                    $this->lastEvent = \sprintf('+$%d (%s)', $earned, $tile->label());
                }
            }

            // Natural decay
            --$visitor->stepsLeft;
            $visitor->happiness = max(0, $visitor->happiness - 1);

            if ($visitor->stepsLeft <= 0 || $visitor->happiness <= 0) {
                unset($this->visitors[$key]);
            }
        }

        $this->visitors = array_values($this->visitors);
    }

    private function stepVisitor(Visitor $visitor): void
    {
        $returningHome = $visitor->stepsLeft < 25;

        $dirs = [[0, -1], [0, 1], [-1, 0], [1, 0]];
        shuffle($dirs);

        // When returning home, prefer directions closer to the entrance
        if ($returningHome) {
            usort($dirs, function (array $a, array $b) use ($visitor): int {
                $ax = abs(($visitor->x + $a[0]) - self::ENTRANCE_X) + abs(($visitor->y + $a[1]) - self::ENTRANCE_Y);
                $bx = abs(($visitor->x + $b[0]) - self::ENTRANCE_X) + abs(($visitor->y + $b[1]) - self::ENTRANCE_Y);

                return $ax <=> $bx;
            });
        }

        // Priority 1: unvisited attractions
        foreach ($dirs as [$dx, $dy]) {
            $nx = $visitor->x + $dx;
            $ny = $visitor->y + $dy;
            if (!$this->inBounds($nx, $ny)) {
                continue;
            }
            $tile = $this->map[$ny][$nx];
            if (!\in_array($tile, [TileType::Coaster, TileType::FoodStall, TileType::Toilet], true)) {
                continue;
            }
            $key = "$nx,$ny";
            $lastTick = $visitor->lastVisit[$key] ?? -999;
            if ($this->tick - $lastTick >= self::VISIT_COOLDOWN) {
                $visitor->x = $nx;
                $visitor->y = $ny;

                return;
            }
        }

        // Priority 2: any non-grass tile
        foreach ($dirs as [$dx, $dy]) {
            $nx = $visitor->x + $dx;
            $ny = $visitor->y + $dy;
            if ($this->inBounds($nx, $ny) && $this->map[$ny][$nx] !== TileType::Grass) {
                $visitor->x = $nx;
                $visitor->y = $ny;

                return;
            }
        }
        // Visitor is stuck — stays put, burns steps
    }

    // -------------------------------------------------------------------------
    // Build / demolish
    // -------------------------------------------------------------------------

    public function build(): void
    {
        $tileType = $this->buildMode->tileType();
        if (null === $tileType) {
            $this->demolish();

            return;
        }

        $cost = $tileType->cost() ?? 0;
        if ($this->money < $cost) {
            $this->lastEvent = 'Not enough money!';

            return;
        }

        $current = $this->map[$this->cursorY][$this->cursorX];
        if ($current !== TileType::Grass) {
            $this->lastEvent = 'Case deja occupee !';

            return;
        }

        $this->map[$this->cursorY][$this->cursorX] = $tileType;
        $this->money -= $cost;
        $this->lastEvent = \sprintf('%s construit (-$%d)', $tileType->label(), $cost);
    }

    public function demolish(): void
    {
        $tile = $this->map[$this->cursorY][$this->cursorX];
        if (\in_array($tile, [TileType::Grass, TileType::Entrance], true)) {
            return;
        }

        $this->map[$this->cursorY][$this->cursorX] = TileType::Grass;
        $refund = (int) (($tile->cost() ?? 0) * 0.5);
        $this->money += $refund;
        $this->lastEvent = \sprintf('%s demoli (+$%d remboursement)', $tile->label(), $refund);
    }

    // -------------------------------------------------------------------------
    // Cursor & mode
    // -------------------------------------------------------------------------

    public function moveCursor(int $dx, int $dy): void
    {
        $this->cursorX = max(0, min(self::MAP_COLS - 1, $this->cursorX + $dx));
        $this->cursorY = max(0, min(self::MAP_ROWS - 1, $this->cursorY + $dy));
    }

    public function setBuildMode(BuildMode $mode): void
    {
        $this->buildMode = $mode;
    }

    public function togglePause(): void
    {
        $this->paused = !$this->paused;
        $this->lastEvent = $this->paused ? 'Paused' : 'Resumed';
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getMoney(): int { return $this->money; }
    public function getTick(): int { return $this->tick; }
    public function getVisitorCount(): int { return \count($this->visitors); }
    public function getTotalVisitors(): int { return $this->totalVisitors; }
    public function getTotalRevenue(): int { return $this->totalRevenue; }
    public function getBuildMode(): BuildMode { return $this->buildMode; }
    public function getCursorX(): int { return $this->cursorX; }
    public function getCursorY(): int { return $this->cursorY; }
    public function isPaused(): bool { return $this->paused; }
    public function getLastEvent(): string { return $this->lastEvent; }

    /** @return list<Visitor> */
    public function getVisitors(): array { return $this->visitors; }

    public function getTileAt(int $x, int $y): TileType
    {
        return $this->map[$y][$x] ?? TileType::Grass;
    }

    public function getAverageHappiness(): int
    {
        if ([] === $this->visitors) {
            return 100;
        }

        return (int) (array_sum(array_map(fn ($v) => $v->happiness, $this->visitors)) / \count($this->visitors));
    }

    private function inBounds(int $x, int $y): bool
    {
        return $x >= 0 && $x < self::MAP_COLS && $y >= 0 && $y < self::MAP_ROWS;
    }
}
