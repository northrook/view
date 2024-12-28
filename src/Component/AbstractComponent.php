<?php

declare(strict_types=1);

namespace Core\View\Component;

use Core\View\Html\{Attributes, Tag};
use Core\View\Interface\ViewComponentInterface;
use Core\View\Template\View;
use Northrook\Logger\Log;
use Override;

abstract class AbstractComponent extends View implements ViewComponentInterface
{
    public readonly string $name;

    public readonly string $uniqueID;

    public readonly Attributes $attributes;

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
        $this->componentUniqueID( $uniqueId ?? \serialize( [$arguments] ) );
        $this->promoteTaggedProperties( $arguments, $promote );
        $this->maybeAssignTag( $arguments );
        $this->assignAttributes( $arguments );

        if ( isset( $arguments['content'] ) ) {
            dump( $arguments );
            unset( $arguments['content'] );
        }

        foreach ( $arguments as $property => $value ) {
            if ( \property_exists( $this, $property ) && ! isset( $this->{$property} ) ) {
                $this->{$property} = $value;

                continue;
            }

            if ( \is_string( $value ) && \method_exists( $this, $value ) ) {
                $this->{$value}();
            }

            Log::error(
                'The {component} was provided with undefined property {property}.',
                ['component' => $this->name, 'property' => $property],
            );
        }

        return $this;
    }

    /**
     * Internally or using the {@see \Core\View\TemplateEngine}.
     *
     * @return string
     */
    abstract protected function render() : string;

    final protected function setAttributes( array $attributes ) : void
    {
        ( $this->attributes ??= new Attributes() )->set( $attributes );
    }

    private function componentUniqueID( string $set ) : void
    {
        // Set a predefined hash
        if ( \strlen( $set ) === 16
             && \ctype_alnum( $set )
             && \strtolower( $set ) === $set
        ) {
            $this->uniqueID = $set;
            return;
        }
        $this->uniqueID = \hash( algo : 'xxh3', data : $set );
    }

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
     * @param array<string, mixed> $arguments
     *
     * @return void
     */
    private function assignAttributes( array &$arguments ) : void
    {
        if ( ! isset( $arguments['attributes'] ) ) {
            return;
        }

        \assert( \is_array( $arguments['attributes'] ) );

        $this->setAttributes( $arguments['attributes'] );

        unset( $arguments['attributes'] );
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
