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
     * Find a discount by code. 
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

        return ['total_discount' => $totalDiscount, 'per_line' => $this->splitAcrossLines($eligibleLines, $totalDiscount)];
    }

    /**
     * Split a discount over the lines in whole cents, in proportion to line value.
     * Every line gets its rounded-down share; the leftover cents go to the lines
     * with the biggest remainder (later lines first on a tie). Shares are never
     * negative and always add up to the total.
     *
     * @param  array<int, array{weekmenu_id: int, menu_id: int, quantity: int, unit_price: float}>  $lines
     * @return array<int, float> weekmenu_id => amount
     */
    private function splitAcrossLines(array $lines, float $totalDiscount): array
    {
        $weights = array_map(fn ($line) => (int) round($line['unit_price'] * $line['quantity'] * 100), $lines);
        $weightSum = array_sum($weights);
        $totalCents = (int) round($totalDiscount * 100);

        $cents = [];
        $remainders = [];

        foreach ($weights as $index => $weight) {
            $exact = $totalCents * $weight / $weightSum;
            $cents[$index] = (int) floor($exact);
            $remainders[$index] = $exact - $cents[$index];
        }

        $leftover = $totalCents - array_sum($cents);
        $order = array_keys($remainders);
        usort($order, fn ($a, $b) => [$remainders[$b], $b] <=> [$remainders[$a], $a]);

        foreach (array_slice($order, 0, $leftover) as $index) {
            $cents[$index]++;
        }

        $perLine = [];

        foreach ($lines as $index => $line) {
            $perLine[$line['weekmenu_id']] = $cents[$index] / 100.0;
        }

        return $perLine;
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
