<?php

declare(strict_types=1);

namespace Core\View;

use Cache\CacheHandler;
use Core\Autowire\Logger;
use Core\Interface\{LazyService, Loggable};
use Core\View\Element\Attributes;
use Countable;
use Psr\Cache\CacheItemPoolInterface;
use function Support\key_hash;
use const Support\AUTO;

class IconProviderService implements LazyService, Countable, Loggable
{
    use Logger;

    /** @var array<string, array{'attributes': array<string,int|string>,'svg':string }> */
    private const array DEFAULT = [
        'chevron' => [
            'attributes' => [
                'fill'   => 'none',
                'stroke' => 'currentColor',
            ],
            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 10 8 6l-4 4"/>',
        ],
        'arrow' => [
            'attributes' => ['stroke' => 'currentColor'],
            'svg'        => '<path class="primary" stroke-linecap="round" stroke-linejoin="round" d="M8 12.5v-9m0 0-4 4m4-4 4 4"/>',
        ],
        'arrow-to-dot' => [
            'attributes' => ['stroke' => 'currentColor'],
            'svg'        => '<path class="primary" stroke-linecap="round" stroke-linejoin="round" d="M8 14V5.5m0 0-4 4m4-4 4 4"/><path class="secondary" d="M8.5 2.5a.5.5 0 0 1-1 0 .5.5 0 0 1 1 0Z"/>',
        ],
        'arrow-from-dot' => [
            'attributes' => ['stroke' => 'currentColor'],
            'svg'        => '<path class="primary" stroke-linecap="round" stroke-linejoin="round" d="M8 10.5V2m0 0L4 6m4-4 4 4"/><path class="secondary" d="M8.5 13.5a.5.5 0 0 1-1 0 .5.5 0 0 1 1 0Z"/>',
        ],
        'arrow-to-line' => [
            'attributes' => ['stroke' => 'currentColor'],
            'svg'        => '<path class="primary" d="M8 14V5m0 0L4 9m4-4 4 4" stroke-linecap="round" stroke-linejoin="round"/><path class="secondary" d="M3 2.5h10" stroke-linecap="round" stroke-linejoin="round"/>',
        ],
        'arrow-from-line' => [
            'attributes' => ['stroke' => 'currentColor'],
            'svg'        => '<path class="primary" d="M8 11V2m0 0L4 6m4-4 4 4" stroke-linecap="round" stroke-linejoin="round" /><path class="secondary" d="M13 13.5H3" stroke-linecap="round" stroke-linejoin="round"/>',
        ],
        'success' => [
            'attributes' => ['fill' => 'currentColor'],
            'svg'        => '<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>',
        ],
        'info' => [
            'attributes' => ['fill' => 'currentColor'],
            'svg'        => '<path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>',
        ],
        'danger' => [
            'attributes' => ['fill' => 'currentColor'],
            'svg'        => '<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/>',
        ],
        'warning' => [
            'attributes' => ['fill' => 'currentColor'],
            'svg'        => '<path fill-rule="evenodd" clip-rule="evenodd" d="M9.336.757c-.594-1.01-2.078-1.01-2.672 0L.21 11.73C-.385 12.739.357 14 1.545 14h12.91c1.188 0 1.93-1.261 1.336-2.27L9.336.757ZM9 4.5C9 4 9 4 8 4s-1 0-1 .5l.383 3.538c.103.505.103.505.617.505s.514 0 .617-.505L9 4.5Zm-1 7.482c1.028 0 1.028 0 1.028-1.01 0-1.009 0-1.009-1.028-1.009s-1.028.094-1.028 1.01c0 1.008 0 1.008 1.028 1.008Z"/>',
        ],
        'notice' => [
            'attributes' => ['fill' => 'currentColor'],
            'svg'        => '<path fill-rule="evenodd" clip-rule="evenodd" d="M6.983 1.006a.776.776 0 0 1 .667.634l1.781 9.967 1.754-3.925a.774.774 0 0 1 .706-.46h3.335c.427 0 .774.348.774.778 0 .43-.347.778-.774.778h-2.834L9.818 14.54a.774.774 0 0 1-1.468-.181L6.569 4.393 4.816 8.318a.774.774 0 0 1-.707.46H.774A.776.776 0 0 1 0 8c0-.43.347-.778.774-.778h2.834L6.182 1.46a.774.774 0 0 1 .8-.453Z"/>',
        ],
        'reveal-password' => [
            'attributes' => ['fill' => 'currentColor'],
            'svg'        => <<<'EOD'
                <path class="show" d="M10.13 8a2.13 2.13 0 1 1-4.26 0 2.13 2.13 0 0 1 4.26 0Z"/>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M8 11.73A8.17 8.17 0 0 1 1.17 8 8.17 8.17 0 0 1 8 4.27c2.88 0 5.3 1.47 6.83 3.73A8.17 8.17 0 0 1 8 11.73ZM8 3.2A9.27 9.27 0 0 0 .08 7.72c-.1.17-.1.39 0 .56A9.27 9.27 0 0 0 8 12.8c3.4 0 6.23-1.82 7.92-4.52.1-.17.1-.39 0-.56A9.27 9.27 0 0 0 8 3.2Z"/>
                <path class="hide" d="M14.24 1.76c.21.2.21.54 0 .75L2.51 14.24a.53.53 0 1 1-.75-.75L13.49 1.76c.2-.21.55-.21.75 0Z"/>
                EOD,
        ],
        'asterisk' => [
            'attributes' => ['stroke' => 'currentColor'],
            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 4v8m3.46-6-6.92 4m0-4 6.92 4"/>',
        ],
        'theme-mode-toggle' => [
            'attributes' => [],
            'svg'        => <<<'EOD'
                <mask mask="svg-theme-moon-mask">
                  <rect x="0" y="0" width="100%" height="100%" fill="white"/>
                  <circle cx="16" cy="6" r="4" fill="currentColor"/>
                </mask>
                <circle class="theme-sun" cx="8" cy="8" r="4" mask="url(#svg-theme-moon-mask)" fill="currentColor"/>
                <path class="theme-rays" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M8 1.33v1.34m0 10.66v1.34M3.29 3.29l.94.94m7.54 7.54.94.94M1.33 8h1.34m10.66 0h1.34M4.23 11.77l-.94.94M12.7 3.3l-.94.94"/>
                EOD,
        ],
        'user' => [
            'attributes' => ['stroke' => 'currentColor'],
            'svg'        => <<<'EOD'
                <path class="primary user" fill="none" stroke-width="1.25" d="M12 13.33a4 4 0 0 0-4-4m0 0a4 4 0 0 0-4 4m4-4A2.67 2.67 0 1 0 8 4a2.67 2.67 0 0 0 0 5.33Z"/>
                <path class="secondary circle" fill="none" d="M8 14.67A6.67 6.67 0 1 0 8 1.33a6.67 6.67 0 0 0 0 13.34Z"/>
                EOD,
        ],
        'ui:toggle' => [
            'attributes' => [
                'fill'   => 'none',
                'stroke' => 'currentColor',
            ],
            // 'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 10 8 6l-4 4"/>',
            'svg' => '<path d="M12 10 8 6l-4 4"/><path d="M12 8H4"/>',
            // '<path stroke="black" stroke-linecap="round" stroke-linejoin="round" d="M12 8H4"/>'
        ],
        'ui:dashboard' => [
            'attributes' => [
                'fill'   => 'none',
                'stroke' => 'currentColor',
            ],
            'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M13.33 2H2.67C2.3 2 2 2.3 2 2.67V6c0 .37.3.67.67.67h10.66c.37 0 .67-.3.67-.67V2.67c0-.37-.3-.67-.67-.67Z"/><path stroke="black" stroke-linecap="round" stroke-linejoin="round" d="M6 9.33H2.67c-.37 0-.67.3-.67.67v3.33c0 .37.3.67.67.67H6c.37 0 .67-.3.67-.67V10c0-.37-.3-.67-.67-.67Z"/><path stroke="black" stroke-linecap="round" stroke-linejoin="round" d="M13.33 9.33H10c-.37 0-.67.3-.67.67v3.33c0 .37.3.67.67.67h3.33c.37 0 .67-.3.67-.67V10c0-.37-.3-.67-.67-.67Z"/>',
        ],
        'ui:layers' => [
            'attributes' => [
                'fill'   => 'none',
                'stroke' => 'currentColor',
            ],
            'svg' => '<path d="M8.55 1.45a1.33 1.33 0 0 0-1.1 0l-5.72 2.6a.67.67 0 0 0 0 1.22l5.72 2.61a1.33 1.33 0 0 0 1.11 0l5.72-2.6a.67.67 0 0 0 0-1.22l-5.73-2.6Z"/><path d="m14.67 8.43-6.12 2.78a1.33 1.33 0 0 1-1.1 0L1.33 8.43"/><path d="m14.67 11.77-6.12 2.77a1.33 1.33 0 0 1-1.1 0l-6.12-2.77"/>',
        ],
        'ui:bar-chart' => [
            'attributes' => [
                'fill'            => 'none',
                'stroke'          => 'currentColor',
                'stroke-width'    => 1,
                'stroke-linecap'  => 'round',
                'stroke-linejoin' => 'round',
            ],
            'svg' => '<path d="M4 14V10"/><path d="M8 14V8"/><path d="M12 14V6"/>',
        ],
        'ui:hex-bolt' => [
            'attributes' => [
                'fill'            => 'none',
                'stroke'          => 'currentColor',
                'stroke-width'    => 1,
                'stroke-linecap'  => 'round',
                'stroke-linejoin' => 'round',
            ],
            'svg' => '<path d="m5.75 6.4 2-1.17a.5.5 0 0 1 .5 0l2 1.16a.5.5 0 0 1 .25.43v2.36a.5.5 0 0 1-.25.43l-2 1.16a.5.5 0 0 1-.5 0l-2-1.16a.5.5 0 0 1-.25-.43V6.82c0-.17.1-.34.25-.43Z"/><path d="M7 1.59 3 3.92a2 2 0 0 0-1 1.73v4.7a2 2 0 0 0 1 1.73l4 2.33a2 2 0 0 0 2 0l4-2.33a2 2 0 0 0 1-1.73v-4.7a2 2 0 0 0-1-1.73L9 1.6a2 2 0 0 0-2 0Z"/>',
        ],
        'ui:settings' => [
            'attributes' => [
                'fill'            => 'none',
                'stroke'          => 'currentColor',
                'stroke-width'    => 1,
                'stroke-linecap'  => 'round',
                'stroke-linejoin' => 'round',
            ],
            'svg' => <<<'EOD'
                <path class="primary" d="M4.67 6.67a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/>
                <path class="primary" d="M11.33 13.33a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/>
                <path class="secondary" d="M13.33 4.67h-6"/>
                <path class="secondary" d="M9.33 11.33h-6"/>
                EOD,
        ],

    ];

    /** @var array<string, array{'attributes': array<string,int|string>,'svg':string }> */
    private array $icons = [];

    /** @var array<string, string> */
    private array $defaultAttributes = [
        'class'   => 'icon',
        'viewbox' => '0 0 16 16',
    ];

    protected readonly CacheHandler $cache;

    final public function __construct( ?CacheItemPoolInterface $cache = null )
    {
        $this->cache = new CacheHandler( $cache, 'icon' );
    }

    /**
     * Check if a given `icon` is present in this set.
     *
     * Allows checking fallback {@see self::DEFAULT} icons by default.
     *
     * @param string  $icon
     * @param ?string $pack
     * @param bool    $includeDefaults
     *
     * @return bool
     */
    final public function has(
        string  $icon,
        ?string $pack = AUTO,
        bool    $includeDefaults = true,
    ) : bool {
        $icon = \strstr( $icon, '.', true ) ?: $icon;
        return $includeDefaults
                ? isset( $this->icons[$icon] ) || isset( self::DEFAULT[$icon] )
                : isset( $this->icons[$icon] );
    }

    /**
     * @param string $icon
     *
     * @return array{0: ?string, 1: string, 2: ?string}
     */
    private function resolve( string $icon ) : array
    {
        $pack = AUTO;
        $tail = null;

        if ( \str_contains( $icon, '.' ) ) {
            [$pack, $icon] = \explode( '.', $icon, 2 );
        }

        if ( \str_contains( $icon, ':' ) ) {
            [$icon, $tail] = \explode( ':', $icon, 2 );
        }

        return [$pack, $icon, $tail];
    }

    final public function getIcon(
        string   $name,
        mixed ...$attributes,
    ) : ?Element {
        $svg = $this->getSvg( $name );

        return $svg ? new Element( 'i', $svg, ...$attributes ) : null;
    }

    final public function getSvg( string $name, mixed ...$attributes ) : ?Element
    {
        [$pack, $name, $tail] = $this->resolve( $name );

        if ( ! $this->has( $name, $pack ) ) {
            return null;
        }

        $svg = $this->cache->get(
            key_hash( 'xxh32', ...\get_defined_vars() ),
            fn() => $this->getSvgElement( $name, $pack, ...$attributes ),
        );

        if ( ! $svg instanceof Element ) {
            $this->log(
                'Unable to provide icon {icon}.',
                ['icon' => $name],
                'warning',
            );
            return null;
        }

        // In order of frequent use - up is default
        if ( \in_array( $tail, ['right', 'down', 'left', 'up'], true ) ) {
            $svg->attributes->class->add( "direction:{$tail}" );
        }

        return $svg;
    }

    final public function count() : int
    {
        return \count( $this->icons ?? self::DEFAULT );
    }

    /**
     * @param string      $name
     * @param null|string $pack
     * @param mixed       ...$attributes
     *
     * @return Element
     */
    private function getSvgElement(
        string   $name,
        ?string  $pack = AUTO,
        mixed ...$attributes,
    ) : Element {
        $attributes = new Attributes( ...$attributes );

        $vector = $this->getIconData( $name, $pack );

        \assert( \is_array( $vector['attributes'] ) && \is_string( $vector['svg'] ) );

        $attributes
            ->add( ...$this->defaultAttributes )
            ->add( ...$vector['attributes'] )
            ->class->add( $name, true );

        $svg = \trim( (string) \preg_replace( ['#\s+#m', '#>\s<#'], [' ', '><'], $vector['svg'] ) );

        return new Element( 'svg', $svg, $attributes );
    }

    /**
     * @param string      $icon
     * @param null|string $pack
     *
     * @return array{attributes: array<string, int|string>, svg: string}
     */
    private function getIconData( string $icon, ?string $pack ) : array
    {
        return $this->icons[$icon] ?? $this::DEFAULT[$icon];
    }
}
