<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTask;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[CoversClass(InvalidateCacheTask::class)]
class InvalidateCacheTaskTest extends TestCase
{
    public function testGetTaskName(): void
    {
        static::assertSame('shopware.invalidate_cache', InvalidateCacheTask::getTaskName());
    }

    public function testShouldRun(): void
    {
        static::assertTrue(InvalidateCacheTask::shouldRun(new ParameterBag()));
    }

    public function testGetDefaultInterval(): void
    {
        static::assertSame(300, InvalidateCacheTask::getDefaultInterval());
    }

    #[DisabledFeatures(['cache_rework'])]
    public function testShouldRunBasedOnDeprecatedConfig(): void
    {
        static::assertTrue(InvalidateCacheTask::shouldRun(new ParameterBag(['shopware.cache.invalidation.delay' => 20])));
        static::assertFalse(InvalidateCacheTask::shouldRun(new ParameterBag(['shopware.cache.invalidation.delay' => 0])));
    }

    #[DisabledFeatures(['cache_rework'])]
    public function testGetDefaultIntervalDeprecated(): void
    {
        static::assertSame(20, InvalidateCacheTask::getDefaultInterval());
    }
}
