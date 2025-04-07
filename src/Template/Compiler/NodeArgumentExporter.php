<?php

declare(strict_types=1);

namespace Core\View\Template\Compiler;

use Stringable, LogicException;
use function Support\str_starts_with_any;

final class NodeArgumentExporter
{
    private string $value = EMPTY_STRING;

    /**
     * @param class-string $class
     * @param              ...$arguments
     *
     * @return $this
     */
    public function newCall( string $class, ...$arguments ) : self
    {
        return $this
            ->append( '( new ', $class, '( ' )
            ->handleCallArguments( $arguments )
            ->append( ' ))' );
    }

    /**
     * @param class-string    $class
     * @param callable-string $method
     * @param                 ...$args
     *
     * @return $this
     */
    public function staticCall( string $class, string $method, ...$args ) : self
    {
        return $this;
    }

    public function getValue() : string
    {
        return $this->value;
    }

    public function toEcho() : string
    {
        return 'echo '.$this->getValue().';';
    }

    public function append( string ...$value ) : self
    {
        foreach ( $value as $append ) {
            $this->value .= $append;
        }
        return $this;
    }

    public function prepend( string $value ) : self
    {
        $this->value = $value.$this->value;
        return $this;
    }

    private function handleCallArguments( array $arguments ) : self
    {
        foreach ( $arguments as $name => $argument ) {
            if ( \is_string( $name ) ) {
                $this->append( "{$name}: " );
            }
            $this->append( $this->handleArgument( $argument ), ', ' );
        }
        return $this;
    }

    private function handleArgument( mixed $argument ) : string
    {
        if ( \is_string( $argument ) || $argument instanceof Stringable ) {
            $argument = (string) $argument;
            return "'{$argument}'";
        }

        if ( \is_array( $argument ) && \array_filter( $argument, 'is_string' ) ) {
            $string = '[ ';

            foreach ( $argument as $key => $value ) {
                \assert( \is_string( $value ) );
                $key   = \trim( $key, " \t\n\r\0\x0B'" );
                $value = \trim( $value, " \t\n\r\0\x0B'" );
                $string .= "'{$key}' => '{$value}', ";
            }

            return $string.']';
        }

        return __FUNCTION__;
    }

    public static function arguments( array $arguments ) : string
    {
        $export = [];

        foreach ( $arguments as $name => $value ) {
            $argument = \is_string( $name ) ? "'{$name}' =>" : '';
            $argument .= match ( \gettype( $value ) ) {
                'string'  => self::string( $value ),
                'array'   => self::array( $value ),
                'NULL'    => 'null',
                'boolean' => self::boolean( $value ),
                default   => throw new LogicException(
                    'TODO : Handle unknown argument type '.\gettype(
                        $value,
                    ).' for '.NodeArgumentExporter::class,
                ),
            };
            $export[] = $argument;
        }

        $string = PHP_EOL.\implode( ', '.PHP_EOL, $export ).PHP_EOL;

        return "[ {$string} ]";
    }

    public static function string( string $value ) : string
    {
        $value = \trim( $value, " \t\n\r\0\x0B'" );

        if ( ! str_starts_with_any( $value, '$', 'LR\Filters' ) ) {
            $value = "'".\str_replace( "'", "\'", $value )."'";
        }
        return $value;
    }

    public static function array( array $argument ) : string
    {
        // dump( $argument );

        if ( empty( $argument ) ) {
            return '[]';
        }

        $string = ' ['.PHP_EOL;

        foreach ( $argument as $key => $value ) {
            if ( \is_string( $key ) ) {
                $key = "'".\trim( $key, " \t\n\r\0\x0B'" )."'";
            }

            if ( \is_string( $value ) ) {
                $value = NodeArgumentExporter::string( $value );
            }

            if ( \is_array( $value ) ) {
                $value = self::array( $value );
            }

            $string .= "{$key} => {$value},".PHP_EOL;
        }

        return $string.']';
    }

    public static function boolean( bool $bool ) : string
    {
        return $bool ? 'true' : 'false';
    }

    public static function integer( ?int $int ) : string
    {
        return $int === null ? 'null' : (string) $int;
    }

    public static function cacheConstant( ?int $cache ) : string
    {
        static $runtimeCache;
        $runtimeCache['constants'] ??= \array_filter(
            \get_defined_constants( true )['user'],
            static fn( $key ) => \str_starts_with( $key, 'Cache' ),
            ARRAY_FILTER_USE_KEY,
        );

        return (string) $runtimeCache[$cache]
                ??= \array_search( $cache, $runtimeCache['constants'], true ) ?: $cache;
    }
}
