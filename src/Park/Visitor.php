<?php

namespace App\Park;

class Visitor
{
    public int $x;
    public int $y;

    /** Remaining steps before leaving. */
    public int $stepsLeft;

    /** 0–100, decays over time, boosted by attractions. */
    public int $happiness;

    /** tile key "x,y" => last tick when this visitor used it (cooldown). */
    public array $lastVisit = [];

    public function __construct(int $x, int $y)
    {
        $this->x = $x;
        $this->y = $y;
        $this->stepsLeft = random_int(60, 120);
        $this->happiness = 100;
    }
}
