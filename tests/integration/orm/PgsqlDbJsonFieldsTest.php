<?php

declare(strict_types=1);

namespace tests\integration\orm;

class PgsqlDbJsonFieldsTest extends DbJsonFieldsBase
{
    protected static string $connectName = 'pgsql';
}
