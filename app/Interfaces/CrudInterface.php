<?php
/**
 * CrudInterface — Contrato estándar para operaciones CRUD.
 */
interface CrudInterface {
    public function findAll(): array;
    public function findById(int $id): array|false;
    public function create(array $data): int|false;
    public function update(int $id, array $data): bool;
    public function softDelete(int $id): bool;
}
