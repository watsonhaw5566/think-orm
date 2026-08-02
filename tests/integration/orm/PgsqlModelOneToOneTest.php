<?php
declare(strict_types=1);

namespace tests\integration\orm;

class PgsqlModelOneToOneTest extends ModelOneToOneBase
{
    protected static string $connectName = 'pgsql';
}
