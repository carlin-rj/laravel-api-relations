# Laravel API Relations

[![Latest Version](https://img.shields.io/packagist/v/carlin/laravel-api-relations.svg)](https://packagist.org/packages/carlin/laravel-api-relations)
[![License](https://img.shields.io/packagist/l/carlin/laravel-api-relations.svg)](https://packagist.org/packages/carlin/laravel-api-relations)
[![PHP Version](https://img.shields.io/packagist/php-v/carlin/laravel-api-relations.svg)](https://packagist.org/packages/carlin/laravel-api-relations)

类 Eloquent 语法的 Laravel API 关系包，支持复合键和通过智能批量加载防止 N+1 问题。

[English](README.md) | 简体中文

## 特性

- 🚀 **类 Eloquent 语法** - 像定义数据库关系一样定义 API 关系
- 🔑 **复合键支持** - 处理具有多个键的复杂关系
- ⚡ **防止 N+1 问题** - 自动批量加载以获得最佳性能
- 🎯 **懒加载与预加载** - 完全支持两种加载策略
- 🔤 **不区分大小写匹配** - 可选的不区分大小写键匹配，实现灵活的 API 集成

## 环境要求

- PHP 8.1 或更高版本
- Laravel 8.x、9.x 或 10.x

## 安装

通过 Composer 安装包：

```bash
composer require carlin/laravel-api-relations
```

## 为什么使用这个包？

### 使用前：传统方式 ❌

在没有这个包的情况下，通常需要通过 Service 类来获取和附加 API 数据：

```php
// UserService.php
class UserService
{
    public function getUserWithProfile($userId)
    {
        $user = User::find($userId);
        
        // 从外部 API 获取用户资料
        $response = Http::post('https://api.example.com/profiles', [
            'user_ids' => [$userId]
        ]);
        $profiles = $response->json();
        $user->profile = $profiles[0] ?? null;
        
        return $user;
    }
    
    public function getUsersWithProfiles($userIds)
    {
        $users = User::whereIn('id', $userIds)->get();
        
        // 批量获取以避免 N+1
        $response = Http::post('https://api.example.com/profiles', [
            'user_ids' => $userIds
        ]);
        $profiles = collect($response->json())->keyBy('user_id');
        
        // 手动将资料附加到用户
        foreach ($users as $user) {
            $user->profile = $profiles->get($user->id);
        }
        
        return $users;
    }
}

// 控制器使用
class UserController extends Controller
{
    public function index(UserService $userService)
    {
        // 必须记得使用 service 方法
        $users = $userService->getUsersWithProfiles([1, 2, 3]);
        
        return view('users.index', compact('users'));
    }
    
    public function show($id, UserService $userService)
    {
        // 单个用户需要不同的方法
        $user = $userService->getUserWithProfile($id);
        
        return view('users.show', compact('user'));
    }
}
```

**存在的问题：**
- 🔴 需要单独的 Service 类来获取 API 数据
- 🔴 控制器必须记得使用特定的 service 方法
- 🔴 单条和多条记录需要不同的方法
- 🔴 每个 service 方法都需要手动附加数据
- 🔴 容易忘记批量加载，导致 N+1 问题
- 🔴 无法使用 Eloquent 的 `with()` 进行预加载
- 🔴 打破了 Eloquent 的约定和模式

### 使用后：Laravel API Relations ✅

API 关系就像 Eloquent 关系一样工作：

```php
// User.php
class User extends Model
{
    use HasApiRelations;
    
    public function profile()
    {
        return $this->hasOneApi(
            callback: fn($userIds) => Http::post('https://api.example.com/profiles', [
                'user_ids' => $userIds
            ])->json(),
            foreignKey: 'user_id',
            localKey: 'id'
        );
    }
    
    public function posts()
    {
        return $this->hasManyApi(
            callback: fn($userIds) => Http::post('https://api.example.com/posts', [
                'user_ids' => $userIds
            ])->json(),
            foreignKey: 'user_id',
            localKey: 'id'
        );
    }
}

// 控制器使用 - 就像普通 Eloquent 一样！
class UserController extends Controller
{
    public function index()
    {
        // 自动批量加载 - 所有用户只需一次 API 调用
        $users = User::with('profile', 'posts')->get();
        
        return view('users.index', compact('users'));
    }
    
    public function show($id)
    {
        // 懒加载 - 无缝工作
        $user = User::find($id);
        $profile = $user->profile()->getResults();
        
        return view('users.show', compact('user', 'profile'));
    }
}
```

**优势：**
- ✅ API 关系无需 Service 层
- ✅ 使用标准的 Eloquent `with()` 进行预加载
- ✅ 通过智能批处理自动防止 N+1 问题
- ✅ 与数据库关系保持一致的 API
- ✅ 单一模型定义适用于所有场景
- ✅ 控制器保持简洁并遵循 Laravel 约定
- ✅ 内置复合键支持

---

## 快速开始

### 1. 在模型中添加 Trait

```php
use Carlin\LaravelApiRelations\Traits\HasApiRelations;

class User extends Model
{
    use HasApiRelations;
    
    // 定义一对一 API 关系
    public function profile()
    {
        return $this->hasOneApi(
            callback: fn($userIds) => $this->fetchProfilesFromApi($userIds),
            foreignKey: 'user_id',
            localKey: 'id'
        );
    }
    
    // 定义一对多 API 关系
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
        // 调用外部 API
        $response = Http::post('https://api.example.com/profiles', [
            'user_ids' => $userIds
        ]);
        
        return $response->json();
    }
    
    private function fetchPostsFromApi(array $userIds): array
    {
        // 调用外部 API
        $response = Http::post('https://api.example.com/posts', [
            'user_ids' => $userIds
        ]);
        
        return $response->json();
    }
}
```

### 2. 像使用普通 Eloquent 关系一样使用

```php
// 懒加载（每个模型一次 API 调用）
$user = User::find(1);
$profile = $user->profile()->getResults();
$posts = $user->posts()->getResults();

// 预加载（所有模型只需一次批量 API 调用）
$users = User::with('profile', 'posts')->get();

foreach ($users as $user) {
    echo $user->profile['name'];
    foreach ($user->posts as $post) {
        echo $post['title'];
    }
}
```

## 高级用法

### 不区分大小写键匹配

默认情况下，键匹配是区分大小写的。当 API 键值可能存在不一致的大小写时，可以启用不区分大小写匹配：

```php
class User extends Model
{
    use HasApiRelations;
    
    public function profile()
    {
        return $this->hasOneApi(
            callback: fn($userIds) => Http::post('https://api.example.com/profiles', [
                'user_ids' => $userIds
            ])->json(),
            foreignKey: 'user_id',
            localKey: 'id',
            caseInsensitive: true  // 启用不区分大小写匹配
        );
    }
}

// 示例：模型有 user_code = 'ABC'
// API 返回的数据中 user_code = 'abc' 或 'Abc' 或 'ABC'
// 所有变体都能成功匹配
```

**何时使用不区分大小写匹配：**
- 外部 API 返回不一致的键大小写
- 传统系统使用混合大小写标识符
- 不区分大小写的数据库排序规则
- 多源数据集成

### 复合键

处理具有多个键字段的关系：

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

// 使用
$order = Order::find(1);
$details = $order->orderDetails()->getResults();
```

### API 回调格式

API 回调接收一个键数组，并应返回一个结果数组：

**对于 hasOneApi：**
```php
// 输入：[1, 2, 3]
// 输出：[
//     ['user_id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
//     ['user_id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
//     ['user_id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com'],
// ]
```

**对于 hasManyApi：**
```php
// 输入：[1, 2, 3]
// 输出：[
//     ['user_id' => 1, 'title' => 'Post 1'],
//     ['user_id' => 1, 'title' => 'Post 2'],
//     ['user_id' => 2, 'title' => 'Post 3'],
//     ['user_id' => 3, 'title' => 'Post 4'],
//     ['user_id' => 3, 'title' => 'Post 5'],
// ]
```

### N+1 问题预防示例

```php
// ❌ 不好：N+1 问题（100 个用户 = 100 次 API 调用）
$users = User::all();
foreach ($users as $user) {
    $profile = $user->profile()->getResults(); // 每个用户都会调用一次 API
}

// ✅ 推荐：批量加载（100 个用户 = 1 次 API 调用）
$users = User::with('profile')->get();
foreach ($users as $user) {
    $profile = $user->profile; // 不会额外调用 API
}
```

## 工作原理

1. **懒加载**：当你在单个模型上访问关系时，它使用该模型的键调用 API
2. **预加载**：当你使用 `with()` 时，它会收集所有模型的所有键并进行单次批量 API 调用
3. **复合键**：多个字段组合成关联数组并正确匹配
4. **结果匹配**：API 结果使用外键自动匹配回正确的模型

## API 参考

### hasOneApi

定义一对一 API 关系。

```php
public function hasOneApi(
    callable $apiCallback,
    string|array $foreignKey,
    string|array $localKey = 'id',
    bool $caseInsensitive = false
): HasOneApi
```

**参数：**
- `$apiCallback` - 接收键数组并返回 API 结果的函数
- `$foreignKey` - API 响应中要匹配的字段名
- `$localKey` - 本地模型中的字段名（默认为 'id'）
- `$caseInsensitive` - 启用不区分大小写键匹配（默认为 false）

**返回值：** 未找到匹配时返回 `null` 或数组

### hasManyApi

定义一对多 API 关系。

```php
public function hasManyApi(
    callable $apiCallback,
    string|array $foreignKey,
    string|array $localKey = 'id',
    bool $caseInsensitive = false
): HasManyApi
```

**参数：**
- `$apiCallback` - 接收键数组并返回 API 结果的函数
- `$foreignKey` - API 响应中要匹配的字段名
- `$localKey` - 本地模型中的字段名（默认为 'id'）
- `$caseInsensitive` - 启用不区分大小写键匹配（默认为 false）

**返回值：** 未找到匹配时返回空数组 `[]`

## 使用场景

非常适合以下场景：

- 从独立的认证服务获取用户资料
- 从外部目录 API 加载产品详情
- 从第三方系统检索订单信息
- 访问微服务数据时保持类 Eloquent 语法
- 使用复合键处理多租户关系

## 贡献

欢迎贡献！请随时提交 Pull Request。

## 许可证

该包是根据 [MIT 许可证](LICENSE) 授权的开源软件。

## 致谢

- [Carlin](https://github.com/carlin)

## 支持

如果您发现任何问题，请发送电子邮件至 rjwangnixingfu@gmail.com 或在 GitHub 上创建 issue。
