<?php

namespace Survos\CiineBundle\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand('ciine:play', 'play Asciinema file, defaulting to the most recent')]
class PlayCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly ?HttpClientInterface                        $httpClient=null,
        private array $config = [],
//        private ?string $apiEndpoint=null,
        private ?string $name = null
    )
    {
        parent::__construct($this->name);
    }

    /* move to SurvosUtils? */
    private function getMostRecentFile(string $directory): ?string
    {
        $latestFile = null;
        $latestTime = 0;
        if (!is_dir($directory)) {
            return null;
        }

        foreach (new \DirectoryIterator($directory) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $mtime = $file->getMTime();
            if ($mtime > $latestTime) {
                $latestTime = $mtime;
                $latestFile = $file->getRealPath();
            }
        }

        return $latestFile;
    }


    public function __invoke(
        SymfonyStyle                                                      $io,
        #[Argument('path to file or directory')] ?string                   $path=null,
        #[Option(name: 'server-url', description: 'api endpoint')] string $apiEndpoint = '',
    ): int
    {
        if (!$filenameTemplate = getenv("CIINE_PATH")) {
            assert(false, "missing CIINE_PATH");
        }
        $currentPath = pathinfo($filenameTemplate, PATHINFO_DIRNAME);
        $castDir = getenv("CIINE_PATH") . '/' . pathinfo(getcwd(), PATHINFO_BASENAME);


        $mostRecent =  $this->getMostRecentFile($castDir);
        // first, get the title from the json

        $process = new Process(['asciinema', 'play', $mostRecent, '-s', '2.0', '-i', 0.5]);
        $process->run();

// executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        echo $process->getOutput();

        $io->success(self::class . " success.");
        return Command::SUCCESS;
    }

}
