<?php

namespace Survos\CiineBundle\Service;

use Survos\CiineBundle\Workflow\IPlayerWorkflow;
use Survos\CiineBundle\Dto\Player;
use Survos\CiineBundle\Dto\PlayerEvent;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

class CiineService
{
    public function __construct(
        #[Target(IPlayerWorkflow::WORKFLOW_NAME)] private ?WorkflowInterface $workflow=null,
        private float             $totalTime = 0.0,
        // crying to be a DTO
        private array             $response = [
            'lines' => [],
            'header' => null,
            'markers' => [],
        ]
    )
    {
    }

    final public function cleanup(string $cast, string $cineCode): array
    {
        // of interest: https://blog.mbedded.ninja/programming/ansi-escape-sequences/

        $isCapturingCommand = true;
        $isCapturingPrompt = false;

        $currentCommand = '';
        $currentOutput = '';
        $inputStartTime = 0.0;
        $lastInput = null;

        $lines = explode("\n", $cast);

        $player = new Player();
        foreach ($lines as $idx => $line) {
            if (!$line) {
                continue;
            }
            $json = json_decode($line, true);
            assert($json, "invalid line: " . $line);
            if ($idx === 0) {
                $this->response['header'] = $json;
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

    private function addOutput(float $interval, string &$text, string $type = 'o'): void
    {
        if ($text) {
            $this->totalTime += $interval;
            $this->response['lines'][] = [$interval, $type, $text, $this->totalTime];
            $text = '';
        }

    }


}
