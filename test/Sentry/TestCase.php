<?php

namespace Sentry\Laravel\Tests;

use ReflectionMethod;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\State\Scope;
use ReflectionProperty;
use Sentry\Laravel\Tracing;
use Sentry\State\HubInterface;
use Sentry\Laravel\ServiceProvider;
use Orchestra\Testbench\TestCase as LaravelTestCase;

abstract class TestCase extends LaravelTestCase
{
    protected $setupConfig = [
        // Set config here before refreshing the app to set it in the container before Sentry is loaded
        // or use the `$this->resetApplicationWithConfig([ /* config */ ]);` helper method
    ];

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('sentry.dsn', 'http://publickey:secretkey@sentry.dev/123');

        foreach ($this->setupConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
            Tracing\ServiceProvider::class,
        ];
    }

    protected function resetApplicationWithConfig(array $config): void
    {
        $this->setupConfig = $config;

        $this->refreshApplication();
    }

    protected function dispatchLaravelEvent($event, array $payload = []): void
    {
        $this->app['events']->dispatch($event, $payload);
    }

    protected function getHubFromContainer(): HubInterface
    {
        return $this->app->make('sentry');
    }

    protected function getClientFromContainer(): ClientInterface
    {
        return $this->getHubFromContainer()->getClient();
    }

    protected function getCurrentScope(): Scope
    {
        $hub = $this->getHubFromContainer();

        $method = new ReflectionMethod($hub, 'getScope');
        $method->setAccessible(true);

        return $method->invoke($hub);
    }

    protected function getCurrentBreadcrumbs(): array
    {
        $scope = $this->getCurrentScope();

        $property = new ReflectionProperty($scope, 'breadcrumbs');
        $property->setAccessible(true);

        return $property->getValue($scope);
    }

    protected function getLastBreadcrumb(): ?Breadcrumb
    {
        $breadcrumbs = $this->getCurrentBreadcrumbs();

        if (empty($breadcrumbs)) {
            return null;
        }

        return end($breadcrumbs);
    }
}
