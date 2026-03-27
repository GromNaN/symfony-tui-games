<?php

namespace App\Tetris;

enum Tetromino: string
{
    case I = 'I';
    case O = 'O';
    case T = 'T';
    case S = 'S';
    case Z = 'Z';
    case J = 'J';
    case L = 'L';

    /**
     * Base matrix for this piece (before rotation).
     *
     * @return list<list<int>>
     */
    public function matrix(): array
    {
        return match ($this) {
            self::I => [
                [0, 0, 0, 0],
                [1, 1, 1, 1],
                [0, 0, 0, 0],
                [0, 0, 0, 0],
            ],
            self::O => [
                [1, 1],
                [1, 1],
            ],
            self::T => [
                [0, 1, 0],
                [1, 1, 1],
                [0, 0, 0],
            ],
            self::S => [
                [0, 1, 1],
                [1, 1, 0],
                [0, 0, 0],
            ],
            self::Z => [
                [1, 1, 0],
                [0, 1, 1],
                [0, 0, 0],
            ],
            self::J => [
                [1, 0, 0],
                [1, 1, 1],
                [0, 0, 0],
            ],
            self::L => [
                [0, 0, 1],
                [1, 1, 1],
                [0, 0, 0],
            ],
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::I => 'bright_cyan',
            self::O => 'bright_yellow',
            self::T => 'bright_magenta',
            self::S => 'bright_green',
            self::Z => 'bright_red',
            self::J => 'bright_blue',
            self::L => 'yellow',
        };
    }

    /**
     * Occupied cells at a given rotation (0–3).
     *
     * @return list<array{int, int}> [row, col] offsets within the bounding box
     */
    public function cells(int $rotation): array
    {
        $matrix = $this->matrix();
        for ($i = 0, $n = $rotation % 4; $i < $n; ++$i) {
            $matrix = self::rotateCW($matrix);
        }

        $cells = [];
        foreach ($matrix as $r => $row) {
            foreach ($row as $c => $val) {
                if ($val) {
                    $cells[] = [$r, $c];
                }
            }
        }

        return $cells;
    }

    /** Bounding-box size (I = 4, O = 2, others = 3). */
    public function size(): int
    {
        return \count($this->matrix());
    }

    public static function random(): self
    {
        $cases = self::cases();

        return $cases[array_rand($cases)];
    }

    /** @return list<list<int>> */
    private static function rotateCW(array $matrix): array
    {
        $size = \count($matrix);
        $rotated = array_fill(0, $size, array_fill(0, $size, 0));
        for ($r = 0; $r < $size; ++$r) {
            for ($c = 0; $c < $size; ++$c) {
                $rotated[$c][$size - 1 - $r] = $matrix[$r][$c];
            }
        }

        return $rotated;
    }
}
