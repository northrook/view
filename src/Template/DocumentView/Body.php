<?php

declare(strict_types=1);

namespace Core\View\Template\DocumentView;

use Core\View\Html\{Element};

/**
 * @internal
 * @author Martin Nielsen <mn@northrook.com>
 */
final class Body extends Element
{
    public function __construct()
    {
        parent::__construct( 'body', innerHtml : '' );
    }
}
