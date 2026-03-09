<?php
defined('ABSPATH') || exit;

/**
 * Validates whether a UK postcode falls within Greater London.
 *
 * Greater London postcode districts cover:
 *   EC, WC, N, NW, W, SW, SE, E, BR (part), CR (part), DA (part),
 *   EN (part), HA (part), IG (part), KT (part), RM (part), SM (part),
 *   TW (part), UB (part), WD (part).
 *
 * We use the Royal Mail postcode area list for Greater London boroughs.
 */
class LendoCare_Delivery_Zone {

    /**
     * Postcode areas that are FULLY within Greater London.
     */
    private static array $full_areas = [
        'EC', 'WC',
        'E', 'N', 'NW', 'SE', 'SW', 'W',
    ];

    /**
     * Postcode districts (area + district number) that are within Greater London.
     * These cover partial areas like BR, CR, DA, EN, HA, IG, KT, RM, SM, TW, UB, WD.
     */
    private static array $partial_districts = [
        // BR (Bromley)
        'BR1','BR2','BR3','BR4','BR5','BR6','BR7',
        // CR (Croydon)
        'CR0','CR2','CR3','CR4','CR5','CR6','CR7','CR8','CR9','CR44','CR90',
        // DA (Dartford/Bexley)
        'DA1','DA5','DA6','DA7','DA8','DA14','DA15','DA16','DA17','DA18',
        // EN (Enfield)
        'EN1','EN2','EN3','EN4','EN5','EN8',
        // HA (Harrow)
        'HA0','HA1','HA2','HA3','HA4','HA5','HA6','HA7','HA8','HA9',
        // IG (Ilford / Redbridge)
        'IG1','IG2','IG3','IG4','IG5','IG6','IG7','IG8','IG11',
        // KT (Kingston)
        'KT1','KT2','KT3','KT4','KT5','KT6','KT7','KT8','KT9','KT19',
        // RM (Romford / Havering)
        'RM1','RM2','RM3','RM4','RM5','RM6','RM7','RM8','RM9','RM10','RM11','RM12','RM13','RM14',
        // SM (Sutton)
        'SM1','SM2','SM3','SM4','SM5','SM6','SM7',
        // TW (Twickenham / Hounslow)
        'TW1','TW2','TW3','TW4','TW5','TW6','TW7','TW8','TW9','TW10','TW11','TW12','TW13','TW14',
        // UB (Uxbridge / Hillingdon)
        'UB1','UB2','UB3','UB4','UB5','UB6','UB7','UB8','UB9','UB10','UB11','UB18',
        // WD (Watford - small part in Barnet)
        'WD6','WD23',
    ];

    /**
     * Check if a postcode is in Greater London.
     *
     * @param string $postcode Raw postcode string (any format/case).
     * @return bool
     */
    public static function is_in_greater_london(string $postcode): bool {
        $clean = strtoupper(preg_replace('/\s+/', '', $postcode));

        // Basic UK postcode format validation
        if (!preg_match('/^([A-Z]{1,2})(\d{1,2}[A-Z]?)(\d[A-Z]{2})$/', $clean, $matches)) {
            return false;
        }

        $area     = $matches[1];          // e.g. "SW"
        $district = $area . $matches[2];  // e.g. "SW1A"

        // 1. Check full areas
        if (in_array($area, self::$full_areas, true)) {
            return true;
        }

        // 2. Check partial districts (strip letters from district suffix for matching)
        $district_numeric = preg_replace('/[A-Z]+$/', '', $district); // "SW1A" -> "SW1"
        if (in_array($district_numeric, self::$partial_districts, true)) {
            return true;
        }
        if (in_array($district, self::$partial_districts, true)) {
            return true;
        }

        return false;
    }

    /**
     * Validate via postcodes.io API for extra accuracy (optional, requires network).
     * Falls back to local validation if API is unavailable.
     *
     * @param string $postcode
     * @return array ['valid' => bool, 'address' => string|null, 'error' => string|null]
     */
    public static function validate_postcode(string $postcode): array {
        $clean = strtoupper(trim($postcode));

        // Try postcodes.io
        $response = wp_remote_get(
            'https://api.postcodes.io/postcodes/' . urlencode($clean),
            ['timeout' => 5]
        );

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($data['result'])) {
                $result = $data['result'];
                $admin_county = $result['admin_county'] ?? '';
                $region       = $result['region'] ?? '';

                $in_london = (
                    stripos($region, 'london') !== false ||
                    stripos($admin_county, 'london') !== false ||
                    self::is_in_greater_london($clean)
                );

                $address_parts = array_filter([
                    $result['parish'] ?? '',
                    $result['admin_ward'] ?? '',
                    $result['admin_district'] ?? '',
                    $result['region'] ?? '',
                ]);

                return [
                    'valid'      => $in_london,
                    'postcode'   => $result['postcode'],
                    'area_name'  => implode(', ', $address_parts),
                    'error'      => null,
                ];
            }

            return ['valid' => false, 'postcode' => $clean, 'area_name' => null, 'error' => __('Postcode not found.', 'lendocare-rental')];
        }

        // Fallback: local logic
        $in_london = self::is_in_greater_london($clean);
        return [
            'valid'     => $in_london,
            'postcode'  => $clean,
            'area_name' => null,
            'error'     => null,
        ];
    }
}
