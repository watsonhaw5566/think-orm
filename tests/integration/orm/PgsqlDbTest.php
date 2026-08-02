<?php
declare(strict_types=1);

namespace tests\integration\orm;

class PgsqlDbTest extends DbTestBase
{
    protected static string $connectName = 'pgsql';
}
