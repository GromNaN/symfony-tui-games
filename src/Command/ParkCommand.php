<?php

namespace App\Command;

use App\Park\ParkGame;
use App\Park\ParkWidget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;

#[AsCommand(name: 'app:park', description: 'Jeu de gestion de parc d\'attractions')]
final class ParkCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $stylesheet = new StyleSheet([
            ':root' => new Style(
                align: Align::Center,
                verticalAlign: VerticalAlign::Center,
            ),
        ]);

        $tui = new Tui($stylesheet);
        $tui->quitOn('ctrl+c', 'q');

        $game   = new ParkGame(startingMoney: 2000);
        $widget = new ParkWidget($game);

        $tui->add($widget);
        $tui->setFocus($widget);

        $elapsed = 0.0;

        $tui->onTick(function (TickEvent $event) use ($game, $widget, &$elapsed): void {
            $elapsed += $event->getDeltaTime();

            // Game tick every 250 ms
            if ($elapsed >= 0.25) {
                $elapsed -= 0.25;
                $game->tick();
                $widget->invalidate();
            }

            $event->setBusy();
        });

        $tui->run();

        $output->writeln(\sprintf(
            'Score final — Argent : <info>$%s</info>  |  Visiteurs : <info>%d</info>  |  Revenu total : <info>$%s</info>',
            number_format($game->getMoney()),
            $game->getTotalVisitors(),
            number_format($game->getTotalRevenue()),
        ));

        return Command::SUCCESS;
    }
}
