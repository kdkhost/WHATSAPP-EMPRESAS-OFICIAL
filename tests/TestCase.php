<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_dir(storage_path())) {
            mkdir(storage_path(), 0775, true);
        }

        file_put_contents(storage_path('installed.lock'), '{}');

        if (! is_dir(storage_path('app'))) {
            mkdir(storage_path('app'), 0775, true);
        }

        file_put_contents(storage_path('app/cron-state.json'), json_encode([
            'last_check' => time(),
        ]));
    }
}
