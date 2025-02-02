<?php

declare(strict_types=1);

namespace Core\View;

use Core\Interface\ActionInterface;
use Core\Symfony\DependencyInjection\Autodiscover;
use Core\View\Html\Attributes;
use Core\View\Template\DocumentView\Body;
use Psr\Log\LoggerInterface;
use Stringable;

#[Autodiscover(
    tag      : [
        'controller.service_arguments',
        'core.service_locator',
    ],
    shared   : true,
    autowire : true,
)]
final class Document implements ActionInterface
{
    private const array GROUPS = [
        'document' => ['title', 'description', 'author', 'keywords'],
        'theme'    => ['color', 'scheme', 'name'],
    ];

    /** @var bool automatically locked when read. */
    private bool $locked = false;

    /** @var array{title: ?string, description: ?string, keywords: ?string[], author: ?string} */
    protected array $document = [];

    /** @var array<array<array-key,string>|string> */
    protected array $meta = [];

    protected array $robots = [];

    /** @var string[] `asset.key` format */
    protected array $enqueueAsset = [];

    protected array $assets = [
        'links'  => [],
        'script' => [],
        'style'  => [],
    ];

    /** @var string[]|Stringable[] */
    protected array $head = [];

    public readonly Attributes $html;

    public readonly Body $body;

    /** @var bool Determines how robot tags will be set */
    public bool $isPublic = false;

    public function __construct( private readonly ?LoggerInterface $logger = null )
    {
        $this->body = new Body();
        $this->html = new Attributes( ['lang' => 'en'] );
    }

    /**
     * @param null|string          $title
     * @param null|string          $description
     * @param null|string|string[] $keywords
     * @param null|string          $author
     * @param null|string          $status
     *
     * @return $this
     */
    public function __invoke(
        ?string           $title = null,
        ?string           $description = null,
        null|string|array $keywords = null,
        ?string           $author = null,
        ?string           $status = null,
    ) : self {
        if ( $this->isLocked( __METHOD__ ) ) {
            return $this;
        }
        // $set = \array_filter( \get_defined_vars() );
        //
        // foreach ( $set as $name => $value ) {
        //     $this->set( $name, $value );
        // }

        return $this;
    }

    /**
     * @param null|string                               $class
     * @param null|'animating'|'init'|'loading'|'ready' $status
     * @param null|string                               $id
     * @param string                                    $lang
     * @param string                                    ...$attributes
     *
     * @return $this
     */
    public function html(
        ?string            $class = null,
        ?string            $status = null,
        ?string            $id = null,
        string             $lang = 'en',
        bool|int|string ...$attributes,
    ) : self {
        if ( $this->isLocked( __METHOD__ ) ) {
            return $this;
        }

        $this->html->add(
            [
                'id'     => $id,
                'class'  => $class,
                'status' => $status,
                'lang'   => $lang,
                ...$attributes,
            ],
        );

        return $this;
    }

    public function head( string|Stringable $html ) : self
    {
        $this->head[] = $html;
        return $this;
    }

    public function title( string $set ) : self
    {
        $this->document['title'] = $set;
        return $this;
    }

    public function description( string $set ) : self
    {
        $this->document['description'] = $set;
        return $this;
    }

    public function keywords( string ...$set ) : self
    {
        $this->document['keywords'] = $set;
        return $this;
    }

    public function author( string $set ) : self
    {
        $this->document['author'] = $set;
        return $this;
    }

    public function meta( ?string $name = null, int|string|bool|Stringable ...$set ) : self
    {
        foreach ( $set as $key => $value ) {
            $set[$key] = match ( true ) {
                \is_bool( $value ) => $value ? 'true' : 'false',
                default            => (string) $value,
            };
        }

        /** @var string[] $set */
        if ( $name ) {
            $this->meta[$name] = $set;
        }
        else {
            $this->meta[] = $set;
        }
        return $this;
    }

    public function robots( string $set ) : self
    {
        if ( $this->isLocked( __METHOD__ ) ) {
            return $this;
        }

        return $this;
    }

    public function assets( string ...$enqueue ) : self
    {
        if ( $this->isLocked( __METHOD__ ) ) {
            return $this;
        }

        foreach ( $enqueue as $asset ) {
            $this->enqueueAsset[$asset] ??= $asset;
        }

        return $this;
    }

    public function theme( string $set ) : self
    {
        return $this;
    }

    public function body(
        ?string            $id = null,
        ?string            $class = null,
        bool|int|string ...$attributes,
    ) : self {
        if ( $this->isLocked( __METHOD__ ) ) {
            return $this;
        }
        $this->body->attributes->add(
            [
                'id'    => $id,
                'class' => $class,
                ...$attributes,
            ],
        );
        return $this;
    }

    protected function key( string $string ) : string
    {
        $key = \trim(
            (string) \preg_replace(
                '/[^a-z0-9-]+/i',
                '-',
                \strtolower( $string ),
            ),
            '-',
        );

        foreach ( Document::GROUPS as $group => $names ) {
            if ( \in_array( $key, $names ) ) {
                return "{$group}.{$key}";
            }
        }

        return $key;
    }

    /**
     * @param null|array<array-key, string>|bool|int|string $value
     *
     * @return string
     */
    protected function value( null|string|int|bool|array $value = null ) : string
    {
        return match ( true ) {
            \is_array( $value ) => \implode( '', $value ),
            default             => (string) $value,
        };
    }

    private function isLocked( string $method = __CLASS__ ) : bool
    {
        if ( ! $this->locked ) {
            return false;
        }

        $this->logger?->error(
            'The {caller} is locked. No further changes can be made at this time.',
            ['caller' => $method, 'document' => $this],
        );

        return true;
    }

    /**
     * @return array{title: ?string, description: ?string, keywords: ?string[], author: ?string}
     */
    public function getDocumentMeta() : array
    {
        return $this->document;
    }

    /**
     * @return array<array<array-key,string>|string>
     */
    public function getMeta() : array
    {
        return $this->meta;
    }

    /**
     * @return string[]
     */
    public function getRegisteredAssetKeys() : array
    {
        return $this->enqueueAsset;
    }

    /**
     * @return array<array-key, string|Stringable>
     */
    public function getAssets() : array
    {
        return $this->assets;
    }

    /**
     * @return string[]|Stringable[]
     */
    public function getRawHeadHtml() : array
    {
        return $this->head;
    }
}
