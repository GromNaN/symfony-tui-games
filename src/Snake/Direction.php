<?php

namespace App\Snake;

enum Direction
{
    case Up;
    case Down;
    case Left;
    case Right;

    public function opposite(): self
    {
        return match ($this) {
            self::Up => self::Down,
            self::Down => self::Up,
            self::Left => self::Right,
            self::Right => self::Left,
        };
    }
}
