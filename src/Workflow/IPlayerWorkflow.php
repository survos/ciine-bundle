<?php

namespace Survos\CiineBundle\Workflow;

use Survos\WorkflowBundle\Attribute\Place;
use Survos\WorkflowBundle\Attribute\Transition;

interface IPlayerWorkflow
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
