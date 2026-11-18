<?php

class TravelPlanningTestController
{
    private TravelPlanningService $travelPlanningService;

    public function __construct()
    {
        $this->travelPlanningService =
            new TravelPlanningService();
    }

    public function calculateBudget(): void
    {
        try {
            $data = Request::all();

            $result =
                $this->travelPlanningService->calculateBudget(
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
