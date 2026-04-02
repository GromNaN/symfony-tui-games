<?php

namespace App\Racer;

/**
 * Color palette and true-color ANSI helpers for the Racer game.
 *
 * The racer renders every terminal cell with arbitrary RGB colors (sky gradient,
 * road tiling, trees, car sprites). Named Style colors are insufficient here, so
 * raw ANSI true-color codes are generated — but only through this class.
 */
final class Palette
{
    // Sky
    public const SKY_TOP = [100, 160, 240];
    public const SKY_BOT = [140, 190, 255];

    // Mountains
    public const MTN_FAR = [60,  90,  50];
    public const MTN_NEAR = [50,  75,  40];
    public const MTN_SNOW = [200, 210, 220];

    // Road
    public const GRASS_1 = [40, 160,  40];
    public const GRASS_2 = [30, 130,  30];
    public const ROAD_1 = [80,  80,  80];
    public const ROAD_2 = [70,  70,  70];
    public const RUMBLE_1 = [200,  50,  50];
    public const RUMBLE_2 = [220, 220, 220];
    public const LINE_WHITE = [255, 255, 255];

    // Trees
    public const TREE_TRUNK = [100,  60,  30];
    public const TREE_LEAF_1 = [30, 120,  30];
    public const TREE_LEAF_2 = [40, 150,  40];

    // Player car
    public const CAR_BODY = [220,  40,  40];
    public const CAR_TOP = [180,  30,  30];
    public const CAR_WINDOW = [100, 160, 220];
    public const CAR_WHEEL = [40,  40,  40];
    public const CAR_HIGH = [255,  80,  80];
    public const CAR_TAIL = [255, 200,  50];

    // Enemy cars (one color per index 0–4)
    public const ENEMY_COLORS = [
        [40, 100, 220],
        [220, 180,  40],
        [40, 180,  40],
        [180,  40, 180],
        [220, 120,  40],
    ];

    /** ANSI true-color background. */
    public static function bg(array $c): string
    {
        return \sprintf("\x1b[48;2;%d;%d;%dm", $c[0], $c[1], $c[2]);
    }

    /** ANSI true-color foreground. */
    public static function fg(array $c): string
    {
        return \sprintf("\x1b[38;2;%d;%d;%dm", $c[0], $c[1], $c[2]);
    }

    /** Linear interpolation between two RGB colors. */
    public static function lerp(array $a, array $b, float $t): array
    {
        return [
            (int) ($a[0] + ($b[0] - $a[0]) * $t),
            (int) ($a[1] + ($b[1] - $a[1]) * $t),
            (int) ($a[2] + ($b[2] - $a[2]) * $t),
        ];
    }
}
