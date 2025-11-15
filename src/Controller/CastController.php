<?php

namespace Survos\CiineBundle\Controller;

use Survos\CiineBundle\Dto\Player;
use Survos\CiineBundle\Dto\PlayerEvent;
use Survos\CiineBundle\Workflow\IPlayerWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Message\DesktopMessage;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Routing\Attribute\Route;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use Symfony\Component\Workflow\WorkflowInterface;


final class CastController extends AbstractController
{
    private AnsiToHtmlConverter $converter;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%kernel.environment')] private string  $environment,
        private MessageBusInterface                        $messageBus,
        private LoggerInterface                            $logger,
        private readonly EntityManagerInterface            $entityManager,
        private ?TexterInterface                            $texter=null,
        #[Target(IPlayerWorkflow::WORKFLOW_NAME)] private ?WorkflowInterface $workflow=null,
        private float                                      $totalTime = 0.0,
        // crying to be a DTO
        private array                                      $response = [
            'lines' => [],
            'header' => null,
            'markers' => [],
        ]
    )
    {
        $this->converter = new AnsiToHtmlConverter();

    }

//    #[Route('/api/asciicasts', name: 'cast_upload')]
//    public function upload(Request        $request,
//                           ShowRepository $showRepository,
//                           string         $cineCode = 'test'): Response
//    {
//        $fileBag = $request->files;
////        return $this->json([]);
//
//        /** @var UploadedFile $uploadedFile */
//        $uploadedFile = $fileBag->all()['asciicast'];
//////        dump($fileBag->all('asciicast'));
//        if ($uploadedFile) {
//            file_put_contents($fn = $uploadedFile->getClientOriginalName(), $uploadedFile->getContent());
//
//            $code = basename($uploadedFile->getClientOriginalName(), '.cast');
//            $message = new DesktopMessage(
//                'New upload! 🎉 ' . $uploadedFile->getClientOriginalName(),
//                json_encode(['code' => $code])
//            );
//            try {
//                if ($this->environment === 'dev') {
//                    $this->texter->send($message);
//                }
//            } catch (\Exception $e) {
//                // hmm
//            }
//
//            if (!$show = $showRepository->findOneBy(['code' => $code])) {
//                $show = new Show($code);
//                $this->entityManager->persist($show);
//            }
//            $content = $uploadedFile->getContent();
//
//            $show
//                ->setAsciiCast($content);
//
//            $header = $show->getHeader();
//            $show->setTitle($header['title'] ?? null);
//
//            $lines = $show->getLines();
//            $show
//                ->setFileSize($uploadedFile->getFileInfo()->getSize())
//                ->setLineCount(count($lines))
//                ->setMarkerCount(0)
//                ->setTotalTime(-1);
//
//            $this->entityManager->flush();
//
////            file_put_contents($fn = $this->projectDir . '/public/' . $uploadedFile->getClientOriginalName(),
////                $uploadedFile->getContent());
////            $this->logger->info($fn);
//        } else {
//        }
//
//
//
//        return new JsonResponse(json_encode([
//            'status' => 'okay',
//            'orig' => $uploadedFile->getClientOriginalName(),
//            'show' => $show->getCode(),
//            'url' => $this->generateUrl('app_player', ['cineCode' => $show->getCode()], UrlGeneratorInterface::ABS_URL),
//        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), json: true);
//    }

    #[Route('/player/{cineCode}.{_format}', name: 'bundle_player')]
    #[Template('cine.html.twig')]
    public function cinePlayer(string $cineCode, string $_format='html'): Response|array
    {
        // @todo: refactor to get markers better
        $asciiCast = $this->getAsciiCast($cineCode);
        $clean = $this->cleanup($this->getAsciiCast($cineCode), $cineCode);
        // we need this for the marker menu
        foreach ($clean['lines'] as $line) {
            if ($line[1] === 'm') {
                $this->addMarker($line[3]-$line[0], $line[2]);
            }
        }

        return $this->render('cine.html.twig', [
//            'asciiCast' => $asciiCast,
        'markers' => $this->response['markers'],
            'jsonCast' => $this->cineJson($cineCode, true),
            'original' => $_format === 'cast',
            'castCode' => $cineCode,
        ]);

    }

    #[Route('/api/{cineCode}.{_format}', name: 'ciine_data')]
    #[Template('cine-data.html.twig')]
    public function cineData(string $cineCode, string $_format='json'): Response|array
    {

//        foreach (new Finder()->in($this->projectDir . '/casts/' . $cineCode) as $file) {
//            try {
//                $this->messageBus->dispatch(new WarmupCache($cineCode . '/' . $file->getFilename()));
//            } catch (\Exception $e) {
//
//            }
//        }
//        // warmup specific cache
//        $messageBus->dispatch(new WarmupCache('the/path/img.png', ['fooFilter']));

        $asciiCast = $this->getAsciiCast($cineCode);
        $clean = $this->cleanup($asciiCast, $cineCode);
        switch ($_format) {
            case 'cast':
                return new Response($asciiCast, 200, ['Content-Type' => 'text/text']);
            case 'ndjson':
                assert(false);
            case 'json':
                return $this->json($clean);
            case 'txt':
                $lines = [json_encode($clean['header'], JSON_UNESCAPED_SLASHES)];
                foreach ($clean['lines'] as $line) {
                    $lines[] = json_encode($line, JSON_UNESCAPED_SLASHES);
                }
//                return new Response(join("\n", $lines), 200, ['Content-Type' => 'application/x-ndjson']);
                return new Response(join("\n", $lines), 200, ['Content-Type' => 'text/text']);
            default:
                return ['data' => $clean]; // $clean['header'], 'code' => $cineCode];
        }
        dd($clean);

        dd($asciiCast);

    }

    private function getAsciiCast($cineCode): string
    {
//        if ($show = $this->showRepository->findOneBy(['code' => $cineCode])) {
//            $asciiCast = $show->getAsciiCast();
//        } else {
            // debug only
            $filename = $cineCode . '.cast';
            $asciiCast = file_get_contents($this->projectDir . '/casts/' . $filename);
//        }
//        assert($show, "Missing $cineCode in database");
        return $asciiCast;

    }

    #[Route('/cine/{cineCode}', name: 'app_cine')]
    public function cineJson(string $cineCode, bool $asArray = false): Response|array
    {

        $clean = $this->cleanup($this->getAsciiCast($cineCode), $cineCode);
        if ($asArray) {
            return $clean;
        }
        $nlJson = json_encode($clean['header'], JSON_UNESCAPED_SLASHES) . "\n";
        foreach ($clean['lines'] as $line) {
            $nlJson .= json_encode($line, JSON_UNESCAPED_SLASHES) . "\n";
        }
        file_put_contents($fn= $this->projectDir . '/casts/new-' . $cineCode . '.cast', $nlJson);
        return new Response($nlJson, 200, ['Content-Type' => 'application/x-asciicast']);

        // return in cast format
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE); //  + JSON_UNESCAPED_SLASHES);
        return new Response($json, Response::HTTP_OK, ['Content-Type' => 'application/json']);
//        $header = json_encode([
//            'version' => 2,
//            'width' => 180,
//            'height' => 30
//        ]);
//        $data = [
//            $header
//        ];
//        foreach ($data as $jsonData) {
//            $x[] = json_encode($jsonData);
//        }
        return new Response(join("\n", $x));

        return $this->json(json_encode());

    }

    private function cleanup(string $cast, string $cineCode): array
    {
        // of interest: https://blog.mbedded.ninja/programming/ansi-escape-sequences/

        $isCapturingCommand = true;
        $isCapturingPrompt = false;

        $currentCommand = '';
        $currentOutput = '';
        $inputStartTime = 0.0;
        $lastInput = null;

        $lines = explode("\n", $cast);

        foreach ($lines as $idx => $line) {
            if (!$line) {
                continue;
            }
            $json = json_decode($line, true);
            assert($json, "invalid line: " . $line);
            if ($idx === 0) {
                $this->response['header'] = $json;
                $player = new Player();
//                $prompt = $ndjson->readline(); // i guess we can show this
                continue;
            }
            $player->setEvent($playerEvent = new PlayerEvent(...$json));
            switch ($player->getEventType()) {
                case 'i':
                    if ($playerEvent->isReturn()) {
//                        $player->setMarking(IPlayerWorkflow::PLACE_CLI_RESPONSE);
//                        $this->addMarker(0.0, $player->prompt);
//                        $this->addOutput(1.0, $player->outputString);
                        if ($player->prompt) {
                            assert($player->prompt, "Missing prompt");
                            foreach (explode(" ", $player->prompt) as $word) {
                                $word .= ' ';
                                $this->addOutput(0.5, $word);
                            }
                            // pretty return symbols
                            $word = '\u21B5\n' . '\u23CE\n';
                            $this->addOutput(0.5, $word);
                            if ($player->getMarking() === IPlayerWorkflow::PLACE_CLI_RESPONSE) {
                                $this->addOutput(1.0, $player->prompt, 'm');
                            }
                            $player->prompt = '';
                        }
                        $player->outputString = '';
                        $player->inputString = '';
                    } else {
                        $player->appendPrompt();
                    }
//                    if ($player->getMarking() === IPlayerWorkflow::PLACE_SHELL) {
//                        $player->add
//                    }
                    break;
                case 'o':
                    $player->appendOutput();
                    if ($playerEvent->endWithAppPrompt()) {
                        $x = $playerEvent->getText();
                        $this->addOutput(0.63, $x);
                        $player->setMarking(IPlayerWorkflow::PLACE_APP);
                    }
                    if ($playerEvent->endWithShellPrompt()) {
                        $player->setMarking(IPlayerWorkflow::PLACE_CLI_RESPONSE);
                        $this->addOutput(0.63, $player->outputString);
                    }
                    if ($playerEvent->isReturn()) {
                        $this->addOutput(0.63, $player->outputString);
                    }
//                    dd(
//                        $playerEvent->endWithShellPrompt(),
//                        $playerEvent->endWithAppPrompt(),
//                        $playerEvent, json_encode($this->response['lines'], JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE));
                    break;
            }

            if ($this->workflow->can($player, IPlayerWorkflow::TRANSITION_SHELL_PROMPT)) {
//                dd($player, $playerEvent, $player->getMarking(), $player->getEvent()->getText());
            }

        }
        $this->addOutput(0.3, $player->outputString);
        return $this->response;
    }

    private function oldWay()
    {

        dd($this->response);

        if (0)
        foreach ($lines as $idx => $line)
        {

            [$interval, $type, $text] = $json;


            $interval = max($interval, "0.1");
            $interval = min($interval, "0.2");
            if ($idx === 1) {
                $this->addOutput($interval, $text);
                continue;
            }
            // output colors: https://stackoverflow.com/questions/5762491/how-to-print-color-in-console-using-system-out-println/5762502#5762502
            /**
             * public static final String ANSI_RESET = "\u001B[0m";
             * public static final String ANSI_BLACK = "\u001B[30m";
             * public static final String ANSI_RED = "\u001B[31m";
             * public static final String ANSI_GREEN = "\u001B[32m";
             * public static final String ANSI_YELLOW = "\u001B[33m";
             * public static final String ANSI_BLUE = "\u001B[34m";
             * public static final String ANSI_PURPLE = "\u001B[35m";
             * public static final String ANSI_CYAN = "\u001B[36m";
             * public static final String ANSI_WHITE = "\u001B[37m";
             */
            // assume we're starting at the CLI, all input is to the terminal until we get to a prompt
            switch ($type) {
                case 'i':
//                    if (in_array($line, ['DEL'])) {
//                        continue 2;
//                    }
//                    if (!$currentCommand) {
//                        $inputStartTime = $this->totalTime; // for the marker
//                    }
                    // the CR at the end of a prompt input or command
                    if ($text === "\r") {
                        if ($isCapturingPrompt) {
                            $this->addOutput(0.5, $currentOutput);
                            $currentOutput = ''; // reset
                        } elseif ($isCapturingCommand) {
                            $isCapturingCommand = false;
                            $this->addOutput(0.4, $currentOutput);
//                            $this->addMarker($this->totalTime + 0.1, $currentCommand);
                            $currentOutput = ''; // reset
                            $currentCommand = ''; //?
                        }
                    }
                    if ($isCapturingCommand) {
                        $currentCommand .= $text;
                    }
                    $lastInput = $text;
                    break;
                case 'o':
                    // if we're just echoing the input, make it faster
                    if ($lastInput === $text) {
                        $interval = 0.03;
                    }
//                    // \b\u001b[K = backspace
//                    if (in_array($line, ["\b\u001b[K"])) {
////                        break;
//                    }
                    // inside of a prompt, e.g. user class.  standard for to $io->ask()
                    if (str_ends_with($text, " > ")) {
                        $this->addOutput($interval, $text);
                        $isCapturingPrompt = true;
                    } else {
                        if ($isCapturingCommand || $isCapturingPrompt) {
                            // this should handle tab expansion
                            $currentOutput .= $text;
                        } else {
                            $this->addOutput($interval, $text);
                        }
                    }
                    if (str_ends_with($text, "$ ") || str_ends_with($text, "% ")) {
//                        dump(cliText: $text);
//                        $inputStartTime = $this->totalTime; // for the marker
                        $isCapturingCommand = true;
                        $isCapturingPrompt = false;
                    }
                    break;
            }
            // stream
        }
        $this->addOutput(0.25, 'last line?');
        $this->addOutput(0.25, $player->outputString);
        $this->addOutput(0.25, "cast $cast has finished");
//        dd($this->response['markers']);
//        dd($this->response);
//        dd($inputStartTime, $currentCommand, $currentOutput);
        return $this->response;

//        foreach (file($cast, FILE_IGNORE_NEW_LINES) as $index => $line) {
//            if ($index === 0) {
//                $response['header'] = json_decode($line, true);
//                continue;
//            }
//
//            [$interval, $type, $text] = json_decode($line, true);
//            $lineData = [
//                'interval' => $interval,
//                'type' => $type,
//            ];
//
//            if ($type === 'o') {
//                if (str_ends_with($text, '$ ')) {
//                    // Start capturing the command
//                    $isCapturingCommand = true;
//                    continue;
//                }
//
//                if ($isCapturingCommand) {
//                    if (str_starts_with($text, "\r\n")) {
//                        // End of the command
//                        $inputStartTime = $totalTime;
//                        $response['markers'][] = [$inputStartTime, $currentCommand];
//
//                        $lineData['text'] = $currentOutput;
//                        $response['lines'][] = [
//                            'interval' => $inputStartTime,
//                            'type' => 'o',
//                            'text' => $currentCommand . "\r\n" //. $currentOutput,
//                        ];
//
//                        $response['lines'][] = $lineData;
//
//                        $currentCommand = '';
//                        $currentOutput = '';
//                        $isCapturingCommand = false;
//                    } else {
//                        $currentCommand .= $text;
//                    }
//                } else {
//                    // Capture regular output
//                    $currentOutput = $text;
//                    $lineData['text'] = $currentOutput;
//                    $response['lines'][] = $lineData;
//                }
//            }
//
//            $totalTime += $interval;
//        }
//
//        return $response;
    }

    private function addOutput(float $interval, string &$text, string $type='o'): void
    {
        if ($text) {
            $this->totalTime += $interval;
            $this->response['lines'][] = [$interval, $type, $text, $this->totalTime];
            $text = '';
        }

    }

    private function addMarker(float $timestamp, string $text)
    {

        $html = $this->converter->convert($text);
        $html = strip_tags($html);
//        $this->response['lines'][] = [$timestamp, 'm', $text, -1];
        $this->response['markers'][] = [$timestamp, $html];
    }

}
