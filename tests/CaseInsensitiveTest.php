<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations\Tests;

use Carlin\LaravelApiRelations\Traits\HasApiRelations;
use Illuminate\Database\Eloquent\Model;

class CaseInsensitiveTest extends TestCase
{
    /**
     * Test case-sensitive matching (default behavior)
     */
    public function testCaseSensitiveMatchingByDefault(): void
    {
        $model = $this->createModelWithTrait(['id' => 1]);

        $apiCallback = function (array $keys) {
            return [
                ['user_id' => 1, 'name' => 'John'],
                ['user_id' => 2, 'name' => 'Jane'],
            ];
        };

        $relation = $model->hasOneApi($apiCallback, 'user_id', 'id');

        // Should match exact case
        $result = $relation->getResults();
        $this->assertEquals('John', $result['name']);
    }

    /**
     * Test case-sensitive matching does NOT match different cases
     */
    public function testCaseSensitiveDoesNotMatchDifferentCases(): void
    {
        $model = $this->createModelWithTrait(['user_key' => 'ABC']);

        $apiCallback = function (array $keys) {
            // API returns lowercase 'abc'
            return [
                ['key' => 'abc', 'value' => 'lowercase'],
                ['key' => 'def', 'value' => 'other'],
            ];
        };

        // Case-sensitive (default) - should NOT match 'ABC' with 'abc'
        $relation = $model->hasOneApi($apiCallback, 'key', 'user_key', caseInsensitive: false);
        $result = $relation->getResults();

        $this->assertNull($result);
    }

    /**
     * Test case-insensitive matching for has-one relationship
     */
    public function testCaseInsensitiveHasOneMatching(): void
    {
        $model = $this->createModelWithTrait(['user_key' => 'ABC']);

        $apiCallback = function (array $keys) {
            // API returns lowercase 'abc'
            return [
                ['key' => 'abc', 'value' => 'matched'],
                ['key' => 'def', 'value' => 'other'],
            ];
        };

        // Enable case-insensitive matching
        $relation = $model->hasOneApi($apiCallback, 'key', 'user_key', caseInsensitive: true);
        $result = $relation->getResults();

        $this->assertNotNull($result);
        $this->assertEquals('matched', $result['value']);
    }

    /**
     * Test case-insensitive matching for has-many relationship
     */
    public function testCaseInsensitiveHasManyMatching(): void
    {
        $model = $this->createModelWithTrait(['user_key' => 'ABC']);

        $apiCallback = function (array $keys) {
            return [
                ['key' => 'abc', 'title' => 'Post 1'],
                ['key' => 'ABC', 'title' => 'Post 2'],
                ['key' => 'Abc', 'title' => 'Post 3'],
                ['key' => 'def', 'title' => 'Other'],
            ];
        };

        $relation = $model->hasManyApi($apiCallback, 'key', 'user_key', caseInsensitive: true);
        $results = $relation->getResults();

        // Should match all variations of 'ABC', 'abc', 'Abc'
        $this->assertCount(3, $results);
        $this->assertEquals('Post 1', $results[0]['title']);
        $this->assertEquals('Post 2', $results[1]['title']);
        $this->assertEquals('Post 3', $results[2]['title']);
    }

    /**
     * Test case-insensitive eager loading for has-one
     */
    public function testCaseInsensitiveEagerLoadingHasOne(): void
    {
        $models = [
            $this->createModelWithTrait(['id' => 1, 'code' => 'ABC']),
            $this->createModelWithTrait(['id' => 2, 'code' => 'def']),
            $this->createModelWithTrait(['id' => 3, 'code' => 'XYZ']),
        ];

        $apiCallback = function (array $keys) {
            // API returns mixed case
            return [
                ['code' => 'abc', 'name' => 'First'],
                ['code' => 'DEF', 'name' => 'Second'],
                ['code' => 'xyz', 'name' => 'Third'],
            ];
        };

        $relation = $models[0]->hasOneApi($apiCallback, 'code', 'code', caseInsensitive: true);
        $relation->addEagerConstraints($models);

        $results = new \Illuminate\Database\Eloquent\Collection();
        $matched = $relation->match($models, $results, 'profile');

        $this->assertEquals('First', $matched[0]->profile['name']);
        $this->assertEquals('Second', $matched[1]->profile['name']);
        $this->assertEquals('Third', $matched[2]->profile['name']);
    }

    /**
     * Test case-insensitive eager loading for has-many
     */
    public function testCaseInsensitiveEagerLoadingHasMany(): void
    {
        $models = [
            $this->createModelWithTrait(['id' => 1, 'code' => 'ABC']),
            $this->createModelWithTrait(['id' => 2, 'code' => 'DEF']),
        ];

        $apiCallback = function (array $keys) {
            return [
                ['code' => 'abc', 'title' => 'Post 1'],
                ['code' => 'Abc', 'title' => 'Post 2'],
                ['code' => 'def', 'title' => 'Post 3'],
            ];
        };

        $relation = $models[0]->hasManyApi($apiCallback, 'code', 'code', caseInsensitive: true);
        $relation->addEagerConstraints($models);

        $results = new \Illuminate\Database\Eloquent\Collection();
        $matched = $relation->match($models, $results, 'posts');

        $this->assertCount(2, $matched[0]->posts);
        $this->assertEquals('Post 1', $matched[0]->posts[0]['title']);
        $this->assertEquals('Post 2', $matched[0]->posts[1]['title']);

        $this->assertCount(1, $matched[1]->posts);
        $this->assertEquals('Post 3', $matched[1]->posts[0]['title']);
    }

    /**
     * Test case-insensitive with composite keys
     */
    public function testCaseInsensitiveWithCompositeKeys(): void
    {
        $model = $this->createModelWithTrait([
            'customer_id' => 'ABC',
            'order_number' => 'ORD-001',
        ]);

        $apiCallback = function (array $keys) {
            return [
                [
                    'customer_id' => 'abc',
                    'order_number' => 'ord-001',
                    'amount' => 100,
                ],
                [
                    'customer_id' => 'def',
                    'order_number' => 'ord-002',
                    'amount' => 200,
                ],
            ];
        };

        $relation = $model->hasOneApi(
            $apiCallback,
            ['customer_id', 'order_number'],
            ['customer_id', 'order_number'],
            caseInsensitive: true
        );

        $result = $relation->getResults();

        $this->assertNotNull($result);
        $this->assertEquals(100, $result['amount']);
    }

    /**
     * Test case-insensitive with integer keys (should not affect)
     */
    public function testCaseInsensitiveWithIntegerKeys(): void
    {
        $model = $this->createModelWithTrait(['id' => 123]);

        $apiCallback = function (array $keys) {
            return [
                ['user_id' => 123, 'name' => 'John'],
                ['user_id' => 456, 'name' => 'Jane'],
            ];
        };

        // Integer keys should work the same with case-insensitive enabled
        $relation = $model->hasOneApi($apiCallback, 'user_id', 'id', caseInsensitive: true);
        $result = $relation->getResults();

        $this->assertEquals('John', $result['name']);
    }

    /**
     * Test case-insensitive with mixed types in composite keys
     */
    public function testCaseInsensitiveWithMixedTypesInCompositeKeys(): void
    {
        $model = $this->createModelWithTrait([
            'user_id' => 100,
            'code' => 'ABC',
        ]);

        $apiCallback = function (array $keys) {
            return [
                [
                    'user_id' => 100,
                    'code' => 'abc',
                    'status' => 'active',
                ],
            ];
        };

        $relation = $model->hasOneApi(
            $apiCallback,
            ['user_id', 'code'],
            ['user_id', 'code'],
            caseInsensitive: true
        );

        $result = $relation->getResults();

        $this->assertNotNull($result);
        $this->assertEquals('active', $result['status']);
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
