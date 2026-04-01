<?php

namespace App\Clock;

use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Style\Style;

use function Symfony\Component\Clock\now;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\ProgressBarWidget;
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
 *
 * Bar colors are applied via StyleSheet element rules; call
 * {@see ClockCommand::configureStylesheet()} before running.
 */
class ClockWidget extends ContainerWidget implements FocusableInterface
{
    use FocusableTrait;
    use QuitableTrait;
    use KeybindingsTrait;
    use ScheduledTickTrait;

    /**
     * @var array<int, array{name: string, time: string, date: string, border: string, css: string, bar: int[]}>
     */
    public const THEMES = [
        ['name' => 'Cyan',   'time' => 'text-cyan-400',   'date' => 'text-cyan-700',   'border' => 'border-cyan-800',   'css' => 'clock-bar-cyan',   'bar' => [80, 200, 220]],
        ['name' => 'Green',  'time' => 'text-green-400',  'date' => 'text-green-700',  'border' => 'border-green-800',  'css' => 'clock-bar-green',  'bar' => [80, 200, 120]],
        ['name' => 'Amber',  'time' => 'text-amber-400',  'date' => 'text-amber-700',  'border' => 'border-amber-800',  'css' => 'clock-bar-amber',  'bar' => [255, 200, 50]],
        ['name' => 'Rose',   'time' => 'text-rose-400',   'date' => 'text-rose-700',   'border' => 'border-rose-800',   'css' => 'clock-bar-rose',   'bar' => [255, 100, 130]],
        ['name' => 'Violet', 'time' => 'text-violet-400', 'date' => 'text-violet-700', 'border' => 'border-violet-800', 'css' => 'clock-bar-violet', 'bar' => [160, 120, 255]],
    ];

    private int $themeIndex = 0;
    private bool $showSeconds = true;
    private bool $show24h = true;
    private string $lastTime = '';

    private readonly TextWidget $timeWidget;
    private readonly TextWidget $dateWidget;
    private readonly TextWidget $statusText;
    private readonly ProgressBarWidget $secondsBar;
    private readonly ProgressBarWidget $dayBar;
    private readonly ProgressBarWidget $weekBar;

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
             ->addStyleClass('bold')
             ->addStyleClass('text-center')
             ->addStyleClass('text-cyan-400');
        $main->add($this->timeWidget);

        $this->dateWidget = new TextWidget('');
        $this->dateWidget
             ->addStyleClass('text-center')
             ->addStyleClass('text-cyan-700');
        $main->add($this->dateWidget);

        // Seconds bar: one block per second, 60 chars wide.
        // Each bar has maxColumns so its rendered line is narrower than the parent
        // container; each is then wrapped in a ContainerWidget with align-center
        // so the Renderer can compute a per-bar centering offset independently.
        $this->secondsBar = new ProgressBarWidget(60, '%bar%');
        $this->secondsBar
             ->setBarWidth(60)
             ->setBarCharacter('█')
             ->setEmptyBarCharacter('░')
             ->setStyle(new Style(maxColumns: 60))
             ->addStyleClass('clock-bar-cyan');
        // Day and week progress bars.
        // maxColumns = label(6) + bar(30) + separators(2) + percent(5) = 43.
        $this->dayBar = new ProgressBarWidget(86400, 'Day   %bar%  %percent:4s%%');
        $this->dayBar
             ->setBarWidth(30)
             ->setBarCharacter('█')
             ->setEmptyBarCharacter('░')
             ->setStyle(new Style(maxColumns: 43))
             ->addStyleClass('text-gray-600')
             ->addStyleClass('clock-bar-cyan');

        // maxColumns = 43 + '  (Wednesday)' (13) = 56.
        $this->weekBar = new ProgressBarWidget(7 * 86400, 'Week  %bar%  %percent:4s%%  (%day%)');
        $this->weekBar
             ->setBarWidth(30)
             ->setBarCharacter('█')
             ->setEmptyBarCharacter('░')
             ->setStyle(new Style(maxColumns: 56))
             ->addStyleClass('text-gray-600')
             ->addStyleClass('clock-bar-cyan');
        $this->weekBar->setPlaceholderFormatter('day', static fn () => now()->format('l'));

        // Group all three bars with gap-1 between secondsBar and the day/week pair.
        $barsGroup = new ContainerWidget();
        $barsGroup->addStyleClass('gap-1');
        $barsGroup->add($this->centeredBar($this->secondsBar));

        $dayWeekGroup = new ContainerWidget();
        $dayWeekGroup->add($this->centeredBar($this->dayBar));
        $dayWeekGroup->add($this->centeredBar($this->weekBar));
        $barsGroup->add($dayWeekGroup);

        $main->add($barsGroup);

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

        $this->setStyleClasses(['bg-gray-950', 'border', 'border-double', $theme['border']]);
        $this->timeWidget->setStyleClasses(['font-big', 'bold', 'text-center', $theme['time']]);
        $this->dateWidget->setStyleClasses(['text-center', $theme['date']]);
        $this->secondsBar->setStyleClasses([$theme['css']]);
        $this->dayBar->setStyleClasses(['text-gray-600', $theme['css']]);
        $this->weekBar->setStyleClasses(['text-gray-600', $theme['css']]);

        $this->lastTime = '';
        $this->updateDisplay();
    }

    private function timeString(): string
    {
        if ($this->show24h) {
            return $this->showSeconds ? now()->format('H:i:s') : now()->format('H:i');
        }

        return $this->showSeconds ? now()->format('g:i:s A') : now()->format('g:i A');
    }

    private function updateDisplay(): void
    {
        $time = $this->timeString();
        if ($time === $this->lastTime) {
            return;
        }
        $this->lastTime = $time;

        $dt  = now();
        $h   = (int) $dt->format('G');
        $m   = (int) $dt->format('i');
        $s   = (int) $dt->format('s');
        $dow = (int) $dt->format('N'); // 1=Mon … 7=Sun

        $this->timeWidget->setText($time);
        $this->dateWidget->setText("\n".$dt->format('l, F j, Y')."\n");

        $this->secondsBar->setProgress($s);
        $this->dayBar->setProgress($h * 3600 + $m * 60 + $s);
        $this->weekBar->setProgress(($dow - 1) * 86400 + $h * 3600 + $m * 60 + $s);

        $this->statusText->setText($this->buildHint());

        $this->invalidate();
    }

    private function buildHint(): string
    {
        $key = new Style(bold: true, color: 'white');
        $val = new Style(color: 'gray');
        $theme = self::THEMES[$this->themeIndex];

        return implode('    ', [
            $key->apply('[t]').' '.$val->apply($theme['name']),
            $key->apply('[s]').' '.$val->apply($this->showSeconds ? 'sec on' : 'sec off'),
            $key->apply('[h]').' '.$val->apply($this->show24h ? '24h' : '12h'),
            $key->apply('[q]').' '.$val->apply('quit'),
        ]);
    }

    /**
     * Wrap a ProgressBarWidget in a ContainerWidget with align-center so the
     * renderer computes a centering offset for the bar independently of its
     * siblings (ProgressBarWidget pads to full width, so the parent's single
     * align-center pass would always see zero available space without a wrapper).
     */
    private function centeredBar(ProgressBarWidget $bar): ContainerWidget
    {
        $wrapper = new ContainerWidget();
        $wrapper->addStyleClass('align-center');
        $wrapper->add($bar);

        return $wrapper;
    }
}
