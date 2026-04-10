<?php

namespace App\Tui;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;

/**
 * Fluent builder wrapper around ContainerWidget.
 *
 * Adds a named constructor and variadic add() to allow
 * declaring the widget tree as a single nested expression.
 */
final class Box extends ContainerWidget
{
    /**
     * Variadic override of ContainerWidget::add() for inline nesting.
     *
     * @return $this
     */
    public function add(AbstractWidget $widget, AbstractWidget ...$widgets): static
    {
        parent::add($widget);
        foreach ($widgets as $widget) {
            parent::add($widget);
        }

        return $this;
    }
}
