<?php

namespace App\Models;

/**
 * Field              Type                 Collation        Null    Key     Default              Extra           Privileges                       Comment
 * -----------------  -------------------  ---------------  ------  ------  -------------------  --------------  -------------------------------  ---------
 * id                 bigint(20) unsigned  (NULL)           NO      PRI     (NULL)               auto_increment  select,insert,update,references
 * companyProfileID   bigint(20) unsigned  (NULL)           NO      MUL     0                                    select,insert,update,references
 * taskID             bigint(20) unsigned  (NULL)           NO              0                                    select,insert,update,references
 * workflowID         bigint(20) unsigned  (NULL)           YES     MUL     (NULL)                               select,insert,update,references
 * originatingLeadID  bigint(20) unsigned  (NULL)           YES     MUL     (NULL)                               select,insert,update,references
 * eventType          varchar(255)         utf8_general_ci  YES             (NULL)                               select,insert,update,references
 * eventScheduled     timestamp            (NULL)           NO      MUL     0000-00-00 00:00:00                  select,insert,update,references
 * timeProcessed      timestamp            (NULL)           NO              0000-00-00 00:00:00                  select,insert,update,references
 * processed          tinyint(1)           (NULL)           YES             0                                    select,insert,update,references
 * success            tinyint(1)           (NULL)           YES             0                                    select,insert,update,references
 * pending            tinyint(1)           (NULL)           YES             0                                    select,insert,update,references
 * pendingSince       timestamp            (NULL)           NO              0000-00-00 00:00:00                  select,insert,update,references
 * pendingApproval    tinyint(1)           (NULL)           YES     MUL     0                                    select,insert,update,references
 * flags              int(10) unsigned     (NULL)           YES             (NULL)                               select,insert,update,references
 * triggerData        mediumblob           (NULL)           YES             (NULL)                               select,insert,update,references
 * workflowEventData  mediumblob           (NULL)           YES             (NULL)                               select,insert,update,references
 * whoID              bigint(20) unsigned  (NULL)           NO      MUL     (NULL)                               select,insert,update,references
 * whoType            varchar(50)          utf8_general_ci  YES             (NULL)                               select,insert,update,references
 * whatID             bigint(20) unsigned  (NULL)           NO      MUL     (NULL)                               select,insert,update,references
 * whatType           varchar(50)          utf8_general_ci  YES             (NULL)                               select,insert,update,references
 * createTimestamp    timestamp            (NULL)           NO      MUL     current_timestamp()                  select,insert,update,references
 */
class AutomationEventQueueModel extends BaseModel
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
