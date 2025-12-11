# Laravel API Relations

[![Latest Version](https://img.shields.io/packagist/v/carlin/laravel-api-relations.svg)](https://packagist.org/packages/carlin/laravel-api-relations)
[![License](https://img.shields.io/packagist/l/carlin/laravel-api-relations.svg)](https://packagist.org/packages/carlin/laravel-api-relations)
[![PHP Version](https://img.shields.io/packagist/php-v/carlin/laravel-api-relations.svg)](https://packagist.org/packages/carlin/laravel-api-relations)

Eloquent-like API relationships for Laravel with composite key support and N+1 prevention through intelligent batch loading.

English | [简体中文](README_CN.md)

## Features

- 🚀 **Eloquent-like syntax** - Define API relationships just like database relationships
- 🔑 **Composite key support** - Handle complex relationships with multiple keys
- ⚡ **N+1 prevention** - Automatic batch loading for optimal performance
- 🎯 **Lazy & Eager loading** - Full support for both loading strategies

## Requirements

- PHP 8.1 or higher
- Laravel 8.x, 9.x, or 10.x

## Installation

Install the package via Composer:

```bash
composer require carlin/laravel-api-relations
```

## Quick Start

### 1. Add the Trait to Your Model

```php
use Carlin\LaravelApiRelations\Traits\HasApiRelations;

class User extends Model
{
    use HasApiRelations;
    
    // Define a has-one API relationship
    public function profile()
    {
        return $this->hasOneApi(
            callback: fn($userIds) => $this->fetchProfilesFromApi($userIds),
            foreignKey: 'user_id',
            localKey: 'id'
        );
    }
    
    // Define a has-many API relationship
    public function posts()
    {
        return $this->hasManyApi(
            callback: fn($userIds) => $this->fetchPostsFromApi($userIds),
            foreignKey: 'user_id',
            localKey: 'id'
        );
    }
    
    private function fetchProfilesFromApi(array $userIds): array
    {
        // Call your external API
        $response = Http::post('https://api.example.com/profiles', [
            'user_ids' => $userIds
        ]);
        
        return $response->json();
    }
    
    private function fetchPostsFromApi(array $userIds): array
    {
        // Call your external API
        $response = Http::post('https://api.example.com/posts', [
            'user_ids' => $userIds
        ]);
        
        return $response->json();
    }
}
```

### 2. Use Like Regular Eloquent Relationships

```php
// Lazy loading (single API call per model)
$user = User::find(1);
$profile = $user->profile()->getResults();
$posts = $user->posts()->getResults();

// Eager loading (single batched API call for all models)
$users = User::with('profile', 'posts')->get();

foreach ($users as $user) {
    echo $user->profile['name'];
    foreach ($user->posts as $post) {
        echo $post['title'];
    }
}
```

## Advanced Usage

### Composite Keys

Handle relationships with multiple key fields:

```php
class Order extends Model
{
    use HasApiRelations;
    
    public function orderDetails()
    {
        return $this->hasOneApi(
            callback: fn($keys) => $this->fetchOrderDetails($keys),
            foreignKey: ['customer_id', 'order_number'],
            localKey: ['customer_id', 'order_number']
        );
    }
    
    private function fetchOrderDetails(array $compositeKeys): array
    {
        // $compositeKeys = [
        //     ['customer_id' => 1, 'order_number' => 'ORD-001'],
        //     ['customer_id' => 2, 'order_number' => 'ORD-002'],
        // ]
        
        $response = Http::post('https://api.example.com/order-details', [
            'keys' => $compositeKeys
        ]);
        
        return $response->json();
    }
}

// Usage
$order = Order::find(1);
$details = $order->orderDetails()->getResults();
```

### API Callback Format

Your API callback receives an array of keys and should return an array of results:

**For hasOneApi:**
```php
// Input: [1, 2, 3]
// Output: [
//     ['user_id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
//     ['user_id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
//     ['user_id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com'],
// ]
```

**For hasManyApi:**
```php
// Input: [1, 2, 3]
// Output: [
//     ['user_id' => 1, 'title' => 'Post 1'],
//     ['user_id' => 1, 'title' => 'Post 2'],
//     ['user_id' => 2, 'title' => 'Post 3'],
//     ['user_id' => 3, 'title' => 'Post 4'],
//     ['user_id' => 3, 'title' => 'Post 5'],
// ]
```

### N+1 Prevention Example

```php
// ❌ BAD: N+1 problem (100 users = 100 API calls)
$users = User::all();
foreach ($users as $user) {
    $profile = $user->profile()->getResults(); // API call per user
}

// ✅ GOOD: Batch loading (100 users = 1 API call)
$users = User::with('profile')->get();
foreach ($users as $user) {
    $profile = $user->profile; // No additional API calls
}
```

## How It Works

1. **Lazy Loading**: When you access a relationship on a single model, it calls the API with that model's key
2. **Eager Loading**: When you use `with()`, it collects all keys from all models and makes a single batched API call
3. **Composite Keys**: Multiple fields are combined into an associative array and properly matched
4. **Result Matching**: API results are automatically matched back to the correct models using the foreign key

## API Reference

### hasOneApi

Define a has-one API relationship.

```php
public function hasOneApi(
    callable $apiCallback,
    string|array $foreignKey,
    string|array $localKey = 'id'
): HasOneApi
```

**Parameters:**
- `$apiCallback` - Function that receives array of keys and returns API results
- `$foreignKey` - Field name(s) in the API response to match against
- `$localKey` - Field name(s) in the local model (defaults to 'id')

**Returns:** `null` or array when no match found

### hasManyApi

Define a has-many API relationship.

```php
public function hasManyApi(
    callable $apiCallback,
    string|array $foreignKey,
    string|array $localKey = 'id'
): HasManyApi
```

**Parameters:**
- `$apiCallback` - Function that receives array of keys and returns API results
- `$foreignKey` - Field name(s) in the API response to match against
- `$localKey` - Field name(s) in the local model (defaults to 'id')

**Returns:** Empty array `[]` when no matches found

## Use Cases

Perfect for scenarios where you need to:

- Fetch user profiles from a separate authentication service
- Load product details from an external catalog API
- Retrieve order information from a third-party system
- Access microservice data while maintaining Eloquent-like syntax
- Handle multi-tenant relationships with composite keys

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

- [Carlin-rj](https://github.com/carlin-rj)

## Support

If you discover any issues, please email rjwangnixingfu@gmail.com or create an issue on GitHub.
