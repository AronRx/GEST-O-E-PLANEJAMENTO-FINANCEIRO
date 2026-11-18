<?php

class HealthService
{
    private HealthRepository $healthRepository;

    public function __construct()
    {
        $this->healthRepository = new HealthRepository();
    }

    public function getStatus(): array
    {
        $status = [
            'sistema' => 'Nymbrio',
            'backend' => 'online',
            'status' => 'operacional'
        ];

        $databaseStatus = $this->healthRepository->check();

        return array_merge($status, $databaseStatus);
    }
}
