<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations\Tests;

use Carlin\LaravelApiRelations\HasManyApi;
use Carlin\LaravelApiRelations\Traits\HasApiRelations;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class HasManyApiTest extends TestCase
{
    /**
     * Test lazy loading with single key
     */
    public function testLazyLoadingWithSingleKey(): void
    {
        $parent = $this->createParentModel(['id' => 1]);
        
        $apiCallback = function (array $keys) {
            // Simulate API response
            return [
                ['post_id' => 1, 'title' => 'Post 1', 'content' => 'Content 1'],
                ['post_id' => 1, 'title' => 'Post 2', 'content' => 'Content 2'],
            ];
        };
        
        $relation = new HasManyApi($parent, 'post_id', 'id', $apiCallback);
        $result = $relation->getResults();
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Post 1', $result[0]['title']);
        $this->assertEquals('Post 2', $result[1]['title']);
    }

    /**
     * Test lazy loading returns empty array when no match found
     */
    public function testLazyLoadingReturnsEmptyArrayWhenNoMatch(): void
    {
        $parent = $this->createParentModel(['id' => 999]);
        
        $apiCallback = function (array $keys) {
            // Simulate API response with no matching data
            return [];
        };
        
        $relation = new HasManyApi($parent, 'post_id', 'id', $apiCallback);
        $result = $relation->getResults();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test lazy loading with composite keys
     */
    public function testLazyLoadingWithCompositeKeys(): void
    {
        $parent = $this->createParentModel(['customer_id' => 2, 'seller_id' => 100]);
        
        $apiCallback = function (array $keys) {
            // Simulate API response
            return [
                ['customer_id' => 2, 'seller_id' => 100, 'item' => 'Item 1'],
                ['customer_id' => 2, 'seller_id' => 100, 'item' => 'Item 2'],
            ];
        };
        
        $relation = new HasManyApi(
            $parent,
            ['customer_id', 'seller_id'],
            ['customer_id', 'seller_id'],
            $apiCallback
        );
        $result = $relation->getResults();
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Item 1', $result[0]['item']);
        $this->assertEquals('Item 2', $result[1]['item']);
    }

    /**
     * Test eager loading (batch query) with single key
     */
    public function testEagerLoadingWithSingleKey(): void
    {
        $models = [
            $this->createParentModel(['id' => 1]),
            $this->createParentModel(['id' => 2]),
            $this->createParentModel(['id' => 3]),
        ];
        
        $apiCallback = function (array $keys) {
            // Simulate batch API response
            $this->assertEquals([1, 2, 3], $keys);
            return [
                ['user_id' => 1, 'comment' => 'Comment 1 for user 1'],
                ['user_id' => 1, 'comment' => 'Comment 2 for user 1'],
                ['user_id' => 2, 'comment' => 'Comment 1 for user 2'],
                ['user_id' => 3, 'comment' => 'Comment 1 for user 3'],
                ['user_id' => 3, 'comment' => 'Comment 2 for user 3'],
                ['user_id' => 3, 'comment' => 'Comment 3 for user 3'],
            ];
        };
        
        $relation = new HasManyApi($models[0], 'user_id', 'id', $apiCallback);
        
        // Initialize relation
        $relation->initRelation($models, 'comments');
        
        // Match results
        $results = new Collection([]);
        $matched = $relation->match($models, $results, 'comments');
        
        $this->assertCount(3, $matched);
        $this->assertCount(2, $matched[0]->getRelation('comments'));
        $this->assertCount(1, $matched[1]->getRelation('comments'));
        $this->assertCount(3, $matched[2]->getRelation('comments'));
        
        $this->assertEquals('Comment 1 for user 1', $matched[0]->getRelation('comments')[0]['comment']);
        $this->assertEquals('Comment 1 for user 2', $matched[1]->getRelation('comments')[0]['comment']);
        $this->assertEquals('Comment 1 for user 3', $matched[2]->getRelation('comments')[0]['comment']);
    }

    /**
     * Test eager loading with missing data
     */
    public function testEagerLoadingWithMissingData(): void
    {
        $models = [
            $this->createParentModel(['id' => 1]),
            $this->createParentModel(['id' => 2]),
            $this->createParentModel(['id' => 3]),
        ];
        
        $apiCallback = function (array $keys) {
            // Only return data for id 1 and 3
            return [
                ['user_id' => 1, 'comment' => 'Comment for user 1'],
                ['user_id' => 3, 'comment' => 'Comment for user 3'],
            ];
        };
        
        $relation = new HasManyApi($models[0], 'user_id', 'id', $apiCallback);
        $relation->initRelation($models, 'comments');
        $matched = $relation->match($models, new Collection([]), 'comments');
        
        $this->assertCount(1, $matched[0]->getRelation('comments'));
        $this->assertEmpty($matched[1]->getRelation('comments'));
        $this->assertCount(1, $matched[2]->getRelation('comments'));
    }

    /**
     * Test eager loading with composite keys
     */
    public function testEagerLoadingWithCompositeKeys(): void
    {
        $models = [
            $this->createParentModel(['customer_id' => 2, 'seller_id' => 100]),
            $this->createParentModel(['customer_id' => 3, 'seller_id' => 101]),
        ];
        
        $apiCallback = function (array $keys) {
            // Verify composite keys are passed correctly
            $this->assertCount(2, $keys);
            $this->assertEquals(['customer_id' => 2, 'seller_id' => 100], $keys[0]);
            $this->assertEquals(['customer_id' => 3, 'seller_id' => 101], $keys[1]);
            
            return [
                ['customer_id' => 2, 'seller_id' => 100, 'item' => 'Item 1'],
                ['customer_id' => 2, 'seller_id' => 100, 'item' => 'Item 2'],
                ['customer_id' => 3, 'seller_id' => 101, 'item' => 'Item 3'],
            ];
        };
        
        $relation = new HasManyApi(
            $models[0],
            ['customer_id', 'seller_id'],
            ['customer_id', 'seller_id'],
            $apiCallback
        );
        
        $relation->initRelation($models, 'items');
        $matched = $relation->match($models, new Collection([]), 'items');
        
        $this->assertCount(2, $matched[0]->getRelation('items'));
        $this->assertCount(1, $matched[1]->getRelation('items'));
        $this->assertEquals('Item 1', $matched[0]->getRelation('items')[0]['item']);
        $this->assertEquals('Item 3', $matched[1]->getRelation('items')[0]['item']);
    }

    /**
     * Test default value is empty array
     */
    public function testDefaultValueIsEmptyArray(): void
    {
        $parent = $this->createParentModel(['id' => null]);
        
        $apiCallback = function (array $keys) {
            // Should not be called
            $this->fail('API callback should not be called when local key is null');
        };
        
        $relation = new HasManyApi($parent, 'user_id', 'id', $apiCallback);
        $result = $relation->getResults();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test hasManyApi method from trait
     */
    public function testHasManyApiTraitMethod(): void
    {
        $model = $this->createModelWithTrait(['id' => 1]);
        
        $apiCallback = function (array $keys) {
            return [['user_id' => 1, 'comment' => 'Test comment']];
        };
        
        $relation = $model->hasManyApi($apiCallback, 'user_id', 'id');
        
        $this->assertInstanceOf(HasManyApi::class, $relation);
    }

    /**
     * Test eager loading with duplicate keys (should be deduplicated)
     */
    public function testEagerLoadingDeduplicatesKeys(): void
    {
        $models = [
            $this->createParentModel(['id' => 1]),
            $this->createParentModel(['id' => 1]), // Duplicate
            $this->createParentModel(['id' => 2]),
        ];
        
        $apiCallback = function (array $keys) {
            // Keys should be deduplicated
            $this->assertEquals([1, 2], $keys);
            
            return [
                ['user_id' => 1, 'comment' => 'Comment for user 1'],
                ['user_id' => 2, 'comment' => 'Comment for user 2'],
            ];
        };
        
        $relation = new HasManyApi($models[0], 'user_id', 'id', $apiCallback);
        $relation->initRelation($models, 'comments');
        $matched = $relation->match($models, new Collection([]), 'comments');
        
        // Both models with id=1 should have the same comments
        $this->assertCount(1, $matched[0]->getRelation('comments'));
        $this->assertCount(1, $matched[1]->getRelation('comments'));
        $this->assertEquals(
            $matched[0]->getRelation('comments'),
            $matched[1]->getRelation('comments')
        );
    }

    /**
     * Helper method to create a parent model
     */
    private function createParentModel(array $attributes): Model
    {
        $model = new class extends Model {
            protected $guarded = [];
            public $timestamps = false;
        };
        
        foreach ($attributes as $key => $value) {
            $model->setAttribute($key, $value);
        }
        
        return $model;
    }

    /**
     * Helper method to create a model with HasApiRelations trait
     */
    private function createModelWithTrait(array $attributes): Model
    {
        $model = new class extends Model {
            use HasApiRelations;
            
            protected $guarded = [];
            public $timestamps = false;
        };
        
        foreach ($attributes as $key => $value) {
            $model->setAttribute($key, $value);
        }
        
        return $model;
    }
}
