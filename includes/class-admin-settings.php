<?php
defined('ABSPATH') || exit;

class LendoCare_Admin_Settings {

    public function init() {
        add_filter('woocommerce_get_sections_products', [$this, 'add_section']);
        add_filter('woocommerce_get_settings_products', [$this, 'get_settings'], 10, 2);
    }

    public function add_section(array $sections): array {
        $sections['lendocare_rental'] = __('LendoCare Rental', 'lendocare-rental');
        return $sections;
    }

    public function get_settings(array $settings, string $current_section): array {
        if ($current_section !== 'lendocare_rental') return $settings;

        return [
            [
                'title' => __('LendoCare Rental Settings', 'lendocare-rental'),
                'type'  => 'title',
                'desc'  => __('Configure delivery pricing and zone settings.', 'lendocare-rental'),
                'id'    => 'lendocare_rental_section_title',
            ],
            [
                'title'   => __('Out-of-hours delivery surcharge (%)', 'lendocare-rental'),
                'desc'    => __('Extra % added to delivery fee for out-of-hours bookings (e.g. 25 = 25% surcharge). Leave 0 to disable.', 'lendocare-rental'),
                'id'      => 'lendocare_ooh_surcharge_pct',
                'type'    => 'number',
                'default' => '0',
                'custom_attributes' => ['min' => '0', 'max' => '200', 'step' => '1'],
            ],
            [
                'title'   => __('Out-of-hours start time', 'lendocare-rental'),
                'id'      => 'lendocare_ooh_start',
                'type'    => 'text',
                'default' => '18:00',
                'desc'    => 'HH:MM (24-hour)',
            ],
            [
                'title'   => __('Out-of-hours end time', 'lendocare-rental'),
                'id'      => 'lendocare_ooh_end',
                'type'    => 'text',
                'default' => '08:00',
                'desc'    => 'HH:MM (24-hour)',
            ],
            [
                'title'   => __('Weekend surcharge (%)', 'lendocare-rental'),
                'desc'    => __('Extra % for Saturday/Sunday deliveries. Leave 0 to disable.', 'lendocare-rental'),
                'id'      => 'lendocare_weekend_surcharge_pct',
                'type'    => 'number',
                'default' => '0',
                'custom_attributes' => ['min' => '0', 'max' => '200', 'step' => '1'],
            ],
            [
                'type' => 'sectionend',
                'id'   => 'lendocare_rental_section_end',
            ],
        ];
    }
}
