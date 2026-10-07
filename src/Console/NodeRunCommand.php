<?php

namespace Yiendos\MySitesIde\Build\Node\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Build\Node\Traits\InteractsWithNode;

class NodeRunCommand extends Command
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
            ->setName('build:node-run')
            ->setDescription('Run any npm command for a site, e.g. build:node-run mysite -- run build')
            ->addArgument('site', InputArgument::REQUIRED, 'Which site, as in Repos/<site>')
            ->addArgument('arguments', InputArgument::IS_ARRAY | InputArgument::REQUIRED, "npm's own arguments - put them after -- so their options reach npm")
        ;
    }

    /**
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $site = $input->getArgument('site');

        if (!is_dir($this->sitePath($site))) {
            $io->error('Repos/' . $this->siteApp($site) . " doesn't exist - is IDE_APP_DIR right for this site?");
            return Command::FAILURE;
        }

        return $this->npm($output, $site, $input->getArgument('arguments')) === 0
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
