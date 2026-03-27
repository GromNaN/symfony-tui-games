<?php

namespace App\Command;

use App\Snake\GameState;
use App\Snake\SnakeGame;
use App\Snake\SnakeWidget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;

#[AsCommand(name: 'app:snake', description: 'Jeu de Snake dans le terminal')]
final class SnakeCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $cols = 30;
        $rows = 20;

        $stylesheet = new StyleSheet([
            // The internal Tui root container: centre its single child.
            ':root' => new Style(
                align: Align::Center,
                verticalAlign: VerticalAlign::Center,
            ),

            // Pin the outer width so :root can compute the centering offset.
            // Outer = cols×2 inner chars + 1 border each side.
            SnakeWidget::class => new Style(
                maxColumns: $cols * 2 + 2,
                border: Border::from([1], BorderPattern::ROUNDED, 'green'),
                dim: true,
            ),

            // Bright border when focused.
            SnakeWidget::class.':focus' => new Style(
                border: Border::from([1], BorderPattern::ROUNDED, 'bright_green'),
                dim: false,
            ),
        ]);

        $tui = new Tui($stylesheet);
        $tui->quitOn('ctrl+c', 'q');

        $game   = new SnakeGame(cols: $cols, rows: $rows);
        $widget = new SnakeWidget($game);

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
                'Final score: <info>%d</info>  |  Length: <info>%d</info>',
                $game->getScore(),
                $game->getLength(),
            ));
        }

        return Command::SUCCESS;
    }
}
