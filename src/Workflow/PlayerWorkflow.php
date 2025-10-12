<?php

namespace Survos\CiineBundle\Workflow;

use Survos\CiineBundle\Dto\Player;
use Survos\StateBundle\Attribute\Workflow;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Survos\CiineBundle\Workflow\IPlayerWorkflow as WF;
class PlayerWorkflow
{
	public const WORKFLOW_NAME = 'PlayerWorkflow';

	public function __construct()
	{
	}


	public function getPlayer(TransitionEvent|GuardEvent $event): Player
	{
		/** @var Player */ return $event->getSubject();
	}


	#[AsGuardListener(WF::WORKFLOW_NAME, WF::TRANSITION_APP_PROMPT)]
    public function onAppPromptGuard(GuardEvent $event): void
    {
        $player = $this->getPlayer($event);
        if (!$player->getEvent()->endWithAppPrompt()) {
            $event->setBlocked(true, "text does not end with >");
        }
    }

    #[AsGuardListener(WF::WORKFLOW_NAME, WF::TRANSITION_SHELL_PROMPT)]
    public function onShellPromptGuard(GuardEvent $event): void
    {
        $player = $this->getPlayer($event);
        if (!$player->getEvent()->endWithShellPrompt()) {
            $event->setBlocked(true, "text does not end with %|$");
        }
    }

	#[AsTransitionListener(WF::WORKFLOW_NAME, WF::TRANSITION_SHELL_PROMPT)]
	public function onShellPrompt(TransitionEvent $event): void
	{
		$player = $this->getPlayer($event);
	}


	#[AsTransitionListener(WF::WORKFLOW_NAME, WF::TRANSITION_APP_PROMPT)]
	public function onAppPrompt(TransitionEvent $event): void
	{
		$player = $this->getPlayer($event);
	}


	#[AsTransitionListener(WF::WORKFLOW_NAME, WF::TRANSITION_RESPOND)]
	public function onRespond(TransitionEvent $event): void
	{
		$player = $this->getPlayer($event);
	}


}
