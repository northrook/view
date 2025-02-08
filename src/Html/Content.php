<?php

declare(strict_types=1);

namespace Core\View\Html;

use JetBrains\PhpStorm\Deprecated;
use Stringable;
use Support\{Normalize};

#[Deprecated]
final class Content implements Stringable
{
    /** @var array<array-key, null|string|Stringable> */
    private array $content;

    public function __construct( null|string|Stringable ...$content )
    {
        $this->content = $content;
    }

    public function __toString() : string
    {
        return \implode( PHP_EOL, $this->content );
    }

    public function set( string $key, string|Stringable $value ) : void
    {
        $this->content[$key] = $value;
    }

    public function prepend( null|string|Stringable ...$content ) : void
    {
        foreach ( $content as $item ) {
            \array_unshift( $this->content, (string) $item );
        }
    }

    public function append( null|string|Stringable ...$content ) : void
    {
        foreach ( $content as $item ) {
            $this->content[] = (string) $item;
        }
    }

    public function getString( string $separator = '' ) : string
    {
        return \implode( $separator, $this->content );
    }

    /**
     * @return null[]|string[]|Stringable[]
     */
    public function getArray() : array
    {
        return $this->content;
    }

    /**
     * @param bool $normalize
     *
     * @return string
     */
    public function getTextContent( bool $normalize = true ) : string
    {
        $textContent = \strip_tags( \implode( ' ', $this->content ) );

        return $normalize ? Normalize::whitespace( $textContent ) : $textContent;
    }
}
