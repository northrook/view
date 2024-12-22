<?php

namespace Core\View\HTML\Attributes;

use const Support\AUTO;
use Stringable;

final class Classes implements Stringable
{
    public const int APPEND = 1;

    public const int PREPEND = 2;

    public function __construct( private array &$classes )
    {
    }

    /**
     * @param ?string|?string[]               $class
     * @param null|self::APPEND|self::PREPEND $order
     *
     * @return $this
     */
    public function add( null|string|array $class, ?int $order = AUTO ) : self
    {
        $add = \array_filter( (array) $class );

        foreach ( $add as $value ) {
            $value = \strtolower( \trim( $value ) );

            if ( ! $order ) {
                $this->classes[$value] = $value;

                continue;
            }
            unset( $this->classes[$value] );

            if ( self::PREPEND === $order ) {
                $this->classes = [[$value => $value], ...$this->classes];
            }
            else {
                $this->classes[$value] = $value;
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
        return $this->classes[$class] ?? false;
    }

    public function get( string $class ) : ?string
    {
        return $this->classes[$class] ?? null;
    }

    /**
     * @return string[]
     */
    public function getAll() : array
    {
        return $this->parse()->classes;
    }

    public function __toString() : string
    {
        return \implode( ' ', $this->parse()->classes );
    }

    public function clear() : self
    {
        $this->classes = [];
        return $this;
    }

    public function remove( string ...$class ) : self
    {
        foreach ( (array) $class as $value ) {
            $value = \strtolower( \trim( $value ) );
            unset( $this->classes[$value] );
        }
        return $this;
    }

    private function parse() : self
    {
        // TODO : Order of classes
        return $this;
    }
}
