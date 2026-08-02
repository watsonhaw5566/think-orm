<?php
declare(strict_types=1);

namespace tests\integration\orm;

class PgsqlModelFieldTypeTest extends ModelFieldTypeBase
{
    protected static string $connectName = 'pgsql';
}
