<?php
declare(strict_types=1);

namespace tests\integration\orm;

class PgsqlDbTransactionTest extends DbTransactionTestBase
{
    protected static string $connectName = 'pgsql';
}
