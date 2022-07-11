<?php

namespace App\Models;

class AutomationEventQueueModel extends WriteOnceModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'automationEventQueue';
    /**
     * @inheritdoc
     */
    protected $casts = [
        'processed'         => 'boolean',
        'success'           => 'boolean',
        'pending'           => 'boolean',
        'triggerData'       => 'array',
        'workflowEventData' => 'array',
    ];
    /**
     * @inheritdoc
     */
    protected $attributes = [
        'companyProfileID' => 0,
        'taskID'           => 0,
        'processed'        => 0,
        'success'          => 0,
        'pending'          => 0,
        'pendingApproval'  => 0,
        'eventScheduled'   => '0000-00-00 00:00:00',
        'timeProcessed'    => '0000-00-00 00:00:00',
        'pendingSince'     => '0000-00-00 00:00:00',
    ];
}
