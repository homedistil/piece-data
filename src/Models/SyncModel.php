<?php

namespace S3lp\PieceData\Models;

use Illuminate\Database\Eloquent\Model;

class SyncModel extends Model
{
    protected $table = 'sync_models_queue';

    protected $fillable = [
        'slave_name',
        'handler_name',
        'model_name',
        'model_id',
        'model_data'
    ];

    protected $casts = [
        'model_data' => 'array'
    ];

    public static function replace($slave_name, $handler_name, $model_name, $model_id, $model_data): SyncModel
    {
        $row = self::query()->firstOrNew([
            'slave_name' => $slave_name,
            'model_name' => $model_name,
            'model_id' => $model_id
        ]);

        $row->handler_name = $handler_name;
        $row->model_data = $model_data + ($row->model_data ?? []);
        $row->attempts = 0;
        $row->save();

        return $row;
    }

    public function getSyncData()
    {
        if ($this->handler_name == 'remove') {
            return null;
        } else {
            return $this->model_data;
        }
    }
}
