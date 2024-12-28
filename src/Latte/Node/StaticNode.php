<?php

declare(strict_types=1);

namespace Core\View\Latte\Node;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use Override;

final class StaticNode extends TextNode
{
    #[Override]
    public function print( PrintContext $context ) : string
    {
        // $this->content = (string) new HtmlMinifier( $this->content );
        return parent::print( $context ); // TODO: Change the autogenerated stub
    }
}