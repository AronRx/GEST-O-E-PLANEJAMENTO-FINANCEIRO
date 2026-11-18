<?php

abstract class BaseRepository
{
    protected ?PDO $database = null;

    public function __construct()
    {
        $this->database = null;
    }

    protected function database(): PDO
    {
        if ($this->database === null) {
            $this->database = FinPlanDatabase::connection();
        }

        return $this->database;
    }
}
