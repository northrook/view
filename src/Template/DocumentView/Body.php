<?php

declare(strict_types=1);

namespace Core\View\Template\DocumentView;

use Core\View\Html\{Attributes, Element};
use Core\Symfony\DependencyInjection\Autodiscover;

/**
 * @internal
 * @author Martin Nielsen <mn@northrook.com>
 */
#[Autodiscover]
final class Body extends Element
{
    public function __construct( array|Attributes $attributes = [] )
    {
        parent::__construct( 'body', $attributes, innerHtml : '' );
    }
}
