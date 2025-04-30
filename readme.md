Pseudo replication Laravel Eloquent models via HTTP

## Master install

1. `composer require s3lp/piece-data:dev-master`
2. `artisan vendor:publish --provider="S3lp\PieceData\ServiceProvider"`
3. configure `slaves`:  
   `['slave_name' => 'http://slave/api/unique/import_route']`
4. configure `export_models`:  
   `['model_name' => App\Models\Model]`
5. configure `access_token`
6. `artisan migrate --path=vendor/s3lp/piece-data/database/migrations` or `artisan sync:model --migrate`
7. implements `Syncable` interface
8. sheduler include console command  
   `S3lp\PieceData\Console\SyncModelsExport`

## Slave install

1. `composer require s3lp/piece-data:dev-master`
2. `artisan vendor:publish --provider="S3lp\PieceData\ServiceProvider"`
3. configure `import_models`  
   `['model_name' => App\Models\Model]`
4. configure access - `allowed_ips` and/or `access_token`
5. setup models $fillable
6. include API routes map:  
   `Route::any('/unique/import_route', '\\S3lp\\PieceData\\Controllers\\SyncImportController@syncImport');`


## Tricks

```php
public function getSyncSlaves(): array
{
    if (true) {
        return ['slave_name' => 'remove']; // force remove
    } elseif (true) {
        return array_filter(array_keys(config('sync_models.slaves')), 'callback');
    } else {
        return array_keys(config('sync_models.slaves'));
    }
}
```