<?php

declare(strict_types=1);

namespace Core\View;

use Core\Interface\{ActionInterface, View};
use InvalidArgumentException;

class ViewFactory implements ActionInterface
{
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
}
