<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Extension\FlowExecutorExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(FlowExecutorExtension::class)]
class FlowExecutorExtensionTest extends TestCase
{
    public function testPublicProperties(): void
    {
        $flow = $this->createFlow();
        $event = $this->createStorableFlow();

        $extension = new FlowExecutorExtension($flow, $event);

        static::assertSame($flow, $extension->flow);
        static::assertSame($event, $extension->event);
    }

    public function testIsPropagationStopped(): void
    {
        $extension = $this->getFlowExecutorExtension();
        static::assertFalse($extension->isPropagationStopped());

        $extension->stopPropagation();

        static::assertTrue($extension->isPropagationStopped());

        $extension->resetPropagation();
        static::assertFalse($extension->isPropagationStopped());
    }

    public function testGetParams(): void
    {
        $extension = $this->getFlowExecutorExtension();

        $result = $extension->getParams();
        static::assertCount(2, $result);
        static::assertInstanceOf(Flow::class, $result['flow']);
        static::assertInstanceOf(StorableFlow::class, $result['event']);
    }

    private function getFlowExecutorExtension(): FlowExecutorExtension
    {
        return new FlowExecutorExtension(
            $this->createFlow(),
            $this->createStorableFlow(),
        );
    }

    private function createFlow(): Flow
    {
        return new Flow(
            Uuid::randomHex(),
            [],
            [],
        );
    }

    private function createStorableFlow(): StorableFlow
    {
        return new StorableFlow(
            Uuid::randomHex(),
            Context::createDefaultContext(),
            [],
            [],
        );
    }
}
