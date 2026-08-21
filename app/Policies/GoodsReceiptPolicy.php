<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    private const MENU = 'purchasing.grn';

    public function before(User $user, string $ability): ?bool
    {
        // manageAttachments di-gate oleh status GRN (belum posted) untuk SEMUA
        // role, termasuk superadmin — lampiran GRN final tetap terkunci. Jadi
        // jangan bypass ability itu di sini; biarkan method-nya yang menentukan.
        if ($ability === 'manageAttachments') {
            return null;
        }

        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, GoodsReceipt $grn): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Operator (gudang) yang buat draft. Admin tidak create (view + posting).
     */
    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, GoodsReceipt $grn): bool
    {
        if (! $grn->canBeEdited()) {
            return false;
        }

        // Operator boleh edit own draft. Admin (canUpdate) tidak edit operator
        // punya — admin reject saja kalau ada masalah.
        if ($user->canUpdate(self::MENU)) {
            return true;
        }

        return $user->canCreate(self::MENU) && $grn->received_by === $user->id;
    }

    public function delete(User $user, GoodsReceipt $grn): bool
    {
        return false;
    }

    public function submit(User $user, GoodsReceipt $grn): bool
    {
        return $this->update($user, $grn);
    }

    public function cancel(User $user, GoodsReceipt $grn): bool
    {
        return $this->update($user, $grn);
    }

    public function post(User $user, GoodsReceipt $grn): bool
    {
        // Admin / superadmin only. (Operator tidak posting.)
        return $user->canUpdate(self::MENU);
    }

    public function reject(User $user, GoodsReceipt $grn): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function approveOverReceive(User $user, GoodsReceipt $grn): bool
    {
        return $user->canUpdate(self::MENU);
    }

    /**
     * Tandai / batal-tandai pending penerimaan langsung selesai — admin /
     * superadmin only (menyentuh angka pending di stok).
     */
    public function settleDirectPending(User $user, GoodsReceipt $grn): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function downloadPdf(User $user, GoodsReceipt $grn): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Upload/hapus lampiran surat penerimaan. Boleh selama GRN belum posted
     * (draft/submitted/rejected). Admin (canUpdate) boleh semua; operator
     * (canCreate) hanya untuk GRN miliknya.
     */
    public function manageAttachments(User $user, GoodsReceipt $grn): bool
    {
        if (! $grn->canManageAttachments()) {
            return false;
        }

        if ($user->canUpdate(self::MENU)) {
            return true;
        }

        return $user->canCreate(self::MENU) && $grn->received_by === $user->id;
    }
}
