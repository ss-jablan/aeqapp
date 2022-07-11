<?php

namespace App\Models;

class AutomationRuleGroupModel extends WriteOnceModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationRuleGroup';
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
