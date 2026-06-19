<?php

namespace App\Jobs;

use App\Services\SimulationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunImpactAnalysisJob implements ShouldQueue
{
    use Queueable;

    /**
     * Total number of attempts including the initial try.
     * Set to 2 to allow one retry on failure.
     */
    public int $tries = 2;

    /**
     * Maximum seconds the job may run before it is killed.
     */
    public int $timeout = 120;

    public function __construct(
        public readonly int $analysisId,
    ) {}

    /**
     * Laravel resolves SimulationService from the container automatically.
     * The analysis id (not the model) is stored so the job serialises safely.
     */
    public function handle(SimulationService $service): void
    {
        $service->run($this->analysisId);
    }
}
