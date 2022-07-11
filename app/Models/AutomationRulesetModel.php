<?php

namespace App\Models;

class AutomationRulesetModel extends WriteOnceModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationRuleset';
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
