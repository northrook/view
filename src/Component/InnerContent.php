<?php

declare(strict_types=1);

namespace Core\View\Component;

use Core\View\Template\ViewContent;

/**
 * @phpstan-require-extends \Core\View\Component\AbstractComponent
 */
trait InnerContent
{
    protected readonly ViewContent $innerContent;

    private function assignInnerContent( array $content ) : void
    {
        $this->innerContent = ViewContent::fromAST( $content );
    }
}
