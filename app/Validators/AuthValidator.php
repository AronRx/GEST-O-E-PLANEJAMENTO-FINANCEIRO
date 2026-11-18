<?php

class AuthValidator extends Validator
{
    public function validateRegister(): self
    {
        $this
            ->required(
                'nome',
                'O nome é obrigatório.'
            )
            ->required(
                'email',
                'O e-mail é obrigatório.'
            )
            ->max(
                'nome',
                120,
                'O nome deve possuir no máximo 120 caracteres.'
            )
            ->max(
                'email',
                160,
                'O e-mail deve possuir no máximo 160 caracteres.'
            )
            ->email(
                'email',
                'Informe um e-mail válido.'
            )
            ->required(
                'senha',
                'A senha é obrigatória.'
            )
            ->min(
                'senha',
                8,
                'A senha deve possuir pelo menos 8 caracteres.'
            )
            ->max(
                'senha',
                72,
                'A senha deve possuir no máximo 72 bytes nesta versão.'
            );

        return $this;
    }

    public function validateLogin(): self
    {
        $this
            ->required(
                'email',
                'O e-mail é obrigatório.'
            )
            ->max(
                'email',
                160,
                'O e-mail deve possuir no máximo 160 caracteres.'
            )
            ->email(
                'email',
                'Informe um e-mail válido.'
            )
            ->required(
                'senha',
                'A senha é obrigatória.'
            );

        return $this;
    }
}
