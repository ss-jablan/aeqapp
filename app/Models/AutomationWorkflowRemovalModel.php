<?php

namespace App\Models;

class AutomationWorkflowRemovalModel extends WriteOnceModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationWorkflowRemoval';
    /**
     * @inheritdoc
     */
    protected $attributes = [
        'removalCount'      => 1,
        'preemptiveRemoval' => 0,
    ];
}
