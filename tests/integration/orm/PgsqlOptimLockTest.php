<?php

declare(strict_types=1);

namespace tests\integration\orm;

class PgsqlOptimLockTest extends OptimLockTestBase
{
    protected static string $connectName = 'pgsql';
}
