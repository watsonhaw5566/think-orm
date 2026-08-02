<?php

declare(strict_types=1);

namespace tests\integration\orm;

class MysqlDbTest extends DbTestBase
{
    protected static string $connectName = 'mysql';
}
