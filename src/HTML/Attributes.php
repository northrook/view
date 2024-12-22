<?php

namespace Core\View\HTML;

use Core\View\HTML\Attributes\{Classes, Styles};
use Stringable;
use InvalidArgumentException;

/**
 * @property-read Classes $class
 * @property-read Styles  $style
 */
final class Attributes implements Stringable
{
    /** @var array{id: ?string, class: string[], style: array<string, string>, ...<string, ?string>} */
    private array $attributes = [
        'id'    => null,
        'class' => [],
        'style' => [],
    ];

    /**
     * @param array<string, null|array<array-key, ?string>|string> $attributes
     */
    public function __construct( array $attributes = [] )
    {
        $this->assign( $attributes );
    }

    public function __get( string $name )
    {
        return match ( $name ) {
            'class' => $this->handleClasses(),
            'style' => $this->handleStyles(),
            default => throw new InvalidArgumentException(),
        };
    }

    /**
     * Assign one or more attributes, clearing any existing attributes.
     *
     * @param array<string, null|array<array-key, ?string>|string> $attributes
     *
     * @return $this
     */
    public function assign( array $attributes ) : self
    {
        foreach ( $attributes as $name => $value ) {
            $name = $this->name( $name );

            if ( \is_array( $value ) ) {
                match ( $name ) {
                    'class', 'classes' => $this->handleClasses()->add( $value ),
                    'style', 'styles' => $this->handleStyles()->add( $value ),
                    default => throw new InvalidArgumentException(
                        "Attribute '{$name}' is invalid: Only 'class' and 'style' accept array values.",
                    ),
                };
            }
            else {
                $this->attributes[$name] = $value;
            }
        }

        return $this;
    }

    /**
     * Add new attributes.
     *
     * - Will not override existing attributes.
     * - Boolean `$value` set as `true|false`.
     * - Only `class` and `style` accept `array` values.
     *
     * @param null|array<string, null|array<array-key, null|bool|string>|string>|string $attribute
     * @param null|array<array-key, null|bool|string>|bool|string                       $value
     *
     * @return $this
     */
    public function add(
        string|array           $attribute = null,
        string|array|bool|null $value = null,
    ) : self {
        return $this;
    }

    /**
     * Set attributes.
     *
     * - Overrides existing attributes.
     * - Boolean `$value` set as `true|false`.
     * - Only `class` and `style` accept `array` values.
     *
     * @param null|array<string, null|array<array-key, null|bool|string>|string>|string $attribute
     * @param null|array<array-key, null|bool|string>|bool|string                       $value
     *
     * @return $this
     */
    public function set(
        string|array           $attribute = null,
        string|array|bool|null $value = null,
    ) : self {
        return $this;
    }

    /**
     * Merges one or more attributes.
     *
     * @param array<string, null|array<array-key, ?string>|string>|Attributes $attributes
     *
     * @return $this
     */
    public function merge( Attributes|array $attributes ) : self
    {
        return $this;
    }

    public function clear() : self
    {
        $this->attributes = [
            'id'    => null,
            'class' => [],
            'style' => [],
        ];
        return $this;
    }

    public function __toString() : string
    {
        return '';
    }

    private function name( string $string ) : string
    {
        $string = \strtolower( \trim( $string ) );

        $string = (string) \preg_replace( '/[^a-z0-9-]+/i', '-', $string );

        return \trim( $string, '-' );
    }

    private function handleClasses() : Classes
    {
        return new Classes( $this->attributes['class'] );
    }

    private function handleStyles() : Styles
    {
        return new Styles( $this->attributes['style'] );
    }
}
