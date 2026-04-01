<?php

namespace App\Tests\Clock;

use App\Clock\ClockWidget;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TailwindStylesheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

class ClockWidgetTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testRenderMatchesSnapshot(): void
    {
        self::mockTime(new \DateTimeImmutable('2025-01-15 14:32:45'));

        $stylesheet = new TailwindStylesheet();
        foreach (ClockWidget::THEMES as $theme) {
            [$r, $g, $b] = $theme['bar'];
            $stylesheet->addRule('.'.$theme['css'].'::bar-fill',
                new Style(color: Color::rgb($r, $g, $b)));
            $stylesheet->addRule('.'.$theme['css'].'::bar-empty',
                new Style(color: Color::rgb((int) ($r * 0.3), (int) ($g * 0.3), (int) ($b * 0.3))));
        }

        $terminal = new VirtualTerminal(120, 30);
        $tui = new Tui($stylesheet, terminal: $terminal);

        $widget = new ClockWidget();
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
