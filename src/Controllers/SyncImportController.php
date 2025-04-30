<?php

namespace S3lp\PieceData\Controllers;

use S3lp\PieceData\Exceptions\SyncModelException;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SyncImportController extends Controller
{
    public function syncImport(Request $request)
    {
        if (!$this->checkSyncAuth($request)) {
            abort(config('sync_models.auth_error_code'));
        }

        $class_names = config('sync_models.import_models');
        $data = json_decode($request->getContent(), true);
        $result = [
            'synced' => 0,
            'removed' => 0
        ];

        foreach ($data['sync'] ?? [] as $model_name => $models_data) {

            $class_name = $class_names[$model_name];
            if (!$class_name) {
                throw new SyncModelException('Invalid sync import model_name');
            }
            /* @var $model_instance Model */
            $model_instance = app($class_name);
            $models = $model_instance::query()
                ->find(array_keys($models_data));

            foreach ($models_data as $model_id => $model_data) {
                $model = $models->find($model_id) ?? $model_instance->newInstance();
                $model->fill($model_data);
                $model->save(['touch' => false]);
                $result['synced']++;
            }
        }

        foreach ($data['remove'] ?? [] as $model_name => $models_data) {

            $class_name = $class_names[$model_name];
            if (!$class_name) {
                throw new SyncModelException('Invalid sync import model_name');
            }
            /* @var $model_instance Model */
            $model_instance = app($class_name);
            $result['removed'] = $model_instance::query()
                ->whereKey(array_keys($models_data))
                ->forceDelete();
        }

        return response($result, 201);
    }

    private function checkSyncAuth(Request $request): bool
    {
        $conf = config('sync_models');

        if ($conf['allowed_ips'] && !in_array($request->ip(), $conf['allowed_ips'])) {
            return false;
        }

        if ($conf['access_token'] && $request->json('token') != $conf['access_token']) {
            return false;
        }

        return true;
    }
}
