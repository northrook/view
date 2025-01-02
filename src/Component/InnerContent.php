<?php

declare(strict_types=1);

namespace Core\View\Component;

/**
 * @phpstan-require-extends \Core\View\Component\AbstractComponent
 */
trait InnerContent
{
    protected readonly ComponentContent $innerContent;

    protected function assignInnerContent( array $content ) : void
    {
        $this->innerContent = new ComponentContent( $content );
    }
}
