<?php

namespace App\Park;

enum BuildMode
{
    case Path;
    case Coaster;
    case FoodStall;
    case Toilet;
    case Demolish;

    public function tileType(): ?TileType
    {
        return match ($this) {
            self::Path      => TileType::Path,
            self::Coaster   => TileType::Coaster,
            self::FoodStall => TileType::FoodStall,
            self::Toilet    => TileType::Toilet,
            self::Demolish  => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Path      => 'Chemin',
            self::Coaster   => 'M. Russes',
            self::FoodStall => 'Bouffe',
            self::Toilet    => 'Toilettes',
            self::Demolish  => 'Demolir',
        };
    }

    public function cost(): ?int
    {
        return $this->tileType()?->cost();
    }

    public function shortKey(): string
    {
        return match ($this) {
            self::Path      => '1',
            self::Coaster   => '2',
            self::FoodStall => '3',
            self::Toilet    => '4',
            self::Demolish  => 'D',
        };
    }
}
