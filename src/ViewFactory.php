<?php

declare(strict_types=1);

namespace Core\View;

use Core\Interface\{ActionInterface, View};
use Core\View\Template\Engine;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ViewFactory implements ActionInterface
{
    /**
     * List of all instantiated View Components and Elements.
     *
     * @var array<string, string[]>
     */
    private array $instantiated = [];

    /**
     * @param Engine                    $engine
     * @param ServiceLocator<Component> $locator
     */
    public function __construct(
        protected readonly Engine         $engine,
        protected readonly ServiceLocator $locator,
    ) {}

    /**
     * This could be used in a `Controller::action(..)` to render any View on-demand.
     *
     * Useful when handling Editor Syntax in a Factory as well.
     *
     * - Each View will have a Template Engine handling rendering.
     * - Output will be cached.
     *
     * @template Render
     *
     * @param class-string<Render> $render
     *
     * @return Render
     */
    public function __invoke( string $render ) : mixed
    {
        if ( ! \is_subclass_of( $render, View::class ) ) {
            throw new InvalidArgumentException( 'Cannot render non-view objects.' );
        }
        return new $render();
    }

    // final public function render( string $view ) : View {}
    //
    // final public function getComponent() : Component {}

    /**
     * @return array<string, string[]>
     */
    final public function getInstantiated() : array
    {
        return $this->instantiated;
    }
}
