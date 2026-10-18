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
     * There is only ever one "current" gift — whichever currently-valid row
     * exists. Eligibility is no longer code/target based; it's a per-client
     * member ID check (see checkEligibility).
     *
     * @throws GiftNotFoundException
     */
    public function findActiveGift(bool $lockForUpdate = false): Gift
    {
        $query = Gift::currentlyValid();

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $gift = $query->first();

        if (!$gift) {
            throw new GiftNotFoundException('There is no gift available right now.');
        }

        return $gift;
    }

    /**
     * @param  array<int, array{weekmenu_id: int, menu_id: int, quantity: int, unit_price: float}>  $cartLines
     *
     * @throws GiftIneligibleException
     */
    public function checkEligibility(Gift $gift, User $user, string $submittedCode, array $cartLines): void
    {
        if (!$gift->active || !$gift->isWithinDateWindow()) {
            throw new GiftIneligibleException('There is no gift available right now.');
        }

        if ($user->member_code === null) {
            throw new GiftIneligibleException('No membership ID is on file for your account.');
        }

        if (trim($submittedCode) !== $user->member_code) {
            throw new GiftIneligibleException('Incorrect ID.');
        }

        $qualifyingTotal = $this->qualifyingTotalFor($cartLines);

        if ($qualifyingTotal < $gift->threshold_amount) {
            throw new GiftIneligibleException(
                'This requires a minimum order of €' . number_format($gift->threshold_amount, 2) . '.'
            );
        }
    }

    /**
     * @param  array<int, array{weekmenu_id: int, menu_id: int, quantity: int, unit_price: float}>  $cartLines
     * @return array{qualifying_total: float, quantity: int}
     */
    public function calculateGift(Gift $gift, array $cartLines): array
    {
        $qualifyingTotal = $this->qualifyingTotalFor($cartLines);
        $quantity = (int) floor($qualifyingTotal / $gift->threshold_amount);

        return ['qualifying_total' => $qualifyingTotal, 'quantity' => $quantity];
    }

    /**
     * @param  array<int, array{weekmenu_id: int, menu_id: int, quantity: int, unit_price: float}>  $cartLines
     */
    private function qualifyingTotalFor(array $cartLines): float
    {
        return array_sum(array_map(fn ($line) => $line['unit_price'] * $line['quantity'], $cartLines));
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
