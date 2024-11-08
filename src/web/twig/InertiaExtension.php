<?php

namespace chasegiunta\inertia\web\twig;

use Craft;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use craft\helpers\Json;
use chasegiunta\inertia\Plugin as Inertia;

/**
 * Twig extension
 */
class InertiaExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('inertia', function ($component, $props = []) {
                // if (Inertia::getInstance()->settings->injectElement) {
                //     // Merge element into $templateVariables
                //     $props['element'] = $element;
                // }
    
                return Json::encode([
                    'component' => $component,
                    'props' => $props,
                ]);
            }, ['is_safe' => ['html']]),
            new TwigFunction('inertiaShare', function ($props) {
                return Json::encode($props);
            }, ['is_safe' => ['html']]),
        ];
    }

    public function getTests()
    {
        return [];
    }
}
