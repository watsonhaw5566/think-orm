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
declare (strict_types=1);

namespace think\db;

use Closure;
use PDOStatement;
use ReflectionFunction;
use think\db\exception\DbException as Exception;
use Generator;

/**
 * PDO数据查询类.
 *
 * 常用链式调用速查：
 * ---------------------------------------------------------------------
 * 查询构造：
 * @method $this field(string|array|Raw $field, mixed ...$args)       指定查询字段
 * @method $this table(mixed $table, mixed ...$args)                  指定数据表（含前缀）
 * @method $this name(string $name)                                    指定数据表名（不含前缀）
 * @method $this alias(string|array $alias)                            指定当前数据表别名
 * @method $this where(mixed $field, mixed $op = null, mixed $condition = null)  指定AND查询条件
 * @method $this whereOr(mixed $field, mixed $op = null, mixed $condition = null)  指定OR查询条件
 * @method $this whereNull(string $field, string $logic = 'AND')       查询字段为Null
 * @method $this whereNotNull(string $field, string $logic = 'AND')    查询字段不为Null
 * @method $this whereIn(string $field, mixed $condition, string $logic = 'AND')  查询字段在某个范围
 * @method $this whereNotIn(string $field, mixed $condition, string $logic = 'AND')  查询字段不在某个范围
 * @method $this whereBetween(string $field, mixed $condition, string $logic = 'AND') 查询字段在区间
 * @method $this whereNotBetween(string $field, mixed $condition, string $logic = 'AND') 不在区间
 * @method $this whereLike(string $field, mixed $condition, string $logic = 'AND') 模糊查询
 * @method $this whereNotLike(string $field, mixed $condition, string $logic = 'AND') 不模糊查询
 * @method $this whereExists(mixed $condition, string $logic = 'AND')  EXISTS查询
 * @method $this whereNotExists(mixed $condition, string $logic = 'AND') NOT EXISTS查询
 * @method $this whereColumn(string $field1, string $op = null, string $field2 = null, string $logic = 'AND') 比较两个字段
 * @method $this whereFindInSet(string $field, mixed $condition, string $logic = 'AND') FIND_IN_SET查询
 * @method $this whereTime(string $field, string $op = null, mixed $range = null, string $logic = 'AND')   时间查询
 * @method $this whereBetweenTime(string $field, mixed $startTime, mixed $endTime, string $logic = 'AND') 时间区间查询
 *
 * 连接查询：
 * @method $this join(mixed $join, mixed $condition = null, string $type = 'INNER', array $bind = []) JOIN查询
 * @method $this leftJoin(mixed $join, mixed $condition = null, array $bind = [])  LEFT JOIN
 * @method $this rightJoin(mixed $join, mixed $condition = null, array $bind = []) RIGHT JOIN
 * @method $this fullJoin(mixed $join, mixed $condition = null, array $bind = [])  FULL JOIN
 * @method $this view(mixed $join, mixed $field = null, mixed $on = null, string $type = 'INNER') 视图查询
 *
 * 排序/分组/分页/限制：
 * @method $this order(mixed $field, string $order = '')               结果排序
 * @method $this limit(int $offset, int $length = null)                查询限制
 * @method $this page(int $page, int $listRows = null)                 指定分页
 *
 * 结果获取：
 * @method TModel|array|null find(mixed $data = null, ?Closure $closure = null)       查询单条记录
 * @method TModel|null findOrFail(mixed $data = null)                                 查询单条记录，不存在则抛出异常
 * @method TModel|null findOrEmpty(mixed $data = null)                                查询单条记录，不存在则返回空模型
 * @method \think\model\Collection<int, TModel>|Collection select(array $data = [])  查询数据集
 * @method \think\model\Collection<int, TModel>|Collection selectOrFail(array $data = []) 查询数据集，不存在则抛出异常
 * @method mixed value(string $field, mixed $default = null, bool $useModelAttr = false) 获取某个字段值
 * @method array column(string|array $field, string $key = '', bool $useModelAttr = false) 获取某一列值
 * @method Paginator paginate(int|array|null $listRows = null, int|bool $simple = false) 分页查询
 *
 * 写入操作：
 * @method int insert(array $data = [], bool $getLastInsID = false, string $sequence = null)  插入单条
 * @method int insertGetId(array $data = [], string $sequence = null)  插入并返回自增ID
 * @method int insertAll(array $dataSet = [], int $limit = 0, bool $replace = false)  批量插入
 * @method int update(array $data = [], bool $force = false)           更新记录
 * @method int delete(mixed $data = true)                              删除记录
 * @method bool save(array $data = [])                                 保存当前记录（自动判断insert/update）
 *
 * 聚合查询：
 * @method int count(string $field = '*')                              COUNT查询
 * @method float sum(string|Raw $field)                                SUM查询
 * @method mixed min(string|Raw $field, bool $force = true)            MIN查询
 * @method mixed max(string|Raw $field, bool $force = true)            MAX查询
 * @method float avg(string|Raw $field)                                AVG查询
 *
 * 模型专用：
 * @method $this with(array|string $relation, ...$args)               关联预载入
 * @method $this withCount(mixed $relation, ...$args)                  关联统计COUNT
 * @method $this withSum(mixed $relation, string $field)               关联统计SUM
 * @method $this withMax(mixed $relation, string $field)               关联统计MAX
 * @method $this withMin(mixed $relation, string $field)               关联统计MIN
 * @method $this withAvg(mixed $relation, string $field)               关联统计AVG
 * @method $this has(string $relation, mixed $operator = '>=', mixed $count = 1, string $logic = 'AND', mixed $callback = null) 关联存在性查询
 * @method $this hasWhere(string $relation, mixed $where = [], mixed $fields = '*', string $logic = 'AND') 关联条件查询
 * @method $this withAttr(array $withAttr)                             动态获取器
 * @method $this hidden(array $hidden, bool $merge = false)            设置隐藏属性
 * @method $this visible(array $visible, bool $merge = false)          设置输出属性
 * @method $this append(array $append, bool $merge = false)            附加输出属性
 *
 * 缓存/事务：
 * @method $this cache(mixed $key = true, int|\DateInterval|\DateTimeInterface $expire = null, string|CacheInterface $tag = null) 设置查询缓存
 * @method $this transaction(Closure $callback)                        执行数据库事务
 * @method $this startTrans()                                          启动事务
 * @method void commit()                                               提交事务
 * @method void rollback()                                             回滚事务
 * ---------------------------------------------------------------------
 */
class Query extends BaseQuery
{
    use concern\JoinAndViewQuery;
    use concern\ParamsBind;
    use concern\TableFieldInfo;

    /**
     * 表达式方式指定Field排序.
     *
     * @param string $field 排序字段
     * @param array  $bind  参数绑定
     *
     * @return $this
     */
    public function orderRaw(string $field, array $bind = [])
    {
        $this->options['order'][] = new Raw($field, $bind);

        return $this;
    }

    /**
     * 表达式方式指定查询字段.
     *
     * @param string $field 字段名
     *
     * @return $this
     */
    public function fieldRaw(string $field)
    {
        $this->options['field'][] = new Raw($field);

        return $this;
    }

    /**
     * 指定Field排序 orderField('id',[1,2,3],'desc').
     *
     * @param string $field  排序字段
     * @param array  $values 排序值
     * @param string $order  排序 desc/asc
     *
     * @return $this
     */
    public function orderField(string $field, array $values, string $order = '')
    {
        if (!empty($values)) {
            $values['sort'] = $order;

            $this->options['order'][$field] = $values;
        }

        return $this;
    }

    /**
     * 随机排序.
     *
     * @return $this
     */
    public function orderRand()
    {
        $this->options['order'][] = '[rand]';

        return $this;
    }

    /**
     * 使用表达式设置数据.
     *
     * @param string $field 字段名
     * @param string $value 字段值
     *
     * @return $this
     */
    public function exp(string $field, string $value)
    {
        $this->options['data'][$field] = new Raw($value);

        return $this;
    }

    /**
     * 表达式方式指定当前操作的数据表.
     *
     * @param mixed $table 表名
     *
     * @return $this
     */
    public function tableRaw(string $table)
    {
        $this->options['table'] = new Raw($table);

        return $this;
    }

    /**
     * 获取执行的SQL语句而不进行实际的查询.
     *
     * @param bool $fetch 是否返回sql
     *
     * @return $this|Fetch
     */
    public function fetchSql(bool $fetch = true)
    {
        $this->options['fetch_sql'] = $fetch;

        if ($fetch) {
            return new Fetch($this);
        }

        return $this;
    }

    /**
     * 批处理执行SQL语句
     * 批处理的指令都认为是execute操作.
     *
     * @param array $sql SQL批处理指令
     *
     * @return bool
     */
    public function batchQuery(array $sql = []): bool
    {
        return $this->connection->batchQuery($this, $sql);
    }

    /**
     * USING支持 用于多表删除.
     *
     * @param mixed $using USING
     *
     * @return $this
     */
    public function using($using)
    {
        $this->options['using'] = $using;

        return $this;
    }

    /**
     * 存储过程调用.
     *
     * @param bool $procedure 是否为存储过程查询
     *
     * @return $this
     */
    public function procedure(bool $procedure = true)
    {
        $this->options['procedure'] = $procedure;

        return $this;
    }

    /**
     * 指定group查询.
     *
     * @param string|array $group GROUP
     *
     * @return $this
     */
    public function group($group)
    {
        $this->options['group'] = $group;

        return $this;
    }

    /**
     * 指定having查询.
     *
     * @param string $having having
     *
     * @return $this
     */
    public function having(string $having)
    {
        $this->options['having'] = $having;

        return $this;
    }

    /**
     * 指定distinct查询.
     *
     * @param bool $distinct 是否唯一
     *
     * @return $this
     */
    public function distinct(bool $distinct = true)
    {
        $this->options['distinct'] = $distinct;

        return $this;
    }

    /**
     * 指定强制索引.
     *
     * @param string $force 索引名称
     *
     * @return $this
     */
    public function force(string $force)
    {
        $this->options['force'] = $force;

        return $this;
    }

    /**
     * 查询注释.
     *
     * @param string $comment 注释
     *
     * @return $this
     */
    public function comment(string $comment)
    {
        $this->options['comment'] = $comment;

        return $this;
    }

    /**
     * 设置是否REPLACE.
     *
     * @param bool $replace 是否使用REPLACE写入数据
     *
     * @return $this
     */
    public function replace(bool $replace = true)
    {
        $this->options['replace'] = $replace;

        return $this;
    }

    /**
     * 设置当前查询所在的分区.
     *
     * @param string|array $partition 分区名称
     *
     * @return $this
     */
    public function partition($partition)
    {
        $this->options['partition'] = $partition;

        return $this;
    }

    /**
     * 设置DUPLICATE.
     *
     * @param array|string|Raw $duplicate DUPLICATE信息
     *
     * @return $this
     */
    public function duplicate($duplicate)
    {
        $this->options['duplicate'] = $duplicate;

        return $this;
    }

    /**
     * 设置查询的额外参数.
     *
     * @param string $extra 额外信息
     *
     * @return $this
     */
    public function extra(string $extra)
    {
        $this->options['extra'] = $extra;

        return $this;
    }

    /**
     * 创建子查询SQL.
     *
     * @param bool $sub 是否添加括号
     *
     * @throws Exception
     *
     * @return string
     */
    public function buildSql(bool $sub = true): string
    {
        return $sub ? '( ' . $this->fetchSql()->select() . ' )' : $this->fetchSql()->select();
    }

    /**
     * 获取当前数据表的主键.
     *
     * @return string|array
     */
    public function getPk()
    {
        if (empty($this->pk)) {
            $this->pk = $this->connection->getPk($this->getTable());
        }

        return $this->pk;
    }

    /**
     * 指定数据表自增主键.
     *
     * @param string $autoinc 自增键
     *
     * @return $this
     */
    public function autoinc(string $autoinc)
    {
        $this->autoinc = $autoinc;

        return $this;
    }

    /**
     * 获取当前数据表的自增主键.
     *
     * @return string|null
     */
    public function getAutoInc()
    {
        $tableName = $this->getTable();

        if (empty($this->autoinc) && $tableName) {
            $this->autoinc = $this->connection->getAutoInc($tableName);
        }

        return $this->autoinc;
    }

    /**
     * 字段值增长
     *
     * @param string $field 字段名
     * @param float  $step  增长值
     *
     * @return $this
     */
    public function inc(string $field, float $step = 1)
    {
        $this->options['data'][$field] = ['INC', $step];

        return $this;
    }

    /**
     * 字段值减少.
     *
     * @param string $field 字段名
     * @param float  $step  增长值
     *
     * @return $this
     */
    public function dec(string $field, float $step = 1)
    {
        $this->options['data'][$field] = ['DEC', $step];

        return $this;
    }

    /**
     * 字段值增长（支持延迟写入）
     *
     * @param string    $field 字段名
     * @param float     $step  步进值
     * @param int       $lazyTime 延迟时间（秒）
     *
     * @return int|false
     */
    public function setInc(string $field, float $step = 1, int $lazyTime = 0)
    {
        if (empty($this->options['where']) && $this->model) {
            $this->where($this->model->getWhere());
        }

        if (empty($this->options['where'])) {
            // 如果没有任何更新条件则不执行
            throw new Exception('miss update condition');
        }

        if ($lazyTime > 0) {
            $guid = $this->getLazyFieldCacheKey($field);
            $step = $this->lazyWrite('inc', $guid, $step, $lazyTime);
            if (false === $step) {
                return true;
            }
        }

        return $this->inc($field, $step)->update();
    }

    /**
     * 字段值减少（支持延迟写入）
     *
     * @param string    $field 字段名
     * @param float     $step  步进值
     * @param int       $lazyTime 延迟时间（秒）
     *
     * @return int|false
     */
    public function setDec(string $field, float $step = 1, int $lazyTime = 0)
    {
        if (empty($this->options['where']) && $this->model) {
            $this->where($this->model->getWhere());
        }

        if (empty($this->options['where'])) {
            // 如果没有任何更新条件则不执行
            throw new Exception('miss update condition');
        }

        if ($lazyTime > 0) {
            $guid = $this->getLazyFieldCacheKey($field);
            $step = $this->lazyWrite('dec', $guid, $step, $lazyTime);
            if (false === $step) {
                return true;
            }

            return $this->inc($field, $step)->update();
        }

        return $this->dec($field, $step)->update();
    }

    /**
     * 延时更新检查 返回false表示需要延时
     * 否则返回实际写入的数值
     * @access protected
     * @param  string  $type     自增或者自减
     * @param  string  $guid     写入标识
     * @param  float   $step     写入步进值
     * @param  int     $lazyTime 延时时间(s)
     * @return false|integer
     */
    protected function lazyWrite(string $type, string $guid, float $step, int $lazyTime)
    {
        $cache = $this->getCache();
        if (!$cache->has($guid . '_time')) {
            // 计时开始
            $cache->set($guid . '_time', time());
            $cache->$type($guid, $step);
        } elseif (time() > $cache->get($guid . '_time') + $lazyTime) {
            // 删除缓存
            $value = $cache->$type($guid, $step);
            $cache->delete($guid);
            $cache->delete($guid . '_time');

            return 0 === $value ? false : $value;
        } else {
            // 更新缓存
            $cache->$type($guid, $step);
        }

        return false;
    }

    /**
     * 获取延迟写入字段值.
     *
     * @param string $field 字段名称
     * @param mixed  $id    主键值
     *
     * @return int
     */
    protected function getLazyFieldValue(string $field, $id = null): int
    {
        return (int) $this->getCache()->get($this->getLazyFieldCacheKey($field, $id));
    }

    /**
     * 获取延迟写入字段的缓存Key
     *
     * @param string  $field 字段名
     * @param mixed   $id    主键值
     *
     * @return string
     */
    protected function getLazyFieldCacheKey(string $field, $id = null): string
    {
        return 'lazy_' . $this->getTable() . '_' . $field . '_' . ($id ?: $this->getKey());
    }

    /**
     * 获取当前的查询标识.
     *
     * @param mixed $data 要序列化的数据
     *
     * @return string
     */
    public function getQueryGuid($data = null): string
    {
        if (null === $data) {
            $data          = $this->options;
            $data['table'] = $this->getConfig('database') . var_export($this->getTable(), true);
            unset($data['scope'], $data['default_model']);
            foreach (['AND', 'OR', 'XOR'] as $logic) {
                if (isset($data['where'][$logic])) {
                    foreach ($data['where'][$logic] as $key => $val) {
                        if ($val instanceof Closure) {
                            $reflection = new ReflectionFunction($val);
                            $properties = $reflection->getStaticVariables();
                            if (empty($properties)) {
                                $name = $reflection->getName() . $reflection->getStartLine() . '-' . $reflection->getEndLine();
                            } else {
                                $name = var_export($properties, true);
                            }
                            $data['Closure'][] = $name;
                            unset($data['where'][$logic][$key]);
                        }
                    }
                }
            }
        }

        return md5(serialize(var_export($data, true)) . serialize($this->getBind(false)));
    }

    /**
     * 执行查询但只返回PDOStatement对象
     *
     * @return PDOStatement
     */
    public function getPdo(): PDOStatement
    {
        return $this->connection->pdo($this);
    }

    /**
     * 使用游标查找记录.
     *
     * @param mixed $data 数据
     *
     * @return Generator
     */
    public function cursor($data = null)
    {
        if (!is_null($data)) {
            // 主键条件分析
            $this->parsePkWhere($data);
        }

        $this->options['data'] = $data;

        $connection = clone $this->connection;

        // 分析查询表达式
        $options   = $this->parseOptions();
        $condition = $options['where']['AND'] ?? null;

        foreach ($connection->cursor($this) as $result) {
            if ($this->model) {
                // JSON数据处理
                if (!empty($options['json'])) {
                    $this->jsonModelResult($result);
                }
                yield $this->model->newInstance($result, $condition);
            } else {
                yield $result;
            }
        }
    }

    /**
     * 分批数据返回处理.
     *
     * @param int               $count    每次处理的数据数量
     * @param callable          $callback 处理回调方法
     * @param string|array|null $column   分批处理的字段名
     * @param string            $order    字段排序
     *
     * @throws Exception
     *
     * @return bool
     */
    public function chunk(int $count, callable $callback, string | array | null $column = null, string $order = 'asc'): bool
    {
        $options = $this->getOptions();
        $column  = $column ?: $this->getPk();

        if (isset($options['order'])) {
            unset($options['order']);
        }

        $bind = $this->bind;

        if (is_array($column)) {
            $times = 1;
            $query = $this->options($options)->page($times, $count);
            $key   = '';
        } else {
            $query = $this->options($options)->limit($count);

            if (str_contains($column, '.')) {
                [$alias, $key] = explode('.', $column);
            } else {
                $key = $column;
            }
        }

        $resultSet = $query->order($column, $order)->select();

        while (count($resultSet) > 0) {
            if (false === call_user_func($callback, $resultSet)) {
                return false;
            }

            if (isset($times)) {
                $times++;
                $query = $this->options($options)->page($times, $count);
            } else {
                $end    = $resultSet->pop();
                $lastId = is_array($end) ? $end[$key] : $end->getData($key);

                $query = $this->options($options)
                    ->limit($count)
                    ->where($column, 'asc' == strtolower($order) ? '>' : '<', $lastId);
            }

            $resultSet = $query->bind($bind)->order($column, $order)->select();
        }

        return true;
    }
}
