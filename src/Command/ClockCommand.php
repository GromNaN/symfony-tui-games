<?php

namespace App\Command;

use App\Clock\ClockWidget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Style\TailwindStylesheet;
use Symfony\Component\Tui\Tui;

#[AsCommand(name: 'app:clock', description: 'Retro digital clock')]
final class ClockCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $tui = new Tui(new TailwindStylesheet());

        $widget = new ClockWidget();

        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->run();

        return Command::SUCCESS;
    }
}
