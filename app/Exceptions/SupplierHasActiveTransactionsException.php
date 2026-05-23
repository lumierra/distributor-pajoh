<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Dilemparkan saat user mencoba soft-delete supplier yang masih punya
 * transaksi open (PO/GRN belum closed). Auto-render 422 dengan list reasons
 * lewat exception handler default Laravel.
 */
class SupplierHasActiveTransactionsException extends RuntimeException implements HttpExceptionInterface
{
    /**
     * @param  array<int, string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct('Supplier tidak bisa dihapus karena masih ada transaksi aktif.');
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
