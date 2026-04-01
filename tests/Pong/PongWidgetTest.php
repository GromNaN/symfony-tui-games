<?php

namespace App\Tests\Pong;

use App\Pong\GameState;
use App\Pong\PongGame;
use App\Pong\PongWidget;
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

class PongWidgetTest extends TestCase
{
    public function testRenderMatchesSnapshot(): void
    {
        $cols = 48;
        $rows = 27;

        $game = new PongGame(cols: $cols, rows: $rows);

        // Fix all random positions for a deterministic snapshot.
        $ref = new \ReflectionClass($game);
        $ref->getProperty('ballX')->setValue($game, (int) ($cols / 2));
        $ref->getProperty('ballY')->setValue($game, (int) ($rows / 2));
        $paddleY = (int) (($rows - $game->getPaddleHeight()) / 2);
        $ref->getProperty('paddle1Y')->setValue($game, $paddleY);
        $ref->getProperty('paddle2Y')->setValue($game, $paddleY);
        $ref->getProperty('state')->setValue($game, GameState::Paused);

        $stylesheet = new StyleSheet([
            ':root' => new Style(
                align: Align::Center,
                verticalAlign: VerticalAlign::Center,
            ),
            PongWidget::class => new Style(
                maxColumns: $cols * 2 + 2,
                border: Border::from([1], BorderPattern::ROUNDED, 'white'),
                dim: true,
            ),
            PongWidget::class.':focus' => new Style(
                border: Border::from([1], BorderPattern::ROUNDED, 'bright_white'),
                dim: false,
            ),
        ]);

        $terminal = new VirtualTerminal(120, 35);
        $tui = new Tui($stylesheet, terminal: $terminal);

        $widget = new PongWidget($game);
        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->start();
        $tui->processRender();

        $plain = AnsiUtils::stripAnsiCodes($terminal->getOutput());

        $snapshotFile = __DIR__.'/snapshots/render.txt';
        if (!file_exists($snapshotFile) || getenv('UPDATE_SNAPSHOTS')) {
            file_put_contents($snapshotFile, $plain);
        }

        $this->assertStringEqualsFile($snapshotFile, $plain);
    }
}
