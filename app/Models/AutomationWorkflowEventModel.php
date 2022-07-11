<?php

namespace App\Models;

class AutomationWorkflowEventModel extends BaseModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationEventQueue';
    /**
     * @inheritdoc
     */
    protected $casts = [
        'data' => 'array',
    ];
    /**
     * @inheritdoc
     */
    protected $attributes = [
        'companyProfileID'     => 0,
        'automationWorkflowID' => 0,
        'tsOffsetSeconds'      => 0,
        'offsetType'           => 1,
    ];
}
