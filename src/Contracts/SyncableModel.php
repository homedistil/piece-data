<?php

namespace S3lp\PieceData\Contracts;

interface SyncableModel
{
    public function getSyncSlaves(): array;
}
