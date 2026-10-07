<?php

namespace Yiendos\MySitesIde\Build\Node\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Build\Node\Traits\InteractsWithNode;

class NodeAssetsCommand extends Command
{
    use InteractsWithNode;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('build:node-assets')
            ->setDescription("Install a site's npm dependencies and build its assets (Repos/<site>/<IDE_APP_DIR>)")
            ->addArgument('site', InputArgument::REQUIRED, 'Which site, as in Repos/<site>')
        ;
    }

    /**
     * `npm install`, then the build script: `prod` for a Laravel Mix site
     * (webpack.mix.js/.cjs), otherwise `build` - what Vite sites, and a
     * new Laravel site, call it.
     *
     * Also run by the site-assets hook, after ide:repo-clone --laravel has
     * installed the site's other dependencies.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $site = $input->getArgument('site');
        $package = $this->packageJson($site);

        if ($package === null) {
            $io->warning('Repos/' . $this->siteApp($site) . "/package.json doesn't exist - no assets to build.");
            return Command::SUCCESS;
        }

        if ($this->npm($output, $site, ['install']) !== 0) {
            $io->error("npm install failed for {$site} - see above.");
            return Command::FAILURE;
        }

        $script = $this->buildScript($site, array_keys($package['scripts'] ?? []));

        if ($script === null) {
            $io->warning("{$site}'s package.json has no build or prod script - run its build yourself with build:node-run {$site} -- run <script>.");
            return Command::SUCCESS;
        }

        if ($this->npm($output, $site, ['run', $script]) !== 0) {
            $io->error("npm run {$script} failed for {$site} - see above.");
            return Command::FAILURE;
        }

        $io->success("Assets built for {$site} (npm run {$script}).");

        return Command::SUCCESS;
    }

    /**
     * @param string $site
     * @param array<int, string> $scripts the package.json script names
     * @return string|null
     */
    private function buildScript(string $site, array $scripts): ?string
    {
        $mix = glob($this->sitePath($site) . '/webpack.mix.{js,cjs}', GLOB_BRACE);

        if ($mix && in_array('prod', $scripts, true)) {
            return 'prod';
        }

        foreach (['build', 'prod'] as $script) {
            if (in_array($script, $scripts, true)) {
                return $script;
            }
        }

        return null;
    }
}
