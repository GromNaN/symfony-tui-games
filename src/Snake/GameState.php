<?php

namespace App\Snake;

enum GameState
{
    case Playing;
    case Paused;
    case GameOver;
}
