<?php

namespace S3lp\PieceData\Traits;

trait SyncSlavesTrait
{
    public function getSyncSlaves(): array
    {
        return array_keys(config('sync_models.slaves'));
    }
}