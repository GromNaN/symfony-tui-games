<?php

namespace App\Pong;

enum GameState
{
    case Playing;
    case Paused;
    case GameOver;
}
