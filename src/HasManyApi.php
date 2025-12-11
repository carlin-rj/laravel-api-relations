<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Has-many API relationship
 */
class HasManyApi extends BaseApiRelation
{
	/**
	 * Get default value (has-many returns empty array)
	 */
	protected function getDefaultFor(Model $parent)
	{
		return [];
	}

	/**
	 * Fetch multiple data items from API (lazy loading)
	 */
	protected function fetchFromApi($localKeyValue): mixed
	{
		// For lazy loading, pass a single value in array
		$apiResults = $this->executeApiCallback([$localKeyValue]);

		// Support composite keys
		if (is_array($this->foreignKey)) {
			$dictionary = collect($apiResults)->groupBy(function($item) {
				$key = $this->getCompositeKeyValueFromData($item, $this->foreignKey);
				return $this->makeDictionaryKey($key);
			})->toArray();
		} else {
			$dictionary = collect($apiResults)->groupBy(function($item) {
				return $this->makeDictionaryKey($item[$this->foreignKey] ?? null);
			})->toArray();
		}

		return $dictionary[$this->makeDictionaryKey($localKeyValue)] ?? [];
	}

	/**
	 * Match eager loaded results to models (batch query core logic)
	 */
	public function match(array $models, Collection $results, $relation): array
	{
		// Collect all key values to query
		$keys = $this->getKeys($models, $this->localKey);

		if (empty($keys)) {
			return $models;
		}

		// Batch call API (pass array)
		$apiResults = $this->executeApiCallback($keys);

		// Support composite keys
		if (is_array($this->foreignKey)) {
			$dictionary = collect($apiResults)->groupBy(function($item) {
				$key = $this->getCompositeKeyValueFromData($item, $this->foreignKey);
				return $this->makeDictionaryKey($key);
			})->toArray();
		} else {
			$dictionary = collect($apiResults)->groupBy(function($item) {
				return $this->makeDictionaryKey($item[$this->foreignKey] ?? null);
			})->toArray();
		}

		// Match results to corresponding models
		foreach ($models as $model) {
			// Support composite keys
			if (is_array($this->localKey)) {
				$key = $this->getCompositeKeyValue($model, $this->localKey);
			} else {
				$key = $model->getAttribute($this->localKey);
			}

			$model->setRelation($relation, $dictionary[$this->makeDictionaryKey($key)] ?? []);
		}

		return $models;
	}
}
