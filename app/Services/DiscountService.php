<?php

namespace App\Services;

use App\Exceptions\DiscountIneligibleException;
use App\Exceptions\DiscountNotFoundException;
use App\Models\Discount;
use App\Models\DiscountRedemption;
use App\Models\User;

class DiscountService
{
    /**
     * Find a discount by code. $cartLines is not needed here; eligibility
     * against the cart is checked separately in checkEligibility().
     *
     * @throws DiscountNotFoundException
     */
    public function findValidCode(string $code, bool $lockForUpdate = false): Discount
    {
        $query = Discount::where('code', $code);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $discount = $query->first();

        if (!$discount || !$discount->active) {
            throw new DiscountNotFoundException();
        }

        return $discount;
    }

    /**
     * @param  array<int, array{weekmenu_id: int, menu_id: int, quantity: int, unit_price: float}>  $cartLines
     *
     * @throws DiscountIneligibleException
     */
    public function checkEligibility(Discount $discount, User $user, array $cartLines): void
    {
        if (!$discount->active) {
            throw new DiscountIneligibleException('This discount code is no longer active.');
        }

        if (!$discount->isWithinDateWindow()) {
            throw new DiscountIneligibleException('This discount code is not valid at this time.');
        }

        if ($discount->target_type === 'user' && (int) $discount->user_id !== (int) $user->id) {
            throw new DiscountIneligibleException('This discount code is not valid for your account.');
        }

        if ($discount->target_type === 'group' && (int) $discount->group_id !== (int) $user->group_id) {
            throw new DiscountIneligibleException('This discount code is not valid for your group.');
        }

        if ($discount->target_type === 'menu') {
            $hasMatchingLine = collect($cartLines)->contains(fn ($line) => (int) $line['menu_id'] === (int) $discount->menu_id);

            if (!$hasMatchingLine) {
                throw new DiscountIneligibleException("This code only applies to a specific item that isn't in your cart.");
            }
        }

        if ($discount->min_order_total !== null) {
            $cartSubtotal = collect($cartLines)->sum(fn ($line) => $line['unit_price'] * $line['quantity']);

            if ($cartSubtotal < $discount->min_order_total) {
                throw new DiscountIneligibleException(
                    'This discount code requires a minimum order total of €' . number_format($discount->min_order_total, 2) . '.'
                );
            }
        }

        if (!$discount->hasRemainingGlobalUses()) {
            throw new DiscountIneligibleException('This discount code has reached its usage limit.');
        }

        if (!$discount->hasRemainingUsesForUser($user->id)) {
            throw new DiscountIneligibleException('You have already used this discount code the maximum number of times.');
        }
    }

    /**
     * @param  array<int, array{weekmenu_id: int, menu_id: int, quantity: int, unit_price: float}>  $cartLines
     * @return array{total_discount: float, per_line: array<int, float>}
     */
    public function calculateDiscount(Discount $discount, array $cartLines): array
    {
        $eligibleLines = $discount->target_type === 'menu'
            ? array_values(array_filter($cartLines, fn ($line) => (int) $line['menu_id'] === (int) $discount->menu_id))
            : $cartLines;

        // Sort deterministically so the "last line absorbs the rounding remainder" is stable.
        usort($eligibleLines, fn ($a, $b) => $a['weekmenu_id'] <=> $b['weekmenu_id']);

        $eligibleSubtotal = array_sum(array_map(fn ($line) => $line['unit_price'] * $line['quantity'], $eligibleLines));

        if ($eligibleSubtotal <= 0) {
            return ['total_discount' => 0.0, 'per_line' => []];
        }

        $rawDiscount = $discount->type === 'percentage'
            ? $eligibleSubtotal * ($discount->value / 100)
            : (float) $discount->value;

        // Never let a discount exceed what's actually eligible (safety net for gifts up to 100% off).
        $totalDiscount = round(min($rawDiscount, $eligibleSubtotal), 2);

        $perLine = [];
        $allocated = 0.0;
        $lastIndex = count($eligibleLines) - 1;

        foreach ($eligibleLines as $index => $line) {
            $lineSubtotal = $line['unit_price'] * $line['quantity'];

            if ($index === $lastIndex) {
                $amount = round($totalDiscount - $allocated, 2);
            } else {
                $amount = round(($lineSubtotal / $eligibleSubtotal) * $totalDiscount, 2);
                $allocated += $amount;
            }

            $perLine[$line['weekmenu_id']] = $amount;
        }

        return ['total_discount' => $totalDiscount, 'per_line' => $perLine];
    }

    public function redeem(Discount $discount, User $user, int $week, int $year, float $orderTotalBefore, float $discountAmount): DiscountRedemption
    {
        return DiscountRedemption::create([
            'discount_id' => $discount->id,
            'user_id' => $user->id,
            'group_id' => $user->group_id,
            'week' => $week,
            'year' => $year,
            'order_total_before' => $orderTotalBefore,
            'discount_amount' => $discountAmount,
        ]);
    }
}
