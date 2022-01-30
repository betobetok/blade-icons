<?php

declare(strict_types=1);

use BladeUI\Icons\Factory;
use BladeUI\Icons\Svg;
use BladeUI\Icons\SvgElement;

if (! function_exists('svg')) {
    function svg(string $name, $class = '', array $attributes = []): Svg
    {
        return app(Factory::class)->svg($name, $class, $attributes);
    }
}