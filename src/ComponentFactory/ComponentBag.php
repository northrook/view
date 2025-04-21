<?php

declare(strict_types=1);

namespace Core\View\ComponentFactory;

use Core\View\Component;
use Core\View\Component\Properties;
use Core\View\Exception\ComponentNotFoundException;

/**
 * @internal
 */
final class ComponentBag
{
    /**
     * @param array<string, array{name: string, class: class-string<Component>, static: bool, tags: string[], tagged: string[][]}|Properties> $components
     */
    public function __construct( private array $components = [] ) {}

    /**
     * Gets the service container parameters.
     *
     * @return array<string, Properties>
     */
    public function all() : array
    {
        foreach ( $this->components as $component => $properties ) {
            if ( \is_array( $properties ) ) {
                $this->components[$component] = new Properties( ...$properties );
            }
        }
        /** @var array<string, Properties> */
        return $this->components;
    }

    /**
     * Gets a service container parameter.
     *
     * @param string $component
     *
     * @return Properties
     * @throws ComponentNotFoundException
     */
    public function get( string $component ) : Properties
    {
        $properties = $this->components[$component] ?? throw new ComponentNotFoundException( $component );

        if ( $properties instanceof Properties ) {
            return $properties;
        }

        return $this->components[$component] = new Properties( ...$properties );
    }

    /**
     * Returns true if a parameter name is defined.
     *
     * @param string $component
     *
     * @return bool
     */
    public function has( string $component ) : bool
    {
        return \array_key_exists( $component, $this->components );
    }
}
