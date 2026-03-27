<?php

namespace App\Tetris;

enum GameState
{
    case Playing;
    case Paused;
    case GameOver;
}
