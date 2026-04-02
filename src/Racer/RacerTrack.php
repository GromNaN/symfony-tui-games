<?php

namespace App\Racer;

/**
 * Procedural road track: 600 segments with a composite sine curve.
 */
final class RacerTrack
{
    private const LENGTH = 600;

    /** @var list<float> */
    private array $segments;

    public function __construct()
    {
        $this->segments = [];
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $p = $i / 15.0;
            $this->segments[] = sin($p) * 0.6
                + sin($p * 2.7 + 1.0) * 0.4
                + sin($p * 0.3 + 2.5) * 0.8;
        }
    }

    /** Curve intensity at world position $i (wraps around). */
    public function curveAt(int $i): float
    {
        return $this->segments[\abs($i) % self::LENGTH];
    }
}
