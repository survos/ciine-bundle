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

#[AsCommand('ciine:upload', 'upload a Asciinema file or directory to SurvosCiine site', aliases: ['ciine:upload'])]
class UploadCommand extends Command
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
        $process = new Process(['asciinema', 'play', $mostRecent, '-s', '2.0', '-i', 0.5]);
        $process->run();

// executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        echo $process->getOutput();

        dd($_ENV, getenv("CIINE_PATH"), $currentPath, pathinfo(getcwd(), PATHINFO_BASENAME));

//        SCREENSHOW_ENDPOINT=https://show.survos.com/api/asciicasts

        if (!$apiEndpoint) {
            $apiEndpoint = 'https://showcase.wip/api/asciicasts';
        }
        if (is_dir($path)) {
            $zipFilename = realpath($path) . '.zip';
            $this->zipDirectory($path, $zipFilename);
            $path = $zipFilename;
            // zip it up
        }
        if (!file_exists($path)) {
            $path = $this->projectDir . $path;
        }
        if (!file_exists($path)) {
            $io->error("$path does not exist");
            return Command::FAILURE;
        }
        $fileHandle = fopen($path, 'r');
        $params = [
//            'content-type' => 'application/json',
            'verify_peer' => false,
            'verify_host' => false,
            'body' => ['asciicast' => $fileHandle]
        ];
        if (str_contains($apiEndpoint, '.wip')) {
            $params['proxy'] = '127.0.0.1:7080';
        }

        $response = $this->httpClient->request('POST', $apiEndpoint, $params);
        if (($statusCode = $response->getStatusCode()) !== 200) {
            $io->error("Api endpoint {$apiEndpoint} not reachable: $statusCode");
        } else {
            $io->writeln($response->getContent(), JSON_PRETTY_PRINT);

            $data = $response->toArray();
            $dl = self::array_map_assoc(fn($var, $val) => [$var => $val], $data);
            $io->definitionList(...$dl);
//
//            $dl = array_walk($data, fn($key, $value) => [$key => $value]);
//            $io->definitionList($dl);
//            static::displayArray($io, $data, "Response");
//            $io->definitionList(...$response->toArray());
//            dump($response->getContent(), $response->toArray());
        }

        $io->success(self::class . " success.");
        return Command::SUCCESS;
    }

    public static function displayArray(SymfonyStyle $io, array $data=[], ?string $title = null): void
    {
        if ($title) {
            $io->section($title);
        }

        $definitions = self::arrayToDefinitions($data);
        $io->definitionList(...$definitions);
    }

    private static function array_map_assoc(callable $callback, array $array): array
    {
        return array_map(function($key) use ($callback, $array){
            return $callback($key, $array[$key]);
        }, array_keys($array));
    }

    private static function arrayToDefinitions(array $data): array
    {
        $definitions = [];

        foreach ($data as $key => $value) {
            $formattedKey = ucfirst(str_replace('_', ' ', (string) $key));
            $formattedValue = self::formatValue($value);
            $definitions[] = [$formattedKey, $formattedValue];
        }

        return $definitions;
    }

    private static function formatValue(mixed $value): string
    {
        return match (true) {
            is_null($value) => '<fg=gray>null</>',
            is_bool($value) => $value ? '<fg=green>true</>' : '<fg=red>false</>',
            is_array($value) => '<fg=yellow>[' . count($value) . ' items]</>',
            is_object($value) => '<fg=cyan>' . get_class($value) . '</>',
            is_string($value) && empty($value) => '<fg=gray>(empty)</>',
            is_string($value) => $value,
            default => (string) $value
        };
    }

    private function zipDirectory(string $sourceDir, string $zipFilePath): void
    {
        if (!extension_loaded('zip')) {
            throw new \RuntimeException('ZIP extension not available.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create ZIP file at: $zipFilePath");
        }

        $finder = new Finder();
        $finder->files()->in($sourceDir);

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            $relativePath = $file->getRelativePathname(); // keeps folder structure
            $zip->addFile($realPath, $relativePath);
        }

        $zip->close();
    }
}
