<?php

namespace App\Support;

/**
 * Guarda la empresa activa del request (tenant actual).
 * null = sin filtro (super admin o CLI) → ve/gestiona todas las empresas.
 */
class Tenancy
{
    protected ?int $companyId = null;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function check(): bool
    {
        return $this->companyId !== null;
    }

    public function forget(): void
    {
        $this->companyId = null;
    }
}
