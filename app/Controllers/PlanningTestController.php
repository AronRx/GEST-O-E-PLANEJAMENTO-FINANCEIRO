<?php

class PlanningTestController
{
    private PlanningService $planningService;

    public function __construct()
    {
        $this->planningService = new PlanningService();
    }

    public function create(): void
    {
        try {
            $data = Request::all();

            $result = $this->planningService->createPlan($data);

            Response::success($result);
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        }
    }
}
