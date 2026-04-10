<?php

namespace App\Tui;

use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

if (!function_exists(__NAMESPACE__.'\box')) {
    /**
     * @only-named-arguments
     */
    function box(string|array $class = []): Box
    {
        $box = new Box();
        foreach ((array) $class as $class) {
            $box->addStyleClass($class);
        }

        return $box;
    }

    /**
     * @only-named-arguments
     */
    function text(string $text, string|array $class = []): TextWidget
    {
        $widget = new TextWidget($text);
        foreach ((array) $class as $class) {
            $widget->addStyleClass($class);
        }

        return $widget;
    }

    /**
     * @only-named-arguments
     */
    function input(string|array $class = [], ?string $prompt = null): InputWidget
    {
        $widget = new InputWidget();
        foreach ((array) $class as $class) {
            $widget->addStyleClass($class);
        }
        if ($prompt) {
            $widget->setPrompt($prompt);
        }

        return $widget;
    }
}
