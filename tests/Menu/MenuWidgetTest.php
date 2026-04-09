<?php

namespace App\Tests\Menu;

use App\Menu\MenuWidget;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

class MenuWidgetTest extends TestCase
{
    private const GAMES = [
        ['name' => 'Clock',  'command' => 'app:clock',  'description' => 'Retro digital clock'],
        ['name' => 'Park',   'command' => 'app:park',   'description' => 'Theme park management game'],
        ['name' => 'Pong',   'command' => 'app:pong',   'description' => 'Two-player Pong game in the terminal'],
        ['name' => 'Racer',  'command' => 'app:racer',  'description' => 'Retro pseudo-3D racing game'],
        ['name' => 'Snake',  'command' => 'app:snake',  'description' => 'Snake game in the terminal'],
        ['name' => 'Space',  'command' => 'app:space',  'description' => 'Space Invaders'],
        ['name' => 'Tetris', 'command' => 'app:tetris', 'description' => 'Tetris game in the terminal'],
    ];

    public function testMenuDoesNotContainItself()
    {
        foreach (self::GAMES as $game) {
            $this->assertNotSame('app:menu', $game['command'], 'app:menu must not appear in the game list');
        }
    }

    public function testRenderMatchesSnapshot()
    {
        $terminal = new VirtualTerminal(80, 20);
        $tui = new Tui($this->buildStylesheet(), terminal: $terminal);

        $widget = new MenuWidget(self::GAMES);
        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->start();
        $tui->processRender();

        $plain = AnsiUtils::stripAnsiCodes($terminal->getOutput());

        $snapshotFile = __DIR__.'/snapshots/render.txt';
        if (!file_exists($snapshotFile) || getenv('UPDATE_SNAPSHOTS')) {
            @mkdir(\dirname($snapshotFile), recursive: true);
            file_put_contents($snapshotFile, $plain);
        }

        $this->assertStringEqualsFile($snapshotFile, $plain);
    }

    public function testRenderAfterNavigationMatchesSnapshot()
    {
        $terminal = new VirtualTerminal(80, 20);
        $tui = new Tui($this->buildStylesheet(), terminal: $terminal);

        $widget = new MenuWidget(self::GAMES);
        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->start();

        $widget->handleInput("\x1b[B"); // down arrow
        $tui->processRender();

        $plain = AnsiUtils::stripAnsiCodes($terminal->getOutput());

        $snapshotFile = __DIR__.'/snapshots/render_cursor_1.txt';
        if (!file_exists($snapshotFile) || getenv('UPDATE_SNAPSHOTS')) {
            @mkdir(\dirname($snapshotFile), recursive: true);
            file_put_contents($snapshotFile, $plain);
        }

        $this->assertStringEqualsFile($snapshotFile, $plain);
    }

    private function buildStylesheet(): StyleSheet
    {
        return new StyleSheet([
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
    }
}
