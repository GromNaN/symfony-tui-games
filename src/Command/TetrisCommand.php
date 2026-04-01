<?php

namespace App\Command;

use App\Tetris\GameState;
use App\Tetris\TetrisGame;
use App\Tetris\TetrisWidget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;

#[AsCommand(name: 'app:tetris', description: 'Tetris game in the terminal')]
final class TetrisCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        // Board (10×2) + gap (2) + side panel (12) + borders (2).
        $innerWidth = 20 + 2 + 12;

        $stylesheet = new StyleSheet([
            ':root' => new Style(
                align: Align::Center,
                verticalAlign: VerticalAlign::Center,
            ),
            TetrisWidget::class => new Style(
                maxColumns: $innerWidth + 2,
                border: Border::from([1], BorderPattern::ROUNDED, 'cyan'),
                dim: true,
            ),
            TetrisWidget::class.':focus' => new Style(
                border: Border::from([1], BorderPattern::ROUNDED, 'bright_cyan'),
                dim: false,
            ),
        ]);

        $tui = new Tui($stylesheet);

        $game = new TetrisGame();
        $widget = new TetrisWidget($game);

        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->run();

        if (GameState::GameOver === $game->getState()) {
            $output->writeln(\sprintf(
                'Final score: <info>%d</info>  |  Level: <info>%d</info>  |  Lines: <info>%d</info>',
                $game->getScore(),
                $game->getLevel(),
                $game->getLinesCleared(),
            ));
        }

        return Command::SUCCESS;
    }
}
