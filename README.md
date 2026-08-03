# ThinkORM

基于PHP8.0+ 和PDO实现的ORM，支持多数据库，3.0版本主要特性包括：

* 基于PDO和PHP强类型实现
* 支持原生查询和查询构造器
* 自动参数绑定和预查询
* 简洁易用的查询功能
* 强大灵活的模型用法
* 支持预载入关联查询和延迟关联查询
* 支持多数据库及动态切换
* 支持分布式及事务
* 支持断点重连
* 支持`JSON`查询
* 支持数据库日志
* 支持`PSR-16`缓存及`PSR-3`日志规范


## 安装
~~~
composer require topthink/think-orm
~~~

## 文档

详细参考 [ThinkORM开发指南](https://doc.thinkphp.cn/@think-orm)

## 参与开发

### 单元测试编写

创建创建一个名为 UserInfo 的迁移文件（以测试单元为单位来创建迁移）  

```bash
./vendor/bin/phinx create UserInfo
```

### 迁移命令

下面相关命令都是 mysql 与 pgsql 同时执行，如果环境不完整可以通过 phinx 手动执行独立的迁移命令。  

#### 执行迁移（mysql、pgsql）

```bash
composer run db-migrate
```

#### 重建，先回滚在迁移（mysql、pgsql）

```bash
composer run db-rebuild
```

#### 回滚迁移（mysql、pgsql）

```bash
composer run db-rollback
```

#### 迁移状态（mysql、pgsql）

```bash
composer run db-status
```

### 环境问题

1. 如果提示 phinx 不存在，尝试手动执行`composer bin phinx install`安装。

## 字段加密功能（Encryption Trait）

基于 `SoftDelete` 同样的 Trait 设计模式，为 ThinkORM 模型提供**字段级自动加密/解密**能力。所有写操作自动加密，所有读操作自动解密，业务层无感。

### 依赖

- PHP 扩展：`ext-openssl`（默认已启用）

### 在模型中引入

```php
<?php

namespace app\model;

use think\Model;
use think\model\concern\Encryption; // 引入 Trait

class User extends Model
{
    use Encryption; // 加载加密能力

    // 【必填】声明哪些字段需要自动加密
    protected $encryptedFields = ['phone', 'idcard', 'secret'];

    // 【推荐】业务自定义密钥（32字符以上建议，生产务必改成自己的）推荐写在 .env 中不建议硬编码。
    // 示例：APP_EN_KEY=your-32-char-app-secret-key-123456
    protected $encryptKey = env('APP_EN_KEY');

    // 【可选】自定义加密算法，默认 AES-256-CBC
    // protected $encryptMethod = 'AES-256-CBC';

    // 【可选】自定义加密标记前缀，防止历史明文被误判。默认 'ENC:'
    // protected $encryptMarker = 'ENC:';
}
```

### 基本使用

写入时自动加密（对业务透明）：

```php
$user = new User();
$user->name  = '张三';             // 非加密字段，按原文存储
$user->phone = '13800138000';      // 属于 encryptedFields，自动加密为 ENC:xxx...
$user->save();

// 批量赋值同样自动加密
$user->data([
    'phone'  => '13800138000',
    'idcard' => '110101199001011234',
]);
$user->save();
```

读取时自动解密：

```php
$user = User::find(1);
echo $user->phone;                 // 输出明文：13800138000（getAttr 时自动解密）

$data = $user->toArray();          // toArray 同样是解密后的明文
```

### 支持的数据类型

| 类型      | 加密前处理       | 解密后还原       | 说明                                 |
|-----------|------------------|------------------|--------------------------------------|
| string    | 原样             | 原样（string）   | 数字字符串保持 string，不会错误变成 int |
| int/float | 转成 string      | string           | 业务层需要时自行强转                 |
| array     | json_encode      | 数组             | 支持嵌套结构                         |
| object    | json_encode      | 数组（assoc）    | stdClass 等会还原成关联数组           |
| null      | **不加密**       | **保持 null**    | 区分"空字符串"与"未填"               |
| Raw       | 不加密           | 不加密           | 如 `Db::raw('NOW()')`                |

### 设计要点

1. **随机 IV 保证安全**：使用 AES-256-CBC + `openssl_random_pseudo_bytes` 生成随机 IV，同一明文每次加密结果不同，防止基于密文的对比攻击。
2. **ENC: 标记前缀**：所有加密值以 `ENC:` 开头，配合 base64 判断实现：
   - 已加密的不会重复加密
   - 解密错误时直接返回原值，避免破坏数据
3. **解密缓存**：同一请求周期内同一字段多次 `getAttr` 只解密一次，存放在 `static $decryptCache` 中。
4. **无 SQL 加密函数**：加密/解密完全在 PHP 层执行，数据库只看到 `ENC:base64(...)`，数据库日志/主从同步不会泄漏明文。

### 运行时 API

```php
// 动态更换加密字段列表（比如分场景使用不同字段集）
$user->setEncryptedFields(['phone']);

// 获取当前加密字段
$fields = $user->getEncryptedFields();

// 运行时更换密钥（用于数据迁移/导入）
$user->setEncryptKey('other-key');
```

### 扩展 / 重写

`Encryption` trait 中所有关键方法都是 `protected`，可以在模型里 override：

```php
class User extends Model
{
    use Encryption;
    protected $encryptedFields = ['secret'];

    // 例：密钥从配置中心/环境变量读取，而非写死在类里
    protected function defaultEncryptKey(): string
    {
        return env('APP_ENCRYPT_KEY') ?: Config::get('app.encrypt_key');
    }

    // 例：换成其他加密方案（如 Sodium、第三方 KMS 接口等）
    // protected function encryptValue($value): string { ... }
    // protected function decryptValue($value): mixed  { ... }
}
```

### 注意事项 / 限制

1. **加密字段不支持 WHERE 精确匹配**：同一明文加密结果每次不同，`where('phone', '13800138000')` 无法命中。需要查询时推荐做法：
   - 增加一个 `phone_hash` 字段存储 `hash_hmac('sha256', $phone, $key)`，按 hash 查询；
   - 或业务层查出候选集后在 PHP 中过滤。
2. **排序/模糊搜索不支持**：加密后不可逆比较，业务设计时请规避把加密字段作为排序/`LIKE` 条件。
3. **密钥丢失 = 数据丢失**：`encryptKey` 不要随意更改。如需轮换密钥，需按「读旧密钥解密→用新密钥重新加密→写回」的流程批量迁移。
4. **PHP 序列化数据不建议直接加密**：数组/对象内部请仅包含可 JSON 序列化的类型（不能有 GMP、PDO、Closure 等）。
5. **字段长度**：AES-256-CBC + Base64 后密文长度约为 `(原始长度 + 16) / 16 * 16 + 16` 再 Base64（约 +40%），数据库列长度预留充足（如 `VARCHAR(1024)` 或 `TEXT`）。

### 单元测试

```bash
# 单独运行 Encryption trait 的 32 个单元测试（不需要数据库）
./vendor/bin/phpunit --testsuite Unit --filter EncryptionTest
```