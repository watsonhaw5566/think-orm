<?php

declare(strict_types=1);

namespace tests\integration\orm;

class MysqlOptimLockTest extends OptimLockTestBase
{
    protected static string $connectName = 'mysql';
}
