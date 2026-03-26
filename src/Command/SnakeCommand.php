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
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

#[AsCommand(name: 'app:snake', description: 'Jeu de Snake dans le terminal')]
final class SnakeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stylesheet = new StyleSheet([
            // Centre the root container in the terminal.
            ':root' => new Style(
                align: Align::Center,
                verticalAlign: VerticalAlign::Center,
            ),

            // Vertical wrapper: grid on top, status bar below, 1-row gap.
            '.snake-wrapper' => new Style(
                direction: Direction::Vertical,
                gap: 1,
            ),

            // Game area: rounded border, dim green when not focused.
            SnakeWidget::class => new Style(
                border: Border::from([1], BorderPattern::ROUNDED, 'green'),
                dim: true,
            ),

            // Game area focused: bright border, full brightness.
            SnakeWidget::class.':focus' => new Style(
                border: Border::from([1], BorderPattern::ROUNDED, 'bright_green'),
                dim: false,
            ),

            // Status bar: dim, centred text.
            '.snake-status' => new Style(
                dim: true,
                textAlign: TextAlign::Center,
            ),
        ]);

        $tui = new Tui($stylesheet);
        $tui->quitOn('ctrl+c', 'q');

        $game = new SnakeGame(cols: 30, rows: 20);
        $grid = new SnakeWidget($game);

        $status = new TextWidget(truncate: true);
        $status->addStyleClass('snake-status');

        $wrapper = new ContainerWidget();
        $wrapper->addStyleClass('snake-wrapper');
        $wrapper->add($grid);
        $wrapper->add($status);

        $tui->add($wrapper);
        $tui->setFocus($grid);

        $elapsed    = 0.0;
        $prevStatus = '';

        $tui->onTick(function (TickEvent $event) use ($game, $grid, $status, &$elapsed, &$prevStatus): void {
            if (GameState::Playing === $game->getState()) {
                $elapsed += $event->getDeltaTime();
                $intervalSec = $game->getStepIntervalMs() / 1000.0;

                if ($elapsed >= $intervalSec) {
                    $elapsed -= $intervalSec;
                    $game->step();
                    $grid->invalidate();
                }
            }

            // Refresh the status bar only when its content changes.
            $newStatus = $this->buildStatus($game);
            if ($newStatus !== $prevStatus) {
                $status->setText($newStatus);
                $prevStatus = $newStatus;
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

    private function buildStatus(SnakeGame $game): string
    {
        $left = \sprintf('Score: %d  Length: %d', $game->getScore(), $game->getLength());

        $left .= match ($game->getState()) {
            GameState::Paused   => '  [PAUSED]',
            GameState::GameOver => '  [GAME OVER]',
            default             => '',
        };

        return $left.'  ·  '.'↑↓←→/WASD  P pause  R restart  Q quit';
    }
}
