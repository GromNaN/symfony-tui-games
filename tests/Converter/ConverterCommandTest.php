<?php

namespace App\Tests\Converter;

use App\Command\ConverterCommand;
use App\Converter\Base64Converter;
use App\Converter\ConverterInterface;
use App\Converter\LeetConverter;
use App\Converter\Rot13Converter;
use App\Converter\UrlConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SettingItem;
use Symfony\Component\Tui\Widget\SettingsListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class ConverterCommandTest extends TestCase
{
    /** @var ConverterInterface[] */
    private static array $converters;

    public static function setUpBeforeClass(): void
    {
        self::$converters = [new LeetConverter(), new Base64Converter(), new Rot13Converter(), new UrlConverter()];
    }

    public function testGetConverterIds()
    {
        $command = new ConverterCommand(self::$converters);
        $suggestions = $command->getConverterIds();

        self::assertCount(4, $suggestions);
        self::assertContainsOnlyInstancesOf(Suggestion::class, $suggestions);

        $ids = array_map(static fn (Suggestion $s) => $s->getValue(), $suggestions);
        self::assertSame(['leet', 'base64', 'rot13', 'url'], $ids);

        $labels = array_map(static fn (Suggestion $s) => $s->getDescription(), $suggestions);
        self::assertSame(['1337 sp34k', 'Base64', 'ROT13', 'URL Encode'], $labels);
    }

    public function testRenderMatchesSnapshot()
    {
        $plain = $this->renderTui(activeConverter: self::$converters[0]);

        $snapshotFile = __DIR__.'/snapshots/render.txt';
        if (!file_exists($snapshotFile) || getenv('UPDATE_SNAPSHOTS')) {
            if (!is_dir(\dirname($snapshotFile))) {
                mkdir(\dirname($snapshotFile), 0755, true);
            }
            file_put_contents($snapshotFile, $plain);
        }

        $this->assertStringEqualsFile($snapshotFile, $plain);
    }

    public function testRenderWithPreselectedConverter()
    {
        $plain = $this->renderTui(activeConverter: new Base64Converter());

        $snapshotFile = __DIR__.'/snapshots/render_base64.txt';
        if (!file_exists($snapshotFile) || getenv('UPDATE_SNAPSHOTS')) {
            if (!is_dir(\dirname($snapshotFile))) {
                mkdir(\dirname($snapshotFile), 0755, true);
            }
            file_put_contents($snapshotFile, $plain);
        }

        $this->assertStringEqualsFile($snapshotFile, $plain);
    }

    public function testRenderWithDefaultText()
    {
        $plain = $this->renderTui(activeConverter: self::$converters[0], text: 'hello');

        $snapshotFile = __DIR__.'/snapshots/render_with_text.txt';
        if (!file_exists($snapshotFile) || getenv('UPDATE_SNAPSHOTS')) {
            if (!is_dir(\dirname($snapshotFile))) {
                mkdir(\dirname($snapshotFile), 0755, true);
            }
            file_put_contents($snapshotFile, $plain);
        }

        $this->assertStringEqualsFile($snapshotFile, $plain);
    }

    private function renderTui(ConverterInterface $activeConverter, ?string $text = null): string
    {
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
            self::$converters,
        );

        $settingsList = new SettingsListWidget($settingItems, maxVisible: \count(self::$converters));
        $settingsList->addStyleClass('leet-settings');

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

        $terminal = new VirtualTerminal(90, 30);
        $tui = new Tui($stylesheet, terminal: $terminal);
        $tui->add($container);
        $tui->setFocus($normalInput);
        $tui->start();
        $tui->processRender();

        return AnsiUtils::stripAnsiCodes($terminal->getOutput());
    }
}
