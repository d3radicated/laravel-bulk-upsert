<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Exception;
use Lapaliv\BulkUpsert\Features\PrepareUpdateBuilderFeature;
use Lapaliv\BulkUpsert\Support\BulkCallback;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Support\Callback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Mockery;
use Mockery\VerificationDirector;

final class PrepareUpdateBuilderFeatureTest extends TestCase
{
    private GenerateUserCollectionTestFeature $generateUserCollectionFeature;

    /**
     * @param BulkCallback|null $updatingCallback
     * @param BulkCallback|null $savingCallback
     * @return void
     * @throws Exception
     * @dataProvider fillInBuilderDataProvider
     */
    public function testWithOneUniqueAttribute(
        ?BulkCallback $updatingCallback,
        ?BulkCallback $savingCallback,
    ): void {
        // arrange
        /** @var PrepareUpdateBuilderFeature $sut */
        $sut = $this->app->make(PrepareUpdateBuilderFeature::class);
        $model = new MySqlUser();
        $users = $this->generateUserCollectionFeature->handle($model::class, 4, ['email', 'name']);

        // act
        $builder = $sut->handle(
            eloquent: $model,
            collection: $users,
            events: [],
            uniqueAttributes: ['email'],
            updateAttributes: null,
            dateFields: [],
            updatingCallback: $updatingCallback,
            savingCallback: $savingCallback,
        );

        // assert
        self::assertNotNull($builder);
        self::assertEquals($builder->getTable(), $model->getTable());
        self::assertCount($builder->getLimit(), $users);

        // name, created_at, updated_at
        self::assertCount(3, $builder->getSets());
        self::assertArrayNotHasKey('email', $builder->getSets());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testCallbacks(): void
    {
        // arrange
        /** @var PrepareUpdateBuilderFeature $sut */
        $sut = $this->app->make(PrepareUpdateBuilderFeature::class);
        $model = new MySqlUser();
        $users = $this->generateUserCollectionFeature->handle($model::class, 4, ['email', 'name']);
        $updatingCallback = Mockery::spy(Callback::class);
        $savingCallback = Mockery::spy(Callback::class);

        // act
        $sut->handle(
            eloquent: $model,
            collection: $users,
            events: [],
            uniqueAttributes: ['email'],
            updateAttributes: null,
            dateFields: [],
            updatingCallback: new BulkCallback($updatingCallback),
            savingCallback: new BulkCallback($savingCallback),
        );

        // assert
        /** @var VerificationDirector $callback */
        $callback = $savingCallback->shouldHaveReceived('__invoke');
        $callback->times(1)
            ->withArgs(
                function (...$args) use ($users): bool {
                    self::assertCount(1, $args);
                    self::assertCount($users->count(), $args[0]);

                    /** @var MySqlUser $user */
                    foreach ($args[0] as $user) {
                        self::assertNotNull($user->created_at);
                        self::assertNotNull($user->updated_at);
                    }

                    return true;
                }
            );

        $callback = $updatingCallback->shouldHaveReceived('__invoke');
        $callback->times(1)
            ->withArgs(
                function (...$args) use ($users): bool {
                    self::assertCount(1, $args);
                    self::assertCount($users->count(), $args[0]);

                    /** @var MySqlUser $user */
                    foreach ($args[0] as $user) {
                        self::assertTrue($user->isDirty());
                    }

                    return true;
                }
            );
    }

    /**
     * @param BulkCallback|null $updatingCallback
     * @param BulkCallback|null $savingCallback
     * @return void
     * @throws Exception
     * @dataProvider fillInBuilderDataProvider
     */
    public function testWithSeveralUniqueAttribute(
        ?BulkCallback $updatingCallback,
        ?BulkCallback $savingCallback,
    ): void {
        // arrange
        /** @var PrepareUpdateBuilderFeature $sut */
        $sut = $this->app->make(PrepareUpdateBuilderFeature::class);
        $model = new MySqlUser();
        $users = $this->generateUserCollectionFeature->handle($model::class, 4, ['email', 'name', 'phone']);

        // act
        $builder = $sut->handle(
            eloquent: $model,
            collection: $users,
            events: [],
            uniqueAttributes: ['email', 'name'],
            updateAttributes: null,
            dateFields: [],
            updatingCallback: $updatingCallback,
            savingCallback: $savingCallback,
        );

        // assert
        self::assertNotNull($builder);
        self::assertEquals($builder->getTable(), $model->getTable());
        self::assertCount($builder->getLimit(), $users);

        // name, created_at, updated_at
        self::assertCount(3, $builder->getSets());
        self::assertArrayNotHasKey('email', $builder->getSets());
    }

    /**
     * @return array[]
     */
    public function fillInBuilderDataProvider(): array
    {
        return [
            'without callbacks' => [
                null,
                null,
            ],
            'with callbacks' => [
                new BulkCallback(fn () => null),
                new BulkCallback(fn () => null),
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionTestFeature::class);
    }
}
