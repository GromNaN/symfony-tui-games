<?php

namespace App\Tests\Converter;

use App\Converter\Base64Converter;
use App\Converter\LeetConverter;
use App\Converter\Rot13Converter;
use App\Converter\UrlConverter;
use PHPUnit\Framework\TestCase;
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
    public function testRenderMatchesSnapshot(): void
    {
        $converters = [new LeetConverter(), new Base64Converter(), new Rot13Converter(), new UrlConverter()];
        $activeConverter = $converters[0];

        $normalInput = (new InputWidget())->setPrompt(' ');
        $normalInput->addStyleClass('leet-input');

        $outputLabel = new TextWidget('  '.$activeConverter->getName());
        $outputLabel->addStyleClass('leet-label');

        $encodedInput = (new InputWidget())->setPrompt(' ');
        $encodedInput->addStyleClass('leet-input');

        $settingItems = array_map(
            static fn ($c) => new SettingItem(
                id: $c->getId(),
                label: $c->getName(),
                currentValue: $c->getId() === $activeConverter->getId() ? '✓' : '○',
                values: ['✓', '○'],
            ),
            $converters,
        );

        $settingsList = new SettingsListWidget($settingItems, maxVisible: \count($converters));
        $settingsList->addStyleClass('leet-settings');

        $container = new ContainerWidget();
        $container->addStyleClass('leet');
        $normalLabel = new TextWidget('  Texte original');
        $normalLabel->addStyleClass('leet-label');
        $convertersLabel = new TextWidget('  Convertisseurs');
        $convertersLabel->addStyleClass('leet-label');

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
