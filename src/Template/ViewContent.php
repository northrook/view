<?php

declare(strict_types=1);

namespace Core\View\Template;

use Core\View\Element;
use Override;

/*
 Renders internal AST syntax:
 - toString
 - as Element tree
 - as top layer array<line,htmlString>
 */

final class ViewContent extends View
{
    protected array $content = [];

    public function __construct() {}

    #[Override]
    public function __toString() : string
    {
        return \implode( '', $this->content );
    }

    /**
     * @param array<array-key, mixed> $ast
     * @param bool                    $parse
     *
     * @return ViewContent
     */
    public static function fromAST( array $ast, bool $parse = true ) : ViewContent
    {
        $view = new ViewContent();

        $view->content = $ast;

        if ( $parse ) {
            $view->parseSyntaxTree();
        }

        return $view;
    }

    public function parse() : self
    {
        $this->parseSyntaxTree();

        return $this;
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

        foreach ( $array as $elementKey => $value ) {
            $elementKey = $this->nodeKey( $elementKey, \gettype( $value ) );

            if ( \is_array( $value ) ) {
                $content[$elementKey] = $this->parseSyntaxTree( $value, $elementKey );
            }
            else {
                self::appendByReference( $value, $content );
            }
        }

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
            return (int) \substr( $node, $index++ );
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

// /**
//  * @param null|array<array-key, string|Stringable>|string|Stringable $content
//  *
//  * @return void
//  */
// public function setContent( null|Stringable|string|array $content = null ) : void
// {
//     if ( ! $content ) {
//         return;
//     }
//
//     if ( \is_array( $content ) ) {
//         foreach ( $content as $key => $value ) {
//             $value = match ( true ) {
//                 $value instanceof Stringable,
//                 \is_string( $value ),
//                 \is_int( $value ),
//                 \is_float( $value ), => (string) $value,
//                 default => false,
//             };
//
//             if ( false === $value ) {
//                 unset( $content[$key] );
//             }
//             else {
//                 $this->{$content}[$key] = $value;
//             }
//         }
//
//         $content = \implode( '', $content );
//     }
// }
