<?php

namespace App\Tests\Menu;

use App\Command\MenuCommand;
use App\Kernel;
use PHPUnit\Framework\TestCase;

class MenuCommandTest extends TestCase
{
    private static array $games;

    public static function setUpBeforeClass(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        /** @var MenuCommand $command */
        $command = $kernel->getContainer()->get(MenuCommand::class);
        self::$games = $command->getGames();
    }

    public function testMenuCommandIsNotInGameList()
    {
        $commands = array_column(self::$games, 'command');

        $this->assertNotContains('app:menu', $commands);
    }

    public function testAllGameCommandsArePresent()
    {
        $commands = array_column(self::$games, 'command');

        foreach (['app:clock', 'app:park', 'app:pong', 'app:racer', 'app:snake', 'app:space', 'app:tetris'] as $expected) {
            $this->assertContains($expected, $commands);
        }
    }

    public function testGamesAreSortedByCommand()
    {
        $commands = array_column(self::$games, 'command');
        $sorted = $commands;
        sort($sorted);

        $this->assertSame($sorted, $commands);
    }

    public function testEachGameHasNameDescriptionAndCommand()
    {
        foreach (self::$games as $game) {
            $this->assertArrayHasKey('name', $game);
            $this->assertArrayHasKey('command', $game);
            $this->assertArrayHasKey('description', $game);
            $this->assertNotEmpty($game['name']);
            $this->assertNotEmpty($game['command']);
        }
    }
}
