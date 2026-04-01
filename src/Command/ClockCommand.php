<?php

namespace App\Command;

use App\Clock\ClockWidget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TailwindStylesheet;
use Symfony\Component\Tui\Tui;

#[AsCommand(name: 'app:clock', description: 'Retro digital clock')]
final class ClockCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $stylesheet = new TailwindStylesheet();

        // Register bar-fill / bar-empty colours for every theme so that
        // ClockWidget can switch CSS classes instead of using raw ANSI codes.
        foreach (ClockWidget::THEMES as $theme) {
            [$r, $g, $b] = $theme['bar'];
            $stylesheet->addRule('.'.$theme['css'].'::bar-fill',
                new Style(color: Color::rgb($r, $g, $b)));
            $stylesheet->addRule('.'.$theme['css'].'::bar-empty',
                new Style(color: Color::rgb((int) ($r * 0.3), (int) ($g * 0.3), (int) ($b * 0.3))));
        }

        $tui = new Tui($stylesheet);

        $widget = new ClockWidget();

        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->run();

        return Command::SUCCESS;
    }
}
