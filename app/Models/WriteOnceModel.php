<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * WriteOnceModel
 *
 * Write-Once models have no `updateTimestamp` column
 */
class WriteOnceModel extends BaseModel
{
    /**
     * @inheritdoc No `updateTimestamp` in this table
     */
    const UPDATED_AT = null;
}
