<?php

namespace App\Command;

use App\Tetris\GameState;
use App\Tetris\TetrisGame;
use App\Tetris\TetrisWidget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
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
        $quitKeys = new Keybindings(['quit' => [Key::ctrl('c'), 'q']]);
        $tui->on(InputEvent::class, function (InputEvent $event) use ($tui, $quitKeys): void {
            if ($quitKeys->matches($event->getData(), 'quit')) {
                $tui->stop();
                $event->stopPropagation();
            }
        });

        $game   = new TetrisGame();
        $widget = new TetrisWidget($game);

        $tui->add($widget);
        $tui->setFocus($widget);

        $elapsed = 0.0;

        $tui->onTick(function (TickEvent $event) use ($game, $widget, &$elapsed): void {
            if (GameState::Playing === $game->getState()) {
                $elapsed += $event->getDeltaTime();
                $intervalSec = $game->getStepIntervalMs() / 1000.0;

                if ($elapsed >= $intervalSec) {
                    $elapsed -= $intervalSec;
                    $game->step();
                    $widget->invalidate();
                }
            }

            $event->setBusy();
        });

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
