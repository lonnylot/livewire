<?php

namespace Livewire;

use Synthetic\Utils as SyntheticUtils;
use Livewire\Mechanisms\DataStore;
use Livewire\Features\SupportValidation\HandlesValidation;
use Livewire\Features\SupportRedirects\HandlesRedirects;
use Livewire\Features\SupportPageComponents\HandlesPageComponents;
use Livewire\Features\SupportNestingComponents\HandlesNestingComponents;
use Livewire\Features\SupportEvents\HandlesEvents;
use Livewire\Features\SupportDisablingBackButtonCache\HandlesDisablingBackButtonCache;
use Livewire\Exceptions\PropertyNotFoundException;
use Livewire\Drawer\Utils;
use Livewire\Concerns\InteractsWithProperties;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Str;
use BadMethodCallException;

/**
 * @todo - add Facade-esque method signatures to this file (from triggered __get and __call)
 */

abstract class Component extends \Synthetic\Component
{
    use Macroable { __call as macroCall; }

    use InteractsWithProperties;
    use HandlesEvents;
    use HandlesRedirects;
    use HandlesValidation;
    use HandlesPageComponents;
    use HandlesDisablingBackButtonCache;
    use HandlesNestingComponents;

    protected $__id;
    protected $__name;

    function id()
    {
        return $this->getId();
    }

    function setId($id)
    {
        $this->__id = $id;
    }

    function getId()
    {
        return $this->__id;
    }

    function setName($name)
    {
        $this->__name = $name;
    }

    function getName()
    {
        if ($this->__name) return $this->__name;

        return static::generateName();
    }

    static function generateName()
    {
        $alias = app(\Livewire\Mechanisms\ComponentRegistry::class)->getAlias(static::class);

        if ($alias) return $alias;

        $namespace = collect(explode('.', str_replace(['/', '\\'], '.', config('livewire.class_namespace'))))
            ->map(fn ($i) => Str::kebab($i))
            ->implode('.');

        $fullName = collect(explode('.', str_replace(['/', '\\'], '.', static::class)))
            ->map(fn ($i) => Str::kebab($i))
            ->implode('.');

        if (str($fullName)->startsWith($namespace)) {
            return (string) str($fullName)->substr(strlen($namespace) + 1);
        }

        return $fullName;
    }

    function skipRender()
    {
        store($this)->set('skipRender', true);
    }

    function __isset($property)
    {
        try {
            $this->__get($property);

            return true;
        } catch(PropertyNotFoundException $e) {
            return false;
        }

        return false;
    }

    function __get($property)
    {
        $value = 'noneset';

        $returnValue = function ($newValue) use (&$value) {
            $value = $newValue;
        };

        $finish = app('synthetic')->trigger('__get', $this, $property, $returnValue);

        $value = $finish($value);

        if ($value === 'noneset') {
            throw new PropertyNotFoundException($property, $this->getName());
        }

        return $value;
    }

    function __call($method, $params)
    {
        $value = 'noneset';

        $returnValue = function ($newValue) use (&$value) {
            $value = $newValue;
        };

        $finish = app('synthetic')->trigger('__call', $this, $method, $params, $returnValue);

        $value = $finish($value);

        if ($value !== 'noneset') {
            return $value;
        }

        if (static::hasMacro($method)) {
            return $this->macroCall($method, $params);
        }

        throw new BadMethodCallException(sprintf(
            'Method %s::%s does not exist.', static::class, $method
        ));
    }
}
