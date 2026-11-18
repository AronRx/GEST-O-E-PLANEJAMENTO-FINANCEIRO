<?php

class EventPlanningTestController
{
    private EventPlanningService $eventPlanningService;

    public function __construct()
    {
        $this->eventPlanningService =
            new EventPlanningService();
    }

    public function calculateBudget(): void
    {
        try {
            $data = Request::all();

            $result =
                $this->eventPlanningService->calculateBudget(
                    $data
                );

            Response::success($result);
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        }
    }
}
