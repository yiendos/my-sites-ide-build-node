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
     * The site's application code, relative to Repos/ (and to /opt/repos in
     * the container) - <site>/<IDE_APP_DIR>, which the IDE sets (deploy by
     * default, `.` for the repository root). Falls back to deploy on an IDE
     * that predates it.
     *
     * @param string $site
     * @return string
     */
    protected function siteApp(string $site): string
    {
        $app = trim((string) (getenv('IDE_APP_DIR') ?: 'deploy'), '/');

        return $app === '.' || $app === '' ? $site : "{$site}/{$app}";
    }

    /**
     * The site's application code on the host
     *
     * @param string $site
     * @return string
     */
    protected function sitePath(string $site): string
    {
        return (getenv('IDE_ROOT') ?: getcwd()) . '/Repos/' . $this->siteApp($site);
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
     * Runs `npm <arguments>` for the site's application code (mounted at
     * /opt/repos/<site>/<IDE_APP_DIR>), echoing the command first like the
     * core commands do
     *
     * @param OutputInterface $output
     * @param string $site
     * @param array<int, string> $arguments
     * @return int the exit code
     */
    protected function npm(OutputInterface $output, string $site, array $arguments): int
    {
        $command = 'docker compose run --rm node npm --prefix ' . escapeshellarg($this->siteApp($site))
            . ' ' . implode(' ', array_map('escapeshellarg', $arguments));

        $output->writeLn($command);
        passthru($command, $code);

        return $code;
    }
}
