<?php
defined('ABSPATH') || exit;

class LendoCare_Booking_Calculator {

    /**
     * Calculate the number of rental days between two dates (inclusive of pickup, exclusive of return).
     *
     * @param string $pickup_date  YYYY-MM-DD
     * @param string $dropoff_date YYYY-MM-DD
     * @return int Number of days
     */
    public static function get_days(string $pickup_date, string $dropoff_date): int {
        $pickup  = new DateTime($pickup_date);
        $dropoff = new DateTime($dropoff_date);
        $diff    = $pickup->diff($dropoff);
        return max(1, $diff->days);
    }

    /**
     * Calculate the rental price.
     *
     * Per Day:  price = days * price_per_day
     * Per Week: price = ceil(days / 7) * price_per_week
     *           So 1-7 days = 1 week, 8-14 = 2 weeks, etc.
     *
     * @param LendoCare_Rental_Product $product
     * @param int $days
     * @return array ['rental_price' => float, 'weeks' => int|null, 'days' => int, 'billing_unit' => string]
     */
    public static function calculate(LendoCare_Rental_Product $product, int $days): array {
        $mode = $product->get_pricing_mode();

        if ($mode === 'per_week') {
            $weeks         = (int) ceil($days / 7);
            $rental_price  = $weeks * $product->get_price_per_week();
            $billing_unit  = $weeks === 1 ? __('1 week', 'lendocare-rental') : sprintf(__('%d weeks', 'lendocare-rental'), $weeks);
        } else {
            $weeks        = null;
            $rental_price = $days * $product->get_price_per_day();
            $billing_unit = $days === 1 ? __('1 day', 'lendocare-rental') : sprintf(__('%d days', 'lendocare-rental'), $days);
        }

        return [
            'rental_price' => round($rental_price, 2),
            'weeks'        => $weeks,
            'days'         => $days,
            'billing_unit' => $billing_unit,
            'mode'         => $mode,
        ];
    }

    /**
     * Full cost breakdown for cart/checkout.
     *
     * @param LendoCare_Rental_Product $product
     * @param int $days
     * @param float $delivery_fee
     * @return array
     */
    public static function full_breakdown(LendoCare_Rental_Product $product, int $days, float $delivery_fee = 0.0): array {
        $calc    = self::calculate($product, $days);
        $deposit = $product->get_deposit_amount();

        return array_merge($calc, [
            'delivery_fee'  => round($delivery_fee, 2),
            'deposit'       => round($deposit, 2),
            'subtotal'      => round($calc['rental_price'] + $delivery_fee, 2),
            'total'         => round($calc['rental_price'] + $delivery_fee + $deposit, 2),
        ]);
    }

    /**
     * Get week boundary label for display.
     * e.g., "Week 1 (days 1–7)", "Week 2 (days 8–14)"
     */
    public static function get_week_label(int $weeks): string {
        $start = ($weeks - 1) * 7 + 1;
        $end   = $weeks * 7;
        return sprintf(__('Week %d (days %d–%d)', 'lendocare-rental'), $weeks, $start, $end);
    }
}
