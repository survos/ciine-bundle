<?php

declare(strict_types=1);

namespace Survos\CiineBundle\Command;

use Playwright\Configuration\PlaywrightConfigBuilder;
use Playwright\PlaywrightFactory;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand('ciine:screenshot', 'take screenshot')]
final class ScreenshotCommand
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument] string $url = '',
        #[Argument] string $screenshotPath = '',
        #[Option('use .wip sites')] bool $dev = false,
        #[Option('local directory')] string $dir = 'public/casts/',
        #[Option('Path to open on the detected local server, e.g. /admin')] ?string $path = null,
        #[Option('Override detected base URL (e.g. https://kpa.wip or http://127.0.0.1:8000)')] ?string $base = null,
        #[Option('Browser: chrome (your installed Google Chrome) or chromium (Playwright\'s own download)')] string $channel = 'chrome',
    ): int {
        // Build absolute URL from --path or relative <url>
        if (null !== $path && '' !== $path) {
            $base ??= $this->detectBaseUri($io);
            $url = rtrim($base, '/').'/'.ltrim($path, '/');
        } elseif ('' !== $url && !preg_match('#^https?://#i', $url)) {
            $base ??= $this->detectBaseUri($io);
            $url = rtrim($base, '/').'/'.ltrim($url, '/');
        }

        if ('' === $url) {
            $io->error('Pass either --path or an absolute <url>.');

            return Command::FAILURE;
        }

        if ('' === $screenshotPath) {
            $screenshotPath = (new \DateTimeImmutable())->format('Y-m-d-H-i-s');
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $screenshotPath = rtrim($dir, '/').'/'.$screenshotPath.'.png';

        $io->warning($url);

        if (!class_exists(PlaywrightFactory::class)) {
            $io->error('Screenshots need playwright-php/playwright: composer require --dev playwright-php/playwright (it drives your installed Google Chrome; --channel=chromium needs vendor/bin/playwright-install chromium)');

            return Command::FAILURE;
        }

        // Chrome through Playwright: your installed Google Chrome by default (--channel=chromium for Playwright's own
        // download). The Symfony local proxy is only needed for .wip hosts (it serves their local TLS certificate, hence
        // ignoreHTTPSErrors); any other URL goes straight out.
        $config = PlaywrightConfigBuilder::create()->withHeadless(true);
        if ('chromium' !== $channel) {
            $config->withChannel($channel);
        }
        if (str_ends_with((string) parse_url($url, \PHP_URL_HOST), '.wip')) {
            $config->withProxy('http://127.0.0.1:7080');
        }
        try {
            $playwright = PlaywrightFactory::create($config->build()); // keep it alive: dropping it stops the Node server
            $browser = $playwright->chromium()->launch();
            try {
                $page = $browser->newContext(['ignoreHTTPSErrors' => true, 'viewport' => ['width' => 1500, 'height' => 4000]])->newPage();
                $page->goto($url);
                $page->screenshot($screenshotPath);
            } finally {
                $browser->close();
            }
        } catch (\Throwable $e) {
            $io->error(sprintf("Could not take the screenshot (browser channel '%s'): %s\nInstall Google Chrome, or use --channel=chromium after: vendor/bin/playwright-install chromium", $channel, $e->getMessage()));

            return Command::FAILURE;
        }

        $publicUri = str_replace('public/', '', $screenshotPath);
        $io->writeln(sprintf('Saved screenshot: <info>%s</info> %s', $screenshotPath, $url.$publicUri));
        $io->writeln(sprintf('Open locally: symfony open:local --path=%s', $publicUri));

        return Command::SUCCESS;
    }

    private function detectBaseUri(SymfonyStyle $io): string
    {
        $process = new \Symfony\Component\Process\Process(['symfony', 'local:server:status']);
        $process->run();

        if (!$process->isSuccessful()) {
            $io->note('No Symfony local server detected; fallback http://127.0.0.1:8000');

            return 'http://127.0.0.1:8000';
        }

        $out = $process->getOutput();

        // 1) strip ANSI escape sequences
        $out = preg_replace("|\x1B\[[0-?]*[ -/]*[@-~]|", '', $out) ?? $out;
        // 2) normalize CRLF / carriage returns that can cause "urlurl" concatenation
        $out = str_replace("\r", "\n", $out);

        $urls = [];
        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            // Extract plain URL tokens on the line
            if (preg_match_all('#https?://[A-Za-z0-9\.\-_:]+#', $line, $m)) {
                foreach ($m[0] as $u) {
                    $urls[$u] = true; // de-dup
                }
            }
        }

        if (!$urls) {
            $io->note('Could not parse a URL from server status; fallback http://127.0.0.1:8000');

            return 'http://127.0.0.1:8000';
        }

        $candidates = array_keys($urls);

        // Prefer https, then http; prefer 127.0.0.1/localhost for dev
        usort($candidates, static function (string $a, string $b): int {
            $score = static function (string $u): int {
                $s = 0;
                if (str_starts_with($u, 'https://')) {
                    $s += 2;
                }
                if (str_contains($u, '127.0.0.1') || str_contains($u, 'localhost')) {
                    ++$s;
                }

                return -$s; // lower is better for usort
            };

            return $score($a) <=> $score($b);
        });

        $url = rtrim($candidates[0], '/');
        $io->note("Detected Symfony local server: $url");

        return $url;
    }
}
