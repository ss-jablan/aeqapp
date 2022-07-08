<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    use HasFactory;

    /**
     * @inheritdoc
     */
    const CREATED_AT = 'createTimestamp';
    /**
     * @inheritdoc
     */
    const UPDATED_AT = 'updateTimestamp';
}
