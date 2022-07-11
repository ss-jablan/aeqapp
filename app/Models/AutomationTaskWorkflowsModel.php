<?php

namespace App\Models;

class AutomationTaskWorkflowsModel extends BaseModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationTaskWorkflows';
    /**
     * @inheritdoc
     */
    protected $attributes = [
        'companyProfileID'     => 0,
        'automationTaskID'     => 0,
        'automationWorkflowID' => 0,
        'automationRulesetID'  => 1,
    ];
}
