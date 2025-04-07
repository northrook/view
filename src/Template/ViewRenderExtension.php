<?php

namespace Core\View\Template;

use Core\View\{Template\Compiler\CompilerExtension, ViewFactory};
use Core\View\Template\Compiler\Node;
use Core\View\Template\Compiler\Nodes\Html\ElementNode;

final class ViewRenderExtension extends CompilerExtension
{
    /** @var array<string, string> */
    private array $matchTags;

    public function __construct( private readonly ViewFactory $factory ) {}

    public function getProviders() : array
    {
        return [$this->factory::PROPERTY => $this->factory]; // render( $component, $arguments );
    }

    protected function conditions( ElementNode $node ) : bool
    {
        return true;
    }

    protected function node( ElementNode $node ) : Node
    {
        if ( ! $componentName = $this->matchTag( $node ) ) {
            return $node;
        }

        if ( $node->getAttribute( 'component-id' ) ) {
            dump( "Existing 'component-id'." );
        }

        dump( $componentName );

        /**
         * Replace matched {@see ElementNode} with {@see ViewProviderNode}.
         *
         * Components will be rendered at runtime using:
         * ```
         * $this->global->view->render(
         *   component : 'component.name',
         *   arguments : [ 'attributes' => ..., 'content' => ... ],
         * );
         * ```
         */

        return $node;
    }

    /**
     * @param ElementNode $node
     *
     * @return false|string
     */
    private function matchTag( ElementNode $node ) : false|string
    {
        $this->matchTags ??= $this->factory->getTags();

        $tag = $node->name;

        if ( $separator = \strpos( $tag, ':' ) ) {
            $tag = \substr( $tag, 0, $separator + 1 );
        }

        if ( ! \array_key_exists( $tag, $this->matchTags ) ) {
            return false;
        }

        return $this->matchTags[$tag];
    }
}
