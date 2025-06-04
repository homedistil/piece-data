<?php

namespace S3lp\PieceData\Console;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use S3lp\PieceData\Exceptions\SyncModelException;
use S3lp\PieceData\Models\SyncModel;
use S3lp\PieceData\Observers\SyncObserver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncModelsExport extends Command
{
    protected $signature = 'sync:models {--status} {--reset} {--reset-chunk=} {--post-chunk=} {--models=}';

    protected $description = 'Sync external-related models';

    /**
     * @var SyncObserver
     */
    protected $observer;

    public function handle(SyncObserver $observer, SyncModel $sync_model)
    {
        $this->observer = $observer;

        if ($this->isRunning()) {
            $this->error('This command already run');
            return 0;
        } else if (empty(config('sync_models.export_models'))) {
            $this->warn('Undefined syncable models');
            return 0;
        } else if (empty(config('sync_models.slaves'))) {
            $this->warn('Undefined slaves');
            return 0;
        }

        try {

            if ($this->option('reset')) {
                $this->setRunning();
                $sync_model::query()->truncate();
                $this->line('Sync queue truncated');
                $this->resetSyncQueue();
            }

            if ($max_attempts = config('sync_models.max_sync_attempts')) {
                $ex_count = $sync_model::query()
                    ->where('attempts', '>=', $max_attempts)
                    ->count();

                if ($ex_count) {
                    $this->warn("Exceeded attempts rows: $ex_count");
                }
            }

            $queue_count = $sync_model::query()
                ->when($max_attempts, function (Builder $query, $max_attempts) {
                    $query->where('attempts', '<', $max_attempts);
                })
                ->count();
            $this->line("Queue size: $queue_count");

        } catch (QueryException $e) {
            $this->error($e->getMessage());
            if ($e->getPrevious()->getErrorCode() == 1146) {
                $this->warn('Need migrate');
            }
        }

        if ($this->option('status')) {
            $this->line('token: ' . config('sync_models.access_token'));
            if ($allowed_ips = config('sync_models.allowed_ips')) {
                $this->line('IPs: ' . implode(', ', $allowed_ips));
            }

            if ($slaves = config('sync_models.slaves')) {
                $this->line('Slaves:');
                foreach ($slaves as $key => $url) {
                    $this->line("$key: $url");
                }
            } else {
                $this->line('No slaves');
            }

            if ($models = config('sync_models.export_models')) {
                $this->line('Export models:');
                foreach ($models as $key => $class) {
                    $this->line("$key: $class");
                }
            } else {
                $this->line('No export models');
            }

            if ($models = config('sync_models.import_models')) {
                $this->line('Import models:');
                foreach ($models as $key => $class) {
                    $this->line("$key: $class");
                }
            } else {
                $this->line('No import models');
            }

            return 0;
        }

        $sync_model::query()
            ->when($max_attempts, function (Builder $query, $max_attempts) {
                $query->where('attempts', '<', $max_attempts);
            })
            ->chunk($this->getPostChunk(), function ($queue) use ($sync_model) {

                foreach ($queue->groupBy('slave_name') as $slave_name => $sync_models) {
                    $this->setRunning();

                    /* @var $model SyncModel */
                    $data = [
                        'token' => config('sync_models.access_token')
                    ];
                    foreach ($sync_models as $model) {
                        $data[$model->handler_name][$model->model_name][$model->model_id] = $model->getSyncData();
                    }

                    $slave_url = config('sync_models.slaves.' . $slave_name);

                    try {
                        $this->postSyncData($slave_url, $data);
                        $sync_model::query()
                            ->whereKey($sync_models->modelKeys())
                            ->delete();
                    } catch (SyncModelException $exception) {
                        $sync_model::query()
                            ->whereKey($sync_models->modelKeys())
                            ->increment('attempts');
                        $error = $slave_name . ' sync response status: ' . $exception->getCode();
                        Log::error($error, ['slave_url' => $slave_url]);
                        $this->error($error);
                    }
                }
            });

        $this->stopRunning();
    }

    protected function postSyncData($slave_url, $data): void
    {
        $ch = curl_init($slave_url);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if ($opts = config('sync_models.export_curl_opts')) {
            curl_setopt_array($ch, $opts);
        }

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch,CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpcode != 201) {
            throw new SyncModelException($result, $httpcode);
        }
    }

    protected function resetSyncQueue()
    {
        foreach ($this->getSyncableModels() as $syncable_model) {
            /* @var $model_instance Model */
            $model_instance = app($syncable_model);
            $model_instance::query()->chunk($this->getResetChuk(), function ($model_rows) {
                /* @var $model_row Model */
                foreach ($model_rows as $model_row) {
                    $this->observer->sync($model_row);
                }
            });
            $this->line($syncable_model . ' add all available entries to sync queue');
        }

        if (empty($syncable_model)) {
            $this->error('No syncable models');
        }
    }

    protected function getSyncableModels(): iterable
    {
        $models = config('sync_models.export_models');
        if ($filter = $this->option('models')) {
            $filter = explode(',', strtolower($filter));
            $filter = array_map('trim', $filter);
            $models = array_intersect_key($models, array_flip($filter));
        }
        return $models;
    }

//    protected function migrateSyncTable()
//    {
//        $dir = str_replace('\\', '\\\\', __DIR__); // windows fix
//        $this->call('migrate --path=' . $dir . '../../database/migrations --realpath');
//        $this->line('Table sync_models_queue recreated');
//    }

    protected function isRunning(): bool
    {
        $sync_models_time = Cache::get('sync_models_time');

        if (empty($sync_models_time)) {
            return false;
        } elseif (time() - $sync_models_time > 3600) {
            return false;
        } else {
            return true;
        }
    }

    protected function setRunning(): void
    {
        Cache::put('sync_models_time', time(), 3600);
    }

    protected function stopRunning(): void
    {
        Cache::forget('sync_models_time');
    }

    protected function getResetChuk(): int
    {
        $value = (int)$this->option('reset-chunk');
        return $value ?: config('sync_models.default_reset_chunk', 3000);
    }

    protected function getPostChunk(): int
    {
        $value = (int)$this->option('post-chunk');
        return $value ?: config('sync_models.default_post_chunk', 1000);
    }
}
