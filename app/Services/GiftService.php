<?php

namespace App\Services;

use App\Exceptions\GiftIneligibleException;
use App\Exceptions\GiftNotFoundException;
use App\Models\Gift;
use App\Models\GiftRedemption;
use App\Models\User;

class GiftService
{
    /**
     * @throws GiftNotFoundException
     */
    public function findValidCode(string $code, bool $lockForUpdate = false): Gift
    {
        $query = Gift::with('qualifyingItems')->where('code', $code);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $gift = $query->first();

        if (!$gift || !$gift->active) {
            throw new GiftNotFoundException();
        }

        return $gift;
    }

    /**
     * @param  array<int, array{weekmenu_id: int, menu_id: int, quantity: int, unit_price: float}>  $cartLines
     *
     * @throws GiftIneligibleException
     */
    public function checkEligibility(Gift $gift, User $user, array $cartLines): void
    {
        if (!$gift->active) {
            throw new GiftIneligibleException('This gift code is no longer active.');
        }

        if (!$gift->isWithinDateWindow()) {
            throw new GiftIneligibleException('This gift code is not valid at this time.');
        }

        if ($gift->target_type === 'user' && (int) $gift->user_id !== (int) $user->id) {
            throw new GiftIneligibleException('This gift code is not valid for your account.');
        }

        if ($gift->target_type === 'group' && (int) $gift->group_id !== (int) $user->group_id) {
            throw new GiftIneligibleException('This gift code is not valid for your group.');
        }

        if ($gift->target_type === 'items') {
            $qualifyingMenuIds = $gift->qualifyingItems->pluck('id')->map(fn ($id) => (int) $id)->all();
            $hasMatchingLine = collect($cartLines)->contains(fn ($line) => in_array((int) $line['menu_id'], $qualifyingMenuIds, true));

            if (!$hasMatchingLine) {
                throw new GiftIneligibleException("This code only applies to a specific item that isn't in your cart.");
            }
        }

        $qualifyingTotal = $this->qualifyingTotalFor($gift, $cartLines);

        if ($qualifyingTotal < $gift->threshold_amount) {
            throw new GiftIneligibleException(
                'This gift code requires a minimum order of €' . number_format($gift->threshold_amount, 2) . '.'
            );
        }
    }

    /**
     * @param  array<int, array{weekmenu_id: int, menu_id: int, quantity: int, unit_price: float}>  $cartLines
     * @return array{qualifying_total: float, quantity: int}
     */
    public function calculateGift(Gift $gift, array $cartLines): array
    {
        $qualifyingTotal = $this->qualifyingTotalFor($gift, $cartLines);
        $quantity = (int) floor($qualifyingTotal / $gift->threshold_amount);

        return ['qualifying_total' => $qualifyingTotal, 'quantity' => $quantity];
    }

    /**
     * @param  array<int, array{weekmenu_id: int, menu_id: int, quantity: int, unit_price: float}>  $cartLines
     */
    private function qualifyingTotalFor(Gift $gift, array $cartLines): float
    {
        if ($gift->target_type === 'items') {
            $qualifyingMenuIds = $gift->qualifyingItems->pluck('id')->map(fn ($id) => (int) $id)->all();
            $lines = array_filter($cartLines, fn ($line) => in_array((int) $line['menu_id'], $qualifyingMenuIds, true));
        } else {
            // general/user/group: whole cart, same "pre-discount gross subtotal"
            // convention as Discount::min_order_total.
            $lines = $cartLines;
        }

        return array_sum(array_map(fn ($line) => $line['unit_price'] * $line['quantity'], $lines));
    }

    public function redeem(Gift $gift, User $user, int $week, int $year, float $qualifyingTotal, int $quantity): GiftRedemption
    {
        return GiftRedemption::create([
            'gift_id' => $gift->id,
            'user_id' => $user->id,
            'group_id' => $user->group_id,
            'week' => $week,
            'year' => $year,
            'qualifying_total' => $qualifyingTotal,
            'gift_quantity' => $quantity,
        ]);
    }
}
