<?php

declare(strict_types=1);

namespace tests\integration\stubs;

use think\Model;
use think\model\concern\OptimLock;

/**
 * 乐观锁测试模型 - 使用自定义字段 version
 */
class OptimLockCustomFieldModel extends Model
{
    use OptimLock;

    protected $table = 'orm_test_optim_lock';
    protected $pk    = 'id';

    protected $optimLock = 'version';
}
