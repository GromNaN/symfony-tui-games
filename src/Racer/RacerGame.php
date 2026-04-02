<?php

namespace App\Racer;

/**
 * Pure game logic for the Racer.
 *
 * Manages physics, collision, enemy spawning, and countdown — no TUI dependency.
 * Screen dimensions are deliberately absent: trees use abstract world-space
 * coordinates; all projection math lives in RacerWidget.
 */
final class RacerGame
{
    private const MAX_SPEED = 22.0;
    private const ACCEL = 4.0;
    private const DECEL = 12.0;
    private const FRICTION = 6.0;
    private const STEER_SPEED = 0.6;
    private const ENEMY_INTERVAL = 5.0;

    private float $position = 0.0;
    private float $speed = 0.0;
    private float $playerX = 0.0;
    private float $distance = 0.0;
    private float $raceTime = 0.0;
    private float $parallaxX = 0.0;
    private float $enemyTimer = 0.0;
    private float $deathTimer = 0.0;
    private float $countdownTimer = 0.0;

    private int $steering = 0;
    private int $score = 0;
    private int $highScore = 0;
    private int $countdownValue = 3;

    private bool $alive = true;
    private bool $started = false;
    private bool $blinkOn = true;
    private bool $counting = false;

    /**
     * @var list<array{z: float, side: int, offset: float, size: float}>
     *                                                                   Trees are placed once at random world-space positions and stay fixed
     */
    private array $trees = [];

    /** @var list<array{z: float, x: float, color: int, speed: float}> */
    private array $enemies = [];

    public function __construct(private readonly RacerTrack $track)
    {
        // 300 trees alternating left/right with random depth and size.
        for ($i = 0; $i < 300; ++$i) {
            $this->trees[] = [
                'z' => $i * 12 + mt_rand(0, 6),
                'side' => (0 === $i % 2) ? -1 : 1,
                'offset' => 1.1 + mt_rand(0, 8) / 20.0,
                'size' => mt_rand(8, 14) / 10.0,
            ];
        }
    }

    public function reset(): void
    {
        $this->position = 0.0;
        $this->speed = 0.0;
        $this->playerX = 0.0;
        $this->distance = 0.0;
        $this->raceTime = 0.0;
        $this->parallaxX = 0.0;
        $this->enemyTimer = 0.0;
        $this->deathTimer = 0.0;
        $this->countdownTimer = 0.0;
        $this->steering = 0;
        $this->score = 0;
        $this->countdownValue = 3;
        $this->enemies = [];
        $this->alive = true;
        $this->started = false;
        $this->blinkOn = true;
        $this->counting = false;
    }

    public function startCountdown(): void
    {
        if (!$this->counting && !$this->started) {
            $this->counting = true;
            $this->countdownTimer = 0.0;
            $this->countdownValue = 3;
        }
    }

    /** Set lateral steering: -1 left, 0 neutral, +1 right. */
    public function steer(int $direction): void
    {
        $this->steering = $direction;
    }

    public function update(float $dt): void
    {
        if (!$this->alive) {
            $this->deathTimer += $dt;
            $this->blinkOn = fmod($this->deathTimer, 0.5) < 0.25;
            $this->speed = max(0.0, $this->speed - self::DECEL * 2 * $dt);
            $this->position += $this->speed * $dt;

            return;
        }

        if ($this->counting && !$this->started) {
            $this->countdownTimer += $dt;
            $this->countdownValue = match (true) {
                $this->countdownTimer < 1 => 3,
                $this->countdownTimer < 2 => 2,
                $this->countdownTimer < 3 => 1,
                default => 0,
            };
            if ($this->countdownTimer >= 3.6) {
                $this->started = true;
                $this->counting = false;
            }

            return;
        }

        if (!$this->started) {
            return;
        }

        $this->raceTime += $dt;
        $this->speed = min(self::MAX_SPEED, $this->speed + self::ACCEL * $dt);

        $curve = $this->track->curveAt((int) ($this->position / 3));
        $this->playerX += $curve * $this->speed * 0.0004 * $dt * 60;
        $this->parallaxX += $curve * $this->speed * $dt * 0.015;
        $this->playerX += $this->steering * self::STEER_SPEED * $dt;
        $this->playerX = max(-1.2, min(1.2, $this->playerX));

        if (abs($this->playerX) > 0.8) {
            $this->speed = max(0.0, $this->speed - self::FRICTION * 3 * $dt);
        }

        $this->position += $this->speed * $dt;
        $this->distance += $this->speed * $dt;
        $this->score = (int) ($this->distance * 0.1);

        // Spawn enemies at increasing frequency as the player progresses.
        $this->enemyTimer += $dt;
        $interval = max(3.0, self::ENEMY_INTERVAL - $this->distance * 0.00001);
        if ($this->enemyTimer >= $interval) {
            $this->enemyTimer = 0.0;
            $this->enemies[] = [
                'z' => $this->position + 80,
                'x' => (mt_rand(0, 100) - 50) / 80.0,
                'color' => mt_rand(0, 4),
                'speed' => $this->speed * 0.65 + mt_rand(0, 5),
            ];
        }

        foreach ($this->enemies as &$e) {
            $e['z'] += $e['speed'] * $dt;
        }
        unset($e);

        $this->enemies = array_values(
            array_filter($this->enemies, fn ($e) => $e['z'] > $this->position - 30)
        );

        // AABB collision: player vs enemies.
        foreach ($this->enemies as $e) {
            $rz = $e['z'] - $this->position;
            if ($rz > 0 && $rz < 10 && abs($this->playerX - $e['x']) < 0.2) {
                $this->alive = false;
                $this->deathTimer = 0.0;
                if ($this->score > $this->highScore) {
                    $this->highScore = $this->score;
                }
            }
        }
    }

    public function formatTime(): string
    {
        return \sprintf('%d:%02d', (int) ($this->raceTime / 60), (int) fmod($this->raceTime, 60));
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getPosition(): float
    {
        return $this->position;
    }

    public function getParallaxX(): float
    {
        return $this->parallaxX;
    }

    public function getPlayerX(): float
    {
        return $this->playerX;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getHighScore(): int
    {
        return $this->highScore;
    }

    public function getRaceTime(): float
    {
        return $this->raceTime;
    }

    public function isAlive(): bool
    {
        return $this->alive;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function isCounting(): bool
    {
        return $this->counting;
    }

    public function isBlinkOn(): bool
    {
        return $this->blinkOn;
    }

    public function getDeathTimer(): float
    {
        return $this->deathTimer;
    }

    public function getCountdownValue(): int
    {
        return $this->countdownValue;
    }

    /** @return list<array{z: float, side: int, offset: float, size: float}> */
    public function getTrees(): array
    {
        return $this->trees;
    }

    /** @return list<array{z: float, x: float, color: int, speed: float}> */
    public function getEnemies(): array
    {
        return $this->enemies;
    }
}
