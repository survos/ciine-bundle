<?php

namespace Survos\CiineBundle\Dto;

use Survos\CiineBundle\Workflow\IPlayerWorkflow;

class Player
{
    private ?PlayerEvent $event = null;

    public function __construct(
        public string $marking = IPlayerWorkflow::PLACE_SHELL,
        public string $inputString = '',
        public string $outputString = '',
        public string $prompt = '', // the CLI prompt response, e.g. ls

    ) {

    }

    public function setMarking(string $marking): void
    {
        $this->marking = $marking;
    }

    public function getMarking(): string
    {
        return $this->marking;
    }

    public function setEvent(PlayerEvent $event): void
    {
        $this->event = $event;
    }

    public function getEvent(): ?PlayerEvent
    {
        return $this->event;
    }

    public function getEventType(): string
    {
        return $this->event->getType();
    }

    public function appendInputString(): string
    {
        $this->inputString .= $this->event->getText();
        return $this->inputString;
    }

    public function appendPrompt(): string
    {
        $this->prompt .= $this->event->getText();
        return $this->prompt;
    }

    public function appendOutput(): string
    {
        $this->outputString .= $this->event->getText();
        return $this->outputString;
    }


}
