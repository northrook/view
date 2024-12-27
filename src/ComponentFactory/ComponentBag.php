<?php

declare(strict_types=1);

namespace Core\View\ComponentFactory;

use Core\View\Exception\ComponentNotFoundException;
use Core\View\Interface\ViewComponentInterface;

final class ComponentBag
{
    /**
     * @param array<string, array{name: string, class: class-string<ViewComponentInterface>, static: bool, priority: int, tags: string[], tagged: string[][]}|ComponentProperties> $components
     */
    public function __construct( private array $components = [] ) {}

    /**
     * Gets the service container parameters.
     *
     * @return array<string, ComponentProperties>
     */
    public function all() : array
    {
        foreach ( $this->components as $component => $properties ) {
            if ( \is_array( $properties ) ) {
                $this->components[$component] = new ComponentProperties( ...$properties );
            }
        }
        /** @var array<string, ComponentProperties> */
        return $this->components;
    }

    /**
     * Gets a service container parameter.
     *
     * @param string $component
     *
     * @return ComponentProperties
     * @throws ComponentNotFoundException
     */
    public function get( string $component ) : ComponentProperties
    {
        $properties = $this->components[$component] ?? throw new ComponentNotFoundException( $component );

        if ( $properties instanceof ComponentProperties ) {
            return $properties;
        }

        return $this->components[$component] = new ComponentProperties( ...$properties );
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
