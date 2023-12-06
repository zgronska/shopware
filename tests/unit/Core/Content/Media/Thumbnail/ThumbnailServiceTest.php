<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Thumbnail;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexingMessage;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ThumbnailService::class)]
class ThumbnailServiceTest extends TestCase
{
    private ThumbnailService $thumbnailService;

    private Context $context;

    private FilesystemOperator&MockObject $filesystemPublic;

    private FilesystemOperator&MockObject $filesystemPrivate;

    private EventDispatcherInterface&MockObject $dispatcher;

    private EntityIndexer&MockObject $indexer;

    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->filesystemPublic = $this->createMock(FilesystemOperator::class);
        $this->filesystemPrivate = $this->createMock(FilesystemOperator::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->indexer = $this->createMock(EntityIndexer::class);
        $this->connection = $this->createMock(Connection::class);

        $this->context = Context::createDefaultContext();
    }

    public function testGenerateWithValidMediaCollection(): void
    {
        $expected = [
            'id' => '$mediaThumbnailEntity-id-1',
        ];

        $staticThumbnailRepository = new StaticEntityRepository([$expected]);
        $staticMediaFolderRepository = new StaticEntityRepository([]);

        $this->thumbnailService = new ThumbnailService(
            $staticThumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $staticMediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->connection
        );

        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();
        $mediaFolderEntity = $this->createMediaFolderEntity();

        $file = file_get_contents(__DIR__ . '/shopware-logo.png');
        $this->filesystemPublic->expects(static::once())->method('read')->willReturn($file);

        $mediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $mediaThumbnailEntity->setMedia($mediaEntity);
        $mediaCollection = new MediaCollection([$mediaEntity]);

        $this->indexer->expects(static::once())
            ->method('handle')
            ->with(static::isInstanceOf(MediaIndexingMessage::class));

        $result = $this->thumbnailService->generate($mediaCollection, $this->context);

        $deletes = $staticThumbnailRepository->deletes[0] ?? [];
        $actual = $deletes[0] ?? [];

        static::assertEquals($expected, $actual);
        static::assertGreaterThan(0, $result);
    }

    public function testGenerateWithEmptyMediaCollection(): void
    {
        $staticThumbnailRepository = new StaticEntityRepository([]);
        $staticMediaFolderRepository = new StaticEntityRepository([]);

        $this->thumbnailService = new ThumbnailService(
            $staticThumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $staticMediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->connection
        );

        $mediaCollection = new MediaCollection([]);

        $result = $this->thumbnailService->generate($mediaCollection, $this->context);

        static::assertEquals(0, $result);
    }

    public function testGenerateWithMediaWithoutThumbnails(): void
    {
        $staticThumbnailRepository = new StaticEntityRepository([]);
        $staticMediaFolderRepository = new StaticEntityRepository([]);

        $this->thumbnailService = new ThumbnailService(
            $staticThumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $staticMediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->connection
        );

        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');

        $mediaCollection = new MediaCollection([$mediaEntity]);

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Thumbnail association not loaded');

        $result = $this->thumbnailService->generate($mediaCollection, $this->context);

        static::assertEquals(0, $result);
    }

    public function testGenerateWithNonImageMediaTypes(): void
    {
        $staticThumbnailRepository = new StaticEntityRepository([
            'id' => '$mediaThumbnailEntity-id-1',
        ]);
        $staticMediaFolderRepository = new StaticEntityRepository([]);

        $this->thumbnailService = new ThumbnailService(
            $staticThumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $staticMediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->connection
        );

        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();

        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');
        $mediaEntity->setMediaType(new DocumentType());
        $mediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $mediaCollection = new MediaCollection([$mediaEntity]);

        $result = $this->thumbnailService->generate($mediaCollection, $this->context);

        static::assertEquals(0, $result);
    }

    public function testGenerateWithInvalidMediaConfiguration(): void
    {
        $staticThumbnailRepository = new StaticEntityRepository([
            'id' => '$mediaThumbnailEntity-id-1',
        ]);
        $staticMediaFolderRepository = new StaticEntityRepository([]);

        $this->thumbnailService = new ThumbnailService(
            $staticThumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $staticMediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->connection
        );

        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();

        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');
        $mediaEntity->setMediaFolder(new MediaFolderEntity());
        $mediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $mediaCollection = new MediaCollection([$mediaEntity]);

        $result = $this->thumbnailService->generate($mediaCollection, $this->context);

        static::assertEquals(0, $result);
    }

    public function testUpdateWithValidMediaCollection(): void
    {
        $expected = [
            'id' => '$mediaThumbnailEntity-id-1',
        ];

        $staticThumbnailRepository = new StaticEntityRepository([$expected]);
        $staticMediaFolderRepository = new StaticEntityRepository([]);

        $this->thumbnailService = new ThumbnailService(
            $staticThumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $staticMediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->connection
        );

        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();
        $mediaFolderEntity = $this->createMediaFolderEntity();

        $file = file_get_contents(__DIR__ . '/shopware-logo.png');
        $this->filesystemPublic->expects(static::once())->method('read')->willReturn($file);

        $mediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $mediaThumbnailEntity->setMedia($mediaEntity);

        $mediaCollection = new MediaCollection([$mediaEntity]);
        $this->thumbnailService->generate($mediaCollection, $this->context);

        $newMediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $newMediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $this->connection->expects(static::once())
            ->method('transactional')
            ->willReturn($expected);

        $actual = $this->thumbnailService->updateThumbnails($newMediaEntity, $this->context, false);

        static::assertEquals(1, $actual);
    }

    public function testDeleteThumbnailsExecutesRepository(): void
    {
        $staticThumbnailRepository = new StaticEntityRepository([
            'id' => '$mediaThumbnailEntity-id-1',
        ]);
        $staticMediaFolderRepository = new StaticEntityRepository([]);

        $this->thumbnailService = new ThumbnailService(
            $staticThumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $staticMediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->connection
        );

        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();

        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');
        $mediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $this->thumbnailService->deleteThumbnails($mediaEntity, $this->context);

        $deletes = $staticThumbnailRepository->deletes[0] ?? [];
        $actual = $deletes[0] ?? [];

        static::assertEquals(['id' => '$mediaThumbnailEntity-id-1'], $actual);
    }

    public function testDeleteThumbnailThrowsMediaContainsNoThumbnailException(): void
    {
        $staticThumbnailRepository = new StaticEntityRepository([]);
        $staticMediaFolderRepository = new StaticEntityRepository([]);

        $this->thumbnailService = new ThumbnailService(
            $staticThumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $staticMediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->connection
        );

        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Media contains no thumbnails.');

        $this->thumbnailService->deleteThumbnails($mediaEntity, $this->context);
    }

    /**
     * @param array<string, int> $imageSize
     * @param array<string, int> $preferredThumbnailSize
     * @param array<string, int> $expectedSize
     */
    #[DataProvider('thumbnailSizeProvider')]
    public function testCalculateThumbnailSize(array $imageSize, bool $keepAspectRatio, array $preferredThumbnailSize, array $expectedSize): void
    {
        $staticThumbnailRepository = new StaticEntityRepository([]);
        $staticMediaFolderRepository = new StaticEntityRepository([]);

        $this->thumbnailService = new ThumbnailService(
            $staticThumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $staticMediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->connection
        );

        $mediaFolderConfigEntity = new MediaFolderConfigurationEntity();
        $mediaFolderConfigEntity->setKeepAspectRatio($keepAspectRatio);

        $thumbnailSizeEntity = new MediaThumbnailSizeEntity();
        $thumbnailSizeEntity->setWidth($preferredThumbnailSize['width']);
        $thumbnailSizeEntity->setHeight($preferredThumbnailSize['height']);

        $calculatedSize = $this->invokeMethod(
            $this->thumbnailService,
            'calculateThumbnailSize',
            [$imageSize, $thumbnailSizeEntity, $mediaFolderConfigEntity]
        );

        static::assertEquals($expectedSize, $calculatedSize);
    }

    /**
     * @return array<array<array<string, int>|bool>>
     */
    public static function thumbnailSizeProvider(): array
    {
        return [
            // image size, keep aspect ratio, preferred size, expected size
            [['width' => 800, 'height' => 600], true, ['width' => 400, 'height' => 300], ['width' => 400, 'height' => 300]],
            [['width' => 800, 'height' => 600], false, ['width' => 400, 'height' => 300], ['width' => 400, 'height' => 300]],
        ];
    }

    /**
     * @param list<mixed> $parameters
     *
     * @throws \ReflectionException
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    private function createMediaEntity(MediaThumbnailEntity $mediaThumbnailEntity, MediaFolderEntity $mediaFolderEntity): MediaEntity
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');
        $mediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));
        $mediaEntity->setMediaFolder($mediaFolderEntity);
        $mediaEntity->setFileName('shopware-logo');
        $mediaEntity->setFileExtension('png');
        $mediaEntity->setMimeType('image/png');
        $mediaEntity->setMetaData(['example' => 'metadata']);
        $mediaType = new ImageType();
        $mediaEntity->setMediaType($mediaType);
        $mediaEntity->setFilesize(100);
        $mediaEntity->setPath(__DIR__ . '/shopware-logo.png');
        $mediaEntity->setPrivate(false);
        $mediaEntity->setTitle('Test Image');
        $mediaEntity->setMetaDataRaw('{"example": "metadata"}');
        $mediaEntity->setUploadedAt(new \DateTime());
        $mediaEntity->setAlt('Test Alt Text');
        $mediaEntity->setUrl('/url/to/shopware-logo.png');

        return $mediaEntity;
    }

    private function createMediaFolderEntity(): MediaFolderEntity
    {
        $mediaThumbnailSizeEntity = new MediaThumbnailSizeEntity();
        $mediaThumbnailSizeEntity->setId('mediaThumbnailSizeEntity-id-1');
        $mediaThumbnailSizeEntity->setWidth(100);
        $mediaThumbnailSizeEntity->setHeight(100);

        $mediaFolderConfigEntity = new MediaFolderConfigurationEntity();
        $mediaFolderConfigEntity->setMediaThumbnailSizes(new MediaThumbnailSizeCollection([$mediaThumbnailSizeEntity]));
        $mediaFolderConfigEntity->setCreateThumbnails(true);

        $mediaFolderEntity = new MediaFolderEntity();
        $mediaFolderEntity->setConfiguration($mediaFolderConfigEntity);

        return $mediaFolderEntity;
    }

    private function createMediaThumbnailEntity(): MediaThumbnailEntity
    {
        $mediaThumbnailEntity = new MediaThumbnailEntity();
        $mediaThumbnailEntity->setId('$mediaThumbnailEntity-id-1');
        $mediaThumbnailEntity->setWidth(100);
        $mediaThumbnailEntity->setHeight(200);
        $mediaThumbnailEntity->setMediaId('media-id-1');
        $mediaThumbnailEntity->setPath(__DIR__ . '/shopware-logo.png');

        return $mediaThumbnailEntity;
    }
}
