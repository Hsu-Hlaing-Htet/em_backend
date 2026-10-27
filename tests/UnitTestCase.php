<?php

namespace Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\NoDatabaseConnection;

/** Framework-assisted unit tests: no HTTP kernel, migrations, or SQL. */
abstract class UnitTestCase extends BaseTestCase
{
    protected Application $app;
    private ?Container $previousContainer;
    private mixed $previousFacadeApplication;
    private mixed $previousResolver;
    private mixed $previousDispatcher;
    private string $previousTimezone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $this->previousTimezone = date_default_timezone_get();

        $this->app = new Application(dirname(__DIR__));
        $this->app->instance('config', new Repository([
            'app' => ['timezone' => 'Asia/Yangon', 'locale' => 'en', 'fallback_locale' => 'en'],
            'hashing' => ['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 4, 'verify' => true]],
        ]));
        foreach ([FilesystemServiceProvider::class, HashServiceProvider::class,
            TranslationServiceProvider::class, ValidationServiceProvider::class] as $provider) {
            $this->app->register($provider);
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        date_default_timezone_set('Asia/Yangon');

        $resolver = new ConnectionResolver(['unit' => new NoDatabaseConnection(null, 'unit')]);
        $resolver->setDefaultConnection('unit');
        Model::clearBootedModels();
        Model::setConnectionResolver($resolver);
        Model::setEventDispatcher($this->app['events']);
    }

    protected function tearDown(): void
    {
        try {
            Carbon::setTestNow();
            Model::clearBootedModels();
            if ($this->previousResolver !== null) {
                Model::setConnectionResolver($this->previousResolver);
            } else {
                Model::unsetConnectionResolver();
            }
            if ($this->previousDispatcher !== null) {
                Model::setEventDispatcher($this->previousDispatcher);
            } else {
                Model::unsetEventDispatcher();
            }
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->previousFacadeApplication);
            $this->app->flush();
            Container::setInstance($this->previousContainer);
            date_default_timezone_set($this->previousTimezone);
        } finally {
            parent::tearDown();
        }
    }
}
