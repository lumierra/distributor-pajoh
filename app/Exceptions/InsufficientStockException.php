<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    /**
     * @param  int  $productId  Produk yang stoknya kurang
     * @param  int  $requested  Qty diminta (base unit)
     * @param  int  $available  Qty yang tersedia (base unit)
     */
    public function __construct(
        public readonly int $productId,
        public readonly int $requested,
        public readonly int $available,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? "Stok produk #{$productId} tidak cukup: minta {$requested}, tersedia {$available}.",
        );
    }
}
