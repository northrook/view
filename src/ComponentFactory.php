<?php

declare(strict_types=1);

namespace Core\View;

use Core\Autowire\{Logger, Profiler};
use Core\View\Component\Properties;
use Throwable;
use Core\View\ComponentFactory\{
    ViewComponent,
    ComponentBag,
};
use Core\View\Template\{
    Engine,
};
use Symfony\Component\DependencyInjection\ServiceLocator;
use Core\Interface\{
    LazyService,
    Loggable,
};
use Core\View\Exception\{ComponentNotFoundException, ViewException};
use InvalidArgumentException;
use function Support\str_start;

class ComponentFactory implements LazyService, Loggable
{
    use Profiler, Logger;

    public const string PROPERTY = 'view';

    /**
     * `[ component.name => uniqueId ]`.
     *
     * @var array<string, array<int, string>>
     */
    private array $instantiated = [];

    /**
     * @param Engine                    $engine
     * @param ServiceLocator<Component> $locator
     * @param ComponentBag              $components
     * @param array<string, string>     $tags
     */
    public function __construct(
        protected readonly Engine         $engine,
        protected readonly ServiceLocator $locator,
        protected readonly ComponentBag   $components,
        protected readonly array          $tags = [],
    ) {}

    /**
     * @param string               $__component
     * @param array<string, mixed> $__attributes
     * @param array<string, mixed> $arguments
     *
     * @return Component
     */
    final public function render(
        string   $__component,
        array    $__attributes = [],
        mixed ...$arguments,
    ) : Component {
        $this->profilerStart( $__component );

        $component = $this->getComponent( $__component );

        $uniqueId = null;
        // TODO : Retrieve static uniqueId if available
        // TODO : Optional callback filter for $arguments by uniqueId

        /** @var array<string, mixed> $arguments */
        $arguments = ['__attributes' => $__attributes, ...$arguments];
        $component->create( $arguments, $uniqueId );

        $this->instantiated[$component->name][] = $component->uniqueId;

        $this->profilerStop( $__component );

        return $component;
    }

    /**
     * @template Render of Element
     *
     * @param class-string<Render> $render
     * @param mixed                ...$arguments
     *
     * @return Render
     */
    final public function element(
        string   $render,
        mixed ...$arguments,
    ) : mixed {
        try {
            if ( ! \is_subclass_of( $render, Element::class ) ) {
                throw new InvalidArgumentException( 'Cannot render non-view objects.' );
            }
            // @phpstan-ignore-next-line
            return new $render( ...$arguments );
        }
        catch ( Throwable $exception ) {
            throw new ViewException( $render, previous : $exception );
        }
    }

    /**
     * Begin the Build process of a component.
     *
     * @param class-string|string $component
     *
     * @return Component
     */
    final public function getComponent( string $component ) : Component
    {
        $serviceID = $this->getComponentServiceID( (string) $component );

        if ( ! $serviceID ) {
            $message = "The component '{$serviceID}'  does not exist.";
            throw new InvalidArgumentException( $message );
        }

        if ( ! $this->locator->has( $serviceID ) ) {
            throw new ComponentNotFoundException( $serviceID, 'Not found in the Component Container.' );
        }

        $viewComponent = $this->locator->get( $serviceID );

        return clone $viewComponent;
    }

    final public function getComponentProperties( string $component ) : Properties
    {
        $component = $this->getComponentServiceID( $component );

        if ( ! $component || ! $this->components->has( $component ) ) {
            $message = "The component '{$component}' not found in the Component Container.";
            throw new ComponentNotFoundException( $component, $message );
        }

        return $this->components->get( $component );
    }

    /**
     * @param string $from class, name, or tag
     *
     * @return string
     */
    final public function getComponentServiceID( string $from ) : string
    {
        if (
            \str_contains( $from, '\\' )
            && \class_exists( $from )
            && \is_subclass_of( $from, Component::class )
        ) {
            return ViewComponent::from( $from )->serviceId;
        }

        $from = str_start( $from, ViewComponent::PREFIX );

        // If the provided $value matches an array name, return it
        if ( $this->components->has( $from ) ) {
            return $from;
        }

        $tag = Properties::tag( $from );

        return $this->tags[$tag] ?? throw new InvalidArgumentException(
            "Unable to resolve Component serviceID from '{$from}'.",
        );
    }

    /**
     * @return array<string, string>
     */
    final public function getTags() : array
    {
        return $this->tags;
    }

    /**
     * @return array<string, array<int, string>>
     */
    final public function getInstantiated() : array
    {
        return $this->instantiated;
    }

    /**
     * @return Properties[]
     */
    final public function getRegisteredComponents() : array
    {
        return $this->components->all();
    }

    /**
     * Check if the provided string matches any {@see ComponentFactory::$components}.
     *
     * @param string $component
     *
     * @return bool
     */
    final public function hasComponent( string $component ) : bool
    {
        return $this->components->has( $component );
    }

    /**
     * Check if the provided string matches any {@see ComponentFactory::$tags}.
     *
     * @param string $tag
     *
     * @return bool
     */
    final public function hasTag( string $tag ) : bool
    {
        return \array_key_exists( $tag, $this->tags );
    }
}
