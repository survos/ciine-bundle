<?php

declare(strict_types=1);

namespace Survos\CiineBundle\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand('ciine:upload', 'upload an Asciinema file or directory to a Survos Ciine site')]
final class UploadCommand
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private array $config = [],
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('path to file or directory; defaults to the newest configured cast')] ?string $path = null,
        #[Option(name: 'server-url', description: 'API endpoint')] string $apiEndpoint = '',
    ): int {
        if (!$apiEndpoint) {
            $apiEndpoint = $this->config['endpoint'] ?? getenv('CIINE_ENDPOINT') ?: '';
        }

        if ($apiEndpoint === '') {
            $io->error('Missing upload endpoint. Configure survos_ciine.endpoint, CIINE_ENDPOINT, or pass --server-url.');

            return Command::FAILURE;
        }

        $path = $this->resolveUploadPath($path);
        if ($path === null) {
            $io->error('No upload path was provided and no cast file could be found.');

            return Command::FAILURE;
        }

        if (!file_exists($path)) {
            $io->error("$path does not exist");

            return Command::FAILURE;
        }

        if (is_dir($path)) {
            $zipFilename = rtrim((string) realpath($path), DIRECTORY_SEPARATOR) . '.zip';
            $this->zipDirectory($path, $zipFilename);
            $path = $zipFilename;
            $io->note("Zipped directory to $path");
        }

        $fileHandle = fopen($path, 'r');
        if ($fileHandle === false) {
            $io->error("Unable to open $path");

            return Command::FAILURE;
        }

        $params = [
            'verify_peer' => false,
            'verify_host' => false,
            'body' => ['asciicast' => $fileHandle],
        ];

        if (str_contains($apiEndpoint, '.wip')) {
            $params['proxy'] = '127.0.0.1:7080';
        }

        try {
            $response = $this->httpClient->request('POST', $apiEndpoint, $params);
            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                $io->error("API endpoint {$apiEndpoint} returned HTTP $statusCode");

                return Command::FAILURE;
            }

            $content = $response->getContent(false);
            if ($content !== '') {
                $io->writeln($content);

                $data = json_decode($content, true);
                if (is_array($data)) {
                    self::displayArray($io, $data, 'Response');
                }
            }
        } finally {
            fclose($fileHandle);
        }

        $io->success(sprintf('Uploaded %s to %s', $path, $apiEndpoint));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function displayArray(SymfonyStyle $io, array $data = [], ?string $title = null): void
    {
        if ($title) {
            $io->section($title);
        }

        $definitions = self::arrayToDefinitions($data);
        $io->definitionList(...$definitions);
    }

    private function resolveUploadPath(?string $path): ?string
    {
        if ($path !== null && $path !== '') {
            if (file_exists($path)) {
                return $path;
            }

            $projectPath = $this->projectDir . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

            return file_exists($projectPath) ? $projectPath : $path;
        }

        $castDir = $this->config['dir'] ?? getenv('CIINE_LOCAL_DIR') ?: null;
        if (!$castDir && ($ciinePath = getenv('CIINE_PATH'))) {
            $castDir = str_ends_with($ciinePath, '.cast') ? dirname($ciinePath) : $ciinePath;
        }

        if (!$castDir) {
            return null;
        }

        if (!is_dir($castDir)) {
            $castDir = $this->projectDir . DIRECTORY_SEPARATOR . ltrim($castDir, DIRECTORY_SEPARATOR);
        }

        return $this->getMostRecentFile($castDir);
    }

    private function getMostRecentFile(string $directory): ?string
    {
        if (!is_dir($directory)) {
            return null;
        }

        $latestFile = null;
        $latestTime = 0;
        $finder = new Finder();
        $finder->files()->in($directory)->name('*.cast');

        foreach ($finder as $file) {
            $mtime = $file->getMTime();
            if ($mtime > $latestTime) {
                $latestTime = $mtime;
                $latestFile = $file->getRealPath();
            }
        }

        return $latestFile ?: null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array{string, string}>
     */
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
            $value === null => '<fg=gray>null</>',
            is_bool($value) => $value ? '<fg=green>true</>' : '<fg=red>false</>',
            is_array($value) => '<fg=yellow>[' . count($value) . ' items]</>',
            is_object($value) => '<fg=cyan>' . get_class($value) . '</>',
            is_string($value) && $value === '' => '<fg=gray>(empty)</>',
            is_string($value) => $value,
            default => (string) $value,
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
            if ($realPath === false) {
                continue;
            }

            $zip->addFile($realPath, $file->getRelativePathname());
        }

        $zip->close();
    }
}
