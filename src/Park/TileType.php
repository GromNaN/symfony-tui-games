<?php

namespace App\Park;

enum TileType
{
    case Grass;
    case Path;
    case Entrance;
    case Coaster;
    case FoodStall;
    case Toilet;

    /** 2 terminal columns */
    public function chars(): string
    {
        return match ($this) {
            self::Grass => '  ',
            self::Path => '░░',
            self::Entrance => '>>',
            self::Coaster => 'RC',
            self::FoodStall => 'FS',
            self::Toilet => 'WC',
        };
    }

    public function ansi(): string
    {
        return match ($this) {
            self::Grass => "\033[32m",
            self::Path => "\033[90m",
            self::Entrance => "\033[93;1m",
            self::Coaster => "\033[94;1m",
            self::FoodStall => "\033[33;1m",
            self::Toilet => "\033[96;1m",
        };
    }

    public function cost(): ?int
    {
        return match ($this) {
            self::Path => 10,
            self::Coaster => 500,
            self::FoodStall => 200,
            self::Toilet => 150,
            default => null,
        };
    }

    /** Happiness added to a visitor upon visiting (with cooldown). */
    public function happinessBoost(): int
    {
        return match ($this) {
            self::Coaster => 25,
            self::FoodStall => 10,
            self::Toilet => 15,
            default => 0,
        };
    }

    /** Money earned each time a visitor uses this tile. */
    public function income(): int
    {
        return match ($this) {
            self::Coaster => 20,
            self::FoodStall => 8,
            self::Toilet => 5,
            default => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Grass => 'Grass',
            self::Path => 'Path',
            self::Entrance => 'Entrance',
            self::Coaster => 'Roller Coaster',
            self::FoodStall => 'Food Stall',
            self::Toilet => 'Toilets',
        };
    }
}
