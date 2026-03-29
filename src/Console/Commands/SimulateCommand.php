<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Moffhub\Ussd\Providers\ProviderFactory;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;

class SimulateCommand extends Command
{
    protected $signature = 'ussd:simulate
                            {--phone=254700000000 : The phone number to simulate}
                            {--provider=generic : The USSD provider adapter to use}
                            {--service-code= : The USSD service code (default: *123#)}';

    protected $description = 'Start an interactive CLI session simulating a USSD flow for local development';

    protected float $startTime;

    protected int $stepCount = 0;

    public function handle(): int
    {
        $phone = is_string($this->option('phone')) ? $this->option('phone') : '254700000000';
        $providerName = is_string($this->option('provider')) ? $this->option('provider') : 'generic';
        $serviceCode = is_string($this->option('service-code')) ? $this->option('service-code') : '*123#';
        $sessionId = 'sim_'.uniqid('', true);

        $this->startTime = microtime(true);

        if (! ProviderFactory::has($providerName)) {
            $this->error("Unknown provider: {$providerName}");
            $this->line('Available providers: '.implode(', ', ProviderFactory::available()));

            return self::FAILURE;
        }

        $this->displaySessionInfo($sessionId, $phone, $providerName, $serviceCode);

        /** @var UssdFramework $framework */
        $framework = app(UssdFramework::class);
        $provider = ProviderFactory::create($providerName);

        $userInput = '';

        while (true) {
            $this->stepCount++;

            $request = $this->buildRequest($sessionId, $phone, $serviceCode, $userInput, $providerName);

            try {
                $response = $framework->handle($request);
            } catch (\Exception $e) {
                $this->error("Framework error: {$e->getMessage()}");

                return self::FAILURE;
            }

            $formattedResponse = $provider->formatResponse($response);
            $this->displayResponse($formattedResponse, $response);
            $this->displayStepInfo($sessionId);

            if ($response->isEnd()) {
                $this->newLine();
                $this->info('Session ended.');
                $this->displaySummary($sessionId);

                return self::SUCCESS;
            }

            $userInput = $this->ask('Enter your response (or type "exit" to quit)');

            if ($userInput === null || strtolower($userInput) === 'exit') {
                $this->newLine();
                $this->info('Simulation exited by user.');
                $this->displaySummary($sessionId);

                return self::SUCCESS;
            }
        }
    }

    protected function buildRequest(
        string $sessionId,
        string $phone,
        string $serviceCode,
        string $userInput,
        string $providerName,
    ): Request {
        $params = match ($providerName) {
            'safaricom', 'africas_talking', 'at' => [
                'sessionId' => $sessionId,
                'phoneNumber' => $phone,
                'serviceCode' => $serviceCode,
                'text' => $userInput,
            ],
            'airtel' => [
                'transactionId' => $sessionId,
                'msisdn' => $phone,
                'input' => $userInput,
                'serviceCode' => $serviceCode,
            ],
            'mtn' => [
                'sessionId' => $sessionId,
                'msisdn' => $phone,
                'UserAnswer' => $userInput,
                'serviceCode' => $serviceCode,
            ],
            default => [
                'sessionId' => $sessionId,
                'phoneNumber' => $phone,
                'serviceCode' => $serviceCode,
                'text' => $userInput,
            ],
        };

        return new Request($params);
    }

    protected function displaySessionInfo(string $sessionId, string $phone, string $provider, string $serviceCode): void
    {
        $this->newLine();
        $this->line('<fg=cyan>=========================================</>');
        $this->line('<fg=cyan>       USSD Simulator</>');
        $this->line('<fg=cyan>=========================================</>');
        $this->line("  Session ID:   {$sessionId}");
        $this->line("  Phone:        {$phone}");
        $this->line("  Provider:     {$provider}");
        $this->line("  Service Code: {$serviceCode}");
        $this->line('<fg=cyan>=========================================</>');
        $this->newLine();
    }

    protected function displayResponse(string $formattedResponse, UssdResponse $response): void
    {
        $this->newLine();

        $type = $response->isContinue() ? '<fg=green>[CON]</>' : '<fg=red>[END]</>';
        $this->line($type);
        $this->line('<fg=yellow>-----------------------------------------</>');

        // Display the message content without the CON/END prefix
        $message = $response->getMessage();
        foreach (explode("\n", $message) as $line) {
            $this->line("  {$line}");
        }

        $this->line('<fg=yellow>-----------------------------------------</>');
    }

    protected function displayStepInfo(string $sessionId): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);
        $this->line("<fg=gray>  Step: {$this->stepCount} | Duration: {$duration}s | Session: {$sessionId}</>");
    }

    protected function displaySummary(string $sessionId): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);

        $this->newLine();
        $this->line('<fg=cyan>=========================================</>');
        $this->line('<fg=cyan>       Session Summary</>');
        $this->line('<fg=cyan>=========================================</>');
        $this->line("  Session ID:   {$sessionId}");
        $this->line("  Total Steps:  {$this->stepCount}");
        $this->line("  Duration:     {$duration}s");
        $this->line('<fg=cyan>=========================================</>');
        $this->newLine();
    }
}
