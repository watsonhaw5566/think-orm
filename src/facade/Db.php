<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2023 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\facade;

use think\Facade;

/**
 * @see \think\DbManager
 * @mixin \think\DbManager
 *
 * 常用静态调用速查（转发至 DbManager -> Connection -> Query）：
 * ---------------------------------------------------------------------
 * 数据库连接：
 * @method static \think\db\ConnectionInterface connect(?string $name = null, bool $force = false) 创建/获取数据库连接
 *
 * 查询构造：
 * @method static \think\db\BaseQuery name(string $name)                                   指定数据表名（不含前缀）
 * @method static \think\db\BaseQuery table(mixed $table, mixed ...$args)                  指定数据表名（含前缀）
 * @method static \think\db\BaseQuery alias(string|array $alias)                            指定数据表别名
 * @method static \think\db\BaseQuery field(string|array|\think\db\Raw $field, mixed ...$args) 指定查询字段
 * @method static \think\db\BaseQuery where(mixed $field, mixed $op = null, mixed $condition = null)  指定AND查询条件
 * @method static \think\db\BaseQuery whereOr(mixed $field, mixed $op = null, mixed $condition = null)  指定OR查询条件
 * @method static \think\db\BaseQuery whereNull(string $field, string $logic = 'AND')       查询字段为Null
 * @method static \think\db\BaseQuery whereNotNull(string $field, string $logic = 'AND')    查询字段不为Null
 * @method static \think\db\BaseQuery whereIn(string $field, mixed $condition, string $logic = 'AND')  在某个范围
 * @method static \think\db\BaseQuery whereNotIn(string $field, mixed $condition, string $logic = 'AND') 不在某个范围
 * @method static \think\db\BaseQuery whereBetween(string $field, mixed $condition, string $logic = 'AND') 在区间
 * @method static \think\db\BaseQuery whereNotBetween(string $field, mixed $condition, string $logic = 'AND') 不在区间
 * @method static \think\db\BaseQuery whereLike(string $field, mixed $condition, string $logic = 'AND') 模糊查询
 * @method static \think\db\BaseQuery whereTime(string $field, mixed $op = null, mixed $range = null, string $logic = 'AND') 时间查询
 * @method static \think\db\BaseQuery order(mixed $field, string $order = '')                结果排序
 * @method static \think\db\BaseQuery limit(int $offset, int $length = null)                 查询限制
 * @method static \think\db\BaseQuery page(int $page, int $listRows = null)                  指定分页
 * @method static \think\db\BaseQuery group(mixed $field, ...$args)                           GROUP BY
 * @method static \think\db\BaseQuery having(mixed $having, mixed ...$args)                   HAVING条件
 * @method static \think\db\BaseQuery join(mixed $join, mixed $condition = null, string $type = 'INNER', array $bind = []) JOIN查询
 * @method static \think\db\BaseQuery leftJoin(mixed $join, mixed $condition = null, array $bind = [])  LEFT JOIN
 * @method static \think\db\BaseQuery rightJoin(mixed $join, mixed $condition = null, array $bind = []) RIGHT JOIN
 *
 * 结果获取：
 * @method static array|null find(mixed $data = null, ?\Closure $closure = null)                        查询单条记录
 * @method static \think\Collection select(array $data = [])                                            查询数据集
 * @method static mixed value(string $field, mixed $default = null, bool $useModelAttr = false)         获取某个字段值
 * @method static array column(string|array $field, string $key = '', bool $useModelAttr = false)       获取某一列值
 * @method static \think\Paginator paginate(int|array|null $listRows = null, int|bool $simple = false)  分页查询
 * @method static \Generator cursor()                                                                   游标查询
 * @method static string fetchSql(bool $fetch = true)                                                   获取SQL语句不执行
 *
 * 写入操作：
 * @method static int insert(array $data = [], bool $getLastInsID = false, string $sequence = null)    插入单条记录
 * @method static int insertGetId(array $data = [], string $sequence = null)                            插入并返回自增ID
 * @method static int insertAll(array $dataSet = [], int $limit = 0, bool $replace = false)             批量插入
 * @method static int update(array $data = [], bool $force = false)                                     更新记录
 * @method static int delete(mixed $data = true)                                                        删除记录
 *
 * 聚合查询：
 * @method static int count(string $field = '*')                                                        COUNT查询
 * @method static float sum(string|\think\db\Raw $field)                                                SUM查询
 * @method static mixed min(string|\think\db\Raw $field, bool $force = true)                            MIN查询
 * @method static mixed max(string|\think\db\Raw $field, bool $force = true)                            MAX查询
 * @method static float avg(string|\think\db\Raw $field)                                                AVG查询
 *
 * 事务操作：
 * @method static mixed transaction(\Closure $callback)                                                 执行数据库事务
 * @method static void startTrans()                                                                     启动事务
 * @method static void commit()                                                                         提交事务
 * @method static void rollback()                                                                       回滚事务
 *
 * 原生SQL：
 * @method static array query(string $sql, array $bind = [], bool $master = false)                      执行原生查询SQL
 * @method static int execute(string $sql, array $bind = [])                                             执行原生写入SQL
 *
 * 动态方法（通过 Query 的 __call 支持）：
 *  - getBy<FieldName>(mixed $value)              如 Db::name('user')->getById(1)
 *  - getFieldBy<FieldName>(mixed $value, string $field)
 *  - where<FieldName>(mixed $op, mixed $condition = null)  如 Db::name('user')->whereStatus(1)
 *  - whereOr<FieldName>(mixed $op, mixed $condition = null)
 *
 * 配置/事件：
 * @method static void setConfig(array $config)                                                        设置配置
 * @method static void setCache(\Psr\SimpleCache\CacheInterface $cache)                                设置缓存对象
 * @method static void setEvent(array|object $event)                                                   设置Event对象
 * @method static void setLog(\Psr\Log\LoggerInterface $log)                                           设置日志对象
 * @method static void listen(\Closure $listen)                                                        注册SQL监听
 * ---------------------------------------------------------------------
 */
class Db extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）.
     *
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'think\DbManager';
    }
}
