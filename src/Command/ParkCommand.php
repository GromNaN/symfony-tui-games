<?php

namespace App\Command;

use App\Park\ParkGame;
use App\Park\ParkWidget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;

#[AsCommand(name: 'app:park', description: 'Theme park management game')]
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

        $game = new ParkGame(startingMoney: 2000);
        $widget = new ParkWidget($game);

        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->run();

        $output->writeln(\sprintf(
            'Final score — Money: <info>$%s</info>  |  Visitors: <info>%d</info>  |  Total revenue: <info>$%s</info>',
            number_format($game->getMoney()),
            $game->getTotalVisitors(),
            number_format($game->getTotalRevenue()),
        ));

        return Command::SUCCESS;
    }
}
