<?php

namespace App\Snake;

class SnakeGame
{
    /** @var list<array{int, int}> Head first */
    private array $snake;

    private Direction $direction;

    /** @var list<Direction> Queued directions, consumed one per step (max 2) */
    private array $directionQueue = [];

    /** @var array{int, int} */
    private array $food;

    private int $score = 0;
    private GameState $state;

    public function __construct(
        private readonly int $cols = 30,
        private readonly int $rows = 20,
    ) {
        $this->reset();
    }

    public function reset(): void
    {
        $midX = (int) ($this->cols / 2);
        $midY = (int) ($this->rows / 2);

        $this->snake = [
            [$midX, $midY],
            [$midX - 1, $midY],
            [$midX - 2, $midY],
        ];

        $this->direction = Direction::Right;
        $this->directionQueue = [];
        $this->score = 0;
        $this->state = GameState::Playing;
        $this->spawnFood();
    }

    public function step(): void
    {
        if (GameState::Playing !== $this->state) {
            return;
        }

        $this->direction = array_shift($this->directionQueue) ?? $this->direction;
        [$hx, $hy] = $this->snake[0];

        [$nx, $ny] = match ($this->direction) {
            Direction::Up => [$hx, $hy - 1],
            Direction::Down => [$hx, $hy + 1],
            Direction::Left => [$hx - 1, $hy],
            Direction::Right => [$hx + 1, $hy],
        };

        // Wall collision
        if ($nx < 0 || $nx >= $this->cols || $ny < 0 || $ny >= $this->rows) {
            $this->state = GameState::GameOver;

            return;
        }

        // Self collision (exclude tail since it will move away)
        $bodyWithoutTail = \array_slice($this->snake, 0, \count($this->snake) - 1);
        foreach ($bodyWithoutTail as [$bx, $by]) {
            if ($bx === $nx && $by === $ny) {
                $this->state = GameState::GameOver;

                return;
            }
        }

        array_unshift($this->snake, [$nx, $ny]);

        if ($nx === $this->food[0] && $ny === $this->food[1]) {
            $this->score += 10;
            $this->spawnFood();
        } else {
            array_pop($this->snake);
        }
    }

    public function changeDirection(Direction $direction): void
    {
        // Reference direction is the last queued one (or the current direction if the queue is empty)
        $lastDir = end($this->directionQueue) ?: $this->direction;

        if ($direction !== $lastDir->opposite() && $direction !== $lastDir && \count($this->directionQueue) < 2) {
            $this->directionQueue[] = $direction;
        }
    }

    public function togglePause(): void
    {
        $this->state = match ($this->state) {
            GameState::Playing => GameState::Paused,
            GameState::Paused => GameState::Playing,
            GameState::GameOver => GameState::GameOver,
        };
    }

    /** Base step interval in ms, decreases as score grows (min 80ms). */
    public function getStepIntervalMs(): int
    {
        $speedLevel = (int) ($this->score / 50);

        return max(80, 200 - $speedLevel * 15);
    }

    public function getCols(): int
    {
        return $this->cols;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getState(): GameState
    {
        return $this->state;
    }

    public function getLength(): int
    {
        return \count($this->snake);
    }

    /** @return list<array{int, int}> */
    public function getSnake(): array
    {
        return $this->snake;
    }

    /** @return array{int, int} */
    public function getFood(): array
    {
        return $this->food;
    }

    private function spawnFood(): void
    {
        do {
            $x = random_int(0, $this->cols - 1);
            $y = random_int(0, $this->rows - 1);
        } while ($this->isSnakeAt($x, $y));

        $this->food = [$x, $y];
    }

    private function isSnakeAt(int $x, int $y): bool
    {
        foreach ($this->snake as [$sx, $sy]) {
            if ($sx === $x && $sy === $y) {
                return true;
            }
        }

        return false;
    }
}
