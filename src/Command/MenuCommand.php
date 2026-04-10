<?php

namespace App\Command;

use App\Menu\GameRegistry;
use App\Menu\MenuWidget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;
use Symfony\Contracts\Service\ServiceProviderInterface;

#[AsCommand(name: 'app:menu', description: 'Game selection menu')]
final class MenuCommand
{
    private readonly GameRegistry $registry;

    public function __construct(
        #[AutowireLocator('console.command', indexAttribute: 'command', excludeSelf: true)]
        ServiceProviderInterface $commands,
    ) {
        $this->registry = new GameRegistry($commands);
    }

    public function getGames(): array
    {
        return $this->registry->getGames();
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $games = $this->registry->getGames();

        while (true) {
            $selected = $this->showMenu($games);

            if (null === $selected) {
                return Command::SUCCESS;
            }

            ($this->registry->get($selected))($input, $output);
        }
    }

    private function showMenu(array $games): ?string
    {
        $stylesheet = new StyleSheet([
            ':root' => new Style(
                align: Align::Center,
                verticalAlign: VerticalAlign::Center,
            ),
            MenuWidget::class => new Style(
                maxColumns: 52,
                border: Border::from([1], BorderPattern::ROUNDED, 'green'),
                dim: true,
            ),
            MenuWidget::class.':focus' => new Style(
                border: Border::from([1], BorderPattern::ROUNDED, 'bright_green'),
                dim: false,
            ),
        ]);

        $tui = new Tui($stylesheet);
        $widget = new MenuWidget($games);

        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->run();

        return $widget->getSelected();
    }
}
