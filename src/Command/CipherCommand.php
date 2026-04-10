<?php

namespace App\Command;

use App\Cipher\CipherInterface;
use App\Cipher\KeyedCipherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Suggestion;
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

#[AsCommand(name: 'app:cipher', description: '🕵️ Secret message encoder (Caesar, Vigenère, 1337, Base64, ROT13, URL)')]
final class CipherCommand
{
    /** @var iterable<CipherInterface> */
    private iterable $ciphers;

    public function __construct(
        #[AutowireIterator(CipherInterface::class)]
        iterable $ciphers,
    ) {
        $this->ciphers = $ciphers;
    }

    /**
     * @return list<Suggestion>
     */
    public function getCipherIds(): array
    {
        $ids = [];
        foreach ($this->ciphers as $cipher) {
            $ids[] = new Suggestion($cipher->getId(), $cipher->getName());
        }

        return $ids;
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Pre-select a cipher', suggestedValues: [self::class, 'getCipherIds'])]
        ?string $algo = null,
        #[Option(description: 'Default text to encode')]
        ?string $text = null,
    ): int {
        /** @var CipherInterface[] $ciphers */
        $ciphers = [];
        /** @var array<string, CipherInterface> $cipherMap */
        $cipherMap = [];
        foreach ($this->ciphers as $c) {
            $ciphers[] = $c;
            $cipherMap[$c->getId()] = $c;
        }

        $activeCipher = isset($algo, $cipherMap[$algo])
            ? $cipherMap[$algo]
            : $ciphers[0];

        // ── Widgets ──────────────────────────────────────────────────────────

        $keyLabel = new TextWidget('  Key');
        $keyLabel->addStyleClass('cipher-label');

        $keyInput = (new InputWidget())->setPrompt(' ');
        $keyInput->addStyleClass('cipher-input');

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
            $encodedInput->setValue($activeCipher instanceof KeyedCipherInterface
                ? $activeCipher->encodeWithKey($text, $activeCipher->getDefaultKey())
                : $activeCipher->encode($text));
        }

        $settingItems = array_map(
            static fn (CipherInterface $c) => new SettingItem(
                id: $c->getId(),
                label: $c->getName(),
                currentValue: $c->getId() === $activeCipher->getId() ? '✓' : '○',
                values: ['✓', '○'],
            ),
            $ciphers,
        );

        $settingsList = new SettingsListWidget($settingItems, maxVisible: \count($ciphers));
        $settingsList->addStyleClass('cipher-settings');
        $settingsList->focusItem($activeCipher->getId());

        // ── Layout ───────────────────────────────────────────────────────────

        $normalLabel = new TextWidget('  Original text');
        $normalLabel->addStyleClass('cipher-label');

        $ciphersLabel = new TextWidget('  Ciphers');
        $ciphersLabel->addStyleClass('cipher-label');

        $keyContainer = new ContainerWidget();
        $keyContainer->addStyleClass('cipher-key');
        $keyContainer->add($keyLabel);
        $keyContainer->add($keyInput);

        $container = new ContainerWidget();
        $container->addStyleClass('cipher');
        $container->add($normalLabel);
        $container->add($normalInput);
        $container->add($outputLabel);
        $container->add($encodedInput);
        $container->add($ciphersLabel);
        $container->add($settingsList);
        $container->add($keyContainer);

        // ── Stylesheet ───────────────────────────────────────────────────────

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
            '.cipher-key' => new Style(direction: Direction::Vertical, hidden: true),
            SettingsListWidget::class.'::hint' => new Style(dim: true),
            SettingsListWidget::class.'::label-selected' => new Style(bold: true, color: 'bright_yellow'),
            SettingsListWidget::class.'::value-selected' => new Style(bold: true, color: 'bright_yellow'),
            SettingsListWidget::class.'::value' => new Style(color: 'bright_black'),
        ]);

        // ── TUI ──────────────────────────────────────────────────────────────

        $tui = new Tui($stylesheet);
        $tui->add($container);
        $tui->setFocus($normalInput);

        // Show key panel if the initial cipher requires a key
        if ($activeCipher instanceof KeyedCipherInterface) {
            $keyInput->setValue($activeCipher->getDefaultKey());
            $stylesheet->addRule('.cipher-key', new Style(direction: Direction::Vertical, hidden: false));
        }

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
        $keyInput->onCancel($quit);
        $settingsList->onCancel($quit);

        // ── Bidirectional encoding ────────────────────────────────────────────
        // setValue() only calls invalidate() — no onChange loop risk.

        $encode = static function () use ($normalInput, $encodedInput, &$activeCipher, $keyInput): void {
            $t = $normalInput->getValue();
            $encodedInput->setValue($activeCipher instanceof KeyedCipherInterface
                ? $activeCipher->encodeWithKey($t, $keyInput->getValue())
                : $activeCipher->encode($t));
        };

        $decode = static function () use ($normalInput, $encodedInput, &$activeCipher, $keyInput): void {
            $t = $encodedInput->getValue();
            $normalInput->setValue($activeCipher instanceof KeyedCipherInterface
                ? $activeCipher->decodeWithKey($t, $keyInput->getValue())
                : $activeCipher->decode($t));
        };

        $normalInput->onChange(static function () use ($encode): void { $encode(); });
        $encodedInput->onChange(static function () use ($decode): void { $decode(); });
        $keyInput->onChange(static function () use ($encode): void { $encode(); });

        // ── Cipher selection ──────────────────────────────────────────────────

        $settingsList->onChange(static function (SettingChangeEvent $e) use (
            $settingsList,
            $cipherMap,
            &$activeCipher,
            $normalInput,
            $encodedInput,
            $outputLabel,
            $keyInput,
            $stylesheet,
            $container,
        ): void {
            if ('✓' === $e->getValue()) {
                if ($activeCipher->getId() === $e->getId()) {
                    return; // Already active — ignore re-activation attempt
                }
                $settingsList->updateValue($activeCipher->getId(), '○');
                $activeCipher = $cipherMap[$e->getId()];
                $outputLabel->setText('  '.$activeCipher->getName());
                $settingsList->focusItem($activeCipher->getId());
                if ($activeCipher instanceof KeyedCipherInterface) {
                    $keyInput->setValue($activeCipher->getDefaultKey());
                    $stylesheet->addRule('.cipher-key', new Style(direction: Direction::Vertical, hidden: false));
                    $encodedInput->setValue($activeCipher->encodeWithKey($normalInput->getValue(), $keyInput->getValue()));
                } else {
                    $stylesheet->addRule('.cipher-key', new Style(direction: Direction::Vertical, hidden: true));
                    $encodedInput->setValue($activeCipher->encode($normalInput->getValue()));
                }
                $container->invalidate();
            } else {
                // Prevent deselecting the currently active cipher
                if ($activeCipher->getId() === $e->getId()) {
                    $settingsList->updateValue($e->getId(), '✓');
                }
            }
        });

        $tui->run();

        return Command::SUCCESS;
    }
}
