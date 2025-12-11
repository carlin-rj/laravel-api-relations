<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations\Tests;

use Carlin\LaravelApiRelations\Traits\HasApiRelations;
use Illuminate\Database\Eloquent\Model;

/**
 * Integration tests demonstrating real-world usage scenarios
 */
class IntegrationTest extends TestCase
{
    /**
     * Test complete workflow: lazy loading -> eager loading with N+1 prevention
     */
    public function testCompleteWorkflowWithN1Prevention(): void
    {
        // Simulate API call counter to verify N+1 prevention
        $apiCallCount = 0;
        
        // Create API callback that tracks calls
        $apiCallback = function (array $keys) use (&$apiCallCount) {
            $apiCallCount++;
            
            // Simulate fetching user profiles from external API
            $profiles = [
                1 => ['user_id' => 1, 'name' => 'John Doe', 'avatar' => 'john.jpg'],
                2 => ['user_id' => 2, 'name' => 'Jane Smith', 'avatar' => 'jane.jpg'],
                3 => ['user_id' => 3, 'name' => 'Bob Johnson', 'avatar' => 'bob.jpg'],
            ];
            
            $results = [];
            foreach ($keys as $key) {
                if (isset($profiles[$key])) {
                    $results[] = $profiles[$key];
                }
            }
            
            return $results;
        };
        
        // Create test models
        $users = [
            $this->createUserModel(['id' => 1], $apiCallback),
            $this->createUserModel(['id' => 2], $apiCallback),
            $this->createUserModel(['id' => 3], $apiCallback),
        ];
        
        // Test 1: Lazy loading (would cause N+1 problem without optimization)
        $apiCallCount = 0;
        foreach ($users as $user) {
            $profile = $user->profile()->getResults();
            $this->assertIsArray($profile);
        }
        // 3 API calls for 3 users (lazy loading)
        $this->assertEquals(3, $apiCallCount);
        
        // Test 2: Eager loading (prevents N+1 problem)
        $apiCallCount = 0;
        $relation = $users[0]->profile();
        $relation->initRelation($users, 'profile');
        $relation->match($users, new \Illuminate\Database\Eloquent\Collection([]), 'profile');
        
        // Only 1 API call for all 3 users (batch loading)
        $this->assertEquals(1, $apiCallCount);
        
        // Verify all users have their profiles loaded
        $this->assertEquals('John Doe', $users[0]->getRelation('profile')['name']);
        $this->assertEquals('Jane Smith', $users[1]->getRelation('profile')['name']);
        $this->assertEquals('Bob Johnson', $users[2]->getRelation('profile')['name']);
    }

    /**
     * Test has-many relationship with realistic scenario
     */
    public function testHasManyWithRealisticScenario(): void
    {
        // Simulate fetching comments from external API
        $apiCallback = function (array $postIds) {
            $allComments = [
                ['post_id' => 1, 'author' => 'Alice', 'text' => 'Great post!'],
                ['post_id' => 1, 'author' => 'Bob', 'text' => 'Thanks for sharing!'],
                ['post_id' => 2, 'author' => 'Charlie', 'text' => 'Interesting read.'],
                ['post_id' => 3, 'author' => 'David', 'text' => 'Love it!'],
                ['post_id' => 3, 'author' => 'Eve', 'text' => 'Awesome content!'],
                ['post_id' => 3, 'author' => 'Frank', 'text' => 'Keep it up!'],
            ];
            
            return array_filter($allComments, function($comment) use ($postIds) {
                return in_array($comment['post_id'], $postIds);
            });
        };
        
        $posts = [
            $this->createPostModel(['id' => 1], $apiCallback),
            $this->createPostModel(['id' => 2], $apiCallback),
            $this->createPostModel(['id' => 3], $apiCallback),
        ];
        
        // Eager load comments
        $relation = $posts[0]->comments();
        $relation->initRelation($posts, 'comments');
        $relation->match($posts, new \Illuminate\Database\Eloquent\Collection([]), 'comments');
        
        // Verify comment counts
        $this->assertCount(2, $posts[0]->getRelation('comments'));
        $this->assertCount(1, $posts[1]->getRelation('comments'));
        $this->assertCount(3, $posts[2]->getRelation('comments'));
        
        // Verify comment content
        $post1Comments = $posts[0]->getRelation('comments');
        $this->assertEquals('Alice', $post1Comments[0]['author']);
        $this->assertEquals('Bob', $post1Comments[1]['author']);
    }

    /**
     * Test composite key relationship with realistic scenario
     */
    public function testCompositeKeyWithRealisticScenario(): void
    {
        // Simulate multi-tenant order system where orders are identified by customer_id + order_number
        $apiCallback = function (array $keys) {
            $allOrders = [
                ['customer_id' => 2, 'order_number' => 'ORD-001', 'total' => 150.00, 'status' => 'shipped'],
                ['customer_id' => 3, 'order_number' => 'ORD-002', 'total' => 280.00, 'status' => 'processing'],
                ['customer_id' => 2, 'order_number' => 'ORD-003', 'total' => 95.50, 'status' => 'delivered'],
            ];
            
            $results = [];
            foreach ($keys as $compositeKey) {
                foreach ($allOrders as $order) {
                    if ($order['customer_id'] === $compositeKey['customer_id'] && 
                        $order['order_number'] === $compositeKey['order_number']) {
                        $results[] = $order;
                    }
                }
            }
            
            return $results;
        };
        
        $orderReferences = [
            $this->createOrderReferenceModel(
                ['customer_id' => 2, 'order_number' => 'ORD-001'], 
                $apiCallback
            ),
            $this->createOrderReferenceModel(
                ['customer_id' => 3, 'order_number' => 'ORD-002'], 
                $apiCallback
            ),
        ];
        
        // Eager load order details
        $relation = $orderReferences[0]->orderDetails();
        $relation->initRelation($orderReferences, 'orderDetails');
        $relation->match($orderReferences, new \Illuminate\Database\Eloquent\Collection([]), 'orderDetails');
        
        // Verify order details
        $this->assertEquals(150.00, $orderReferences[0]->getRelation('orderDetails')['total']);
        $this->assertEquals('shipped', $orderReferences[0]->getRelation('orderDetails')['status']);
        
        $this->assertEquals(280.00, $orderReferences[1]->getRelation('orderDetails')['total']);
        $this->assertEquals('processing', $orderReferences[1]->getRelation('orderDetails')['status']);
    }

    /**
     * Test handling of missing data gracefully
     */
    public function testHandleMissingDataGracefully(): void
    {
        $apiCallback = function (array $keys) {
            // Only return data for some keys
            return [
                ['user_id' => 1, 'name' => 'John'],
                ['user_id' => 3, 'name' => 'Bob'],
            ];
        };
        
        $users = [
            $this->createUserModel(['id' => 1], $apiCallback),
            $this->createUserModel(['id' => 2], $apiCallback), // No data for this user
            $this->createUserModel(['id' => 3], $apiCallback),
        ];
        
        $relation = $users[0]->profile();
        $relation->initRelation($users, 'profile');
        $relation->match($users, new \Illuminate\Database\Eloquent\Collection([]), 'profile');
        
        // User 1 and 3 have profiles, user 2 doesn't
        $this->assertNotNull($users[0]->getRelation('profile'));
        $this->assertNull($users[1]->getRelation('profile'));
        $this->assertNotNull($users[2]->getRelation('profile'));
    }

    /**
     * Helper: Create a user model with profile relationship
     */
    private function createUserModel(array $attributes, callable $apiCallback): Model
    {
        $model = new class extends Model {
            use HasApiRelations;
            
            protected $guarded = [];
            public $timestamps = false;
            private $apiCallback;
            
            public function setApiCallback(callable $callback)
            {
                $this->apiCallback = $callback;
            }
            
            public function profile()
            {
                return $this->hasOneApi($this->apiCallback, 'user_id', 'id');
            }
        };
        
        $model->setApiCallback($apiCallback);
        
        foreach ($attributes as $key => $value) {
            $model->setAttribute($key, $value);
        }
        
        return $model;
    }

    /**
     * Helper: Create a post model with comments relationship
     */
    private function createPostModel(array $attributes, callable $apiCallback): Model
    {
        $model = new class extends Model {
            use HasApiRelations;
            
            protected $guarded = [];
            public $timestamps = false;
            private $apiCallback;
            
            public function setApiCallback(callable $callback)
            {
                $this->apiCallback = $callback;
            }
            
            public function comments()
            {
                return $this->hasManyApi($this->apiCallback, 'post_id', 'id');
            }
        };
        
        $model->setApiCallback($apiCallback);
        
        foreach ($attributes as $key => $value) {
            $model->setAttribute($key, $value);
        }
        
        return $model;
    }

    /**
     * Helper: Create an order reference model with composite key relationship
     */
    private function createOrderReferenceModel(array $attributes, callable $apiCallback): Model
    {
        $model = new class extends Model {
            use HasApiRelations;
            
            protected $guarded = [];
            public $timestamps = false;
            private $apiCallback;
            
            public function setApiCallback(callable $callback)
            {
                $this->apiCallback = $callback;
            }
            
            public function orderDetails()
            {
                return $this->hasOneApi(
                    $this->apiCallback,
                    ['customer_id', 'order_number'],
                    ['customer_id', 'order_number']
                );
            }
        };
        
        $model->setApiCallback($apiCallback);
        
        foreach ($attributes as $key => $value) {
            $model->setAttribute($key, $value);
        }
        
        return $model;
    }
}
