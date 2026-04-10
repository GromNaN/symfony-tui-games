<?php

namespace App\Menu;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Contracts\Service\ServiceProviderInterface;

#[Exclude]
class GameRegistry
{
    /**
     * @param ServiceProviderInterface<object&callable>
     */
    public function __construct(
        private readonly ServiceProviderInterface $commands,
    ) {
    }

    /**
     * @return array<array{name: string, command: string, description: string}>
     */
    public function getGames(): array
    {
        $games = [];
        foreach ($this->commands->getProvidedServices() as $commandName => $serviceType) {
            if (!str_starts_with($commandName, 'app:')) {
                continue;
            }

            $attrs = (new \ReflectionClass($serviceType))->getAttributes(AsCommand::class);
            $description = $attrs ? $attrs[0]->newInstance()->description ?? '' : '';

            $games[] = [
                'name' => ucfirst(substr($commandName, 4)),
                'command' => $commandName,
                'description' => $description,
            ];
        }

        usort($games, fn ($a, $b) => strcmp($a['command'], $b['command']));

        return $games;
    }

    /**
     * @return object&callable
     */
    public function get(string $commandName): object
    {
        return $this->commands->get($commandName);
    }
}
