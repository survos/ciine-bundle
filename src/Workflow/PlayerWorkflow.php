<?php

namespace Survos\CiineBundle\Workflow;

use Survos\CiineBundle\Dto\Player;
use Survos\WorkflowBundle\Attribute\Workflow;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

#[Workflow(supports: [Player::class], name: self::WORKFLOW_NAME)]
class PlayerWorkflow implements IPlayerWorkflow
{
	public const WORKFLOW_NAME = 'PlayerWorkflow';

	public function __construct()
	{
	}


	public function getPlayer(TransitionEvent|GuardEvent $event): Player
	{
		/** @var Player */ return $event->getSubject();
	}


	#[AsGuardListener(self::WORKFLOW_NAME, self::TRANSITION_APP_PROMPT)]
    public function onAppPromptGuard(GuardEvent $event): void
    {
        $player = $this->getPlayer($event);
        if (!$player->getEvent()->endWithAppPrompt()) {
            $event->setBlocked(true, "text does not end with >");
        }
    }

    #[AsGuardListener(self::WORKFLOW_NAME, self::TRANSITION_SHELL_PROMPT)]
    public function onShellPromptGuard(GuardEvent $event): void
    {
        $player = $this->getPlayer($event);
        if (!$player->getEvent()->endWithShellPrompt()) {
            $event->setBlocked(true, "text does not end with %|$");
        }
    }

	#[AsTransitionListener(self::WORKFLOW_NAME, self::TRANSITION_SHELL_PROMPT)]
	public function onShellPrompt(TransitionEvent $event): void
	{
		$player = $this->getPlayer($event);
	}


	#[AsTransitionListener(self::WORKFLOW_NAME, self::TRANSITION_APP_PROMPT)]
	public function onAppPrompt(TransitionEvent $event): void
	{
		$player = $this->getPlayer($event);
	}


	#[AsTransitionListener(self::WORKFLOW_NAME, self::TRANSITION_RESPOND)]
	public function onRespond(TransitionEvent $event): void
	{
		$player = $this->getPlayer($event);
	}


}
