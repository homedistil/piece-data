<?php

namespace S3lp\PieceData\Test;

use Orchestra\Testbench\TestCase;
use S3lp\PieceData\Models\SyncModel;
use S3lp\PieceData\Models\TestModel;
use S3lp\PieceData\ServiceProvider;

class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('sync_models.export_models', [
            'model1' => TestModel::class
        ]);

        $app['config']->set('sync_models.slaves', [
            'slave1' => 'http://127.0.0.2'
        ]);

        $app['config']->set('sync_models.export_curl_opts', [
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
    }

    protected function setUpDatabase()
    {
        $dir = str_replace('\\', '\\\\', dirname(__DIR__)); // windows fix
        $this->artisan('migrate --path=' . $dir . '/database/migrations --realpath')->run();
        $this->artisan('migrate --path=' . $dir . '/database/migrations_test --realpath')->run();
    }

    public function testSyncTestModelCycle()
    {
        $test_model = $this->createSyncTestModel([
            'id' => 1,
            'name' => 'test_name'
        ]);
        $this->updateSyncTestModel($test_model);
        $this->removeSyncTestModel($test_model);
    }

    private function createSyncTestModel(array $data): TestModel
    {
        $test_model = new TestModel($data);
        $test_model->save();

        $slave_name = array_keys(config('sync_models.slaves'))[0];
        $model_name = array_keys(config('sync_models.export_models'))[0];

        $sync_row = $this->getFirstSyncModel();
        $this->assertEquals($slave_name, $sync_row->slave_name);
        $this->assertEquals($model_name, $sync_row->model_name);
        $this->assertEquals('sync', $sync_row->handler_name);
        $this->assertEquals($data, $sync_row->getSyncData());

        return $test_model;
    }

    private function updateSyncTestModel(TestModel $test_model): void
    {
        $test_model->name = $test_model->name . '_new';
        $test_model->save();

        $sync_row = $this->getFirstSyncModel();
        $this->assertEquals('sync', $sync_row->handler_name);
        $this->assertEquals($test_model->getAttributes(), $sync_row->getSyncData());
    }

    private function removeSyncTestModel(TestModel $test_model)
    {
        $test_model->forceDelete();

        $sync_row = $this->getFirstSyncModel();
        $this->assertEquals('remove', $sync_row->handler_name);
        $this->assertNull($sync_row->getSyncData());

        $sync_row->forceDelete();
    }

    private function getFirstSyncModel(): ?SyncModel
    {
        return SyncModel::query()->first();
    }

    public function testSyncCommandEmpty()
    {
        $output = $this->artisan('sync:models');

        $output->assertExitCode(0);
        $output->expectsOutput('Queue size: 0');
    }

    public function testSyncCommandPost()
    {
        $slave_name = array_keys(config('sync_models.slaves'))[0];
        $model_name = array_keys(config('sync_models.export_models'))[0];
        $model_id = 1;
        $model_data = ['key' => 'value'];

        $sync_row = SyncModel::replace($slave_name, 'sync', $model_name, $model_id, $model_data);
        $output = $this->artisan('sync:models');

        $output->assertExitCode(0);
        $output->expectsOutput('Queue size: 1');

        //$sync_row->forceDelete();
    }

    public function testSyncCommandStatus()
    {
        $output = $this->artisan('sync:models --status');
        $output->assertExitCode(0);
    }
}
