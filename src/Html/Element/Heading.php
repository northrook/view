<?php

declare(strict_types=1);

namespace Core\View\Html\Element;

use Core\View\Html\{Attributes, Element};
use Stringable;

final class Heading extends Element
{
    /**
     * @param int<1, 6>                                                              $level
     * @param array<array-key, null|array<array-key, string>|bool|string>|Attributes $attributes
     * @param string|Stringable                                                      ...$content
     */
    public function __construct(
        int                  $level,
        array|Attributes     $attributes = [],
        string|Stringable ...$content,
    ) {
        parent::__construct( 'h'.(string) $level, $attributes, ...$content );
    }
}
