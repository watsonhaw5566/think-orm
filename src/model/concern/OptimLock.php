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
declare(strict_types=1);

namespace think\model\concern;

use think\db\exception\OptimLockException;

/**
 * 乐观锁
 *
 * 用于处理并发场景下的数据更新冲突问题。
 * 通过在数据表中增加一个版本号字段，在更新时校验版本号是否一致，
 * 如果不一致则抛出异常，表示数据已被其他进程修改。
 *
 * 使用方法:
 * 1. 在数据表中添加版本号字段(默认: lock_version, 类型 INT, 默认值 0)
 * 2. 在模型中 use OptimLock trait
 * 3. 如需自定义字段名, 在模型中设置 protected $optimLock = 'your_version_field';
 *
 * <code>
 * class User extends Model
 * {
 *     use \think\model\concern\OptimLock;
 *     protected $optimLock = 'version'; // 可选，自定义锁字段名
 * }
 * </code>
 *
 * @mixin \think\Model
 */
trait OptimLock
{
    /**
     * 是否强制更新（跳过乐观锁检查）
     *
     * @var bool
     */
    protected bool $forceUpdateLock = false;

    /**
     * 获取乐观锁字段名
     *
     * @return string|false 锁字段名，如果未设置则返回 false
     */
    protected function getOptimLockField(): string|false
    {
        $field = property_exists($this, 'optimLock') && isset($this->optimLock) ? $this->optimLock : 'lock_version';

        if (false === $field) {
            return false;
        }

        return $field;
    }

    /**
     * 设置强制更新，跳过乐观锁检查
     *
     * @param bool $force 是否强制更新
     *
     * @return $this
     */
    public function forceUpdateLock(bool $force = true): static
    {
        $this->forceUpdateLock = $force;

        return $this;
    }

    /**
     * 数据检查
     *
     * 在写入数据前调用，根据数据是否存在处理版本号
     *
     * @return void
     */
    protected function checkData(): void
    {
        $this->isExists() ? $this->updateLockVersion() : $this->recordLockVersion();
    }

    /**
     * 记录乐观锁 - 新增数据时初始化版本号
     *
     * @return void
     */
    protected function recordLockVersion(): void
    {
        $optimLock = $this->getOptimLockField();

        if ($optimLock) {
            $this->set($optimLock, 0);
        }
    }

    /**
     * 更新乐观锁 - 更新数据时递增版本号
     *
     * @return void
     */
    protected function updateLockVersion(): void
    {
        if ($this->forceUpdateLock) {
            return;
        }

        $optimLock = $this->getOptimLockField();

        if ($optimLock) {
            $lockVer = $this->getOrigin($optimLock);
            // 即使原版本为0也需要更新，因为需要在where中带上条件
            $this->set($optimLock, (int) $lockVer + 1);
        }
    }

    /**
     * 获取更新条件，附加乐观锁版本号条件
     *
     * @return mixed
     */
    public function getWhere()
    {
        $where     = parent::getWhere();
        $optimLock = $this->getOptimLockField();

        if ($optimLock && !$this->forceUpdateLock && $this->isExists()) {
            $lockVer = $this->getOrigin($optimLock);
            $where[] = [$optimLock, '=', $lockVer];
        }

        return $where;
    }

    /**
     * 检查更新结果
     *
     * @param mixed $result 更新结果
     *
     * @return void
     *
     * @throws OptimLockException
     */
    protected function checkResult($result): void
    {
        $optimLock = $this->getOptimLockField();

        if (!$optimLock || $this->forceUpdateLock || !$this->isExists()) {
            return;
        }

        if (!$result) {
            $lockVer = $this->getOrigin($optimLock);

            throw new OptimLockException(
                'Record has been updated or deleted by another process',
                $optimLock,
                $lockVer,
                (int) $result
            );
        }
    }

    /**
     * 删除当前的记录（带乐观锁检查）
     *
     * @return bool
     *
     * @throws OptimLockException
     */
    public function delete(): bool
    {
        if (!$this->isExists() || $this->isEmpty() || false === $this->trigger('BeforeDelete')) {
            return false;
        }

        $optimLock = $this->getOptimLockField();

        if (!$optimLock || $this->forceUpdateLock || $this->isForce()) {
            // 没有乐观锁、强制更新锁、或者强制删除时，走原生删除
            return parent::delete();
        }

        // 读取更新条件
        $where = $this->getWhere();
        $db    = $this->db();

        $affected = 0;
        $db->transaction(function () use ($where, $db, &$affected) {
            // 删除当前模型数据（带乐观锁条件）
            $affected = $db->where($where)->delete();

            if (!$affected) {
                $optimLock = $this->getOptimLockField();
                $lockVer   = $this->getOrigin($optimLock);

                throw new OptimLockException(
                    'Record has been updated or deleted by another process, cannot delete',
                    $optimLock,
                    $lockVer,
                    $affected
                );
            }

            // 关联删除
            if (!empty($this->relationWrite)) {
                $this->autoRelationDelete();
            }
        });

        $this->trigger('AfterDelete');

        $this->exists(false);

        return true;
    }
}
