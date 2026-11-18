<?php

class Validator
{
    protected array $data;

    protected array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;

        if ($value === null || trim((string) $value) === '') {
            $this->errors[$field] = $message
                ?? "O campo {$field} é obrigatório.";
        }

        return $this;
    }

    public function email(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== '') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                if (!isset($this->errors[$field])) {
                    $this->errors[$field] = $message
                        ?? "O campo {$field} deve conter um e-mail válido.";
                }
            }
        }

        return $this;
    }

    public function numeric(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== '') {
            if (!is_numeric($value)) {
                $this->errors[$field] = $message
                    ?? "O campo {$field} deve ser numérico.";
            }
        }

        return $this;
    }

    public function min(string $field, int $length, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;

        if ($value !== null && strlen((string) $value) < $length) {
            $this->errors[$field] = $message
                ?? "O campo {$field} deve possuir pelo menos {$length} caracteres.";
        }

        return $this;
    }

    public function max(string $field, int $length, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;

        if ($value !== null && strlen((string) $value) > $length) {
            $this->errors[$field] = $message
                ?? "O campo {$field} deve possuir no máximo {$length} caracteres.";
        }

        return $this;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
