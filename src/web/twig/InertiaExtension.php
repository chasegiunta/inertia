<?php

namespace chasegiunta\inertia\web\twig;

use Craft;
use Twig\Error\RuntimeError;
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
        return [
            'recursive_merge' => new TwigFilter('recursive_merge', [
                'chasegiunta\inertia\web\twig\InertiaExtension',
                'recursiveMergeFilter',
            ]),
            'set' => new TwigFilter('set', [
                'chasegiunta\inertia\web\twig\InertiaExtension',
                'setFilter',
            ]),
            'add' => new TwigFilter('add', [
                'chasegiunta\inertia\web\twig\InertiaExtension',
                'addFilter',
            ]),
        ];
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

    /**
     * Recursively merges an array into the element, replacing existing values.
     *
     * {{ form|recursive_merge( {'element': {'attributes': {'placeholder': 'Label'}}} ) }}
     */
    public static function recursiveMergeFilter($element, $array): array
    {
        if (!is_iterable($element)) {
            throw new RuntimeError(sprintf('The recursive_merge filter only works on arrays or "Traversable" objects, got "%s".', gettype($element)));
        }

        return array_replace_recursive($element, $array);
    }

    /**
     * Sets a deeply-nested property on an array.
     *
     * If the deeply-nested property exists, the existing data will be replaced
     * with the new value.
     * 
     * {{ form|set( 'element.#attributes.placeholder', 'Label' ) }}
     *
     * Or using named arguments:
     * 
     * {{ form|set( at='element.#attributes.placeholder', value='Label' ) }}
     */
    public static function setFilter($element, string $at, $value)
    {
        if (!is_iterable($element)) {
            throw new RuntimeError(sprintf('The "set" filter only works on arrays or "Traversable" objects, got "%s".', gettype($element)));
        }

        return self::addOrSetFilter($element, $at, $value);
    }

    /**
     * Adds a deeply-nested property on an array.
     *
     * If the deeply-nested property exists, the existing data will be replaced
     * with the new value, unless the existing data is an array. In which case,
     * the new value will be merged into the existing array.
     *
     * {{ form|add( 'element.#attributes.class', 'new-class' ) }}
     *
     * Or using named arguments:
     * {{ form|add( at='element.#attributes.class', value='new-class' ) }}
     * 
     * {# We accept the plural form of "values" as a grammatical convenience. #}
     * {{ form|add( at='element.#attributes.class', values=['new-class', 'new-class-2'] ) }}
     */
    public static function addFilter($element, string $at, $value = NULL, $values = NULL)
    {
        if (!is_iterable($element)) {
            throw new RuntimeError(sprintf('The "add" filter only works on arrays or "Traversable" objects, got "%s".', gettype($element)));
        }

        return self::addOrSetFilter($element, $at, !is_null($values) ? $values : $value, TRUE);
    }

    /**
     * Helper function for the set/add filters.
     *
     * @param array|iterable|\Traversable $element
     *   The parent renderable array to merge into.
     * @param string $at
     *   The dotted-path to the deeply nested element to replace.
     * @param mixed $value
     *   The value to set.
     * @param bool $isAddFilter
     *   Which filter is being called.
     *
     * @return array
     *   The merged renderable array.
     */
    protected static function addOrSetFilter($element, string $at, $value, $isAddFilter = FALSE)
    {
        if ($element instanceof \ArrayAccess) {
            $filteredElement = self::toArray($element);
        } else {
            $filteredElement = $element;
        }

        // Convert the dotted path into an array of keys.
        $path = explode('.', $at);
        $lastPath = array_pop($path);

        // Traverse the element down the path, creating arrays as needed.
        $childElement =& $filteredElement;
        foreach ($path as $childPath) {
            if (!isset($childElement[$childPath])) {
                $childElement[$childPath] = [];
            }
            $childElement =& $childElement[$childPath];
        }

        // If this is the add() filter and if the targeted child element is an
        // array, add the value to it.
        if ($isAddFilter && isset($childElement[$lastPath]) && is_array($childElement[$lastPath])) {
            if (is_array($value)) {
                $childElement[$lastPath] = array_merge($childElement[$lastPath], $value);
            } else {
                $childElement[$lastPath][] = $value;
            }
        } else {
            // Otherwise, replace the target element with the given value.
            $childElement[$lastPath] = $value;
        }

        return $filteredElement;
    }

    private static function toArray($object)
    {
        if (is_array($object)) {
            return $object;
        }
        if (method_exists($object, 'toArray')) {
            return $object->toArray();
        }
        if ($object instanceof \JsonSerializable) {
            return (array) $object;
        }
        return get_object_vars($object);
    }
}
