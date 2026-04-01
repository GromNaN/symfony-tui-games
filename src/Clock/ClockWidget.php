<?php

namespace App\Clock;

use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\QuitableTrait;
use Symfony\Component\Tui\Widget\ScheduledTickTrait;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\WidgetContext;

/**
 * Retro digital clock widget.
 *
 * Displays the current time in FIGlet (big font), date, a seconds progress
 * bar, and day/week progress indicators.  Keybindings: t=theme, s=seconds
 * toggle, h=12/24 h toggle, q/ctrl+c=quit.
 */
class ClockWidget extends ContainerWidget implements FocusableInterface
{
    use FocusableTrait;
    use QuitableTrait;
    use KeybindingsTrait;
    use ScheduledTickTrait;

    private const THEMES = [
        ['name' => 'Cyan',   'time' => 'text-cyan-400',   'date' => 'text-cyan-700',   'border' => 'border-cyan-800',   'bar' => [80, 200, 220]],
        ['name' => 'Green',  'time' => 'text-green-400',  'date' => 'text-green-700',  'border' => 'border-green-800',  'bar' => [80, 200, 120]],
        ['name' => 'Amber',  'time' => 'text-amber-400',  'date' => 'text-amber-700',  'border' => 'border-amber-800',  'bar' => [255, 200, 50]],
        ['name' => 'Rose',   'time' => 'text-rose-400',   'date' => 'text-rose-700',   'border' => 'border-rose-800',   'bar' => [255, 100, 130]],
        ['name' => 'Violet', 'time' => 'text-violet-400', 'date' => 'text-violet-700', 'border' => 'border-violet-800', 'bar' => [160, 120, 255]],
    ];

    private int $themeIndex = 0;
    private bool $showSeconds = true;
    private bool $show24h = true;
    private string $lastTime = '';

    private readonly TextWidget $timeWidget;
    private readonly TextWidget $dateWidget;
    private readonly TextWidget $barWidget;
    private readonly TextWidget $infoWidget;
    private readonly TextWidget $statusText;

    public function __construct()
    {
        $this->expandVertically(true);
        $this->addStyleClass('bg-gray-950')
             ->addStyleClass('border')
             ->addStyleClass('border-double')
             ->addStyleClass('border-cyan-800');

        // Main centred area.
        $main = new ContainerWidget();
        $main->expandVertically(true);
        $main->addStyleClass('align-center')
             ->addStyleClass('valign-center')
             ->addStyleClass('text-center');

        $this->timeWidget = new TextWidget('');
        $this->timeWidget
             ->addStyleClass('font-big')
             ->addStyleClass('text-cyan-400')
             ->addStyleClass('bold')
             ->addStyleClass('text-center');
        $main->add($this->timeWidget);

        $this->dateWidget = new TextWidget('');
        $this->dateWidget
             ->addStyleClass('text-cyan-700')
             ->addStyleClass('text-center');
        $main->add($this->dateWidget);

        $this->barWidget = new TextWidget('');
        $this->barWidget
             ->addStyleClass('text-gray-500')
             ->addStyleClass('text-center');
        $main->add($this->barWidget);

        $this->infoWidget = new TextWidget('');
        $this->infoWidget
             ->addStyleClass('text-gray-600')
             ->addStyleClass('dim')
             ->addStyleClass('text-center');
        $main->add($this->infoWidget);

        $this->add($main);

        // Status bar at the bottom.
        $statusBar = new ContainerWidget();
        $statusBar
            ->addStyleClass('bg-gray-900')
            ->addStyleClass('px-2')
            ->addStyleClass('border-t')
            ->addStyleClass('border-cyan-900')
            ->addStyleClass('align-center');

        $this->statusText = new TextWidget('');
        $this->statusText
             ->addStyleClass('text-gray-600')
             ->addStyleClass('dim');
        $statusBar->add($this->statusText);

        $this->add($statusBar);
    }

    // -------------------------------------------------------------------------
    // Keybindings
    // -------------------------------------------------------------------------

    protected static function getDefaultKeybindings(): array
    {
        return [
            'theme'   => ['t'],
            'seconds' => ['s'],
            'format'  => ['h'],
            'quit'    => [Key::ctrl('c'), 'q'],
        ];
    }

    public function handleInput(string $data): void
    {
        $kb = $this->getKeybindings();

        if ($kb->matches($data, 'theme')) {
            $this->themeIndex = ($this->themeIndex + 1) % \count(self::THEMES);
            $this->applyTheme();
            $this->lastTime = '';
            $this->updateDisplay();
        } elseif ($kb->matches($data, 'seconds')) {
            $this->showSeconds = !$this->showSeconds;
            $this->lastTime = '';
            $this->updateDisplay();
        } elseif ($kb->matches($data, 'format')) {
            $this->show24h = !$this->show24h;
            $this->lastTime = '';
            $this->updateDisplay();
        } elseif ($kb->matches($data, 'quit')) {
            $this->dispatchQuit();
        }
    }

    // -------------------------------------------------------------------------
    // Scheduling
    // -------------------------------------------------------------------------

    protected function resolveScheduledTickContext(): ?WidgetContext
    {
        return $this->getContext();
    }

    protected function onScheduledTick(): void
    {
        $this->updateDisplay();
    }

    protected function onAttach(WidgetContext $context): void
    {
        $this->applyTheme();
        $this->updateDisplay();
        $this->startScheduledTick(1.0);
    }

    protected function onDetach(): void
    {
        $this->stopScheduledTick();
    }

    // -------------------------------------------------------------------------
    // Rendering helpers
    // -------------------------------------------------------------------------

    private function applyTheme(): void
    {
        $theme = self::THEMES[$this->themeIndex];
        $this->timeWidget->setStyleClasses(['font-big', 'bold', 'text-center', $theme['time']]);
        $this->dateWidget->setStyleClasses(['text-center', $theme['date']]);
        $this->setStyleClasses(['bg-gray-950', 'border', 'border-double', $theme['border']]);
    }

    private function timeString(): string
    {
        if ($this->show24h) {
            return $this->showSeconds ? date('H:i:s') : date('H:i');
        }

        return $this->showSeconds ? date('g:i:s A') : date('g:i A');
    }

    private function updateDisplay(): void
    {
        $time = $this->timeString();
        if ($time === $this->lastTime) {
            return;
        }
        $this->lastTime = $time;

        $theme = self::THEMES[$this->themeIndex];
        [$r, $g, $b] = $theme['bar'];
        $barFg  = \sprintf("\x1b[38;2;%d;%d;%dm", $r, $g, $b);
        $barDim = \sprintf("\x1b[38;2;%d;%d;%dm", (int) ($r * 0.3), (int) ($g * 0.3), (int) ($b * 0.3));
        $reset  = "\x1b[0m";

        $this->timeWidget->setText($time);
        $this->dateWidget->setText("\n".date('l, F j, Y')."\n");

        // Seconds progress bar (60 chars wide = 1 char per second).
        $sec    = (int) date('s');
        $barW   = 60;
        $filled = min($barW, $sec);
        $this->barWidget->setText(
            $barFg.str_repeat('█', $filled)
            .$barDim.str_repeat('░', $barW - $filled)
            .$reset."\n"
        );

        // Day and week progress.
        $h = (int) date('G');
        $m = (int) date('i');
        $s = (int) date('s');

        $dayPct  = ($h * 3600 + $m * 60 + $s) / 86400.0 * 100;
        $dow     = (int) date('N'); // 1=Mon … 7=Sun
        $weekPct = (($dow - 1 + $h / 24.0) / 7.0) * 100;

        $dayFilled  = (int) ($dayPct / 100 * 30);
        $weekFilled = (int) ($weekPct / 100 * 30);
        $dim        = "\x1b[2m";
        $gray       = "\x1b[38;2;70;70;80m";

        $dayBar  = $barFg.str_repeat('█', $dayFilled).$barDim.str_repeat('░', 30 - $dayFilled).$reset;
        $weekBar = $barFg.str_repeat('█', $weekFilled).$barDim.str_repeat('░', 30 - $weekFilled).$reset;

        $this->infoWidget->setText(\sprintf(
            "%sDay%s   %s  %s%4.1f%%%s\n%sWeek%s  %s  %s%4.1f%%%s  %s(%s)%s",
            $dim.$gray, $reset, $dayBar, $gray, $dayPct, $reset,
            $dim.$gray, $reset, $weekBar, $gray, $weekPct, $reset,
            $dim.$gray, date('l'), $reset,
        ));

        $this->statusText->setText(\sprintf(
            '[t] theme (%s)    [s] seconds %s    [h] 12/24h    [q/ctrl+c] quit',
            $theme['name'],
            $this->showSeconds ? 'on' : 'off',
        ));

        $this->invalidate();
    }
}
