<?php

class PurchasePlanningTestController
{
    private PurchasePlanningService $purchasePlanningService;

    public function __construct()
    {
        $this->purchasePlanningService =
            new PurchasePlanningService();
    }

    public function calculateBudget(): void
    {
        try {
            $data = Request::all();

            $result =
                $this->purchasePlanningService->calculateBudget(
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
