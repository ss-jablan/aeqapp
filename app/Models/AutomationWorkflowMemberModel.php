<?php

namespace App\Models;

class AutomationWorkflowMemberModel extends WriteOnceModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationWorkflowMember';
    /**
     * @inheritdoc
     */
    protected $attributes = [
        'referenceCount' => 0,
    ];
}
