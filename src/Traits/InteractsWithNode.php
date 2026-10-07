<?php

namespace Yiendos\MySitesIde\Build\Node\Traits;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs npm for a site in a throwaway node container. Every `docker compose`
 * call runs from the IDE root, as the my-sites-ide CLI is run there.
 */
trait InteractsWithNode
{
    /**
     * Repos/<site>/Sites on the host
     *
     * @param string $site
     * @return string
     */
    protected function sitePath(string $site): string
    {
        return (getenv('IDE_ROOT') ?: getcwd()) . "/Repos/{$site}/Sites";
    }

    /**
     * The site's package.json, decoded - null when there isn't one
     *
     * @param string $site
     * @return array<string, mixed>|null
     */
    protected function packageJson(string $site): ?array
    {
        $json = @file_get_contents($this->sitePath($site) . '/package.json');

        return $json === false ? null : (json_decode($json, true) ?? []);
    }

    /**
     * Runs `npm <arguments>` for Repos/<site>/Sites (mounted at
     * /opt/repos/<site>/Sites), echoing the command first like the core
     * commands do
     *
     * @param OutputInterface $output
     * @param string $site
     * @param array<int, string> $arguments
     * @return int the exit code
     */
    protected function npm(OutputInterface $output, string $site, array $arguments): int
    {
        $command = 'docker compose run --rm node npm --prefix ' . escapeshellarg("{$site}/Sites")
            . ' ' . implode(' ', array_map('escapeshellarg', $arguments));

        $output->writeLn($command);
        passthru($command, $code);

        return $code;
    }
}
