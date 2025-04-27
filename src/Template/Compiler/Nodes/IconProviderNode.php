<?php

declare(strict_types=1);

namespace Core\View\Template\Compiler\Nodes;

use Core\View\Template\Exception\CompileException;
use Generator;
use Core\View\Template\Compiler\{
    Nodes\Php\Expression\ArrayNode,
    PrintContext,
    Tag
};
use const Support\AUTO;

/**
 * Renders a `<i ...$attributes><svg $get..></i>` element from the global {@see IconProviderInterface}.
 *
 *  {icon [$get] [...$attributes]}
 */
final class IconProviderNode extends StatementNode
{
    protected readonly string $action;

    public ArrayNode $arguments;

    /**
     * @param Tag $tag
     *
     * @throws CompileException
     */
    public function __construct( Tag $tag )
    {
        $tag->outputMode = $tag::OutputRemoveIndentation;
        $this->action    = 'get'.\ucfirst( $tag->name );
        $this->arguments = $tag->parser->parseArguments();
    }

    /**
     * @param Tag $tag
     *
     * @return static
     * @throws CompileException
     */
    public static function create( Tag $tag ) : static
    {
        return new self( $tag );
    }

    public function print( ?PrintContext $context = AUTO ) : string
    {
        $context ??= new PrintContext();

        // dump( [ __METHOD__ => $context ] );

        if ( $context->raw ) {
            return $context->format(
                "\$this->global->icon->{$this->action}( %args )",
                $this->arguments,
            );
        }

        return $context->format(
            "echo \$this->global->icon->{$this->action}( %args );",
            $this->arguments,
        );
    }

    public function &getIterator() : Generator
    {
        yield $this->arguments;
    }
}
