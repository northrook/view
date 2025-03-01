<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Core\View\Latte\Node;

use Latte;
use Latte\{CompileException, ContentType};
use Latte\Compiler\{PrintContext, Tag, TemplateParser};
use Latte\Compiler\Nodes\Php\Expression\AuxiliaryNode;
use Latte\Compiler\Nodes\StatementNode;
use Generator;
use LogicException;

/**
 * n:tag="..."
 */
final class ElementTagNode extends StatementNode
{
    public static int $hit = 0;

    public static function create( Tag $tag, TemplateParser $parser ) : void
    {
        self::$hit++;

        if ( self::$hit > 5 ) {
            return;
        }

        dump(
            $tag,
            $parser,
        );

        // if ( \preg_match( '(style$|script$)iA', $tag->htmlElement->name ) ) {
        //     throw new CompileException( 'Attribute n:tag is not allowed in <script> or <style>', $tag->position );
        // }
        //
        // $tag->expectArguments();
        // $tag->htmlElement->variableName = new AuxiliaryNode(
        //     fn( PrintContext $context, $newName ) => $context->format(
        //         self::class.'::check(%dump, %node, %dump)',
        //         $tag->htmlElement->name,
        //         $newName,
        //         $parser->getContentType() === ContentType::Xml,
        //     ),
        //     [$tag->parser->parseExpression()],
        // );
    }

    public function print( PrintContext $context ) : string
    {
        throw new LogicException( 'Cannot directly print' );
    }

    public static function check( string $orig, mixed $new, bool $xml ) : mixed
    {
        if ( $new === null ) {
            return $orig;
        }
        if ( ! $xml
             && \is_string( $new )
             && isset(
                 Latte\Helpers::$emptyElements[\strtolower(
                     $orig,
                 )],
             ) !== isset( Latte\Helpers::$emptyElements[\strtolower( $new )] )
        ) {
            throw new Latte\RuntimeException( "Forbidden tag <{$orig}> change to <{$new}>" );
        }

        return $new;
    }

    public function &getIterator() : Generator
    {
        false && yield;
    }
}
