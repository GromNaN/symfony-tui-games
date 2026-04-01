<?php

namespace App\Command;

use App\SpaceInvaders\SpaceGame;
use App\SpaceInvaders\SpaceWidget;
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

#[AsCommand(name: 'app:space', description: 'Space Invaders')]
final class SpaceCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $stylesheet = new StyleSheet([
            ':root' => new Style(
                align: Align::Center,
                verticalAlign: VerticalAlign::Center,
            ),

            // Outer width = GAME_W * 2 content + 2 border — pins width for :root centering.
            SpaceWidget::class => new Style(
                maxColumns: SpaceGame::GAME_W * 2 + 2,
                border: Border::from([1], BorderPattern::DOUBLE, 'white'),
                dim: true,
            ),

            SpaceWidget::class.':focus' => new Style(
                border: Border::from([1], BorderPattern::DOUBLE, 'bright_white'),
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

        $game   = new SpaceGame();
        $widget = new SpaceWidget($game);

        $tui->add($widget);
        $tui->setFocus($widget);

        $tui->onTick(function (TickEvent $event) use ($game, $widget): void {
            $game->tick($event->getDeltaTime());
            $widget->invalidate();
            $event->setBusy();
        });

        $tui->run();

        $output->writeln(\sprintf(
            'Final score — <info>%d pts</info>  |  Wave: <info>%d</info>',
            $game->getScore(),
            $game->getWave(),
        ));

        return Command::SUCCESS;
    }
}
