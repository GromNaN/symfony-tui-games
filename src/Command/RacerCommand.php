<?php

namespace App\Command;

use App\Racer\RacerGame;
use App\Racer\RacerTrack;
use App\Racer\RacerWidget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Tui;

#[AsCommand(name: 'app:racer', description: 'Retro pseudo-3D racing game')]
final class RacerCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        // No border or centering: the widget fills the full terminal.
        $tui = new Tui(new StyleSheet([]));

        $track = new RacerTrack();
        $game = new RacerGame($track);
        $widget = new RacerWidget($game, $track);

        $tui->add($widget);
        $tui->setFocus($widget);

        // Physics are dt-based, so every tick triggers an update + redraw.
        $tui->onTick(function (TickEvent $event) use ($game, $widget): void {
            $game->update(min($event->getDeltaTime(), 0.04));
            $widget->invalidate();
            $event->setBusy();
        });

        $tui->run();

        $output->writeln(\sprintf(
            'Score: <info>%d</info>  |  Time: <info>%s</info>  |  Best: <info>%d</info>',
            $game->getScore(),
            $game->formatTime(),
            $game->getHighScore(),
        ));

        return Command::SUCCESS;
    }
}
