<?php

namespace App\Models;

/**
 * ListModelField                        Type                 Collation        Null    Key     Default              Extra                          Privileges                       Comment
 * ---------------------------  -------------------  ---------------  ------  ------  -------------------  -----------------------------  -------------------------------  ---------
 * id                           bigint(20) unsigned  (NULL)           NO      PRI     (NULL)               auto_increment                 select,insert,update,references
 * companyProfileID             bigint(20) unsigned  (NULL)           NO      MUL     (NULL)                                              select,insert,update,references
 * authorID                     int(11)              (NULL)           YES             (NULL)                                              select,insert,update,references
 * automationTaskID             bigint(20)           (NULL)           YES             (NULL)                                              select,insert,update,references
 * lastUpdatedBy                int(11)              (NULL)           YES             (NULL)                                              select,insert,update,references
 * name                         varchar(127)         utf8_general_ci  YES             (NULL)                                              select,insert,update,references
 * isActive                     tinyint(1)           (NULL)           NO              1                                                   select,insert,update,references
 * isDynamic                    tinyint(1)           (NULL)           NO              1                                                   select,insert,update,references
 * isPending                    tinyint(1)           (NULL)           NO              0                                                   select,insert,update,references
 * isHidden                     tinyint(1)           (NULL)           NO              0                                                   select,insert,update,references
 * isAvailableInContactManager  tinyint(1)           (NULL)           NO              0                                                   select,insert,update,references
 * isTemporary                  tinyint(1)           (NULL)           NO              0                                                   select,insert,update,references
 * isSystemList                 tinyint(1)           (NULL)           NO              0                                                   select,insert,update,references
 * isRulesOnly                  tinyint(1)           (NULL)           NO              0                                                   select,insert,update,references
 * memberCount                  int(11)              (NULL)           NO              0                                                   select,insert,update,references
 * lastMemberCountTimestamp     timestamp            (NULL)           YES     MUL     (NULL)                                              select,insert,update,references
 * removedCount                 int(11) unsigned     (NULL)           NO              0                                                   select,insert,update,references
 * manuallyAddedCount           int(11) unsigned     (NULL)           NO              0                                                   select,insert,update,references
 * createTimestamp              timestamp            (NULL)           NO              current_timestamp()                                 select,insert,update,references
 * updateTimestamp              timestamp            (NULL)           NO      MUL     current_timestamp()  on update current_timestamp()  select,insert,update,references
 * UUID                         varchar(255)         utf8_general_ci  YES     MUL     (NULL)                                              select,insert,update,references
 * folderID                     bigint(20) unsigned  (NULL)           YES             (NULL)                                              select,insert,update,references
 * qualityScore                 double               (NULL)           NO              0                                                   select,insert,update,references
 * description                  tinytext             utf8_general_ci  YES             (NULL)                                              select,insert,update,references
 */
class ListModel extends BaseModel
{
    /**
     * @inheritdoc
     */
    protected $table = 'list';
    /**
     * @inheritdoc
     */
    protected $casts = [
        'isActive'                    => 'boolean',
        'isDynamic'                   => 'boolean',
        'isPending'                   => 'boolean',
        'isHidden'                    => 'boolean',
        'isAvailableInContactManager' => 'boolean',
        'isTemporary'                 => 'boolean',
        'isSystemList'                => 'boolean',
        'isRulesOnly'                 => 'boolean',
        'lastMemberCountTimestamp'    => 'datetime',
    ];
    /**
     * @inheritdoc
     */
    protected $attributes = [
        'isActive'                    => true,
        'isDynamic'                   => true,
        'isPending'                   => false,
        'isHidden'                    => false,
        'isAvailableInContactManager' => false,
        'isTemporary'                 => false,
        'isSystemList'                => false,
        'isRulesOnly'                 => false,
        'memberCount'                 => 0,
        'removedCount'                => 0,
        'manuallyAddedCount'          => 0,
        'qualityScore'                => 0,
    ];
}
