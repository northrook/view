<?php

declare(strict_types=1);

namespace Core\View\Interface;

use Stringable;

interface NodeInterface extends Stringable
{
    /**
     * Return a {@see ViewInterface} as {@see Stringable} or `string`.
     *
     * Pass `true` to return as `string`.
     *
     * @param bool $string [false]
     *
     * @return Stringable
     */
    public function getNode( bool $string = false ) : string|Stringable;
}
