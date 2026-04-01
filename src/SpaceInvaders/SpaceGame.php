<?php

namespace App\SpaceInvaders;

class SpaceGame
{
    public const GAME_W = 30;
    public const GAME_H = 20;
    public const PLAYER_Y = 19;

    public const ROWS = 4;
    public const COLS = 10;
    public const INVADER_START_X = 5;
    public const INVADER_START_Y = 2;

    private const MAX_PLAYER_BULLETS = 2;
    private const MAX_INVADER_BULLETS = 3;
    private const BULLET_INTERVAL = 0.08;
    private const SHOOT_INTERVAL = 0.9;
    private const EXPLOSION_DURATION = 0.3;
    private const INVINCIBLE_DURATION = 2.0;

    private GameState $state = GameState::Playing;

    private int $playerX;
    private int $score = 0;
    private int $lives = 3;
    private int $wave = 1;

    /**
     * @var array<int, array<int, bool>> [row][col] true = alive
     */
    private array $invaders = [];

    /** @var list<array{x:int,y:int}> player bullets */
    private array $playerBullets = [];

    /** @var list<array{x:int,y:int}> invader bullets */
    private array $invaderBullets = [];

    /** @var list<array{x:int,y:int,timer:float}> short-lived explosions */
    private array $explosions = [];

    /** Formation offset from the initial grid positions. */
    private int $formationOffsetX = 0;
    private int $formationOffsetY = 0;
    private int $formationDir = 1; // 1 = right, -1 = left

    private float $formationTimer = 0.0;
    private float $bulletTimer = 0.0;
    private float $shootTimer = 0.0;
    private float $invincibleTimer = 0.0;
    private float $waveClearTimer = 0.0;

    public function __construct()
    {
        $this->playerX = (int) (self::GAME_W / 2);
        $this->spawnWave();
    }

    // -------------------------------------------------------------------------
    // Public actions
    // -------------------------------------------------------------------------

    public function movePlayer(int $dx): void
    {
        $this->playerX = max(0, min(self::GAME_W - 1, $this->playerX + $dx));
    }

    public function shoot(): void
    {
        if (\count($this->playerBullets) >= self::MAX_PLAYER_BULLETS) {
            return;
        }
        $this->playerBullets[] = ['x' => $this->playerX, 'y' => self::PLAYER_Y - 1];
    }

    public function togglePause(): void
    {
        if (GameState::Playing === $this->state) {
            $this->state = GameState::Paused;
        } elseif (GameState::Paused === $this->state) {
            $this->state = GameState::Playing;
        }
    }

    public function reset(): void
    {
        $this->score = 0;
        $this->lives = 3;
        $this->wave = 1;
        $this->playerX = (int) (self::GAME_W / 2);
        $this->playerBullets = [];
        $this->invaderBullets = [];
        $this->explosions = [];
        $this->formationOffsetX = 0;
        $this->formationOffsetY = 0;
        $this->formationDir = 1;
        $this->formationTimer = 0.0;
        $this->bulletTimer = 0.0;
        $this->shootTimer = 0.0;
        $this->invincibleTimer = 0.0;
        $this->waveClearTimer = 0.0;
        $this->state = GameState::Playing;
        $this->spawnWave();
    }

    public function nextWave(): void
    {
        ++$this->wave;
        $this->playerBullets = [];
        $this->invaderBullets = [];
        $this->explosions = [];
        $this->formationOffsetX = 0;
        $this->formationOffsetY = 0;
        $this->formationDir = 1;
        $this->formationTimer = 0.0;
        $this->bulletTimer = 0.0;
        $this->shootTimer = 0.0;
        $this->waveClearTimer = 0.0;
        $this->state = GameState::Playing;
        $this->spawnWave();
    }

    // -------------------------------------------------------------------------
    // Tick
    // -------------------------------------------------------------------------

    public function tick(float $delta): void
    {
        if (GameState::Paused === $this->state) {
            return;
        }

        if (GameState::WaveCleared === $this->state) {
            $this->waveClearTimer -= $delta;
            if ($this->waveClearTimer <= 0.0) {
                $this->nextWave();
            }

            return;
        }

        if (GameState::Playing !== $this->state) {
            return;
        }

        // Update explosion timers
        foreach ($this->explosions as $key => &$exp) {
            $exp['timer'] -= $delta;
            if ($exp['timer'] <= 0.0) {
                unset($this->explosions[$key]);
            }
        }
        unset($exp);
        $this->explosions = array_values($this->explosions);

        // Invincibility
        if ($this->invincibleTimer > 0.0) {
            $this->invincibleTimer -= $delta;
        }

        // Move bullets
        $this->bulletTimer += $delta;
        if ($this->bulletTimer >= self::BULLET_INTERVAL) {
            $this->bulletTimer -= self::BULLET_INTERVAL;
            $this->movePlayerBullets();
            $this->moveInvaderBullets();
        }

        // Move formation
        $this->formationTimer += $delta;
        $formationInterval = $this->formationInterval();
        if ($this->formationTimer >= $formationInterval) {
            $this->formationTimer -= $formationInterval;
            $this->stepFormation();
        }

        // Invaders shoot
        $this->shootTimer += $delta;
        if ($this->shootTimer >= self::SHOOT_INTERVAL) {
            $this->shootTimer -= self::SHOOT_INTERVAL;
            $this->invaderShoot();
        }

        // Check wave cleared
        if (0 === $this->countAlive()) {
            $this->state = GameState::WaveCleared;
            $this->waveClearTimer = 2.0;
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function spawnWave(): void
    {
        $this->invaders = [];
        for ($r = 0; $r < self::ROWS; ++$r) {
            for ($c = 0; $c < self::COLS; ++$c) {
                $this->invaders[$r][$c] = true;
            }
        }
    }

    private function formationInterval(): float
    {
        $alive = $this->countAlive();
        $total = self::ROWS * self::COLS;

        return max(0.08, 0.8 * $alive / $total);
    }

    private function countAlive(): int
    {
        $n = 0;
        for ($r = 0; $r < self::ROWS; ++$r) {
            for ($c = 0; $c < self::COLS; ++$c) {
                if ($this->invaders[$r][$c]) {
                    ++$n;
                }
            }
        }

        return $n;
    }

    private function stepFormation(): void
    {
        $newOffsetX = $this->formationOffsetX + $this->formationDir;

        // Compute horizontal bounds of the formation
        $minCol = self::COLS;
        $maxCol = -1;
        for ($r = 0; $r < self::ROWS; ++$r) {
            for ($c = 0; $c < self::COLS; ++$c) {
                if ($this->invaders[$r][$c]) {
                    $minCol = min($minCol, $c);
                    $maxCol = max($maxCol, $c);
                }
            }
        }

        $leftX = self::INVADER_START_X + $minCol + $newOffsetX;
        $rightX = self::INVADER_START_X + $maxCol + $newOffsetX;

        if ($leftX < 0 || $rightX >= self::GAME_W) {
            // Bounce: reverse direction and descend
            $this->formationDir = -$this->formationDir;
            ++$this->formationOffsetY;
        } else {
            $this->formationOffsetX = $newOffsetX;
        }

        // Check if any invader sprite (2 rows tall) reached the player row → game over
        for ($r = 0; $r < self::ROWS; ++$r) {
            for ($c = 0; $c < self::COLS; ++$c) {
                if (!$this->invaders[$r][$c]) {
                    continue;
                }
                $iy = $this->invaderY($r);
                if ($iy >= self::PLAYER_Y) {
                    $this->state = GameState::GameOver;

                    return;
                }
            }
        }
    }

    private function movePlayerBullets(): void
    {
        foreach ($this->playerBullets as $key => &$b) {
            --$b['y'];
            if ($b['y'] < 0) {
                unset($this->playerBullets[$key]);
                continue;
            }
            // Collision with invader?
            if ($this->hitInvader($b['x'], $b['y'])) {
                $this->explosions[] = ['x' => $b['x'], 'y' => $b['y'], 'timer' => self::EXPLOSION_DURATION];
                unset($this->playerBullets[$key]);
            }
        }
        unset($b);
        $this->playerBullets = array_values($this->playerBullets);
    }

    private function moveInvaderBullets(): void
    {
        foreach ($this->invaderBullets as $key => &$b) {
            ++$b['y'];
            if ($b['y'] >= self::GAME_H) {
                unset($this->invaderBullets[$key]);
                continue;
            }
            // Hit player?
            if (self::PLAYER_Y === $b['y'] && $b['x'] === $this->playerX && $this->invincibleTimer <= 0.0) {
                unset($this->invaderBullets[$key]);
                $this->playerHit();
            }
        }
        unset($b);
        $this->invaderBullets = array_values($this->invaderBullets);
    }

    private function hitInvader(int $bx, int $by): bool
    {
        for ($r = 0; $r < self::ROWS; ++$r) {
            for ($c = 0; $c < self::COLS; ++$c) {
                if (!$this->invaders[$r][$c]) {
                    continue;
                }
                $ix = $this->invaderX($c);
                $iy = $this->invaderY($r);
                if ($ix === $bx && $iy === $by) {
                    $this->invaders[$r][$c] = false;
                    $this->score += $this->invaderPoints($r);

                    return true;
                }
            }
        }

        return false;
    }

    private function invaderShoot(): void
    {
        if (\count($this->invaderBullets) >= self::MAX_INVADER_BULLETS) {
            return;
        }

        // Collect columns that have at least one alive invader
        $cols = [];
        for ($c = 0; $c < self::COLS; ++$c) {
            for ($r = self::ROWS - 1; $r >= 0; --$r) {
                if ($this->invaders[$r][$c]) {
                    $cols[] = ['r' => $r, 'c' => $c];
                    break;
                }
            }
        }

        if ([] === $cols) {
            return;
        }

        $chosen = $cols[array_rand($cols)];
        $ix = $this->invaderX($chosen['c']);
        $iy = $this->invaderY($chosen['r']);

        $this->invaderBullets[] = ['x' => $ix, 'y' => $iy + 1];
    }

    /** Logical x of invader column $c, including formation drift. */
    public function invaderX(int $c): int
    {
        return self::INVADER_START_X + $c + $this->formationOffsetX;
    }

    /** Logical y of invader row $r. */
    public function invaderY(int $r): int
    {
        return self::INVADER_START_Y + $r + $this->formationOffsetY;
    }

    private function playerHit(): void
    {
        --$this->lives;
        if ($this->lives <= 0) {
            $this->state = GameState::GameOver;
        } else {
            $this->invincibleTimer = self::INVINCIBLE_DURATION;
        }
    }

    private function invaderPoints(int $row): int
    {
        return match ($row) {
            0 => 30,
            1 => 20,
            default => 10,
        };
    }

    // -------------------------------------------------------------------------
    // Render data getters
    // -------------------------------------------------------------------------

    public function getState(): GameState
    {
        return $this->state;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getLives(): int
    {
        return $this->lives;
    }

    public function getWave(): int
    {
        return $this->wave;
    }

    public function getPlayerX(): int
    {
        return $this->playerX;
    }

    public function isInvincible(): bool
    {
        return $this->invincibleTimer > 0.0;
    }

    /** @return array<int, array<int, bool>> [row][col] */
    public function getInvaders(): array
    {
        return $this->invaders;
    }

    public function getFormationOffsetX(): int
    {
        return $this->formationOffsetX;
    }

    public function getFormationOffsetY(): int
    {
        return $this->formationOffsetY;
    }

    /** @return list<array{x:int,y:int}> */
    public function getPlayerBullets(): array
    {
        return $this->playerBullets;
    }

    /** @return list<array{x:int,y:int}> */
    public function getInvaderBullets(): array
    {
        return $this->invaderBullets;
    }

    /** @return list<array{x:int,y:int,timer:float}> */
    public function getExplosions(): array
    {
        return $this->explosions;
    }
}
