<?php

declare(strict_types=1);

namespace Core\View\HTML;

use Core\View\HTML\Attributes\{ClassAttribute, StyleAttribute};
use Stringable, InvalidArgumentException, LogicException;

/**
 * @property-read ClassAttribute                                                                          $class
 * @property-read StyleAttribute                                                                          $style
 * @property-read array{id: ?string, class: string[], style: array<string, string>, ...<string, ?string>} $array
 */
final class Attributes implements Stringable
{
    // /** @var array{id: ?string, class: string[], style: array<string, string>, ...} */
    /** @var array<string, null|array<array-key, string>|bool|string> */
    private array $attributes = [
        'id'    => null,
        'class' => [],
        'style' => [],
    ];

    /**
     * @param array<string, null|array<array-key, string>|bool|string> $attributes
     */
    public function __construct( array $attributes = [] )
    {
        $this->assign( $attributes );
    }

    /**
     * @param string $name
     *
     * @return array<string, string|string[]>|ClassAttribute|StyleAttribute
     */
    public function __get( string $name ) : ClassAttribute|StyleAttribute|array
    {
        return match ( $name ) {
            'class' => $this->handleClasses(),
            'style' => $this->handleStyles(),
            'array' => $this->attributeArray(),
            default => throw new InvalidArgumentException(
                'Warning: Undefined property: '.$this::class."::\${$name}",
            ),
        };
    }

    public function __set( string $name, mixed $value ) : void
    {
        throw new LogicException( $this::class."::\${$name} cannot be dynamically set." );
    }

    /**
     * Assign one or more attributes, clearing any existing attributes.
     *
     * @param array<string, null|array<array-key, string>|string> $attributes
     *
     * @return $this
     */
    public function assign( array $attributes ) : self
    {
        $this->setAttributes( $attributes, true );
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
        if ( \is_string( $attribute ) ) {
            $attribute = [$attribute => $value];
        }

        $this->setAttributes( $attribute );

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
        if ( \is_string( $attribute ) ) {
            $attribute = [$attribute => $value];
        }

        $this->setAttributes( $attribute, true );

        return $this;
    }

    /**
     * @param string  $name
     * @param ?string $value
     *
     * @return bool
     */
    public function has( string $name, ?string $value = null ) : bool
    {
        // Get attribute by $name, or false if unset
        $attribute = $this->attributes[$name] ?? false;

        // Check against value if requested
        if ( $value ) {
            return $attribute === $value;
        }

        // If the attribute is anything but false, consider it set
        return false !== $attribute;
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

    /**
     * Remove all attributes.
     *
     * @return $this
     */
    public function clear() : self
    {
        $this->attributes = [
            'id'    => null,
            'class' => [],
            'style' => [],
        ];
        return $this;
    }

    /**
     * Return a string of fully resolved attributes.
     *
     * Will be prefixed with a single whitespace unless empty.
     *
     * @return string
     */
    public function __toString() : string
    {
        $attributes = \implode( ' ', $this->parseAttributes() );
        return $attributes ? " {$attributes}" : '';
    }

    /**
     * @param array<string, null|array<array-key, string>|bool|string> $attributes
     * @param bool                                                     $override
     */
    private function setAttributes( array $attributes, bool $override = false ) : void
    {
        foreach ( $attributes as $name => $value ) {
            $name = $this->name( $name );

            if ( 'class' == $name || 'classes' == $name ) {
                \assert(
                    \is_array( $value ) || \is_string( $value ),
                    "Attribute '{$name}' can only be string|string[]. ".\gettype( $value ).' provided.',
                );
                if ( $override ) {
                    $this->handleClasses()->clear();
                }
                $this->handleClasses()->add( $value );

                continue;
            }

            if ( 'style' == $name || 'styles' == $name ) {
                \assert(
                    \is_array( $value ) || \is_string( $value ),
                    "Attribute '{$name}' can only be string|array<string,string>. ".\gettype(
                        $value,
                    ).' provided.',
                );
                if ( $override ) {
                    $this->handleStyles()->clear();
                }
                $this->handleStyles()->add( $value );

                continue;
            }

            if ( false === $override && $this->has( $name ) ) {
                continue;
            }

            \assert(
                \is_string( $value ) || \is_null( $value ) || \is_bool( $value ),
                "Attribute '{$name}' can only be null|string|bool. ".\gettype( $value ).' provided.',
            );

            $this->attributes[$name] = $value;
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes() : array
    {
        $attributes = [];

        foreach ( $this->attributes as $attribute => $value ) {
            // Attribute value formatting
            $value = match ( $attribute ) {
                'class' => ClassAttribute::resolve( (array) $value ),
                'style' => StyleAttribute::resolve( (array) $value ),
                default => $value,
            };

            // Convert types to string
            $value = match ( \gettype( $value ) ) {
                'string'  => $value,
                'boolean' => $value ? 'true' : 'false',
                'array'   => \implode( ' ', \array_filter( $value ) ),
                'object'  => \method_exists( $value, '__toString' ) ? $value->__toString() : null,
                'NULL'    => null,
                default   => (string) $value,
            };

            if ( \is_null( $value ) ) {
                continue;
            }

            if ( $value ) {
                $attributes[$attribute] = "{$attribute}=\"{$value}\"";
            }
            else {
                $attributes[$attribute] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * Return a normalized, but unprocessed version of {@see self::$attributes}.
     *
     * @return array<string, string|string[]>
     */
    private function attributeArray() : array
    {
        $attributes = \array_filter( $this->attributes );
        if ( isset( $attributes['class'] ) && \is_array( $attributes['class'] ) ) {
            $attributes['class'] = \array_values( $attributes['class'] );
        }
        return $attributes;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    private function name( int|string $string ) : string
    {
        \assert(
            \is_string( $string ),
            'Attribute names must be strings, '.\gettype( $string ).' provided.',
        );

        $string = \strtolower( \trim( $string ) );

        $string = (string) \preg_replace( '/[^a-z0-9-]+/i', '-', $string );

        return \trim( $string, '-' );
    }

    private function handleClasses() : ClassAttribute
    {
        \assert( \is_array( $this->attributes['class'] ) );
        return ClassAttribute::byReference( $this->attributes['class'], $this );
    }

    private function handleStyles() : StyleAttribute
    {
        \assert( \is_array( $this->attributes['style'] ) );
        return StyleAttribute::byReference( $this->attributes['style'], $this );
    }
}
