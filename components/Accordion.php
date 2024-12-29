<?php

declare(strict_types=1);

namespace Core\View\Component;

use Core\View\Html\{Attributes, Content, Element, Element\Heading, Element\Span};
use Support\Normalize;

final class Accordion extends AbstractComponent
{
    protected function render() : string
    {
        return (string) self::element(
            'Example Title',
            'Content indeed.',
        );
    }

    /**
     * @param Heading|Span|string                                                 $title
     * @param Content|string                                                      $content
     * @param bool                                                                $open
     * @param null|string                                                         $icon
     * @param array<string, null|array<array-key, string>|bool|string>|Attributes $attributes
     *
     * @return string
     */
    public static function element(
        string|Span|Heading $title,
        string|Content      $content,
        bool                $open = false,
        ?string             $icon = null,
        array|Attributes    $attributes = [],
    ) : string {
        $attributes = $attributes instanceof Attributes ? $attributes : new Attributes( $attributes );

        if ( \is_string( $title ) ) {
            $title = new Span( [], $title );
        }

        if ( ! $attributes->has( 'id' ) ) {
            $attributes->set( 'id', Normalize::key( (string) $title->content ) );
        }

        $state     = $open ? 'true' : 'false';
        $ariaID    = \hash( algo : 'xxh3', data : 'accordion'.(string) $title );
        $buttonID  = "{$ariaID}-button";
        $sectionID = "{$ariaID}-section";

        if ( ! $content instanceof Content ) {
            $content = new Content( $content );
        }

        if ( ! $title instanceof Heading ) {
            $title->attributes->set( 'role', 'heading' );
        }
        //
        // $accordion = new Element( 'div', $attributes, $title, $content );
        //
        // if ( ! $accordion->attributes->has( 'id' ) ) {
        //     $accordion->attributes->set( 'id', Normalize::key( ['accordion', (string) $title->content] ) );
        // }
        //
        // $id = $accordion->attributes->get( 'id' );

        return <<<HTML
            <accordion{$attributes}>
              <button id="{$buttonID}" aria-controls="{$sectionID}" aria-expanded="{$state}">{$icon}{$title}</button>
              <section id="{$sectionID}" aria-labelledby="{$buttonID}">{$content}</section>
            </accordion>
            HTML;
    }
}
