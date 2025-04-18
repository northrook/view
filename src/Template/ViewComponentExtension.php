<?php

namespace Core\View\Template;

use Core\View\{ComponentFactory\Properties,
    Template\Compiler\Nodes\ViewRenderNode,
    ComponentFactory,
    Template\Compiler\Nodes\Php\ExpressionNode,
    Template\Compiler\Nodes\TemplateNode,
    Template\Compiler\NodeTraverser
};
use Core\View\Template\Compiler\Node;
use Core\View\Template\Compiler\Nodes\Html\ElementNode;
use function Support\slug;

final class ViewComponentExtension extends Extension
{
    /** @var array<string, string> */
    private readonly array $matchTags;

    public function __construct(
        private readonly ComponentFactory $factory,
    ) {
        $this->matchTags = $this->factory->getTags();
    }

    public function getProviders() : array
    {
        return [$this->factory::PROPERTY => $this->factory]; // render( $component, $arguments );
    }

    protected function parse( ElementNode $node, Properties $properties ) : Node
    {
        if ( $node->getAttribute( 'component-id' ) ) {
            dump( "Existing 'component-id'." );
        }

        $component = $this->factory->getComponent( $properties->name );

        if ( $properties->static ) {
            return new ViewRenderNode(
                $component->getStaticArguments( $node, $properties ),
                action : 'element',
            );
        }

        /**
         * Replace matched {@see ElementNode} with {@see ViewRenderNode}.
         *
         * Components will be rendered at runtime using:
         * ```
         * $this->global->view->render(
         *   component : 'component.name',
         *   arguments: [ 'attributes' => ..., 'content' => ... ],
         * );
         * ```
         */
        return new ViewRenderNode(
            [
                'component' => $properties->name,
                ...$component->getNodeArguments( $node, $properties ),
            ],
        );
    }

    public function getPasses() : array
    {
        return [slug( $this::class ) => $this->proccessTemplatePass( ... )];
    }

    private function proccessTemplatePass( TemplateNode $templateNode ) : void
    {
        ( new NodeTraverser() )->traverse(
            node  : $templateNode,
            enter : $this->traverseTemplate( ... ),
        );
    }

    private function traverseTemplate( Node $node ) : Node|int
    {
        // Skip expression nodes, as a component cannot exist there
        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::CONTINUE;
        }

        // Only parse ElementNodes
        if ( ! $node instanceof ElementNode ) {
            return $node;
        }

        $componentName = $this->matchTag( $node );

        if ( ! $componentName ) {
            return $node;
        }

        $properties = $this->factory->getComponentProperties( $componentName );

        return $this->parse( $node, $properties );
    }

    /**
     * @param ElementNode $node
     *
     * @return false|string
     */
    private function matchTag( ElementNode $node ) : false|string
    {
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
