<?php
defined('ABSPATH') || exit;

class LendoCare_Product_Type {

    const TYPE = 'rental';

    /**
     * Add "Rental" to WooCommerce product type dropdown.
     */
    public static function add_product_type(array $types): array {
        $types[self::TYPE] = __('Rental', 'lendocare-rental');
        return $types;
    }

    /**
     * Map product type slug to our custom class.
     */
    public static function product_class(string $classname, string $product_type): string {
        if ($product_type === self::TYPE) {
            return 'LendoCare_Rental_Product';
        }
        return $classname;
    }
}
