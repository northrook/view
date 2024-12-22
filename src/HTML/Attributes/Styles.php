<?php

namespace Core\View\HTML\Attributes;

use const Support\AUTO;
use Stringable;

/**
 * @internal
 */
final class Styles implements Stringable
{
    public const int APPEND = 1;

    public const int PREPEND = 2;

    public function __construct( private array &$styles )
    {
    }

    /**
     * @param ?string|?string[]               $style
     * @param null|self::APPEND|self::PREPEND $order
     *
     * @return $this
     */
    public function add( null|string|array $style, ?int $order = AUTO ) : self
    {
        foreach ( (array) $style as $value ) {
            $value = \strtolower( \trim( $value ) );

            if ( ! $order ) {
                $this->styles[$value] = $value;

                continue;
            }
            unset( $this->styles[$value] );

            if ( self::PREPEND === $order ) {
                $this->styles = [[$value => $value], ...$this->styles];
            }
            else {
                $this->styles[$value] = $value;
            }
        }
        return $this;
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function has( string $class ) : bool
    {
        return $this->styles[$class] ?? false;
    }

    public function get( string $class ) : ?string
    {
        return $this->styles[$class] ?? null;
    }

    /**
     * @return string[]
     */
    public function getAll() : array
    {
        return $this->parse()->styles;
    }

    public function __toString() : string
    {
        return \implode( ' ', $this->parse()->styles );
    }

    public function clear() : self
    {
        $this->styles = [];
        return $this;
    }

    public function remove( string ...$class ) : self
    {
        foreach ( (array) $class as $value ) {
            $value = \strtolower( \trim( $value ) );
            unset( $this->styles[$value] );
        }
        return $this;
    }

    private function parse() : self
    {
        // TODO : Order of classes
        return $this;
    }
}
