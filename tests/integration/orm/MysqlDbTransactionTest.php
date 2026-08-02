<?php
declare(strict_types=1);

namespace tests\integration\orm;

class MysqlDbTransactionTest extends DbTransactionTestBase
{
    protected static string $connectName = 'mysql';
}
