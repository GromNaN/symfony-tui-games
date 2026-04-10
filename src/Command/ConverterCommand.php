<?php

namespace App\Command;

use App\Converter\ConverterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\SettingChangeEvent;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SettingItem;
use Symfony\Component\Tui\Widget\SettingsListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

#[AsCommand(name: 'app:converter', description: 'Interactive text converter (1337, Base64, ROT13, URL)')]
final class ConverterCommand
{
    /** @var iterable<ConverterInterface> */
    private iterable $converters;

    public function __construct(
        #[AutowireIterator(ConverterInterface::class)]
        iterable $converters,
    ) {
        $this->converters = $converters;
    }

    public function getConverterIds(): array
    {
        $ids = [];
        foreach ($this->converters as $converter) {
            $ids[] = $converter->getId();
        }

        return $ids;
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Pre-select a converter', suggestedValues: [self::class, 'getConverterIds'])]
        ?string $converter = null,
        #[Option(description: 'Default text to convert')]
        ?string $text = null,
    ): int {
        /** @var ConverterInterface[] $converters */
        $converters = [];
        /** @var array<string, ConverterInterface> $converterMap */
        $converterMap = [];
        foreach ($this->converters as $c) {
            $converters[] = $c;
            $converterMap[$c->getId()] = $c;
        }

        $activeConverter = isset($converter, $converterMap[$converter])
            ? $converterMap[$converter]
            : $converters[0];

        // ── Widgets ──────────────────────────────────────────────────────────

        $normalInput = (new InputWidget())->setPrompt(' ');
        $normalInput->addStyleClass('leet-input');
        if (null !== $text) {
            $normalInput->setValue($text);
        }

        $outputLabel = new TextWidget('  '.$activeConverter->getName());
        $outputLabel->addStyleClass('leet-label');

        $encodedInput = (new InputWidget())->setPrompt(' ');
        $encodedInput->addStyleClass('leet-input');
        if (null !== $text) {
            $encodedInput->setValue($activeConverter->encode($text));
        }

        $settingItems = array_map(
            static fn (ConverterInterface $c) => new SettingItem(
                id: $c->getId(),
                label: $c->getName(),
                currentValue: $c->getId() === $activeConverter->getId() ? '✓' : '○',
                values: ['✓', '○'],
            ),
            $converters,
        );

        $settingsList = new SettingsListWidget($settingItems, maxVisible: \count($converters));
        $settingsList->addStyleClass('leet-settings');

        // ── Layout ───────────────────────────────────────────────────────────

        $normalLabel = new TextWidget('  Original text');
        $normalLabel->addStyleClass('leet-label');

        $convertersLabel = new TextWidget('  Converters');
        $convertersLabel->addStyleClass('leet-label');

        $container = new ContainerWidget();
        $container->addStyleClass('leet');
        $container->add($normalLabel);
        $container->add($normalInput);
        $container->add($outputLabel);
        $container->add($encodedInput);
        $container->add($convertersLabel);
        $container->add($settingsList);

        // ── Stylesheet ───────────────────────────────────────────────────────

        $panelBorder = Border::from([1], BorderPattern::ROUNDED, 'bright_black');
        $panelBorderFocused = Border::from([1], BorderPattern::ROUNDED, 'bright_yellow');

        $stylesheet = new StyleSheet([
            ':root' => new Style(align: Align::Center, verticalAlign: VerticalAlign::Center),
            '.leet' => new Style(
                direction: Direction::Vertical,
                maxColumns: 64,
                border: Border::from([1], BorderPattern::ROUNDED, 'yellow'),
                padding: Padding::from([0, 1]),
                gap: 1,
            ),
            '.leet-label' => new Style(bold: true, color: 'yellow'),
            '.leet-input' => new Style(border: $panelBorder, padding: Padding::from([0, 1])),
            '.leet-input:focus' => new Style(border: $panelBorderFocused),
            '.leet-settings' => new Style(border: $panelBorder),
            '.leet-settings:focus' => new Style(border: $panelBorderFocused),
            SettingsListWidget::class.'::hint' => new Style(dim: true),
            SettingsListWidget::class.'::label-selected' => new Style(bold: true, color: 'bright_yellow'),
            SettingsListWidget::class.'::value-selected' => new Style(bold: true, color: 'bright_yellow'),
            SettingsListWidget::class.'::value' => new Style(color: 'bright_black'),
        ]);

        // ── TUI ──────────────────────────────────────────────────────────────

        $tui = new Tui($stylesheet);
        $tui->add($container);
        $tui->setFocus($normalInput);

        // Tab / Shift+Tab for focus navigation (intercept before widgets see it)
        $tui->on(InputEvent::class, static function (InputEvent $e) use ($tui): void {
            if ("\t" === $e->getData()) {
                $e->stopPropagation();
                $tui->getFocusManager()->focusNext();
            } elseif ("\x1b[Z" === $e->getData()) {
                $e->stopPropagation();
                $tui->getFocusManager()->focusPrevious();
            }
        });

        // Quit on Escape / Ctrl+C from any focusable widget
        $quit = static fn () => $tui->stop();
        $normalInput->onCancel($quit);
        $encodedInput->onCancel($quit);
        $settingsList->onCancel($quit);

        // ── Bidirectional conversion ──────────────────────────────────────────
        // setValue() only calls invalidate() — no onChange loop risk.

        $normalInput->onChange(static function ($e) use ($encodedInput, &$activeConverter): void {
            $encodedInput->setValue($activeConverter->encode($e->getValue()));
        });

        $encodedInput->onChange(static function ($e) use ($normalInput, &$activeConverter): void {
            $normalInput->setValue($activeConverter->decode($e->getValue()));
        });

        // ── Converter selection ───────────────────────────────────────────────

        $settingsList->onChange(static function (SettingChangeEvent $e) use (
            $settingsList,
            $converterMap,
            &$activeConverter,
            $normalInput,
            $encodedInput,
            $outputLabel,
        ): void {
            if ('✓' === $e->getValue()) {
                if ($activeConverter->getId() === $e->getId()) {
                    return; // Already active — ignore re-activation attempt
                }
                $settingsList->updateValue($activeConverter->getId(), '○');
                $activeConverter = $converterMap[$e->getId()];
                $outputLabel->setText('  '.$activeConverter->getName());
                $encodedInput->setValue($activeConverter->encode($normalInput->getValue()));
            } else {
                // Prevent deselecting the currently active converter
                if ($activeConverter->getId() === $e->getId()) {
                    $settingsList->updateValue($e->getId(), '✓');
                }
            }
        });

        $tui->run();

        return Command::SUCCESS;
    }
}
