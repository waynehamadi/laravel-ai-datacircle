<?php

declare(strict_types=1);

namespace Datacircle\LaravelAi\Tests;

use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [AiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai.providers.ollama', ['driver' => 'ollama', 'key' => '', 'url' => 'http://127.0.0.1:11434']);
    }
}
