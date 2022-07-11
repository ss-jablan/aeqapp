<?php

namespace App\Models;

class AutomationRuleGroupOperationModel extends WriteOnceModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationRuleGroupOperation';
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
        'setID'         => 1,
        'isConditional' => false,
    ];
}
