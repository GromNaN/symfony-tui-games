<?php

namespace App\SpaceInvaders;

enum GameState
{
    case Playing;
    case Paused;
    case GameOver;
    case WaveCleared;
}
