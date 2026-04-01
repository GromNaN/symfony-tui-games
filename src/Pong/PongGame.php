<?php

namespace App\Pong;

class PongGame
{
    private const PADDLE_HEIGHT = 4;
    private const WIN_SCORE = 11;

    private int $ballX;
    private int $ballY;
    private int $ballDx;
    private int $ballDy;

    private int $paddle1Y;
    private int $paddle2Y;

    private int $score1 = 0;
    private int $score2 = 0;

    private GameState $state;

    public function __construct(
        private readonly int $cols = 30,
        private readonly int $rows = 20,
    ) {
        $this->reset();
    }

    public function reset(): void
    {
        $this->paddle1Y = (int) (($this->rows - self::PADDLE_HEIGHT) / 2);
        $this->paddle2Y = $this->paddle1Y;
        $this->score1 = 0;
        $this->score2 = 0;
        $this->state = GameState::Playing;
        $this->serveBall(0 === random_int(0, 1) ? 1 : -1);
    }

    public function step(): void
    {
        if (GameState::Playing !== $this->state) {
            return;
        }

        $nx = $this->ballX + $this->ballDx;
        $ny = $this->ballY + $this->ballDy;

        // Vertical wall bounce.
        if ($ny < 0) {
            $ny = 0;
            $this->ballDy = 1;
        } elseif ($ny >= $this->rows) {
            $ny = $this->rows - 1;
            $this->ballDy = -1;
        }

        // Left paddle (x = 0).
        if ($nx <= 0) {
            if ($ny >= $this->paddle1Y && $ny < $this->paddle1Y + self::PADDLE_HEIGHT) {
                $this->ballDx = 1;
                $nx = 1;
                $this->deflect($this->paddle1Y, $ny);
            } else {
                $this->scorePoint(2);

                return;
            }
        }

        // Right paddle (x = cols − 1).
        if ($nx >= $this->cols - 1) {
            if ($ny >= $this->paddle2Y && $ny < $this->paddle2Y + self::PADDLE_HEIGHT) {
                $this->ballDx = -1;
                $nx = $this->cols - 2;
                $this->deflect($this->paddle2Y, $ny);
            } else {
                $this->scorePoint(1);

                return;
            }
        }

        $this->ballX = $nx;
        $this->ballY = $ny;
    }

    public function movePaddle1(int $dy): void
    {
        if (GameState::Playing !== $this->state) {
            return;
        }
        $this->paddle1Y = max(0, min($this->rows - self::PADDLE_HEIGHT, $this->paddle1Y + $dy));
    }

    public function movePaddle2(int $dy): void
    {
        if (GameState::Playing !== $this->state) {
            return;
        }
        $this->paddle2Y = max(0, min($this->rows - self::PADDLE_HEIGHT, $this->paddle2Y + $dy));
    }

    public function togglePause(): void
    {
        $this->state = match ($this->state) {
            GameState::Playing => GameState::Paused,
            GameState::Paused => GameState::Playing,
            GameState::GameOver => GameState::GameOver,
        };
    }

    public function getCols(): int
    {
        return $this->cols;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getScore1(): int
    {
        return $this->score1;
    }

    public function getScore2(): int
    {
        return $this->score2;
    }

    public function getState(): GameState
    {
        return $this->state;
    }

    public function getPaddle1Y(): int
    {
        return $this->paddle1Y;
    }

    public function getPaddle2Y(): int
    {
        return $this->paddle2Y;
    }

    public function getBallX(): int
    {
        return $this->ballX;
    }

    public function getBallY(): int
    {
        return $this->ballY;
    }

    public function getPaddleHeight(): int
    {
        return self::PADDLE_HEIGHT;
    }

    public function getWinScore(): int
    {
        return self::WIN_SCORE;
    }

    public function getWinner(): ?int
    {
        if (GameState::GameOver !== $this->state) {
            return null;
        }

        return $this->score1 >= self::WIN_SCORE ? 1 : 2;
    }

    private function serveBall(int $direction): void
    {
        $this->ballX = (int) ($this->cols / 2);
        $this->ballY = (int) ($this->rows / 2);
        $this->ballDx = $direction;
        $this->ballDy = 0 === random_int(0, 1) ? -1 : 1;
    }

    private function scorePoint(int $player): void
    {
        if (1 === $player) {
            ++$this->score1;
        } else {
            ++$this->score2;
        }

        if ($this->score1 >= self::WIN_SCORE || $this->score2 >= self::WIN_SCORE) {
            $this->state = GameState::GameOver;

            return;
        }

        // Serve toward the player who just lost.
        $this->serveBall(1 === $player ? 1 : -1);
    }

    /** Adjust vertical direction based on where the ball hit the paddle. */
    private function deflect(int $paddleY, int $hitY): void
    {
        $relative = $hitY - $paddleY;

        if (0 === $relative) {
            $this->ballDy = -1;
        } elseif (self::PADDLE_HEIGHT - 1 === $relative) {
            $this->ballDy = 1;
        }
    }
}
