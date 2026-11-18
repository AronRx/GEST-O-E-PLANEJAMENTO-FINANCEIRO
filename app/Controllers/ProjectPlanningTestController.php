<?php

class ProjectPlanningTestController
{
    private ProjectPlanningService $projectPlanningService;

    public function __construct()
    {
        $this->projectPlanningService =
            new ProjectPlanningService();
    }

    public function calculateBudget(): void
    {
        try {
            $data = Request::all();

            $result =
                $this->projectPlanningService->calculateBudget(
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
