<?php

declare(strict_types=1);

namespace Core\View\Template;

use Core\Symfony\DependencyInjection\Autodiscover;
use Core\View\Document;
use Core\View\Template\DocumentView\{Head, Body};
use Psr\Log\LoggerInterface;

#[Autodiscover(
    tag      : [
        'core.service_locator',
        'monolog.logger' => ['channel' => 'http_event'],
    ],
    lazy     : true,
    autowire : true,
)]
class DocumentView extends View
{
    public readonly Head $head;

    public readonly Body $body;

    protected bool $contentOnly = false;

    public function __construct(
        public readonly Document            $document,
        protected readonly ?LoggerInterface $logger = null,
    ) {
        $this->head = new Head();
        $this->body = $this->document->body;
    }

    final public function __toString() : string
    {
        return $this->contentOnly ? $this->renderContent() : $this->renderDocument();
    }

    final public function renderDocument() : string
    {
        $this->head->meta( charset : 'utf-8' );
        $this->renderHead();
        $document = ['<!DOCTYPE html>'];

        $document[] = "<html{$this->document->html}>";
        $document[] = $this->head->render();

        $document[] = $this->body;
        $document[] = '</html>';

        return \implode( PHP_EOL, $document );
    }

    final public function renderContent() : string
    {
        $this->renderHead();

        $document = $this->head->array();

        $document[] = $this->body;

        return \implode( PHP_EOL, $document );
    }

    final public function contentOnly( bool $set = true ) : DocumentView
    {
        $this->contentOnly = $set;
        return $this;
    }

    final public function setInnerHtml( string $content ) : DocumentView
    {
        $this->body->content->set( 'innerHtml', $content );
        return $this;
    }

    final protected function renderHead() : void
    {
        foreach ( $this->document->getDocumentMeta() as $meta => $value ) {
            if ( ! $value ) {
                continue;
            }
            match ( $meta ) {
                'title'       => $this->head->title( $value ),
                'description' => $this->head->description( $value ),
                'keywords'    => $this->head->keywords( $value ),
                'author'      => $this->head->author( $value ),
            };
        }

        foreach ( $this->document->getMeta() as $name => $properties ) {
            if ( \is_int( $name ) ) {
                $name = null;
            }
            if ( \is_string( $properties ) ) {
                $properties = [$properties];
            }
            $this->head->meta( $name, ...$properties );
        }

        foreach ( $this->document->getRawHeadHtml() as $html ) {
            $this->head->injectHtml( $html );
        }
    }
}
