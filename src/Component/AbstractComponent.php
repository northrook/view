<?php

declare(strict_types=1);

namespace Core\View\Component;

use Core\View\Html\Tag;
use Core\View\Interface\ViewComponentInterface;
use Core\View\Template\View;
use Override;

abstract class AbstractComponent extends View implements ViewComponentInterface
{
    public readonly string $name;

    public readonly string $uniqueID;

    #[Override]
    public function __toString() : string
    {
        return $this->render();
    }

    final public function create(
        array   $arguments,
        array   $promote = [],
        ?string $uniqueId = null,
    ) : ViewComponentInterface {
        $this->promoteTaggedProperties( $arguments, $promote );
        $this->maybeAssignTag( $arguments );

        return $this;
    }

    /**
     * Internally or using the {@see \Core\View\TemplateEngine}.
     *
     * @return string
     */
    abstract protected function render() : string;

    /**
     * @param array<string, mixed> $arguments
     *
     * @return void
     */
    private function maybeAssignTag( array &$arguments ) : void
    {
        if ( ! ( isset( $arguments['tag'], $this->tag ) && $this->tag instanceof Tag ) ) {
            return;
        }

        \assert( \is_string( $arguments['tag'] ) );

        $this->tag->set( $arguments['tag'] );

        unset( $arguments['tag'] );
    }

    /**
     * @param array<string, mixed>     $arguments
     * @param array<string, ?string[]> $promote
     *
     * @return void
     */
    private function promoteTaggedProperties( array &$arguments, array $promote = [] ) : void
    {
        if ( ! isset( $arguments['tag'] ) ) {
            return;
        }

        \assert( \is_string( $arguments['tag'] ) );

        /** @var array<int, string> $exploded */
        $exploded         = \explode( ':', $arguments['tag'] );
        $arguments['tag'] = $exploded[0];

        $promote = $promote[$arguments['tag']] ?? null;

        foreach ( $exploded as $position => $tag ) {
            if ( $promote && ( $promote[$position] ?? false ) ) {
                $arguments[$promote[$position]] = $tag;
                unset( $arguments[$position] );

                continue;
            }
            if ( $position ) {
                $arguments[$position] = $tag;
            }
        }
    }
}
