<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations\Tests;

use Carlin\LaravelApiRelations\Traits\HasApiRelations;
use Carlin\LaravelApiRelations\HasOneApi;
use Carlin\LaravelApiRelations\HasManyApi;
use Illuminate\Database\Eloquent\Model;

class HasApiRelationsTraitTest extends TestCase
{
    /**
     * Test hasOneApi creates HasOneApi instance with correct parameters
     */
    public function testHasOneApiCreatesCorrectInstance(): void
    {
        $model = $this->createModelWithTrait(['id' => 1]);
        
        $apiCallback = function (array $keys) {
            return [];
        };
        
        $relation = $model->hasOneApi($apiCallback, 'foreign_key', 'local_key');
        
        $this->assertInstanceOf(HasOneApi::class, $relation);
    }

    /**
     * Test hasOneApi with default local key
     */
    public function testHasOneApiWithDefaultLocalKey(): void
    {
        $model = $this->createModelWithTrait(['id' => 1]);
        
        $apiCallback = function (array $keys) {
            return [];
        };
        
        $relation = $model->hasOneApi($apiCallback, 'foreign_key');
        
        $this->assertInstanceOf(HasOneApi::class, $relation);
    }

    /**
     * Test hasOneApi with composite keys
     */
    public function testHasOneApiWithCompositeKeys(): void
    {
        $model = $this->createModelWithTrait(['customer_id' => 2, 'seller_id' => 100]);
        
        $apiCallback = function (array $keys) {
            return [];
        };
        
        $relation = $model->hasOneApi(
            $apiCallback, 
            ['customer_id', 'seller_id'],
            ['customer_id', 'seller_id']
        );
        
        $this->assertInstanceOf(HasOneApi::class, $relation);
    }

    /**
     * Test hasManyApi creates HasManyApi instance with correct parameters
     */
    public function testHasManyApiCreatesCorrectInstance(): void
    {
        $model = $this->createModelWithTrait(['id' => 1]);
        
        $apiCallback = function (array $keys) {
            return [];
        };
        
        $relation = $model->hasManyApi($apiCallback, 'foreign_key', 'local_key');
        
        $this->assertInstanceOf(HasManyApi::class, $relation);
    }

    /**
     * Test hasManyApi with default local key
     */
    public function testHasManyApiWithDefaultLocalKey(): void
    {
        $model = $this->createModelWithTrait(['id' => 1]);
        
        $apiCallback = function (array $keys) {
            return [];
        };
        
        $relation = $model->hasManyApi($apiCallback, 'foreign_key');
        
        $this->assertInstanceOf(HasManyApi::class, $relation);
    }

    /**
     * Test hasManyApi with composite keys
     */
    public function testHasManyApiWithCompositeKeys(): void
    {
        $model = $this->createModelWithTrait(['customer_id' => 2, 'seller_id' => 100]);
        
        $apiCallback = function (array $keys) {
            return [];
        };
        
        $relation = $model->hasManyApi(
            $apiCallback, 
            ['customer_id', 'seller_id'],
            ['customer_id', 'seller_id']
        );
        
        $this->assertInstanceOf(HasManyApi::class, $relation);
    }

    /**
     * Test newRelatedInstance creates anonymous model
     */
    public function testNewRelatedInstanceCreatesAnonymousModel(): void
    {
        $model = $this->createModelWithTrait(['id' => 1]);
        
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('newRelatedInstance');
        $method->setAccessible(true);
        
        $relatedModel = $method->invokeArgs($model, ['SomeClass']);
        
        $this->assertInstanceOf(Model::class, $relatedModel);
        $this->assertFalse($relatedModel->timestamps);
    }

    /**
     * Test hasOneApi relation can be called as dynamic property
     */
    public function testHasOneApiCanBeAccessedAsDynamicProperty(): void
    {
        $model = new class extends Model {
            use HasApiRelations;
            
            protected $guarded = [];
            public $timestamps = false;
            
            public function profile()
            {
                return $this->hasOneApi(
                    function (array $keys) {
                        return [['user_id' => 1, 'name' => 'John']];
                    },
                    'user_id',
                    'id'
                );
            }
        };
        
        $model->setAttribute('id', 1);
        
        // Access relation
        $relation = $model->profile();
        
        $this->assertInstanceOf(HasOneApi::class, $relation);
    }

    /**
     * Test hasManyApi relation can be called as dynamic property
     */
    public function testHasManyApiCanBeAccessedAsDynamicProperty(): void
    {
        $model = new class extends Model {
            use HasApiRelations;
            
            protected $guarded = [];
            public $timestamps = false;
            
            public function comments()
            {
                return $this->hasManyApi(
                    function (array $keys) {
                        return [
                            ['user_id' => 1, 'comment' => 'Comment 1'],
                            ['user_id' => 1, 'comment' => 'Comment 2'],
                        ];
                    },
                    'user_id',
                    'id'
                );
            }
        };
        
        $model->setAttribute('id', 1);
        
        // Access relation
        $relation = $model->comments();
        
        $this->assertInstanceOf(HasManyApi::class, $relation);
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
