<?php

namespace App\Models;

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
