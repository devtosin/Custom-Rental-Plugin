<?php
/**
 * Template: Rental Booking Form
 * Step 1: Delivery address  (airport yes/no → pick airport OR search address)
 *         Collection address (same-as toggle, same airport logic)
 * Step 2: Delivery date + time
 * Step 3: Collection date + time
 * Step 4: Price summary + Book Now
 *
 * @var LendoCare_Rental_Product $product
 */
defined( 'ABSPATH' ) || exit;

$product_id = $product->get_id();
$min_days   = (int) $product->get_min_rental_days();
$blocked    = wp_json_encode( $product->get_blocked_dates() ?: [] );
$deposit    = (float) $product->get_deposit_amount();

/* Time <option> list — used for both delivery and collection selects */
$time_options = '
<option value="">— Select a time —</option>
<optgroup label="Standard hours (Mon–Fri, 9 am–5 pm) — no extra charge">
  <option value="09:00">9:00 am</option>
  <option value="10:00">10:00 am</option>
  <option value="11:00">11:00 am</option>
  <option value="12:00">12:00 pm (noon)</option>
  <option value="13:00">1:00 pm</option>
  <option value="14:00">2:00 pm</option>
  <option value="15:00">3:00 pm</option>
  <option value="16:00">4:00 pm</option>
</optgroup>
<optgroup label="Out-of-hours (evenings / weekends / bank holidays) — +£65 extra charge">
  <option value="07:00">7:00 am</option>
  <option value="08:00">8:00 am</option>
  <option value="17:00">5:00 pm</option>
  <option value="18:00">6:00 pm</option>
  <option value="19:00">7:00 pm</option>
  <option value="20:00">8:00 pm</option>
</optgroup>';
?>

<div class="lendocare-booking-form-wrap">

    <div class="lc-headline-price">
        <span class="lc-price-display"><?php echo wc_price( $product->get_price_per_week() ); ?> <span class="lc-per-unit">/ week</span></span>
        <span class="lc-pricing-note">Billed per week — e.g. 8 days = 2 weeks</span>
    </div>

    <form class="lendocare-booking-form" novalidate>
        <?php wp_nonce_field( 'lendocare_nonce', 'lendocare_nonce_field' ); ?>
        <input type="hidden" name="product_id"           value="<?php echo esc_attr( $product_id ); ?>">
        <input type="hidden" id="lc-hidden-deliv-pc"     name="postcode"             value="">
        <input type="hidden" id="lc-hidden-coll-pc"      name="collection_postcode"  value="">
        <input type="hidden" id="lc-hidden-deliv-airport" name="delivery_airport"    value="none">
        <input type="hidden" id="lc-hidden-coll-airport"  name="collection_airport"  value="none">

        <!-- ════════════════════════════════════════════════════════
             STEP 1 — Addresses
        ════════════════════════════════════════════════════════ -->
        <div class="lc-step" id="lc-step-1">
            <div class="lc-step-header">
                <span class="lc-step-num">1</span>
                <span class="lc-step-title">Delivery &amp; collection addresses</span>
            </div>

            <!-- ──── DELIVERY ──── -->
            <div class="lc-addr-block" id="lc-deliv-block">
                <p class="lc-block-label">
                    <span class="lc-icon">📍</span>
                    <strong>Delivery address</strong>
                </p>

                <!-- Q: Is it an airport? -->
                <label class="lc-checkbox-label lc-showroom-toggle">
                    <input type="checkbox" id="lc-deliv-showroom" name="deliv_showroom" value="1">
                    <span class="lc-checkbox-box"></span>
                    Delivery from our showroom (Unit 7, Firmdale Village, Ryan Dr, Brentford, TW8 9ZB)
                </label>

                <div class="lc-question-row" id="lc-deliv-airport-question">
                    <span class="lc-question-text">Is this delivery to an airport?</span>
                    <div class="lc-yn-wrap">
                        <label class="lc-yn-pill">
                            <input type="radio" name="deliv_is_airport" value="no" checked>
                            <span>No</span>
                        </label>
                        <label class="lc-yn-pill">
                            <input type="radio" name="deliv_is_airport" value="yes">
                            <span>Yes</span>
                        </label>
                    </div>
                </div>

                <!-- Airport picker (shown when Yes) -->
                <div id="lc-deliv-airport-picker" class="lc-airport-picker" style="display:none;">
                    <p class="lc-picker-hint">Select the airport:</p>
                    <div class="lc-airport-pills">
                        <label class="lc-ap-pill">
                            <input type="radio" name="deliv_airport_name" value="heathrow">
                            <span>✈️ Heathrow<em>+£17.50</em></span>
                        </label>
                        <label class="lc-ap-pill">
                            <input type="radio" name="deliv_airport_name" value="stansted">
                            <span>✈️ Stansted<em>+£20</em></span>
                        </label>
                        <label class="lc-ap-pill">
                            <input type="radio" name="deliv_airport_name" value="luton">
                            <span>✈️ Luton<em>+£28.50</em></span>
                        </label>
                        <label class="lc-ap-pill">
                            <input type="radio" name="deliv_airport_name" value="gatwick">
                            <span>✈️ Gatwick<em>+£59 each way</em></span>
                        </label>
                    </div>
                    <p class="lc-airport-confirmed" id="lc-deliv-airport-ok" style="display:none;">
                        ✓ Airport selected — we deliver there
                    </p>
                </div>

                <!-- Regular address search (shown when No) -->
                <div id="lc-deliv-addr-search" class="lc-addr-search">
                    <p class="lc-search-hint">
                        Type a postcode (e.g. <code>TW8 9ZB</code>) or a hotel / street name:
                    </p>
                    <div class="lc-input-wrap">
                        <input type="text"
                               id="lc-input-delivery"
                               name="delivery_address"
                               class="lc-text-input"
                               placeholder="Postcode or place name…"
                               autocomplete="off"
                               spellcheck="false">
                        <span class="lc-spinner" id="lc-spin-delivery"></span>
                    </div>
                    <ul class="lc-dropdown" id="lc-dd-delivery" role="listbox"></ul>
                    <div class="lc-addr-feedback" id="lc-fb-delivery" role="alert" aria-live="polite"></div>
                </div>
            </div><!-- /delivery -->

            <hr class="lc-divider">

            <!-- ──── COLLECTION ──── -->
            <div class="lc-addr-block" id="lc-coll-block">
                <p class="lc-block-label">
                    <span class="lc-icon">🔄</span>
                    <strong>Collection address</strong>
                    <span class="lc-label-sub">(where we pick the equipment up from)</span>
                </p>

                <label class="lc-checkbox-label">
                    <input type="checkbox" id="lc-same-addr" name="same_address" value="1" checked>
                    <span class="lc-checkbox-box"></span>
                    Same as delivery address
                </label>

                <!-- Extra collection section — hidden by default (same-addr checked) -->
                <div id="lc-coll-extra" style="display:none; margin-top:1rem;">

                    <label class="lc-checkbox-label lc-showroom-toggle" style="margin-bottom:0.75rem;">
                        <input type="checkbox" id="lc-collect-showroom" name="collect_showroom" value="1">
                        <span class="lc-checkbox-box"></span>
                        Collection from our showroom (Unit 7, Firmdale Village, Ryan Dr, Brentford, TW8 9ZB)
                    </label>

                    <!-- Q: Is it an airport? -->
                    <div class="lc-question-row" id="lc-coll-airport-question">
                        <span class="lc-question-text">Is this collection from an airport?</span>
                        <div class="lc-yn-wrap">
                            <label class="lc-yn-pill">
                                <input type="radio" name="coll_is_airport" value="no" checked>
                                <span>No</span>
                            </label>
                            <label class="lc-yn-pill">
                                <input type="radio" name="coll_is_airport" value="yes">
                                <span>Yes</span>
                            </label>
                        </div>
                    </div>

                    <!-- Airport picker (shown when Yes) -->
                    <div id="lc-coll-airport-picker" class="lc-airport-picker" style="display:none;">
                        <p class="lc-picker-hint">Select the airport:</p>
                        <div class="lc-airport-pills">
                            <label class="lc-ap-pill">
                                <input type="radio" name="coll_airport_name" value="heathrow">
                                <span>✈️ Heathrow<em>+£17.50</em></span>
                            </label>
                            <label class="lc-ap-pill">
                                <input type="radio" name="coll_airport_name" value="stansted">
                                <span>✈️ Stansted<em>+£20</em></span>
                            </label>
                            <label class="lc-ap-pill">
                                <input type="radio" name="coll_airport_name" value="luton">
                                <span>✈️ Luton<em>+£28.50</em></span>
                            </label>
                            <label class="lc-ap-pill">
                                <input type="radio" name="coll_airport_name" value="gatwick">
                                <span>✈️ Gatwick<em>+£59 each way</em></span>
                            </label>
                        </div>
                        <p class="lc-airport-confirmed" id="lc-coll-airport-ok" style="display:none;">
                            ✓ Airport selected — we collect from there
                        </p>
                    </div>

                    <!-- Regular address search (shown when No) -->
                    <div id="lc-coll-addr-search" class="lc-addr-search">
                        <p class="lc-search-hint">Type a postcode or hotel / street name:</p>
                        <div class="lc-input-wrap">
                            <input type="text"
                                   id="lc-input-collection"
                                   name="collection_address"
                                   class="lc-text-input"
                                   placeholder="Postcode or place name…"
                                   autocomplete="off"
                                   spellcheck="false">
                            <span class="lc-spinner" id="lc-spin-collection"></span>
                        </div>
                        <ul class="lc-dropdown" id="lc-dd-collection" role="listbox"></ul>
                        <div class="lc-addr-feedback" id="lc-fb-collection" role="alert" aria-live="polite"></div>
                    </div>
                </div>
            </div><!-- /collection -->
        </div><!-- /step 1 -->

        <!-- ════════════════════════════════════════════════════════
             STEP 2 — Delivery date & time
        ════════════════════════════════════════════════════════ -->
        <div class="lc-step" id="lc-step-2">
            <div class="lc-step-header">
                <span class="lc-step-num">2</span>
                <span class="lc-step-title">Delivery date &amp; time</span>
            </div>
            <div class="lc-dt-row">
                <div class="lc-field">
                    <label class="lc-field-label" for="lc-pickup-date">Delivery date</label>
                    <input type="text" id="lc-pickup-date" name="pickup_date"
                           class="lc-text-input" placeholder="Select date" readonly required>
                </div>
                <div class="lc-field">
                    <label class="lc-field-label" for="lc-deliv-time">Delivery time</label>
                    <select id="lc-deliv-time" name="delivery_time" class="lc-select">
                        <?php echo $time_options; ?>
                    </select>
                    <div class="lc-time-badge" id="lc-badge-deliv"></div>
                </div>
            </div>
        </div><!-- /step 2 -->

        <!-- ════════════════════════════════════════════════════════
             STEP 3 — Collection date & time
        ════════════════════════════════════════════════════════ -->
        <div class="lc-step" id="lc-step-3">
            <div class="lc-step-header">
                <span class="lc-step-num">3</span>
                <span class="lc-step-title">Collection date &amp; time</span>
            </div>
            <div class="lc-dt-row">
                <div class="lc-field">
                    <label class="lc-field-label" for="lc-dropoff-date">Collection date</label>
                    <input type="text" id="lc-dropoff-date" name="dropoff_date"
                           class="lc-text-input" placeholder="Select date" readonly required>
                </div>
                <div class="lc-field">
                    <label class="lc-field-label" for="lc-coll-time">Collection time</label>
                    <select id="lc-coll-time" name="collection_time" class="lc-select">
                        <?php echo $time_options; ?>
                    </select>
                    <div class="lc-time-badge" id="lc-badge-coll"></div>
                </div>
            </div>
            <div id="lc-duration-hint" class="lc-duration-hint" aria-live="polite"></div>
        </div><!-- /step 3 -->

        <!-- ════════════════════════════════════════════════════════
             STEP 4 — Price summary
        ════════════════════════════════════════════════════════ -->
        <div class="lc-step" id="lc-step-4">
            <div class="lc-step-header">
                <span class="lc-step-num">4</span>
                <span class="lc-step-title">Your booking summary</span>
            </div>
            <table class="lc-summary-table">
                <tbody id="lc-summary-tbody"></tbody>
            </table>
            <p class="lc-billing-note" id="lc-billing-note"></p>
        </div><!-- /step 4 -->

        <!-- CTA -->
        <div id="lc-actions" style="display:none;">
            <button type="submit" class="lc-btn lc-btn-book single_add_to_cart_button button alt">
                <span class="lc-btn-text">Book Now</span>
                <span class="lc-btn-loading" style="display:none;">Adding to cart…</span>
            </button>
            <p class="lc-policy-note">Free cancellation up to 48 hours before pickup.</p>
        </div>

        <div class="lc-error-message" role="alert" aria-live="assertive"></div>

        <div class="lc-success-message" style="display:none;" role="status">
            <div class="lc-success-icon">✓</div>
            <p>Your rental has been added to the cart!</p>
            <div class="lc-success-buttons">
                <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="lc-btn lc-btn-checkout">
                    Proceed to Checkout
                </a>
                <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="lc-btn lc-btn-cart">
                    View Cart
                </a>
            </div>
        </div>
    </form>

    <!-- Product config for JS -->
    <script type="application/json" id="lc-product-data">
    {
        "product_id":    <?php echo $product_id; ?>,
        "pricing_mode":  "per_week",
        "min_days":      <?php echo $min_days; ?>,
        "blocked_dates": <?php echo $blocked; ?>,
        "has_deposit":   <?php echo $deposit > 0 ? 'true' : 'false'; ?>
    }
    </script>
</div>
