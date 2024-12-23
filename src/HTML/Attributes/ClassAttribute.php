<?php

declare(strict_types=1);

namespace Core\View\HTML\Attributes;

use Core\View\HTML\Attributes;
use ValueError;

/**
 * @internal
 */
final class ClassAttribute
{
    /**
     * @param array<string, string> $class
     * @param Attributes            $return
     */
    private function __construct(
        private array &               $class,
        private readonly Attributes $return,
    ) {}

    /**
     * @param string[]   $data
     * @param Attributes $return
     *
     * @return self
     */
    public static function byReference( mixed &$data, Attributes $return ) : self
    {
        return new self( $data, $return );
    }

    /**
     * @param string[] $array
     *
     * @return string
     */
    public static function resolve( array $array ) : string
    {
        return \implode( ' ', \array_filter( $array ) );
    }

    /**
     * @param null|string|string[] $class
     * @param bool                 $prepend
     * @param bool                 $append
     *
     * @return Attributes
     */
    public function add( null|string|array $class, bool $prepend = false, bool $append = false ) : Attributes
    {
        // Cast and filter to array of values
        foreach ( \array_filter( (array) $class ) as $value ) {
            //
            // Normalize, and skip if empty
            if ( ! $selector = \strtolower( \trim( $value ) ) ) {
                continue;
            }

            if ( \ctype_digit( $selector[0] ) ) {
                throw new ValueError( 'CSS class selectors cannot start with a number.' );
            }

            // Parse strings with multiple classes
            if ( \str_contains( $selector, ' ' ) ) {
                $selector = \explode( ' ', \str_replace( ',', ' ', $selector ) );
                $this->add( $selector, $prepend, $append );

                continue;
            }

            // Append by default
            if ( ! $prepend && ! $append ) {
                $this->class[$selector] = $selector;

                continue;
            }

            // Remove the relevant $selector if we are prepended or appending
            unset( $this->class[$selector] );

            // Place the $selector at the start
            if ( $prepend ) {
                $this->class = [...[$selector => $selector], ...$this->class];
            }
            // Append at the end, even if it was set previously
            else {
                $this->class[$selector] = $selector;
            }
        }

        return $this->return;
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function has( string $class ) : bool
    {
        return \array_key_exists( $class, $this->class );
    }

    public function get( string $class ) : ?string
    {
        return $this->class[$class] ?? null;
    }

    /**
     * @return string[]
     */
    public function getAll() : array
    {
        return $this->class;
    }

    public function clear() : Attributes
    {
        $this->class = [];
        return $this->return;
    }

    public function remove( string ...$class ) : Attributes
    {
        foreach ( $class as $value ) {
            $value = \strtolower( \trim( $value ) );
            unset( $this->class[$value] );
        }
        return $this->return;
    }
}
