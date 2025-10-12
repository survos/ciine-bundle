<?php

namespace Survos\CiineBundle\Workflow;

use Survos\CiineBundle\Dto\Player;
use Survos\StateBundle\Attribute\Place;
use Survos\StateBundle\Attribute\Transition;
use Survos\StateBundle\Attribute\Workflow;

#[Workflow(supports: [Player::class], name: self::WORKFLOW_NAME)]

class IPlayerWorkflow
{
	public const WORKFLOW_NAME = 'PlayerWorkflow';

	#[Place(info: "In the shell $", initial: true)]
	public const PLACE_SHELL = 'shell';

	#[Place(info: "in app>")]
	public const PLACE_APP = 'app_response';

    #[Place(info: "responding to shell prompt")]
	public const PLACE_CLI_RESPONSE = 'shell_response';

    #[Transition(from: [self::PLACE_SHELL, self::PLACE_CLI_RESPONSE], to: self::PLACE_CLI_RESPONSE, info: "eg $ ls")]
    public const TRANSITION_SHELL_PROMPT = 'shell_prompt';


    #[Transition(from: [self::PLACE_SHELL, self::PLACE_APP], to: self::PLACE_APP)]
	public const TRANSITION_APP_PROMPT = 'app_prompt';

	#[Transition(from: [self::PLACE_APP], to: self::PLACE_APP)]
	public const TRANSITION_RESPOND = 'respond';

    #[Transition(from: [self::PLACE_APP], to: self::PLACE_SHELL)]
    public const TRANSITION_FINISH_APP_RESPONSE = 'finish_app_response';

}
