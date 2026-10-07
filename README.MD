# ApFrame

ApFrame 是一个用于学习 PHP MVC 框架设计的项目，参考了 Laravel、ThinkPHP 的部分用法。它包含路由、控制器、模板、模型、Session、CSRF 和命令行工具。

当前版本已修复核心安全、路由、控制器和模型中的一批问题，并提供隔离回归测试。它仍是学习型框架，示例 CRUD 方法不代表完整业务功能。

## 环境要求

- PHP 7.3 或以上；当前回归测试在 Windows / PHP 8.0.17 下验证。
- Composer，以及依赖要求的 PHP 扩展。
- MySQL：`mysqli`、`mysqlnd`；SQLite：`pdo_sqlite`；PostgreSQL：`pdo_pgsql`；SQL Server：`pdo_sqlsrv`。
- 使用 Redis Session 时需要 PHP Redis 扩展；文件 Session 不需要 Redis。
- Web 进程需要对 `runtime/` 具有读写权限。

框架的最低 PHP 要求不等于所有 Composer 依赖组合的最低要求，请以安装时的依赖检查为准。真实 MySQL、PostgreSQL、SQL Server、Redis 服务尚未纳入回归验证。

## 安装与启动

```powershell
git clone https://github.com/kkzyt/ApFrame.git
cd ApFrame
composer install
Copy-Item .set-default .set
```

Linux/macOS 可使用 `cp .set-default .set`。编辑 `.set`，将数据库账号等示例值替换为实际配置。`.set` 已被 Git 忽略，不应提交账号密码。

开发时可从 `public/` 启动 PHP 内置服务器：

```powershell
cd public
php -S 127.0.0.1:8000 index.php
```

访问 `http://127.0.0.1:8000/`。上述入口方式用于本地动态路由调试；实际部署应将站点根目录设为 `public/`，并配置请求重写到 `index.php`。不要将仓库根目录作为公开 Web 目录。

### 配置

`.set-default` 是起点，主要配置包括：

| 配置项 | 用途 |
| --- | --- |
| `NOW_USE_DB` | `mysql`、`sqlite`、`postgresql` 或 `mssql` |
| `MYSQL_*` | MySQL 主机、端口、账号、数据库和字符集 |
| `SQLITE_FILE` | `resource/database/` 下的数据库文件名 |
| `PGSQL_*` / `MSSQL_*` | PostgreSQL / SQL Server 连接配置，按 `config/databaseconfig.php` 补齐 |
| `SESSION_DRIVER` | `file`、`redis` 或 `mysql`，默认 `file` |
| `USE_SESSION` | 是否启用 Session；默认全局 CSRF 校验需要 Session |
| `REDIS_HOST` / `REDIS_PORT` | Redis Session 连接地址 |

配置读取器当前只支持由字母、数字、下划线和点组成的值，不支持通用 dotenv 的引号、空格或特殊字符语法。配置过的缓存也可能优先于 `.set`，修改配置时需要清理旧缓存。数据库表和业务迁移需要自行准备；MySQL Session 的 `session` 表需要唯一的 `key` 列以及 `value` 列。

## 目录结构

```text
ApFrame/
├─ apphp/                  框架代码和命令
│  └─ Core/                路由、控制器、模型、数据库、模板和响应
├─ application/            应用模块
│  ├─ test/Controller/     示例控制器
│  ├─ test/Model/          示例模型
│  └─ kernel.php           路由中间件别名和全局中间件
├─ bootstrap/              自动加载和配置缓存
├─ config/                 框架配置及服务注册
├─ public/                 Web 根目录和入口 index.php
├─ resource/view/          HTML 模板
├─ route/route.php         路由定义
├─ runtime/                模板缓存、Session 等运行文件
├─ tests/                  隔离回归测试
├─ .set-default            本地配置示例
├─ composer.json           依赖配置
└─ apcmd                   命令行入口
```

## 路由

在 `route/route.php` 注册路由：

```php
use apphp\Core\Route;

Route::get('/hello', function () {
    return 'Hello World';
});

Route::get('/users/{id}/posts/{post}', function ($id, $post) {
    return htmlspecialchars("User {$id}, post {$post}", ENT_QUOTES, 'UTF-8');
});

// 模块.控制器.方法
Route::get('/user/{name}', 'test.User.show');

Route::group(['prefix' => 'admin', 'middleware' => 'test'], function () {
    Route::get('/hello', function () { return 'Admin'; });
});
```

参数按出现顺序传入，不能为空；静态路由优先，路径前后斜杠统一处理，查询字符串不参与匹配。路由组可嵌套，`middleware` 可以是别名、类名或列表。别名在 `application/kernel.php` 中注册。

中间件应通过 `return $next()` 继续执行并保留响应结果。执行顺序为全局、外层组、内层组、单条路由中间件，随后调用处理函数，再按相反顺序返回。未知路径返回 404，不支持的方法返回 405；HEAD 可复用 GET 路由。

### REST 路由

```php
Route::restful('/resource', 'test.resource');
```

| 方法 | 路径 | 控制器方法 |
| --- | --- | --- |
| GET | `/resource` | `index` |
| GET | `/resource/create` | `create` |
| GET | `/resource/{id}` | `show` |
| GET | `/resource/edit/{id}` | `edit` |
| POST | `/resource` | `store` |
| PUT | `/resource/{id}` | `update` |
| DELETE | `/resource/{id}` | `delete` |

`Route::get('/resource', 'test.resource')` 只注册默认 `Index` 方法，不会生成 REST 路由。

## 控制器与模板

```php
namespace app\test\Controller;

use apphp\Core\Controller;
use apphp\Core\Request;

class Greeting extends Controller
{
    public function show(Request $request, $name)
    {
        return $this->view('greeting', ['name' => $name]);
    }

    public function moved()
    {
        $this->redirect('/hello', 302);
    }
}
```

`Request` 可以作为方法第一个参数注入。控制器操作必须是公开方法；构造函数支持可实例化的具体类依赖和默认参数，无法自动提供的标量或接口依赖会明确报错。

`view('greeting', ...)` 对应 `resource/view/greeting.html`；点分名称如 `user.profile` 对应子目录。模板返回 HTML，入口统一发送内容：

```html
<h1>Hello {{ $name }}</h1>
```

`{{ ... }}` 转义输出，`{!! ... !!}` 原样输出，仅用于可信内容。模板数据不能覆盖内部路径变量。模板更新后按修改时间重新编译；若部署保留了源文件时间，应主动清理模板缓存。

推荐返回字符串；入口兼容旧的 `echo` 处理函数，但同一内容不要同时输出和返回。数组需要先编码为 JSON/XML，响应类型头由应用设置。`redirect()` 会立即中断操作，由 Web 入口发送重定向；直接调用 `Init::exec()` 的程序需要处理 `Response\Redirect` 异常。

## 模型

```php
namespace app\test\Model;

use apphp\Core\Model;

class User extends Model
{
    protected $table = 'users';
    protected $primary_key = 'id';
}
```

以下示例假设业务数据库和 `users` 表已建立：

```php
$user = new \app\test\Model\User();
$rows = $user->where('name', "O'Reilly")->limit(10)->get();
$all = $user->getAll();
$found = $user->find(1); // 行列表，未找到时为 []

$user->name = 'Alice';
$user['email'] = 'alice@example.com';
$user->create();

$user->name = 'Updated';
$user->where('id', 1)->update();
$user->where('id', 1)->remove();
```

`get()`、`getAll()`、`find()` 保留数组返回格式。`getAll()` 按原有语义忽略条件和 LIMIT。每次执行后清理条件、LIMIT 和字段选择；创建、更新还会清理待写字段，包括执行失败时。失败后重试需重新设置字段及条件。

属性和数组访问共用字段存储；缺失字段返回 `null`，单行查询会加载当前记录属性。更新、删除必须显式提供条件，空数据不能创建或更新。所有内置驱动绑定值并校验标识符，不接受原始 WHERE 字符串；显式 SQL 接口应使用 `query($sql, $params)` / `exec($sql, $params)` 的占位符。

### 关联

在模型子类中定义关联方法：

```php
public function profile()
{
    return $this->hasOne(Profile::class, 'user_id');
}

public function posts()
{
    return $this->hasMany(Post::class, 'user_id');
}
```

相关类必须继承 `Model`。先通过 `find()` 加载当前记录，或显式设置本地主键，再调用关联。`hasOne` 返回单行或 `null`，`hasMany` 返回行列表；第三个参数可指定本地键。创建记录后不会自动获取数据库生成的 ID，关联前需重新加载或设置 ID。

## Session 与 CSRF

```php
session()->set('name', 'Alice');
$name = session()->get('name');
session()->set('token', 'value', 'Auth');
```

Session ID 使用随机值并校验格式；文件路径限定在 Session 目录。Cookie 使用 HttpOnly、SameSite=Lax，HTTPS 下启用 Secure。序列化对象不会恢复为可执行对象。

CSRF 中间件默认全局启用。先通过 GET 获取 Session 和 Token，表单在不安全请求中提交：

```html
<form method="post" action="/resource">
    <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
    <button type="submit">提交</button>
</form>
```

也可通过 `X-CSRF-Token` 请求头发送同一 Token。POST、PUT、DELETE 等不安全请求缺少或携带错误 Token 时返回 403。客户端需要保留 Session Cookie；JSON 请求可使用请求头传 Token。

## 命令行与测试

在仓库根目录运行 `php apcmd help` 查看命令。旧文档中的浏览器命令入口没有在当前路由表注册，请以命令行入口和实际配置为准。

```powershell
php tests/regression.php
php tests/routes.php
php tests/controllers.php
php tests/models.php
```

目前共 140 项检查：安全与数据库 34 项、路由 34 项、控制器 30 项、模型 42 项。测试使用内存 SQLite、独立临时目录及入口子进程，不连接业务数据库。控制器测试需要可用的 `proc_open` 和临时目录读写、重命名权限。

详细行为及兼容性变化见 [安全修复](SECURITY_FIXES.md)、[路由修复](ROUTING_FIXES.md)、[控制器修复](CONTROLLER_FIXES.md)、[模型修复](MODEL_FIXES.md)。
