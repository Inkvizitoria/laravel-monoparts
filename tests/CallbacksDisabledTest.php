<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

final class CallbacksDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('monoparts.callbacks.enabled', false);
    }

    public function test_callback_route_not_registered_when_disabled(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('monoparts.callback');
        $this->assertNull($route);
    }
}
