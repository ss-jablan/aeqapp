<?php

namespace App\Models;

class AutomationTaskModel extends BaseModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationTask';
    /**
     * @inheritdoc
     */
    protected $casts = [
        'isLive'       => 'boolean',
        'isActive'     => 'boolean',
        'isVisual'     => 'boolean',
        'isRunnable'   => 'boolean',
        'isConverted'  => 'boolean',
        'isMagicTrick' => 'boolean',
    ];
    /**
     * @inheritdoc
     */
    protected $attributes = [
        'isLive'                 => false,
        'isActive'               => false,
        'isVisual'               => false,
        'isRunnable'             => false,
        'isConverted'            => false,
        'isMagicTrick'           => false,
        'workflowTriggeredCount' => 0,
        'lastRunTimestamp'       => '0000-00-00 00:00:00',
        'lastWorkflowTriggered'  => '0000-00-00 00:00:00',
    ];
}
