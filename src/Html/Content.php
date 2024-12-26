<?php

declare(strict_types=1);

namespace Core\View\Html;

use Stringable;

final class Content implements Stringable
{
    /** @var string[]|Stringable[] */
    private array $content = [];

    public function __construct( string|Stringable ...$content )
    {
        $this->content = $content;
    }

    public function __toString() : string
    {
        return \implode( '', $this->content );
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
}
