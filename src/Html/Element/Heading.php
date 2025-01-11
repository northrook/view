<?php

declare(strict_types=1);

namespace Core\View\Html\Element;

use Core\View\Html\{Attributes, Element};
use Stringable;

final class Heading extends Element
{
    /**
     * @param int<1, 6>                                                              $level
     * @param array<array-key, null|array<array-key, string>|bool|string>|Attributes $attributes
     * @param string|Stringable                                                      ...$content
     */
    public function __construct(
        int                  $level,
        array|Attributes     $attributes = [],
        string|Stringable ...$content,
    ) {
        parent::__construct( 'h'.(string) $level, $attributes, ...$content );
    }

    /**
     * Expects a valid level string or int.
     *
     * - `h1-h6`
     * - `1-6`
     *
     * Uses {@see assert} to validate before returning.
     *
     * @param int|string $level
     *
     * @return int<1, 6>
     */
    public static function validLevel( string|int $level ) : int
    {
        if ( \is_string( $level ) ) {
            \assert(
                ( \strlen( $level ) === 1 || \strlen( $level ) === 2 ),
                "Heading levels must be one of h1, h2, etc. or 1, 2, etc; '{$level}' provided.",
            );

            \assert(
                ( \strlen( $level ) === 2 && \strtolower( $level[0] ) === 'h' ),
                "Heading levels must be one of h1, h2, etc; '{$level}' provided.",
            );

            $level = (int) \ltrim( $level, 'h' );
        }

        \assert(
            \in_array( $level, [1, 2, 3, 4, 5, 6], true ),
            "Heading level must be between 1 and 6. '{$level}' provided.",
        );
        return $level;
    }
}
