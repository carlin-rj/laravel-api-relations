<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations\Tests;

use Carlin\LaravelApiRelations\BaseApiRelation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class BaseApiRelationTest extends TestCase
{
    /**
     * Test makeDictionaryKey with string value
     */
    public function testMakeDictionaryKeyWithString(): void
    {
        $relation = $this->createTestRelation();
        
        $key = $this->invokeProtectedMethod($relation, 'makeDictionaryKey', ['test-key']);
        
        $this->assertEquals('test-key', $key);
    }

    /**
     * Test makeDictionaryKey with integer value
     */
    public function testMakeDictionaryKeyWithInteger(): void
    {
        $relation = $this->createTestRelation();
        
        $key = $this->invokeProtectedMethod($relation, 'makeDictionaryKey', [123]);
        
        $this->assertEquals('123', $key);
    }

    /**
     * Test makeDictionaryKey with array (composite key)
     */
    public function testMakeDictionaryKeyWithArray(): void
    {
        $relation = $this->createTestRelation();
        
        $compositeKey = ['customer_id' => 2, 'seller_id' => 100];
        $key1 = $this->invokeProtectedMethod($relation, 'makeDictionaryKey', [$compositeKey]);
        
        // Same composite key should produce same hash
        $key2 = $this->invokeProtectedMethod($relation, 'makeDictionaryKey', [$compositeKey]);
        $this->assertEquals($key1, $key2);
        
        // Different composite key should produce different hash
        $differentKey = ['customer_id' => 3, 'seller_id' => 101];
        $key3 = $this->invokeProtectedMethod($relation, 'makeDictionaryKey', [$differentKey]);
        $this->assertNotEquals($key1, $key3);
    }

    /**
     * Test getCompositeKeyValue with valid model
     */
    public function testGetCompositeKeyValueWithValidModel(): void
    {
        $relation = $this->createTestRelation();
        $model = $this->createParentModel(['customer_id' => 2, 'seller_id' => 100]);
        
        $result = $this->invokeProtectedMethod(
            $relation, 
            'getCompositeKeyValue', 
            [$model, ['customer_id', 'seller_id']]
        );
        
        $this->assertEquals(['customer_id' => 2, 'seller_id' => 100], $result);
    }

    /**
     * Test getCompositeKeyValue returns null when any field is null
     */
    public function testGetCompositeKeyValueReturnsNullWithNullField(): void
    {
        $relation = $this->createTestRelation();
        $model = $this->createParentModel(['customer_id' => 2, 'seller_id' => null]);
        
        $result = $this->invokeProtectedMethod(
            $relation, 
            'getCompositeKeyValue', 
            [$model, ['customer_id', 'seller_id']]
        );
        
        $this->assertNull($result);
    }

    /**
     * Test getCompositeKeyValueFromData with valid data
     */
    public function testGetCompositeKeyValueFromDataWithValidData(): void
    {
        $relation = $this->createTestRelation();
        $data = ['customer_id' => 2, 'seller_id' => 100, 'amount' => 500];
        
        $result = $this->invokeProtectedMethod(
            $relation, 
            'getCompositeKeyValueFromData', 
            [$data, ['customer_id', 'seller_id']]
        );
        
        $this->assertEquals(['customer_id' => 2, 'seller_id' => 100], $result);
    }

    /**
     * Test getCompositeKeyValueFromData returns null when field is missing
     */
    public function testGetCompositeKeyValueFromDataReturnsNullWithMissingField(): void
    {
        $relation = $this->createTestRelation();
        $data = ['customer_id' => 2, 'amount' => 500];
        
        $result = $this->invokeProtectedMethod(
            $relation, 
            'getCompositeKeyValueFromData', 
            [$data, ['customer_id', 'seller_id']]
        );
        
        $this->assertNull($result);
    }

    /**
     * Test getKeys with single key
     */
    public function testGetKeysWithSingleKey(): void
    {
        $relation = $this->createTestRelation();
        $models = [
            $this->createParentModel(['id' => 1]),
            $this->createParentModel(['id' => 2]),
            $this->createParentModel(['id' => 3]),
        ];
        
        $keys = $this->invokeProtectedMethod($relation, 'getKeys', [$models, 'id']);
        
        $this->assertEquals([1, 2, 3], $keys);
    }

    /**
     * Test getKeys filters out null values
     */
    public function testGetKeysFiltersNullValues(): void
    {
        $relation = $this->createTestRelation();
        $models = [
            $this->createParentModel(['id' => 1]),
            $this->createParentModel(['id' => null]),
            $this->createParentModel(['id' => 3]),
        ];
        
        $keys = $this->invokeProtectedMethod($relation, 'getKeys', [$models, 'id']);
        
        $this->assertEquals([1, 3], $keys);
    }

    /**
     * Test getKeys deduplicates values
     */
    public function testGetKeysDeduplicatesValues(): void
    {
        $relation = $this->createTestRelation();
        $models = [
            $this->createParentModel(['id' => 1]),
            $this->createParentModel(['id' => 2]),
            $this->createParentModel(['id' => 1]), // duplicate
            $this->createParentModel(['id' => 2]), // duplicate
        ];
        
        $keys = $this->invokeProtectedMethod($relation, 'getKeys', [$models, 'id']);
        
        $this->assertEquals([1, 2], $keys);
    }

    /**
     * Test getKeys with composite keys
     */
    public function testGetKeysWithCompositeKeys(): void
    {
        $relation = $this->createTestRelation();
        $models = [
            $this->createParentModel(['customer_id' => 2, 'seller_id' => 100]),
            $this->createParentModel(['customer_id' => 3, 'seller_id' => 101]),
        ];
        
        $keys = $this->invokeProtectedMethod(
            $relation, 
            'getKeys', 
            [$models, ['customer_id', 'seller_id']]
        );
        
        $this->assertCount(2, $keys);
        $this->assertEquals(['customer_id' => 2, 'seller_id' => 100], $keys[0]);
        $this->assertEquals(['customer_id' => 3, 'seller_id' => 101], $keys[1]);
    }

    /**
     * Test getKeys with composite keys filters null values
     */
    public function testGetKeysWithCompositeKeysFiltersNullValues(): void
    {
        $relation = $this->createTestRelation();
        $models = [
            $this->createParentModel(['customer_id' => 2, 'seller_id' => 100]),
            $this->createParentModel(['customer_id' => 3, 'seller_id' => null]), // has null
            $this->createParentModel(['customer_id' => 4, 'seller_id' => 102]),
        ];
        
        $keys = $this->invokeProtectedMethod(
            $relation, 
            'getKeys', 
            [$models, ['customer_id', 'seller_id']]
        );
        
        $this->assertCount(2, $keys);
        $this->assertEquals(['customer_id' => 2, 'seller_id' => 100], $keys[0]);
        $this->assertEquals(['customer_id' => 4, 'seller_id' => 102], $keys[1]);
    }

    /**
     * Test initRelation sets default value on models
     */
    public function testInitRelationSetsDefaultValue(): void
    {
        $relation = $this->createTestRelation();
        $models = [
            $this->createParentModel(['id' => 1]),
            $this->createParentModel(['id' => 2]),
        ];
        
        $result = $relation->initRelation($models, 'testRelation');
        
        $this->assertCount(2, $result);
        $this->assertNull($result[0]->getRelation('testRelation'));
        $this->assertNull($result[1]->getRelation('testRelation'));
    }

    /**
     * Test addConstraints does nothing (expected for API relations)
     */
    public function testAddConstraintsDoesNothing(): void
    {
        $relation = $this->createTestRelation();
        
        // Should not throw any exception
        $relation->addConstraints();
        
        $this->assertTrue(true);
    }

    /**
     * Test addEagerConstraints does nothing (expected for API relations)
     */
    public function testAddEagerConstraintsDoesNothing(): void
    {
        $relation = $this->createTestRelation();
        $models = [
            $this->createParentModel(['id' => 1]),
        ];
        
        // Should not throw any exception
        $relation->addEagerConstraints($models);
        
        $this->assertTrue(true);
    }

    /**
     * Test executeApiCallback throws exception when callback is null
     */
    public function testExecuteApiCallbackThrowsExceptionWhenNull(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API callback is not defined');
        
        $parent = $this->createParentModel(['id' => 1]);
        $relation = new TestApiRelation($parent, 'foreign_key', 'id', null);
        
        $this->invokeProtectedMethod($relation, 'executeApiCallback', [[1]]);
    }

    /**
     * Test executeApiCallback calls the callback correctly
     */
    public function testExecuteApiCallbackCallsCallback(): void
    {
        $called = false;
        $apiCallback = function (array $keys, $foreignKey) use (&$called) {
            $called = true;
            $this->assertEquals([1, 2, 3], $keys);
            $this->assertEquals('foreign_key', $foreignKey);
            return ['result'];
        };
        
        $parent = $this->createParentModel(['id' => 1]);
        $relation = new TestApiRelation($parent, 'foreign_key', 'id', $apiCallback);
        
        $result = $this->invokeProtectedMethod($relation, 'executeApiCallback', [[1, 2, 3]]);
        
        $this->assertTrue($called);
        $this->assertEquals(['result'], $result);
    }

    /**
     * Helper method to create a test relation instance
     */
    private function createTestRelation(): BaseApiRelation
    {
        $parent = $this->createParentModel(['id' => 1]);
        return new TestApiRelation($parent, 'foreign_key', 'id');
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
     * Helper method to invoke protected methods
     */
    private function invokeProtectedMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $parameters);
    }
}

/**
 * Concrete implementation of BaseApiRelation for testing
 */
class TestApiRelation extends BaseApiRelation
{
    protected function fetchFromApi($localKeyValue): mixed
    {
        return null;
    }

    public function match(array $models, Collection $results, $relation): array
    {
        return $models;
    }
}
