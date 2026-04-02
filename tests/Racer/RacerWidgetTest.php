<?php

namespace App\Tests\Racer;

use App\Racer\RacerGame;
use App\Racer\RacerTrack;
use App\Racer\RacerWidget;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

class RacerWidgetTest extends TestCase
{
    public function testRenderMatchesSnapshot()
    {
        // Fix random seed for deterministic tree positions.
        mt_srand(42);

        $track = new RacerTrack();
        $game = new RacerGame($track);

        // No border or centering: the widget fills the full terminal.
        $stylesheet = new StyleSheet([]);

        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui($stylesheet, terminal: $terminal);

        $widget = new RacerWidget($game, $track);
        $tui->add($widget);
        $tui->setFocus($widget);
        $tui->start();
        $tui->processRender();

        $plain = AnsiUtils::stripAnsiCodes($terminal->getOutput());

        $snapshotFile = __DIR__.'/snapshots/render.txt';
        if (!file_exists($snapshotFile) || getenv('UPDATE_SNAPSHOTS')) {
            if (!is_dir(\dirname($snapshotFile))) {
                mkdir(\dirname($snapshotFile), 0755, true);
            }
            file_put_contents($snapshotFile, $plain);
        }

        $this->assertStringEqualsFile($snapshotFile, $plain);
    }
}
