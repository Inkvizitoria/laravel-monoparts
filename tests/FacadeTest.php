<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Facades\MonoParts;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

final class FacadeTest extends TestCase
{
    public function test_facade_accessor_points_to_client(): void
    {
        $method = new \ReflectionMethod(MonoParts::class, 'getFacadeAccessor');
        $method->setAccessible(true);

        $this->assertSame(MonoPartsClient::class, $method->invoke(null));
    }
}
