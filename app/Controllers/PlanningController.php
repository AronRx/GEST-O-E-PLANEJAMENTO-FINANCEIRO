<?php

class PlanningController
{
    private PlanningService $planningService;

    public function __construct()
    {
        $this->planningService = new PlanningService();
    }

    public function create(): void
    {
        $data = Request::all();

        $validator = new PlanningValidator($data);

        $validator->validateCreate();

        if ($validator->fails()) {
            Response::error(
                'Dados do planejamento inválidos.',
                422,
                $validator->errors()
            );
        }

        try {
            $result = $this->planningService->createPlan(
                $data
            );

            Response::success(
                $result,
                201
            );
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        }
    }
}
