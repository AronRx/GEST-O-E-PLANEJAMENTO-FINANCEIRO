<?php

class HealthController
{
    private HealthService $healthService;

    public function __construct()
    {
        $this->healthService = new HealthService();
    }

    public function index(): void
    {
        $status = $this->healthService->getStatus();

        Response::success($status);
    }
}
