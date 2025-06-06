<?php

declare(strict_types=1);

namespace Core\View\Template;

use Core\View\IconProviderService;
use Core\View\Template\Compiler\{CompilerExtension, Node};
use Core\View\Template\Compiler\Nodes\Html\ElementNode;
use Core\View\Template\Compiler\Nodes\IconProviderNode;

final class IconProviderExtension extends CompilerExtension
{
    public function __construct( private readonly IconProviderService $provider ) {}

    public function getProviders() : array
    {
        return ['icon' => $this->provider]; // get( $get, $fallback, ... $attributes );
    }

    public function getTags() : array
    {
        return [
            'icon' => [IconProviderNode::class, 'create'],
            'svg'  => [IconProviderNode::class, 'create'],
        ];
    }

    protected function conditions( ElementNode $node ) : bool
    {
        return true;
    }

    protected function node( ElementNode $node ) : Node
    {
        return $node;
    }
}
