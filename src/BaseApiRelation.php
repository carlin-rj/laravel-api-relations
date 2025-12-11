<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Base class for API relationships
 * Fetches related data from external APIs with batch query support to prevent N+1 problems
 */
abstract class BaseApiRelation extends Relation
{
    /**
     * Foreign key field name(s)
     */
    protected string|array $foreignKey;

    /**
     * Local key field name(s)
     */
    protected string|array $localKey;

    /**
     * API callback function (receives array parameters)
     * @var callable|null
     */
    protected $apiCallback;

    /**
     * Whether to perform case-insensitive key matching
     */
    protected bool $caseInsensitive = false;

    public function __construct(
        Model $parent,
        string|array $foreignKey,
        string|array $localKey,
        ?callable $apiCallback = null,
        bool $caseInsensitive = false
    ) {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->apiCallback = $apiCallback;
        $this->caseInsensitive = $caseInsensitive;

        // Query builder not needed, use an empty Query Builder
        parent::__construct($parent->newQuery(), $parent);
    }

    /**
     * Add constraints for the relationship (not needed for API relationships)
     */
    public function addConstraints(): void
    {
        // API relationships don't need database constraints
    }

    /**
     * Add constraints for eager loading (API relationships implement batch loading)
     */
    public function addEagerConstraints(array $models): void
    {
        // Mark as eager loading mode, batch query will be executed later
    }

    /**
     * Initialize the relation on a set of models
     */
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /**
     * Get the default value for the relationship
     */
    protected function getDefaultFor(Model $parent)
    {
        return null;
    }

    /**
     * Generate a dictionary key
     * Converts composite key array to string key for dictionary lookup
     *
     * @param array|string|int $keyValue
     * @return string
     */
    protected function makeDictionaryKey($keyValue): string
    {
        if (is_array($keyValue)) {
            // For composite keys, normalize each value if case-insensitive
            $normalized = $this->caseInsensitive 
                ? array_map(fn($v) => is_string($v) ? strtolower($v) : $v, $keyValue)
                : $keyValue;
            return md5(serialize($normalized));
        }
        
        // For single keys, normalize if case-insensitive
        $normalized = ($this->caseInsensitive && is_string($keyValue)) 
            ? strtolower($keyValue) 
            : $keyValue;
        return (string)$normalized;
    }

    /**
     * Fetch data from API
     */
    abstract protected function fetchFromApi($localKeyValue): mixed;

    /**
     * Get relationship query results (lazy loading)
     */
    public function getResults()
    {
        // Support composite keys
        if (is_array($this->localKey)) {
            $localKeyValue = $this->getCompositeKeyValue($this->parent, $this->localKey);
        } else {
            $localKeyValue = $this->parent->getAttribute($this->localKey);
        }

        if ($localKeyValue === null) {
            return $this->getDefaultFor($this->parent);
        }

        return $this->fetchFromApi($localKeyValue);
    }

    /**
     * Execute API callback
     * @param array $localKeyValues Array of local key values
     * @return array
     */
    protected function executeApiCallback(array $localKeyValues): array
    {
        if ($this->apiCallback === null) {
            throw new \RuntimeException('API callback is not defined');
        }
        return call_user_func($this->apiCallback, $localKeyValues, $this->foreignKey);
    }

    /**
     * Get list of key values from models
     * Supports single keys and composite keys
     */
	protected function getKeys(array $models, $key = null)
    {
        // Composite key handling
        if (is_array($key)) {
            return collect($models)
                ->map(function ($model) use ($key) {
                    return $this->getCompositeKeyValue($model, $key);
                })
                ->filter(function($value) {
                    return $value !== null;
                })
                ->unique(function($item) {
                    return json_encode($item);
                })
                ->values()
                ->all();
        }

        // Single key handling
        return collect($models)
            ->pluck($key)
            ->unique()
            ->filter(function($value) {
                return $value !== null;
            })
            ->values()
            ->all();
    }

    /**
     * Get composite key value from a model
     * Combines multiple field values into an associative array
     * Example: Model(['customer_id' => 2, 'seller_id' => 100]) => ['customer_id' => 2, 'seller_id' => 100]
     *
     * @param Model $model
     * @param array $keys Array of field names
     * @return array|null
     */
    protected function getCompositeKeyValue(Model $model, array $keys): ?array
    {
        $values = [];
        foreach ($keys as $key) {
            $value = $model->getAttribute($key);
            if ($value === null) {
                return null; // If any field is null, the entire composite key is invalid
            }
            $values[$key] = $value;
        }
        return $values;
    }

    /**
     * Extract composite key value from data
     * Extracts and combines composite key field values from API response data
     *
     * @param array $data Single data item from API response
     * @param array $keys Array of composite key field names
     * @return array|null
     */
    protected function getCompositeKeyValueFromData(array $data, array $keys): ?array
    {
        $values = [];
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                return null; // If any field is missing, the entire composite key is invalid
            }
            $values[$key] = $data[$key];
        }
        return $values;
    }

    /**
     * Match eager loaded results to models (must be implemented by subclasses)
     */
    abstract public function match(array $models, Collection $results, $relation): array;

    /**
     * Get eager loaded results (batch query)
     */
    public function getEager(): Collection
    {
        // Subclass implements batch query logic
        return $this->get();
    }

	/**
	 * Get relationship results (supports batch and single queries)
	 */
	public function get($columns = ['*']): Collection
	{
		// If in eager loading mode, handled by initRelation and match
		// Return empty collection here, actual data is filled in match
		return $this->related->newCollection();
	}
}
