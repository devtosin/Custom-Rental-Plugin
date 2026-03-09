=== Custom Rental - Tosin ===
Requires at least: WordPress 6.0
Tested up to: WordPress 6.7
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later

WooCommerce rental booking system — Greater London delivery zone, per-day or per-week pricing, Nominatim address search (no API key needed).

== Description ==

Adds a custom **Rental** product type to WooCommerce that replaces the standard buy-now flow with a multi-step booking form.

**Features:**

* New **Rental** product type — set a product to Rental and the standard Add to Cart is replaced automatically
* **Per Day or Per Week** pricing mode, configurable per product
  * Per Week mode: days are always rounded up to the next full week (8 days = 2 weeks charge; 7 days = 1 week)
* **Postcode validation** — customer must enter their postcode; only Greater London postcodes are accepted (validated against postcodes.io with local fallback)
* **Flatpickr date picker** — blocked dates, minimum & maximum rental duration enforced on the frontend
* **Price breakdown** shown live: rental charge, delivery fee, refundable security deposit, total
* All booking details (pickup date, return date, duration, postcode, price breakdown) saved to WooCommerce order line items
* **WooCommerce Checkout** handles payment as normal — no custom payment logic needed
* Admin settings for out-of-hours and weekend delivery surcharges (framework ready — apply in `LendoCare_Ajax::add_to_cart` for v1.1)

== Installation ==

1. Upload the `lendocare-rental` folder to `/wp-content/plugins/`
2. Activate through **Plugins → Installed Plugins**
3. Go to any product → change type to **Rental** → fill in the Rental Settings tab

== Rental Settings tab (per product) ==

| Field | Description |
|---|---|
| Charge rental | Per Day or Per Week |
| Price per day / week | Base rental rate |
| Security deposit | Refundable amount added at checkout |
| Standard delivery fee | Base delivery & collection charge |
| Minimum rental (days) | Prevents too-short bookings |
| Maximum rental (days) | Prevents too-long bookings |
| Blocked dates | JSON array of unavailable dates |

== Global Settings ==

WooCommerce → Settings → Products → **LendoCare Rental**

* Out-of-hours surcharge % + time window
* Weekend surcharge %

== Changelog ==

= 1.0.0 =
* Initial release
