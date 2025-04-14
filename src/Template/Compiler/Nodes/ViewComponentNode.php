<?php

declare(strict_types=1);

namespace Core\View\Template\Compiler\Nodes;

use Core\View\Template\Compiler\Nodes\Php\Expression\ArrayNode;
use Core\View\Template\Compiler\{PrintContext, Tag};
use Core\View\Template\Exception\CompileException;
use Generator;

/**
 * View Component node.
 */
final class ViewComponentNode extends StatementNode
{
    public ArrayNode $arguments;

    /**
     * @param Tag $tag
     *
     * @throws CompileException
     */
    public function __construct( Tag $tag )
    {
        $tag->outputMode = $tag::OutputKeepIndentation;
        $this->arguments = $tag->parser->parseArguments();
    }

    public function print( PrintContext $context ) : string
    {
        return $context->format(
            '/**echo $this->global->view->render( %args );*/',
            $this->arguments,
        );
    }

    public function &getIterator() : Generator
    {
        yield $this->arguments;
    }
}
