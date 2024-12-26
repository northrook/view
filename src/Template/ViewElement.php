<?php

declare(strict_types=1);

namespace Core\View\Template;

use Latte\Runtime as Latte;
use Core\View\Html\Element;
use Core\View\Interface\ViewInterface;
use Stringable;

class ViewElement extends Element implements ViewInterface
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
