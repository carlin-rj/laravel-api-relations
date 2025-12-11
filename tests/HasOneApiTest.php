<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations\Tests;

use Carlin\LaravelApiRelations\HasOneApi;
use Carlin\LaravelApiRelations\Traits\HasApiRelations;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class HasOneApiTest extends TestCase
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
                ['user_id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ];
        };
        
        $relation = new HasOneApi($parent, 'user_id', 'id', $apiCallback);
        $result = $relation->getResults();
        
        $this->assertIsArray($result);
        $this->assertEquals('John', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    /**
     * Test lazy loading returns null when no match found
     */
    public function testLazyLoadingReturnsNullWhenNoMatch(): void
    {
        $parent = $this->createParentModel(['id' => 999]);
        
        $apiCallback = function (array $keys) {
            // Simulate API response with no matching data
            return [];
        };
        
        $relation = new HasOneApi($parent, 'user_id', 'id', $apiCallback);
        $result = $relation->getResults();
        
        $this->assertNull($result);
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
                ['customer_id' => 2, 'seller_id' => 100, 'order_id' => 'ORD-123'],
            ];
        };
        
        $relation = new HasOneApi(
            $parent,
            ['customer_id', 'seller_id'],
            ['customer_id', 'seller_id'],
            $apiCallback
        );
        $result = $relation->getResults();
        
        $this->assertIsArray($result);
        $this->assertEquals('ORD-123', $result['order_id']);
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
                ['user_id' => 1, 'name' => 'John'],
                ['user_id' => 2, 'name' => 'Jane'],
                ['user_id' => 3, 'name' => 'Bob'],
            ];
        };
        
        $relation = new HasOneApi($models[0], 'user_id', 'id', $apiCallback);
        
        // Initialize relation
        $relation->initRelation($models, 'profile');
        
        // Match results
        $results = new Collection([]);
        $matched = $relation->match($models, $results, 'profile');
        
        $this->assertCount(3, $matched);
        $this->assertEquals('John', $matched[0]->getRelation('profile')['name']);
        $this->assertEquals('Jane', $matched[1]->getRelation('profile')['name']);
        $this->assertEquals('Bob', $matched[2]->getRelation('profile')['name']);
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
                ['user_id' => 1, 'name' => 'John'],
                ['user_id' => 3, 'name' => 'Bob'],
            ];
        };
        
        $relation = new HasOneApi($models[0], 'user_id', 'id', $apiCallback);
        $relation->initRelation($models, 'profile');
        $matched = $relation->match($models, new Collection([]), 'profile');
        
        $this->assertEquals('John', $matched[0]->getRelation('profile')['name']);
        $this->assertNull($matched[1]->getRelation('profile'));
        $this->assertEquals('Bob', $matched[2]->getRelation('profile')['name']);
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
                ['customer_id' => 2, 'seller_id' => 100, 'order_id' => 'ORD-123'],
                ['customer_id' => 3, 'seller_id' => 101, 'order_id' => 'ORD-456'],
            ];
        };
        
        $relation = new HasOneApi(
            $models[0],
            ['customer_id', 'seller_id'],
            ['customer_id', 'seller_id'],
            $apiCallback
        );
        
        $relation->initRelation($models, 'order');
        $matched = $relation->match($models, new Collection([]), 'order');
        
        $this->assertEquals('ORD-123', $matched[0]->getRelation('order')['order_id']);
        $this->assertEquals('ORD-456', $matched[1]->getRelation('order')['order_id']);
    }

    /**
     * Test with null local key value
     */
    public function testWithNullLocalKeyValue(): void
    {
        $parent = $this->createParentModel(['id' => null]);
        
        $apiCallback = function (array $keys) {
            // Should not be called
            $this->fail('API callback should not be called when local key is null');
        };
        
        $relation = new HasOneApi($parent, 'user_id', 'id', $apiCallback);
        $result = $relation->getResults();
        
        $this->assertNull($result);
    }

    /**
     * Test hasOneApi method from trait
     */
    public function testHasOneApiTraitMethod(): void
    {
        $model = $this->createModelWithTrait(['id' => 1]);
        
        $apiCallback = function (array $keys) {
            return [['user_id' => 1, 'name' => 'John']];
        };
        
        $relation = $model->hasOneApi($apiCallback, 'user_id', 'id');
        
        $this->assertInstanceOf(HasOneApi::class, $relation);
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
