<?php

namespace Tests\Feature\Database\Seeders;

use Database\Seeders\EteFlowSeeder;
use LogicException;
use Tests\TestCase;

class EteFlowSeederTest extends TestCase
{
    public function test_refuses_to_create_demo_data_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('O seeder de homologação não pode ser executado em produção.');

        (new EteFlowSeeder)->run();
    }

    public function test_requires_all_uat_passwords_outside_production(): void
    {
        config()->set('eteflow.uat_passwords.operator_2');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Configure todas as senhas UAT antes de executar o seeder.');

        (new EteFlowSeeder)->run();
    }
}
