<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations\Traits;

use Carlin\LaravelApiRelations\HasOneApi;
use Carlin\LaravelApiRelations\HasManyApi;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for defining API relationships on Eloquent models
 */
trait HasApiRelations
{
	/**
	 * Define a has-one API relationship
	 *
	 * @param callable $apiCallback API callback function that receives an array of keys and returns ['key' => data, ...]
	 * @param string|array $foreignKey Foreign key field name(s) in the API response data
	 * @param string|array $localKey Local key field name(s) in the current model
	 * @return HasOneApi
	 */
	public function hasOneApi(
		callable $apiCallback,
		string|array $foreignKey,
		string|array $localKey = 'id',
	): HasOneApi {
		return new HasOneApi($this, $foreignKey, $localKey, $apiCallback);
	}

	/**
	 * Define a has-many API relationship
	 *
	 * @param callable $apiCallback API callback function that receives an array of keys and returns ['key' => [items], ...]
	 * @param string|array $foreignKey Foreign key field name(s) in the API response data
	 * @param string|array $localKey Local key field name(s) in the current model
	 * @return HasManyApi
	 */
	public function hasManyApi(
		callable $apiCallback,
		string|array $foreignKey,
		string|array $localKey = 'id',
	): HasManyApi {
		return new HasManyApi($this, $foreignKey, $localKey, $apiCallback);
	}

	/**
	 * Create a new related model instance
	 */
	protected function newRelatedInstance($class): Model
	{
		return new class extends Model {
			protected $guarded = [];
			public $timestamps = false;
		};
	}
}
