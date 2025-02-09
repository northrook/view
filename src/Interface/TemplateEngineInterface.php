<?php

declare(strict_types=1);

namespace Core\View\Interface;

use Core\Interface\PathfinderInterface;
use Core\View\Parameters;
use Psr\Log\LoggerInterface;

interface TemplateEngineInterface
{
    /**
     * @param string               $cacheDirectory full path or `parameter.key`
     * @param ?Parameters          $parameters
     * @param PathfinderInterface  $pathfinder
     * @param null|LoggerInterface $logger
     */
    public function __construct(
        string              $cacheDirectory,
        ?Parameters         $parameters,
        PathfinderInterface $pathfinder,
        ?LoggerInterface    $logger,
    );

    /**
     * Renders a provided `view` to string.
     *
     * Accepts
     * - Path to template file, absolute or relative to provided `templateDirectory`
     * - String of `HTML`
     * - Template string
     *
     * @param string                      $view
     * @param array<string, mixed>|object $parameters [optional]
     *
     * @return string
     */
    public function render( string $view, object|array $parameters = [] ) : string;

    /**
     * Clears the entire template cache.
     *
     * ⚠️ This forces a recompilation of each template on-demand.
     *
     * @return self
     */
    public function clearTemplateCache() : self;

    /**
     * Removes unnecessary cached template files.
     *
     * Returns an array of pruned files.
     *
     * @return array<array-key, string>
     */
    public function pruneTemplateCache() : array;
}
