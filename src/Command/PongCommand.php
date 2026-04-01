<?php

namespace App\Command;

use App\Pong\GameState;
use App\Pong\PongGame;
use App\Pong\PongWidget;
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

#[AsCommand(name: 'app:pong', description: 'Two-player Pong game in the terminal')]
final class PongCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $cols = 48;
        $rows = 27;

        $stylesheet = new StyleSheet([
            ':root' => new Style(
                align: Align::Center,
                verticalAlign: VerticalAlign::Center,
            ),

            PongWidget::class => new Style(
                maxColumns: $cols * 2 + 2,
                border: Border::from([1], BorderPattern::ROUNDED, 'white'),
                dim: true,
            ),

            PongWidget::class.':focus' => new Style(
                border: Border::from([1], BorderPattern::ROUNDED, 'bright_white'),
                dim: false,
            ),
        ]);

        $tui = new Tui($stylesheet);

        $game = new PongGame(cols: $cols, rows: $rows);
        $widget = new PongWidget($game);

        $tui->add($widget);
        $tui->setFocus($widget);

        $elapsed = 0.0;
        $stepSec = 0.08;

        $tui->onTick(function (TickEvent $event) use ($game, $widget, &$elapsed, $stepSec): void {
            if (GameState::Playing === $game->getState()) {
                $elapsed += $event->getDeltaTime();

                if ($elapsed >= $stepSec) {
                    $elapsed -= $stepSec;
                    $game->step();
                    $widget->invalidate();
                }
            }

            $event->setBusy();
        });

        $tui->run();

        if (GameState::GameOver === $game->getState()) {
            $output->writeln(\sprintf(
                'Player %d wins!  Final score: <info>%d</info> — <info>%d</info>',
                $game->getWinner(),
                $game->getScore1(),
                $game->getScore2(),
            ));
        }

        return Command::SUCCESS;
    }
}
