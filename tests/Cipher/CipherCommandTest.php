<?php

namespace App\Tests\Cipher;

use App\Cipher\Base64Cipher;
use App\Cipher\CaesarCipher;
use App\Cipher\CipherInterface;
use App\Cipher\LeetCipher;
use App\Cipher\VigenereCipher;
use App\Command\CipherCommand;
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

class CipherCommandTest extends TestCase
{
    /** @var CipherInterface[] */
    private static array $ciphers;

    public static function setUpBeforeClass(): void
    {
        self::$ciphers = [new CaesarCipher(), new VigenereCipher(), new LeetCipher(), new Base64Cipher()];
    }

    public function testGetCipherIds()
    {
        $command = new CipherCommand(self::$ciphers);
        $suggestions = $command->getCipherIds();

        self::assertContainsOnlyInstancesOf(Suggestion::class, $suggestions);

        $ids = array_map(static fn (Suggestion $s) => $s->getValue(), $suggestions);
        self::assertContains('caesar', $ids);
        self::assertContains('vigenere', $ids);
        self::assertContains('leet', $ids);
        self::assertContains('base64', $ids);
        self::assertNotContains('url', $ids);
        self::assertNotContains('rot13', $ids);
    }

    public function testRenderMatchesSnapshot()
    {
        $plain = $this->renderTui(activeCipher: self::$ciphers[0]);

        $snapshotFile = __DIR__.'/snapshots/render.txt';
        if (!file_exists($snapshotFile) || getenv('UPDATE_SNAPSHOTS')) {
            if (!is_dir(\dirname($snapshotFile))) {
                mkdir(\dirname($snapshotFile), 0755, true);
            }
            file_put_contents($snapshotFile, $plain);
        }

        $this->assertStringEqualsFile($snapshotFile, $plain);
    }

    public function testRenderWithPreselectedCipher()
    {
        $plain = $this->renderTui(activeCipher: new Base64Cipher());

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
        $plain = $this->renderTui(activeCipher: self::$ciphers[0], text: 'hello');

        $snapshotFile = __DIR__.'/snapshots/render_with_text.txt';
        if (!file_exists($snapshotFile) || getenv('UPDATE_SNAPSHOTS')) {
            if (!is_dir(\dirname($snapshotFile))) {
                mkdir(\dirname($snapshotFile), 0755, true);
            }
            file_put_contents($snapshotFile, $plain);
        }

        $this->assertStringEqualsFile($snapshotFile, $plain);
    }

    private function renderTui(CipherInterface $activeCipher, ?string $text = null): string
    {
        $normalInput = (new InputWidget())->setPrompt(' ');
        $normalInput->addStyleClass('cipher-input');
        if (null !== $text) {
            $normalInput->setValue($text);
        }

        $outputLabel = new TextWidget('  '.$activeCipher->getName());
        $outputLabel->addStyleClass('cipher-label');

        $encodedInput = (new InputWidget())->setPrompt(' ');
        $encodedInput->addStyleClass('cipher-input');
        if (null !== $text) {
            $encodedInput->setValue($activeCipher->encode($text));
        }

        $settingItems = array_map(
            static fn (CipherInterface $c) => new SettingItem(
                id: $c->getId(),
                label: $c->getName(),
                currentValue: $c->getId() === $activeCipher->getId() ? '✓' : '○',
                values: ['✓', '○'],
            ),
            self::$ciphers,
        );

        $settingsList = new SettingsListWidget($settingItems, maxVisible: \count(self::$ciphers));
        $settingsList->addStyleClass('cipher-settings');

        $normalLabel = new TextWidget('  Original text');
        $normalLabel->addStyleClass('cipher-label');

        $ciphersLabel = new TextWidget('  Ciphers');
        $ciphersLabel->addStyleClass('cipher-label');

        $container = new ContainerWidget();
        $container->addStyleClass('cipher');
        $container->add($normalLabel);
        $container->add($normalInput);
        $container->add($outputLabel);
        $container->add($encodedInput);
        $container->add($ciphersLabel);
        $container->add($settingsList);

        $panelBorder = Border::from([1], BorderPattern::ROUNDED, 'bright_black');
        $panelBorderFocused = Border::from([1], BorderPattern::ROUNDED, 'bright_yellow');

        $stylesheet = new StyleSheet([
            ':root' => new Style(align: Align::Center, verticalAlign: VerticalAlign::Center),
            '.cipher' => new Style(
                direction: Direction::Vertical,
                maxColumns: 64,
                border: Border::from([1], BorderPattern::ROUNDED, 'yellow'),
                padding: Padding::from([0, 1]),
                gap: 1,
            ),
            '.cipher-label' => new Style(bold: true, color: 'yellow'),
            '.cipher-input' => new Style(border: $panelBorder, padding: Padding::from([0, 1])),
            '.cipher-input:focus' => new Style(border: $panelBorderFocused),
            '.cipher-settings' => new Style(border: $panelBorder),
            '.cipher-settings:focus' => new Style(border: $panelBorderFocused),
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
