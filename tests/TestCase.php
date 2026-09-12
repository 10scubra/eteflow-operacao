<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('eteflow.uat_passwords', [
            'master' => 'Master@2026',
            'operator_1' => 'Operador1@2026',
            'operator_2' => 'Operador2@2026',
        ]);
    }
}
