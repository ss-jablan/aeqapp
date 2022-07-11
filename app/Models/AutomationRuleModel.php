<?php

namespace App\Models;

class AutomationRuleModel extends BaseModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationRule';
    /**
     * @inheritdoc
     */
    protected $casts = [
        'isTrigger'     => 'boolean',
        'isActive'      => 'boolean',
        'isConditional' => 'boolean',
    ];
    /**
     * @inheritdoc
     */
    protected $attributes = [
        'isTrigger'     => true,
        'isActive'      => true,
        'isConditional' => false,
    ];
}
