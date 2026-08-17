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
* **支持乐观锁（Optimistic Lock）** 处理并发更新冲突


## 安装
~~~
composer require watsonhaw/think-orm
~~~

## 文档

详细参考 [ThinkORM开发指南](https://doc.thinkphp.cn/@think-orm)

## 乐观锁使用指南

乐观锁用于处理并发场景下的数据更新冲突问题。其原理是通过在数据表中增加一个版本号字段，
在更新数据时校验版本号是否一致，如果不一致则抛出异常，表示数据已被其他进程修改。

### 数据库准备

首先需要在数据表中添加版本号字段（字段名可自定义，默认为 `lock_version`）：

```sql
-- 使用默认字段名 lock_version
ALTER TABLE `your_table` ADD COLUMN `lock_version` INT NOT NULL DEFAULT 0 COMMENT '乐观锁版本号';

-- 或者使用自定义字段名，比如 version
ALTER TABLE `your_table` ADD COLUMN `version` INT NOT NULL DEFAULT 0 COMMENT '乐观锁版本号';
```

### 模型中使用乐观锁

在模型类中引入 `OptimLock` trait：

```php
<?php
namespace app\model;

use think\Model;
use think\model\concern\OptimLock;

class User extends Model
{
    use OptimLock;

    // 可选：自定义锁字段名，默认是 lock_version
    // protected $optimLock = 'version';

    protected $table = 'user';
    protected $pk = 'id';
}
```

### 基本使用

#### 创建记录

创建新记录时，乐观锁版本号会自动初始化为 0：

```php
$user = User::create([
    'name'  => '张三',
    'email' => 'zhangsan@example.com',
]);

echo $user->lock_version; // 输出: 0
```

#### 更新记录

每次成功更新记录后，版本号会自动 +1：

```php
// 读取记录
$user = User::find(1);
echo $user->lock_version; // 输出: 0

// 修改并保存
$user->name = '李四';
$user->save();

echo $user->lock_version; // 输出: 1

// 再次读取验证
$fresh = User::find(1);
echo $fresh->lock_version; // 输出: 1
```

### 处理并发冲突

当两个进程同时读取同一条记录并尝试更新时，先更新成功的进程会使版本号 +1，
后更新的进程会因为版本号不匹配而抛出 `OptimLockException` 异常：

```php
use think\db\exception\OptimLockException;

// 进程1读取记录
$user1 = User::find(1);

// 进程2读取同一条记录
$user2 = User::find(1);

// 进程1先更新
$user1->name = '进程1修改';
$user1->save(); // 成功，版本号变为 1

// 进程2尝试更新
try {
    $user2->name = '进程2修改';
    $user2->save();
} catch (OptimLockException $e) {
    // 捕获异常：记录已被其他进程修改
    echo "更新失败: " . $e->getMessage();
    echo "锁字段: " . $e->getLockField();           // lock_version
    echo "期望版本: " . $e->getExpectedVersion();    // 0
    echo "影响行数: " . $e->getAffectedRows();       // 0
}
```

### 冲突重试机制

捕获异常后，可以重新读取数据并重试更新操作：

```php
function updateWithRetry($id, $newData, $maxRetries = 3)
{
    $retries = 0;
    while ($retries < $maxRetries) {
        try {
            $user = User::find($id);
            foreach ($newData as $key => $value) {
                $user->$key = $value;
            }
            $user->save();
            return $user;
        } catch (OptimLockException $e) {
            $retries++;
            if ($retries >= $maxRetries) {
                throw $e;
            }
            // 可选：加入短暂延迟避免频繁重试
            usleep(100000); // 100ms
        }
    }
}

// 使用
$result = updateWithRetry(1, ['name' => '最终修改名']);
```

### 删除操作的乐观锁

删除记录时同样会进行乐观锁检查，如果版本号不匹配则抛出异常：

```php
use think\db\exception\OptimLockException;

$user1 = User::find(1);
$user2 = User::find(1);

// 先更新其中一个实例，改变版本号
$user1->name = '修改后';
$user1->save(); // 版本号 +1

try {
    // 尝试用旧版本号删除，会失败
    $user2->delete();
} catch (OptimLockException $e) {
    echo "删除失败: " . $e->getMessage();
}
```

### 强制操作（跳过乐观锁检查）

在某些特殊场景下，可以强制跳过乐观锁检查：

```php
// 强制更新，跳过版本号检查
$user->forceUpdateLock()->save();

// 强制删除，跳过版本号检查（也可以使用 force() 方法，与软删除共用）
$user->forceUpdateLock()->delete();
// 或者
$user->force()->delete();
```

### 自定义版本号字段

如果不想使用默认的 `lock_version` 字段，可以在模型中自定义：

```php
class Goods extends Model
{
    use OptimLock;

    // 自定义乐观锁字段名为 version
    protected $optimLock = 'version';
}
```

### 注意事项

1. **先读取再更新**：使用乐观锁时，必须先通过 `find()` / `select()` 等方法从数据库读取数据，
   然后再调用 `save()` 更新。如果直接使用静态 `update()` 方法而没有设置正确的版本号，
   可能无法正确触发乐观锁检查。

2. **批量更新**：乐观锁仅适用于单条记录的 `save()` 和 `delete()` 操作。
   使用查询构造器执行的批量更新（如 `User::where('status', 1)->update(...)`）
   不会自动触发乐观锁机制。

3. **事务环境**：乐观锁与数据库事务兼容。如果在事务中更新失败抛出异常，
   事务会自动回滚（前提是更新操作在事务内）。

4. **高并发场景**：在高并发写入场景下，建议配合合理的重试机制使用，
   同时考虑是否需要使用悲观锁（`Db::lock(true)`）等其他方案。

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