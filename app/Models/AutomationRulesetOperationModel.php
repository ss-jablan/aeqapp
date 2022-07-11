<?php

namespace App\Models;

class AutomationRulesetOperationModel extends WriteOnceModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationRulesetOperation';
    /**
     * @inheritdoc
     */
    protected $casts = [
        'isConditional' => 'boolean',
    ];
    /**
     * @inheritdoc
     */
    protected $attributes = [
        'isConditional' => false,
    ];
}
