<?php

declare(strict_types=1);

namespace Core\View\Component;

use Core\View\Html\Element;
use Northrook\Logger\Log;
use Stringable;

/**
 * @internal
 */
final class ComponentContent implements Stringable
{
    /** @var array<array-key, string> */
    protected array $content;

    /**
     * @param array<array-key, mixed> $ast
     */
    public function __construct( public readonly array $ast )
    {
        /** @var array<array-key, string> $ast */
        $this->content = $ast;
        $this->parseSyntaxTree();
    }

    public function __toString() : string
    {
        return $this->getString();
    }

    /**
     * @param string $separator
     *
     * @return array<array-key, string>
     */
    public function getString( string $separator = '' ) : string
    {
        return \implode( $separator, $this->content );
    }

    /**
     * @return array<array-key, string>
     */
    public function getArray() : array
    {
        return $this->content;
    }

    /**
     * @internal
     *
     * String is returned during recursion.
     * Array returned upon completion.
     *
     * @param null|array<array-key, mixed> $array 🔁 recursive
     * @param null|int|string              $key   🔂 recursive
     *
     * @return array<array-key, mixed>|string
     */
    private function parseSyntaxTree( ?array $array = null, null|string|int $key = null ) : string|array
    {
        // Grab $this->content for initial loop
        $array ??= $this->content;
        $tag        = null;
        $attributes = [];

        // If $key is string, this iteration is an element
        if ( \is_string( $key ) ) {
            $tag        = \strrchr( $key, ':', true );
            $attributes = $array['attributes'];
            $array      = $array['content'];

            // if ( \str_ends_with( $tag, 'icon' ) && $get = $attributes['get'] ?? null ) {
            //     unset( $attributes['get'] );
            //     return (string) new Icon( $tag, $get, $attributes );
            // }
        }

        $content = [];

        \assert( \is_array( $array ) );

        foreach ( $array as $elementKey => $value ) {
            $elementKey = $this->nodeKey( $elementKey, \gettype( $value ) );

            if ( \is_array( $value ) ) {
                $content[$elementKey] = $this->parseSyntaxTree( $value, $elementKey );
            }
            elseif ( \is_string( $value ) ) {
                self::appendByReference( $value, $content );
            }
            else {
                Log::warning(
                    '{method} encountered unexpected value type {type}.',
                    ['method' => __METHOD__, 'type' => \gettype( $value )],
                );
            }
        }

        /** @var string[] $content */
        if ( $tag ) {
            $element = new Element( $tag, $attributes, ...$content );

            return $element->__toString();
        }

        return $this->content = $content;
    }

    /**
     * @internal
     *
     * @param int|string $node
     * @param string     $valueType
     *
     * @return int|string
     */
    private function nodeKey( string|int $node, string $valueType ) : string|int
    {
        if ( \is_int( $node ) ) {
            return $node;
        }

        $index = \strrpos( $node, ':' );

        // Treat parsed string variables as simple strings
        if ( false !== $index && 'string' === $valueType && \str_starts_with( $node, '$' ) ) {
            return (int) \substr( $node, $index + 1 );
        }

        return $node;
    }

    /**
     * @param string                  $string
     * @param array<array-key, mixed> $content
     *
     * @return void
     */
    private function appendByReference( string $string, array &$content ) : void
    {
        // Trim $value, and bail early if empty
        if ( ! $string = \trim( $string ) ) {
            return;
        }

        $lastIndex = \array_key_last( $content );
        $index     = \count( $content );

        if ( \is_int( $lastIndex ) ) {
            if ( $index > 0 ) {
                $index--;
            }
        }

        if ( isset( $content[$index] ) && \is_string( $content[$index] ) ) {
            $content[$index] .= " {$string}";
        }
        else {
            $content[$index] = $string;
        }
    }
}
