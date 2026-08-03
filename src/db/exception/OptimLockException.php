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

namespace think\db\exception;

use think\Exception;

/**
 * 乐观锁异常
 */
class OptimLockException extends Exception
{
    /**
     * 被更新的记录数
     *
     * @var int
     */
    protected int $affectedRows = 0;

    /**
     * 锁字段名
     *
     * @var string
     */
    protected string $lockField = '';

    /**
     * 期望的锁版本
     *
     * @var mixed
     */
    protected mixed $expectedVersion = null;

    /**
     * OptimLockException constructor.
     *
     * @param string $message
     * @param string $lockField
     * @param mixed  $expectedVersion
     * @param int    $affectedRows
     * @param int    $code
     */
    public function __construct(
        string $message = 'The record has been updated by another process',
        string $lockField = '',
        mixed $expectedVersion = null,
        int $affectedRows = 0,
        int $code = 10600
    ) {
        $this->message         = $message;
        $this->code            = $code;
        $this->lockField       = $lockField;
        $this->expectedVersion = $expectedVersion;
        $this->affectedRows    = $affectedRows;

        $this->setData('Optimistic Lock', [
            'Lock Field'       => $lockField,
            'Expected Version' => $expectedVersion,
            'Affected Rows'    => $affectedRows,
        ]);
    }

    /**
     * 获取受影响的行数
     *
     * @return int
     */
    public function getAffectedRows(): int
    {
        return $this->affectedRows;
    }

    /**
     * 获取锁字段名
     *
     * @return string
     */
    public function getLockField(): string
    {
        return $this->lockField;
    }

    /**
     * 获取期望的锁版本
     *
     * @return mixed
     */
    public function getExpectedVersion(): mixed
    {
        return $this->expectedVersion;
    }
}
