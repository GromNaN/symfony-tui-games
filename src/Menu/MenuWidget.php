<?php

namespace App\Menu;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\QuitableTrait;

#[Exclude]
class MenuWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;
    use QuitableTrait;

    private int $cursor = 0;
    private ?string $selected = null;

    private readonly Style $styleTitle;
    private readonly Style $styleItem;
    private readonly Style $styleItemSelected;
    private readonly Style $styleDesc;
    private readonly Style $styleDescSelected;
    private readonly Style $styleSep;
    private readonly Style $styleHint;

    /**
     * @param array<array{name: string, command: string, description: string}> $games
     */
    public function __construct(private readonly array $games)
    {
        $this->styleTitle = new Style(color: 'bright_white', bold: true);
        $this->styleItem = new Style(color: 'white');
        $this->styleItemSelected = new Style(color: 'bright_green', bold: true);
        $this->styleDesc = new Style(dim: true);
        $this->styleDescSelected = new Style(color: 'green');
        $this->styleSep = new Style(dim: true);
        $this->styleHint = new Style(dim: true);
    }

    public function getSelected(): ?string
    {
        return $this->selected;
    }

    protected static function getDefaultKeybindings(): array
    {
        return [
            'up' => [Key::UP, 'k'],
            'down' => [Key::DOWN, 'j'],
            'select' => [Key::ENTER],
            'quit' => [Key::ctrl('c'), 'q'],
        ];
    }

    public function handleInput(string $data): void
    {
        $kb = $this->getKeybindings();

        if ($kb->matches($data, 'up')) {
            $this->cursor = max(0, $this->cursor - 1);
            $this->invalidate();
        } elseif ($kb->matches($data, 'down')) {
            $this->cursor = min(\count($this->games) - 1, $this->cursor + 1);
            $this->invalidate();
        } elseif ($kb->matches($data, 'select')) {
            $this->selected = $this->games[$this->cursor]['command'];
            $this->getContext()?->stop();
        } elseif ($kb->matches($data, 'quit')) {
            $this->dispatchQuit();
        }
    }

    public function render(RenderContext $context): array
    {
        $w = $context->getColumns();
        $sep = $this->styleSep->apply(str_repeat('─', $w));

        $lines = [];
        $lines[] = $this->styleTitle->apply(str_pad(' Symfony TUI Games ', $w, ' ', \STR_PAD_BOTH));
        $lines[] = $sep;

        foreach ($this->games as $i => $game) {
            $active = $i === $this->cursor;
            $prefix = $active ? '▶ ' : '  ';
            $name = $prefix.$game['name'];
            $nameW = 18;
            $namePadded = str_pad($name, $nameW);
            $desc = $game['description'];

            $available = $w - $nameW - 2;
            if ($available > 0 && AnsiUtils::visibleWidth($desc) > $available) {
                $desc = mb_substr($desc, 0, $available - 1).'…';
            }

            $nameStyle = $active ? $this->styleItemSelected : $this->styleItem;
            $descStyle = $active ? $this->styleDescSelected : $this->styleDesc;

            $lines[] = $nameStyle->apply($namePadded).'  '.$descStyle->apply($desc);
        }

        $lines[] = $sep;
        $lines[] = $this->styleHint->apply(str_pad('  ↑↓ navigate   enter launch   q quit', $w));

        return $lines;
    }
}
