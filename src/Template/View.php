<?php

declare(strict_types=1);

namespace Core\View\Template;

use Core\Interface\ViewInterface;
use Latte\Runtime as Latte;
use Stringable;

abstract class View implements ViewInterface
{
    /**
     * Return a {@see ViewInterface} as {@see Stringable} or `string`.
     *
     * Pass `true` to return as `string`.
     *
     * @param bool $string [false]
     *
     * @return ($string is true ? string : Stringable)
     */
    final public function getHtml( bool $string = false ) : string|Stringable
    {
        if ( \class_exists( Latte\Html::class ) ) {
            return new Latte\Html( $this->__toString() );
        }
        return $string ? $this->__toString() : $this;
    }
}
