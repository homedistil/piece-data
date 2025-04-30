<?php

namespace S3lp\PieceData\Observers;

use S3lp\PieceData\Contracts\SyncableModel;
use S3lp\PieceData\Exceptions\SyncModelException;
use S3lp\PieceData\Models\SyncModel;
use Illuminate\Database\Eloquent\Model;

class SyncObserver
{
    public function sync(Model $model)
    {
        $this->handleSyncQueueEvent($model, 'sync', $model->getAttributes());
    }

    public function created(Model $model)
    {
        $this->handleSyncQueueEvent($model, 'sync', $model->getAttributes());
    }

    public function updated(Model $model)
    {
        if (config('sync_models.rewrite_on_update', true)) {
            $data = $model->getAttributes();
        } else {
            $data = $model->getChanges();
        }
        $this->handleSyncQueueEvent($model, 'sync', $data);
    }

    public function deleted(Model $model)
    {
        $this->handleSyncQueueEvent($model, 'remove', []);
    }

    public function restored(Model $model)
    {
        $this->handleSyncQueueEvent($model, 'sync', $model->getAttributes());
    }

    private function handleSyncQueueEvent(SyncableModel $model, string $event, array $data)
    {
        foreach ($model->getSyncSlaves() as $key => $slave_name) {
            if (!is_int($key)) {
                $handler_name = $slave_name;
                $slave_name = $key;
            } else {
                $handler_name = $event;
            }

            SyncModel::replace($slave_name, $handler_name, $this->getSyncModelName($model), $model->getKey(), $data);
        }
    }

    private function getSyncModelName(Model $model)
    {
        $name = array_search(get_class($model), config('sync_models.export_models'));
        if (!$name) {
            throw new SyncModelException('Undefined sync model name ' . get_class($model));
        }
        return $name;
    }
}
