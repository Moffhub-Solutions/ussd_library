<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Console;

use Illuminate\Testing\PendingCommand;
use Moffhub\Ussd\Console\Commands\SimulateCommand;
use Moffhub\Ussd\Tests\TestCase;

class SimulateCommandTest extends TestCase
{
    private function runArtisan(string $command, array $parameters = []): PendingCommand
    {
        $result = $this->artisan($command, $parameters);
        $this->assertInstanceOf(PendingCommand::class, $result);

        return $result;
    }

    public function test_command_is_registered(): void
    {
        $this->runArtisan('list')
            ->expectsOutputToContain('ussd:simulate')
            ->assertExitCode(0);
    }

    public function test_command_has_correct_signature(): void
    {
        $command = new SimulateCommand;

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('phone'));
        $this->assertTrue($definition->hasOption('provider'));
        $this->assertTrue($definition->hasOption('service-code'));

        $this->assertSame('254700000000', $definition->getOption('phone')->getDefault());
        $this->assertSame('generic', $definition->getOption('provider')->getDefault());
        $this->assertNull($definition->getOption('service-code')->getDefault());
    }

    public function test_command_can_be_instantiated(): void
    {
        $command = new SimulateCommand;

        $this->assertInstanceOf(SimulateCommand::class, $command);
    }

    public function test_command_fails_with_invalid_provider(): void
    {
        $this->runArtisan('ussd:simulate', ['--provider' => 'nonexistent'])
            ->expectsOutputToContain('Unknown provider: nonexistent')
            ->assertExitCode(1);
    }
}
