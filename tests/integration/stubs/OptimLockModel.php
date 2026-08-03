<?php

declare(strict_types=1);

namespace tests\integration\stubs;

use think\Model;
use think\model\concern\OptimLock;

/**
 * 乐观锁测试模型 - 使用默认字段 lock_version
 */
class OptimLockModel extends Model
{
    use OptimLock;

    protected $table = 'orm_test_optim_lock';
    protected $pk    = 'id';
}
