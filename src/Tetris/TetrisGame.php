<?php

namespace App\Tetris;

class TetrisGame
{
    private const COLS = 10;
    private const ROWS = 20;

    /** @var array<int, array<int, Tetromino|null>> [row][col] */
    private array $board;

    private Tetromino $current;
    private int $currentRotation = 0;
    private int $currentRow;
    private int $currentCol;

    private Tetromino $next;

    private int $score = 0;
    private int $level = 1;
    private int $linesCleared = 0;
    private GameState $state;

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->board = $this->createEmptyBoard();
        $this->score = 0;
        $this->level = 1;
        $this->linesCleared = 0;
        $this->state = GameState::Playing;
        $this->next = Tetromino::random();
        $this->spawnPiece();
    }

    /** Gravity: move the current piece down one row, or lock it. */
    public function step(): void
    {
        if (GameState::Playing !== $this->state) {
            return;
        }

        if ($this->canPlace($this->currentRow + 1, $this->currentCol, $this->currentRotation)) {
            ++$this->currentRow;
        } else {
            $this->lockAndAdvance();
        }
    }

    public function moveLeft(): void
    {
        if (GameState::Playing !== $this->state) {
            return;
        }
        if ($this->canPlace($this->currentRow, $this->currentCol - 1, $this->currentRotation)) {
            --$this->currentCol;
        }
    }

    public function moveRight(): void
    {
        if (GameState::Playing !== $this->state) {
            return;
        }
        if ($this->canPlace($this->currentRow, $this->currentCol + 1, $this->currentRotation)) {
            ++$this->currentCol;
        }
    }

    public function rotateCW(): void
    {
        if (GameState::Playing !== $this->state) {
            return;
        }

        $newRot = ($this->currentRotation + 1) % 4;

        // Try basic rotation, then simple wall-kicks (±1, ±2 columns).
        foreach ([0, -1, 1, -2, 2] as $offset) {
            if ($this->canPlace($this->currentRow, $this->currentCol + $offset, $newRot)) {
                $this->currentCol += $offset;
                $this->currentRotation = $newRot;

                return;
            }
        }
    }

    /** Soft-drop: move down one row and earn 1 point. */
    public function softDrop(): void
    {
        if (GameState::Playing !== $this->state) {
            return;
        }
        if ($this->canPlace($this->currentRow + 1, $this->currentCol, $this->currentRotation)) {
            ++$this->currentRow;
            ++$this->score;
        }
    }

    /** Hard-drop: instantly place the piece and earn 2 pts per row. */
    public function hardDrop(): void
    {
        if (GameState::Playing !== $this->state) {
            return;
        }

        $dropped = 0;
        while ($this->canPlace($this->currentRow + 1, $this->currentCol, $this->currentRotation)) {
            ++$this->currentRow;
            ++$dropped;
        }
        $this->score += $dropped * 2;
        $this->lockAndAdvance();
    }

    public function togglePause(): void
    {
        $this->state = match ($this->state) {
            GameState::Playing => GameState::Paused,
            GameState::Paused => GameState::Playing,
            GameState::GameOver => GameState::GameOver,
        };
    }

    /** Step interval in ms — decreases with level (min 100 ms). */
    public function getStepIntervalMs(): int
    {
        return max(100, 800 - ($this->level - 1) * 75);
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getCols(): int
    {
        return self::COLS;
    }

    public function getRows(): int
    {
        return self::ROWS;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getLinesCleared(): int
    {
        return $this->linesCleared;
    }

    public function getState(): GameState
    {
        return $this->state;
    }

    /** @return array<int, array<int, Tetromino|null>> */
    public function getBoard(): array
    {
        return $this->board;
    }

    public function getCurrentPiece(): Tetromino
    {
        return $this->current;
    }

    public function getCurrentRotation(): int
    {
        return $this->currentRotation;
    }

    public function getNextPiece(): Tetromino
    {
        return $this->next;
    }

    /** @return list<array{int, int}> Absolute board positions of the falling piece. */
    public function getCurrentCells(): array
    {
        return $this->absoluteCells($this->currentRow, $this->currentCol, $this->currentRotation);
    }

    /** @return list<array{int, int}> Absolute board positions of the ghost (drop preview). */
    public function getGhostCells(): array
    {
        $ghostRow = $this->currentRow;
        while ($this->canPlace($ghostRow + 1, $this->currentCol, $this->currentRotation)) {
            ++$ghostRow;
        }

        return $this->absoluteCells($ghostRow, $this->currentCol, $this->currentRotation);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /** @return array<int, array<int, null>> */
    private function createEmptyBoard(): array
    {
        $board = [];
        for ($r = 0; $r < self::ROWS; ++$r) {
            $board[$r] = array_fill(0, self::COLS, null);
        }

        return $board;
    }

    private function spawnPiece(): void
    {
        $this->current = $this->next;
        $this->next = Tetromino::random();
        $this->currentRotation = 0;
        $this->currentRow = 0;
        $this->currentCol = (int) ((self::COLS - $this->current->size()) / 2);

        if (!$this->canPlace($this->currentRow, $this->currentCol, $this->currentRotation)) {
            $this->state = GameState::GameOver;
        }
    }

    private function canPlace(int $row, int $col, int $rotation): bool
    {
        foreach ($this->current->cells($rotation) as [$cr, $cc]) {
            $r = $row + $cr;
            $c = $col + $cc;

            if ($r < 0 || $r >= self::ROWS || $c < 0 || $c >= self::COLS) {
                return false;
            }
            if (null !== $this->board[$r][$c]) {
                return false;
            }
        }

        return true;
    }

    private function lockPiece(): void
    {
        foreach ($this->current->cells($this->currentRotation) as [$cr, $cc]) {
            $r = $this->currentRow + $cr;
            $c = $this->currentCol + $cc;
            if ($r >= 0 && $r < self::ROWS && $c >= 0 && $c < self::COLS) {
                $this->board[$r][$c] = $this->current;
            }
        }
    }

    private function clearLines(): void
    {
        $cleared = 0;

        for ($r = self::ROWS - 1; $r >= 0; --$r) {
            $full = true;
            for ($c = 0; $c < self::COLS; ++$c) {
                if (null === $this->board[$r][$c]) {
                    $full = false;
                    break;
                }
            }

            if ($full) {
                ++$cleared;
                array_splice($this->board, $r, 1);
                array_unshift($this->board, array_fill(0, self::COLS, null));
                ++$r; // re-check this index (new row shifted in)
            }
        }

        if ($cleared > 0) {
            $this->linesCleared += $cleared;
            $this->score += match ($cleared) {
                1 => 100,
                2 => 300,
                3 => 500,
                4 => 800,
                default => 0,
            } * $this->level;
            $this->level = (int) ($this->linesCleared / 10) + 1;
        }
    }

    private function lockAndAdvance(): void
    {
        $this->lockPiece();
        $this->clearLines();
        $this->spawnPiece();
    }

    /** @return list<array{int, int}> */
    private function absoluteCells(int $row, int $col, int $rotation): array
    {
        $cells = [];
        foreach ($this->current->cells($rotation) as [$cr, $cc]) {
            $cells[] = [$row + $cr, $col + $cc];
        }

        return $cells;
    }
}
