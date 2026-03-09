/* global jQuery, lendocareData, flatpickr */
/* Custom Rental – Tosin */
(function ($) {
    'use strict';

    /* ─── Bank Holidays England & Wales ──────────────────────────────────── */
    var SHOWROOM_ADDRESS = 'Unit 7, Firmdale Village, Ryan Dr, Brentford, TW8 9ZB';
    var SHOWROOM_POSTCODE = 'TW8 9ZB';

    var BANK_HOLIDAYS = [
        '2025-01-01','2025-04-18','2025-04-21','2025-05-05','2025-05-26',
        '2025-08-25','2025-12-25','2025-12-26',
        '2026-01-01','2026-04-03','2026-04-06','2026-05-04','2026-05-25',
        '2026-08-31','2026-12-25','2026-12-28',
        '2027-01-01','2027-03-26','2027-03-29','2027-05-03','2027-05-31',
        '2027-08-30','2027-12-27','2027-12-28'
    ];

    /* ─── Pricing ─────────────────────────────────────────────────────────── */
    var PRICE = {
        base:           100,  // £50 delivery + £50 collection
        ooh_delivery:    65,  // extra charge per OOH delivery leg
        ooh_collection:  65,  // extra charge per OOH collection leg
        airport: {
            heathrow: 35,     // £17.50 each way
            stansted: 40,     // £20 each way
            luton:    57,     // £28.50 each way
            gatwick: 118     // £59 each way / £118 both legs
        }
    };

    /* ─── London postcode prefixes ────────────────────────────────────────── */
    var LONDON_PREFIXES = [
        'E','EC','N','NW','SE','SW','W','WC',
        'BR','CR','DA','EN','HA','IG','KT','RM','SM','TW','UB',
        'TN14','TN16','WD3','WD6','WD23'
    ];
    function inLondon(rawPc) {
        var pc = (rawPc || '').toUpperCase().replace(/\s+/g, '');
        // Check each allowed prefix
        for (var i = 0; i < LONDON_PREFIXES.length; i++) {
            var pref = LONDON_PREFIXES[i];
            // For multi-char numeric prefixes like TN14, match exactly
            if (pref.match(/\d/)) {
                if (pc.indexOf(pref) === 0) return true;
            } else {
                // Letter-only prefix — make sure the char after it is a digit
                if (pc.indexOf(pref) === 0 && /\d/.test(pc.charAt(pref.length))) return true;
            }
        }
        return false;
    }

    /* ─── OOH detection ──────────────────────────────────────────────────── */
    function isOOH(dateStr, timeStr) {
        if (!dateStr || !timeStr) return false;
        var dow  = new Date(dateStr + 'T12:00:00').getDay(); // 0=Sun 6=Sat
        var hour = parseInt(timeStr.split(':')[0], 10);
        return dow === 0 || dow === 6
            || BANK_HOLIDAYS.indexOf(dateStr) !== -1
            || hour < 9 || hour >= 17;
    }

    /* ─── £ formatting ────────────────────────────────────────────────────── */
    function gbp(n) {
        var s = parseFloat(n).toFixed(2);
        return '\u00a3' + (s.slice(-3) === '.00' ? s.slice(0, -3) : s);
    }

    /* ════════════════════════════════════════════════════════════════════════
       ADDRESS SEARCH
       postcodes.io  → fast postcode autocomplete (no rate limit)
       Nominatim     → place name search (hotels, streets etc.)
    ════════════════════════════════════════════════════════════════════════ */
    var _cache  = {};
    var PC_TEST = /^[A-Z]{1,2}\d/i;

    function searchPostcodes(q, cb) {
        var key = 'p:' + q;
        if (_cache[key]) { cb(_cache[key]); return; }
        var url = 'https://api.postcodes.io/postcodes/'
                + encodeURIComponent(q.replace(/\s/g, ''))
                + '/autocomplete';
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var list = (d.status === 200 && Array.isArray(d.result)) ? d.result : [];
                var items = list.map(function (pc) {
                    return { label: pc, postcode: pc, isPC: true };
                });
                _cache[key] = items;
                cb(items);
            })
            .catch(function () { cb([]); });
    }

    function searchPlaces(q, cb) {
        var key = 'n:' + q;
        if (_cache[key]) { cb(_cache[key]); return; }
        var url = 'https://nominatim.openstreetmap.org/search'
                + '?format=jsonv2&addressdetails=1&countrycodes=gb&limit=6&dedupe=1'
                + '&q=' + encodeURIComponent(q);
        fetch(url, { headers: { 'Accept': 'application/json', 'Accept-Language': 'en' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!Array.isArray(data)) { cb([]); return; }
                var items = data.map(function (item) {
                    var pc = (item.address && item.address.postcode) ? item.address.postcode : '';
                    if (!pc) {
                        var m = String(item.display_name).match(/\b([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})\b/i);
                        if (m) pc = m[1];
                    }
                    return { label: item.display_name, postcode: pc, isPC: false };
                });
                _cache[key] = items;
                cb(items);
            })
            .catch(function () { cb([]); });
    }

    function runSearch(raw, cb) {
        var q = (raw || '').trim();
        if (q.length < 2) { cb([]); return; }
        if (PC_TEST.test(q)) {
            searchPostcodes(q, cb);
        } else {
            searchPlaces(q, cb);
        }
    }

    /* ════════════════════════════════════════════════════════════════════════
       addressWidget(cfg)
       cfg: { inputId, dropdownId, feedbackId, spinnerId, onOk, onFail }
       Returns state object: { valid, postcode, label }
    ════════════════════════════════════════════════════════════════════════ */
    function addressWidget(cfg) {
        var state  = { valid: false, postcode: '', label: '' };
        var timer  = null;
        var $inp   = $('#' + cfg.inputId);
        var $dd    = $('#' + cfg.dropdownId);
        var $fb    = $('#' + cfg.feedbackId);
        var $spin  = $('#' + cfg.spinnerId);

        function hideDD() { $dd.empty().hide(); }

        function showDD(items) {
            $dd.empty();
            if (!items.length) { $dd.hide(); return; }
            $.each(items, function (i, item) {
                var txt  = item.label.length > 78 ? item.label.slice(0, 75) + '\u2026' : item.label;
                var $li  = $('<li>').addClass('lc-dd-item').attr('tabindex', '0')
                                    .text(txt).attr('title', item.label);
                $li.on('mousedown', function (e) { e.preventDefault(); pickItem(item); });
                $li.on('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pickItem(item); }
                    if (e.key === 'ArrowDown') { e.preventDefault(); $li.next('.lc-dd-item').focus(); }
                    if (e.key === 'ArrowUp')   {
                        e.preventDefault();
                        var $p = $li.prev('.lc-dd-item');
                        if ($p.length) { $p.focus(); } else { $inp.focus(); }
                    }
                    if (e.key === 'Escape') { hideDD(); $inp.focus(); }
                });
                $dd.append($li);
            });
            $dd.show();
        }

        function pickItem(item) {
            hideDD();
            // Fill the input with a clean short label
            $inp.val(item.isPC ? item.label : item.label.split(',')[0].trim());
            $inp.attr('title', item.label);

            var pc = item.postcode || '';
            if (!pc) {
                setFb(false, 'No postcode found \u2014 please pick a more specific result or type a full postcode.');
                markState(false, '', item.label);
            } else if (inLondon(pc)) {
                setFb(true, '\u2713 We deliver to this address');
                markState(true, pc, item.label);
            } else {
                setFb(false, 'Sorry \u2014 <strong>' + esc(pc) + '</strong> is outside our Greater London zone.');
                markState(false, '', item.label);
            }
        }

        function markState(ok, pc, lbl) {
            state.valid    = ok;
            state.postcode = pc;
            state.label    = lbl;
            if (ok && cfg.onOk)   cfg.onOk(pc, lbl);
            if (!ok && cfg.onFail) cfg.onFail();
        }

        function setFb(ok, html) {
            $fb.removeClass('lc-fb-ok lc-fb-err')
               .addClass(ok ? 'lc-fb-ok' : 'lc-fb-err')
               .html(html);
        }

        function clearState() {
            state.valid    = false;
            state.postcode = '';
            state.label    = '';
            $fb.removeClass('lc-fb-ok lc-fb-err').html('');
            if (cfg.onFail) cfg.onFail();
        }

        $inp.on('input', function () {
            clearState();
            var q = $(this).val().trim();
            if (q.length < 2) { hideDD(); $spin.removeClass('active'); return; }
            clearTimeout(timer);
            $spin.addClass('active');
            var delay = PC_TEST.test(q) ? 160 : 320;
            timer = setTimeout(function () {
                runSearch(q, function (items) {
                    $spin.removeClass('active');
                    showDD(items);
                });
            }, delay);
        });

        $inp.on('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); $dd.find('.lc-dd-item').first().focus(); }
            if (e.key === 'Escape')    { hideDD(); }
        });
        $inp.on('blur', function () { setTimeout(hideDD, 200); });

        return state;  // caller uses state.valid / state.postcode / state.label
    }

    /* ════════════════════════════════════════════════════════════════════════
       BOOT
    ════════════════════════════════════════════════════════════════════════ */
    $(function () {
        $('.lendocare-booking-form-wrap').each(function () {
            initForm($(this));
        });
    });

    function initForm($wrap) {
        var cfg       = JSON.parse($wrap.find('#lc-product-data').text());
        var productId = cfg.product_id;
        var $form     = $wrap.find('.lendocare-booking-form');

        /* ── Per-leg address state ──────────────────────────────────────────── */
        var sameAddr      = true;

        var delivIsAirport      = false;
        var delivAirport        = '';
        var delivShowroomPickup = false;
        var delivAddrOk         = false;   // true when address OR airport/showroom confirmed

        var collIsAirport      = false;
        var collAirport        = '';
        var collShowroomPickup = false;
        var collAddrOk         = true;    // starts true because same-addr is checked

        /* ── Address widgets ────────────────────────────────────────────────── */
        var delivState = addressWidget({
            inputId:    'lc-input-delivery',
            dropdownId: 'lc-dd-delivery',
            feedbackId: 'lc-fb-delivery',
            spinnerId:  'lc-spin-delivery',
            onOk:   function () { delivAddrOk = true;  checkStep1(); },
            onFail: function () { delivAddrOk = false; checkStep1(); }
        });

        var collState = addressWidget({
            inputId:    'lc-input-collection',
            dropdownId: 'lc-dd-collection',
            feedbackId: 'lc-fb-collection',
            spinnerId:  'lc-spin-collection',
            onOk:   function () { collAddrOk = true;  checkStep1(); },
            onFail: function () { collAddrOk = false; checkStep1(); }
        });


        $wrap.find('#lc-deliv-showroom').on('change', function () {
            delivShowroomPickup = $(this).is(':checked');
            if (delivShowroomPickup) {
                delivAddrOk = true;
                delivIsAirport = false;
                delivAirport = '';
                $wrap.find('[name="deliv_is_airport"][value="no"]').prop('checked', true);
                $wrap.find('[name="deliv_airport_name"]').prop('checked', false);
                $wrap.find('#lc-hidden-deliv-airport').val('none');
                $wrap.find('#lc-deliv-airport-ok').hide();
                $wrap.find('#lc-deliv-airport-question').hide();
                $wrap.find('#lc-deliv-airport-picker').hide();
                $wrap.find('#lc-deliv-addr-search').hide();
            } else {
                $wrap.find('#lc-deliv-airport-question').show();
                $wrap.find('#lc-deliv-addr-search').show();
                delivAddrOk = false;
            }
            checkStep1();
        });

        /* ── Delivery: airport yes/no ───────────────────────────────────────── */
        $wrap.find('[name="deliv_is_airport"]').on('change', function () {
            delivIsAirport = $(this).val() === 'yes';
            $wrap.find('#lc-deliv-airport-picker').toggle(delivIsAirport);
            $wrap.find('#lc-deliv-addr-search').toggle(!delivIsAirport);
            // Reset previous airport/address selection
            delivShowroomPickup = false;
            $wrap.find('#lc-deliv-showroom').prop('checked', false);
            delivAirport = '';
            delivAddrOk  = false;
            $wrap.find('#lc-hidden-deliv-airport').val('none');
            $wrap.find('[name="deliv_airport_name"]').prop('checked', false);
            $wrap.find('#lc-deliv-airport-ok').hide();
            checkStep1();
        });

        $wrap.find('[name="deliv_airport_name"]').on('change', function () {
            delivAirport = $(this).val();
            delivAddrOk  = !!delivAirport;
            $wrap.find('#lc-hidden-deliv-airport').val(delivAirport || 'none');
            $wrap.find('#lc-deliv-airport-ok').toggle(delivAddrOk);
            checkStep1();
        });

        /* ── Same-address toggle ────────────────────────────────────────────── */
        $wrap.find('#lc-same-addr').on('change', function () {
            sameAddr = $(this).is(':checked');
            $wrap.find('#lc-coll-extra').slideToggle(200);
            if (sameAddr) {
                collAddrOk = true;  // no extra collection validation needed
                collShowroomPickup = delivShowroomPickup;
            } else {
                collAddrOk    = false;
                collIsAirport = false;
                collAirport   = '';
                collShowroomPickup = false;
                $wrap.find('#lc-collect-showroom').prop('checked', false);
                $wrap.find('[name="coll_is_airport"][value="no"]').prop('checked', true);
                $wrap.find('#lc-coll-airport-question').show();
                $wrap.find('#lc-coll-airport-picker').hide();
                $wrap.find('#lc-coll-addr-search').show();
                $wrap.find('#lc-hidden-coll-airport').val('none');
            }
            checkStep1();
        });

        /* ── Collection: airport yes/no ─────────────────────────────────────── */
        $wrap.find('[name="coll_is_airport"]').on('change', function () {
            collIsAirport = $(this).val() === 'yes';
            $wrap.find('#lc-coll-airport-picker').toggle(collIsAirport);
            $wrap.find('#lc-coll-addr-search').toggle(!collIsAirport);
            collAirport = '';
            collAddrOk  = false;
            $wrap.find('#lc-hidden-coll-airport').val('none');
            $wrap.find('[name="coll_airport_name"]').prop('checked', false);
            $wrap.find('#lc-coll-airport-ok').hide();
            checkStep1();
        });

        $wrap.find('[name="coll_airport_name"]').on('change', function () {
            collAirport = $(this).val();
            collAddrOk  = !!collAirport;
            $wrap.find('#lc-hidden-coll-airport').val(collAirport || 'none');
            $wrap.find('#lc-coll-airport-ok').toggle(collAddrOk);
            checkStep1();
        });

        $wrap.find('#lc-collect-showroom').on('change', function () {
            collShowroomPickup = $(this).is(':checked');
            if (collShowroomPickup) {
                collAddrOk = true;
                collIsAirport = false;
                collAirport = '';
                $wrap.find('[name="coll_is_airport"][value="no"]').prop('checked', true);
                $wrap.find('#lc-coll-airport-question').hide();
                $wrap.find('#lc-coll-airport-picker').hide();
                $wrap.find('#lc-coll-addr-search').hide();
                $wrap.find('[name="coll_airport_name"]').prop('checked', false);
                $wrap.find('#lc-hidden-coll-airport').val('none');
            } else {
                $wrap.find('#lc-coll-airport-question').show();
                $wrap.find('#lc-coll-addr-search').show();
                collAddrOk = false;
            }
            checkStep1();
        });

        /* ── Step 1 gate ────────────────────────────────────────────────────── */
        function checkStep1() {
            var ok = delivAddrOk && (sameAddr || collAddrOk);
            if (!ok) {
                $wrap.find('#lc-step-4').addClass('lc-step--locked');
                $wrap.find('#lc-actions').hide();
            }
        }

        /* ── Step 2: Delivery date + time ───────────────────────────────────── */
        var pickupDate = '', delivTime = '', dropoffDate = '', collTime = '';
        var today = new Date(); today.setHours(0, 0, 0, 0);

        var returnFP;

        flatpickr($wrap.find('#lc-pickup-date')[0], {
            minDate:    today,
            disable:    cfg.blocked_dates || [],
            dateFormat: 'Y-m-d',
            altInput:   true,
            altFormat:  'D, d M Y',
            onChange: function (sel, ds) {
                pickupDate = ds;
                if (sel.length && returnFP) {
                    var min = new Date(sel[0]);
                    min.setDate(min.getDate() + (cfg.min_days || 1));
                    returnFP.set('minDate', min);
                    returnFP.clear();
                }
                dropoffDate = ''; collTime = '';
                $wrap.find('#lc-coll-time').val('');
                $wrap.find('#lc-badge-coll').html('');
                $wrap.find('#lc-step-4').addClass('lc-step--locked');
                $wrap.find('#lc-actions').hide();
                refreshDelivBadge();
                checkStep2();
            }
        });

        $wrap.find('#lc-deliv-time').on('change', function () {
            delivTime = $(this).val();
            refreshDelivBadge();
            checkStep2();
        });

        function refreshDelivBadge() {
            renderTimeBadge($wrap.find('#lc-badge-deliv'), pickupDate, delivTime, PRICE.ooh_delivery);
        }

        function checkStep2() {
            if (pickupDate && !delivTime) {
                var delivTimeEl = $wrap.find('#lc-deliv-time')[0];
                if (delivTimeEl) {
                    delivTimeEl.focus();
                    if (typeof delivTimeEl.showPicker === 'function') {
                        delivTimeEl.showPicker();
                    }
                }
            }
        }

        /* ── Step 3: Collection date + time ─────────────────────────────────── */
        returnFP = flatpickr($wrap.find('#lc-dropoff-date')[0], {
            disable:    cfg.blocked_dates || [],
            dateFormat: 'Y-m-d',
            altInput:   true,
            altFormat:  'D, d M Y',
            onChange: function (sel, ds) {
                dropoffDate = ds;
                if (dropoffDate && !collTime) {
                    $wrap.find('#lc-coll-time').focus();
                }
                refreshCollBadge();
                refreshDurationHint();
                checkStep3();
            }
        });

        $wrap.find('#lc-coll-time').on('change', function () {
            collTime = $(this).val();
            refreshCollBadge();
            checkStep3();
        });

        function refreshCollBadge() {
            renderTimeBadge($wrap.find('#lc-badge-coll'), dropoffDate, collTime, PRICE.ooh_collection);
        }

        function refreshDurationHint() {
            var $h = $wrap.find('#lc-duration-hint');
            if (!pickupDate || !dropoffDate) { $h.html(''); return; }
            var days = daysBetween(pickupDate, dropoffDate);
            if (days <= 0) {
                $h.html('<span class="lc-hint-err">Collection must be after delivery</span>');
                return;
            }
            var weeks = Math.ceil(days / 7);
            var txt   = days + ' day' + (days !== 1 ? 's' : '')
                      + ' \u2192 billed as ' + weeks + ' week' + (weeks !== 1 ? 's' : '');
            if (days % 7 !== 0) {
                txt += ' (up to ' + (weeks * 7) + ' days at the same price)';
            }
            $h.html('<span class="lc-hint-ok">' + txt + '</span>');
        }

        function checkStep3() {
            var days = dropoffDate ? daysBetween(pickupDate, dropoffDate) : 0;
            var ready = !!(dropoffDate && collTime && days > 0);
            if (ready) {
                buildSummary(function () {
                    $wrap.find('#lc-step-4').removeClass('lc-step--locked');
                    $wrap.find('#lc-actions').fadeIn(200);
                });
            }
        }


        /* ── Price summary ──────────────────────────────────────────────────── */
        function buildSummary(done) {
            var days = daysBetween(pickupDate, dropoffDate);
            if (days <= 0) return;

            var dOOH  = isOOH(pickupDate,  delivTime);
            var cOOH  = isOOH(dropoffDate, collTime);
            var effDA = delivIsAirport ? delivAirport : '';
            var effCA = sameAddr ? effDA : (collIsAirport ? collAirport : '');

            var legDeliveryBase   = 50;
            var legCollectionBase = 50;
            var fBase  = legDeliveryBase + legCollectionBase;
            var fDOOH  = dOOH ? PRICE.ooh_delivery   : 0;
            var fCOOH  = cOOH ? PRICE.ooh_collection  : 0;
            var fDAP   = effDA ? (PRICE.airport[effDA] || 0) : 0;
            var fCAP   = effCA ? (PRICE.airport[effCA] || 0) : 0;

            if (effDA && !effCA) {
                fDAP = fDAP / 2;
            } else if (!effDA && effCA) {
                fCAP = fCAP / 2;
            }

            var totalFee = fBase + fDOOH + fCOOH + fDAP + fCAP;

            $.post(lendocareData.ajax_url, {
                action:       'lendocare_calculate_price',
                nonce:        lendocareData.nonce,
                product_id:   productId,
                pickup_date:  pickupDate,
                dropoff_date: dropoffDate,
                delivery_fee: totalFee
            }).done(function (res) {
                if (!res.success) { showErr(res.data.message); return; }
                var d  = res.data;
                var $t = $wrap.find('#lc-summary-tbody').empty();

                appendRow($t, 'Rental charge', d.rental_price_f + ' <small>(' + d.billing_unit + ')</small>');
                appendRow($t, 'Base delivery & collection', gbp(fBase));

                if (fDOOH) appendSub($t, 'Out-of-hours extra charge (delivery)',   gbp(fDOOH));
                if (fCOOH) appendSub($t, 'Out-of-hours extra charge (collection)', gbp(fCOOH));

                if (fDAP) {
                    var dapLbl = ucfirst(effDA) + ' airport extra charge';
                    dapLbl += sameAddr ? ' (delivery + collection)' : ' (delivery)';
                    appendSub($t, dapLbl, gbp(fDAP));
                }
                if (fCAP && !sameAddr) {
                    appendSub($t, ucfirst(effCA) + ' airport extra charge (collection)', gbp(fCAP));
                }

                if (d.deposit > 0) appendRow($t, 'Security deposit (refundable)', d.deposit_f);

                var $tot = $('<tr>').addClass('lc-row-total');
                $tot.append($('<td>').addClass('lc-sum-label').html('<strong>Total</strong>'));
                $tot.append($('<td>').addClass('lc-sum-val').html('<strong>' + d.total_f + '</strong>'));
                $t.append($tot);

                var note = '';
                if (d.weeks) {
                    note = 'Your ' + d.days + '-day rental is billed as '
                         + d.weeks + ' week' + (d.weeks !== 1 ? 's' : '') + '.';
                }
                $wrap.find('#lc-billing-note').text(note);

                if (done) done();
            }).fail(function () {
                showErr('Could not calculate price. Please try again.');
            });
        }

        /* ── Form submit ────────────────────────────────────────────────────── */
        $form.on('submit', function (e) {
            e.preventDefault();
            if (!delivAddrOk)            { showErr('Please confirm your delivery address or select an airport.'); return; }
            if (!sameAddr && !collAddrOk){ showErr('Please confirm your collection address or select an airport.'); return; }
            if (!pickupDate || !delivTime){ showErr('Please select a delivery date and time.'); return; }
            if (!dropoffDate || !collTime){ showErr('Please select a collection date and time.'); return; }
            if (daysBetween(pickupDate, dropoffDate) <= 0) { showErr('Collection date must be after delivery date.'); return; }

            var dOOH  = isOOH(pickupDate,  delivTime);
            var cOOH  = isOOH(dropoffDate, collTime);
            var effDA = delivIsAirport ? delivAirport : '';
            var effCA = sameAddr ? effDA : (collIsAirport ? collAirport : '');

            var deliveryAirportFee   = effDA ? (PRICE.airport[effDA] || 0) : 0;
            var collectionAirportFee = effCA ? (PRICE.airport[effCA] || 0) : 0;
            if (effDA && !effCA) {
                deliveryAirportFee = deliveryAirportFee / 2;
            } else if (!effDA && effCA) {
                collectionAirportFee = collectionAirportFee / 2;
            }

            var fee   = PRICE.base
                      + (dOOH ? PRICE.ooh_delivery   : 0)
                      + (cOOH ? PRICE.ooh_collection  : 0)
                      + deliveryAirportFee
                      + collectionAirportFee;

            var delivLabel = delivIsAirport
                ? ucfirst(delivAirport) + ' Airport'
                : (delivShowroomPickup ? SHOWROOM_ADDRESS : (delivState.label || $wrap.find('#lc-input-delivery').val()));
            var collLabel  = sameAddr ? delivLabel
                : (collShowroomPickup ? SHOWROOM_ADDRESS
                : (collIsAirport ? ucfirst(collAirport) + ' Airport'
                                 : (collState.label || $wrap.find('#lc-input-collection').val())));

            var $btn = $wrap.find('.lc-btn-book');
            $btn.prop('disabled', true);
            $wrap.find('.lc-btn-text').hide();
            $wrap.find('.lc-btn-loading').show();
            clearErr();

            $.post(lendocareData.ajax_url, {
                action:             'lendocare_add_to_cart',
                nonce:              lendocareData.nonce,
                product_id:         productId,
                pickup_date:        pickupDate,
                dropoff_date:       dropoffDate,
                postcode:           delivIsAirport ? 'AIRPORT' : (delivShowroomPickup ? SHOWROOM_POSTCODE : delivState.postcode),
                delivery_address:   delivLabel,
                collection_address: collLabel,
                same_address:       sameAddr ? '1' : '0',
                delivery_time:      delivTime,
                collection_time:    collTime,
                delivery_airport:   effDA || 'none',
                collection_airport: effCA || 'none',
                delivery_ooh:       dOOH ? '1' : '0',
                collection_ooh:     cOOH ? '1' : '0',
                delivery_type:      (dOOH || cOOH) ? 'ooh' : 'standard',
                delivery_fee:       fee
            }).done(function (res) {
                if (res.success) {
                    $form.fadeOut(200, function () {
                        $wrap.find('.lc-success-message').fadeIn(300);
                    });
                    $(document.body).trigger('wc_fragment_refresh');
                } else {
                    showErr(res.data.message);
                    $btn.prop('disabled', false);
                    $wrap.find('.lc-btn-text').show();
                    $wrap.find('.lc-btn-loading').hide();
                }
            }).fail(function () {
                showErr('Something went wrong. Please try again.');
                $btn.prop('disabled', false);
                $wrap.find('.lc-btn-text').show();
                $wrap.find('.lc-btn-loading').hide();
            });
        });

        /* ── Local helpers ──────────────────────────────────────────────────── */
        function renderTimeBadge($el, dateStr, timeStr, oohFee) {
            if (!dateStr || !timeStr) { $el.html(''); return; }
            if (isOOH(dateStr, timeStr)) {
                $el.html('<span class="lc-badge lc-badge--ooh">\uD83C\uDF19 Out-of-hours \u2014 +' + gbp(oohFee) + ' extra charge</span>');
            } else {
                $el.html('<span class="lc-badge lc-badge--std">\u2600\uFE0F Standard hours \u2014 no extra charge</span>');
            }
        }

        function appendRow($t, label, valHtml) {
            var $tr = $('<tr>');
            $tr.append($('<td>').addClass('lc-sum-label').text(label));
            $tr.append($('<td>').addClass('lc-sum-val').html(valHtml));
            $t.append($tr);
        }
        function appendSub($t, label, valHtml) {
            var $tr = $('<tr>').addClass('lc-sub-row');
            $tr.append($('<td>').addClass('lc-sum-label lc-sub-label').text(label));
            $tr.append($('<td>').addClass('lc-sum-val').html(valHtml));
            $t.append($tr);
        }

        function daysBetween(a, b) {
            return Math.round((new Date(b) - new Date(a)) / 86400000);
        }
        function scrollTo($el) {
            $('html,body').animate({ scrollTop: $el.offset().top - 20 }, 280);
        }
        function showErr(m) { $wrap.find('.lc-error-message').text(m).show(); }
        function clearErr() { $wrap.find('.lc-error-message').text('').hide(); }
        function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
    }

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

}(jQuery));
