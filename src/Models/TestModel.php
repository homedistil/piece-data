<?php

namespace S3lp\PieceData\Models;

use Illuminate\Database\Eloquent\Model;
use S3lp\PieceData\Contracts\SyncableModel;
use S3lp\PieceData\Traits\SyncSlavesTrait;

class TestModel extends Model implements SyncableModel
{
    use SyncSlavesTrait;

    protected $table = 'test_models';

    protected $fillable = [
        'id',
        'name'
    ];

    public $timestamps = false;
}
