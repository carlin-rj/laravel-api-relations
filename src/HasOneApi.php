<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations;

use Illuminate\Database\Eloquent\Collection;

/**
 * Has-one API relationship
 */
class HasOneApi extends BaseApiRelation
{
	/**
	 * Fetch single data item from API (lazy loading)
	 */
	protected function fetchFromApi($localKeyValue): mixed
	{
		// For lazy loading, pass a single value in array
		$apiResults = $this->executeApiCallback([$localKeyValue]);

		// Support composite keys
		if (is_array($this->foreignKey)) {
			$dictionary = collect($apiResults)->mapWithKeys(function($item) {
				$key = $this->getCompositeKeyValueFromData($item, $this->foreignKey);
				return [$this->makeDictionaryKey($key) => $item];
			})->toArray();
		} else {
			$dictionary = collect($apiResults)->keyBy($this->foreignKey)->toArray();
		}

		return $dictionary[$this->makeDictionaryKey($localKeyValue)] ?? null;
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
			$dictionary = collect($apiResults)->mapWithKeys(function($item) {
				$key = $this->getCompositeKeyValueFromData($item, $this->foreignKey);
				return [$this->makeDictionaryKey($key) => $item];
			})->toArray();
		} else {
			$dictionary = collect($apiResults)->keyBy($this->foreignKey)->toArray();
		}

		// Match results to corresponding models
		foreach ($models as $model) {
			// Support composite keys
			if (is_array($this->localKey)) {
				$key = $this->getCompositeKeyValue($model, $this->localKey);
			} else {
				$key = $model->getAttribute($this->localKey);
			}

			if (isset($dictionary[$this->makeDictionaryKey($key)])) {
				$model->setRelation($relation, $dictionary[$this->makeDictionaryKey($key)]);
			} else {
				$model->setRelation($relation, null);
			}
		}

		return $models;
	}
}
