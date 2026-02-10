/**
 * WooCommerce Balikovna Checkout JavaScript
 *
 * Handles branch selection using Select2
 */

(function ($) {
    'use strict';
// --- START: global wrapper + buffer pro aplikaci branch dat (iframe může volat kdykoli) ---
window.wcBalikovnaPendingBranch = null;

/**
 * Globální wrapper, který může volat inline iframe kód (např. render_box_selection).
 * Pokud bude WCBalikovnaCheckout.init() hotové, wrapper zavolá applyBranchToFields ihned,
 * jinak uloží data do wcBalikovnaPendingBranch a WCBalikovnaCheckout je aplikuje později.
 *
 * Použij: window.wcBalikovnaApplyBranch(branchData);
 */
window.wcBalikovnaApplyBranch = function(branchData) {
    try {
        if (typeof branchData === 'string') {
            try { branchData = JSON.parse(branchData); } catch (e) { /* ignore */ }
        }
        // pokud WCBalikovnaCheckout je dostupný a metoda existuje, zavolej ji
        if (window.WCBalikovnaCheckout && typeof window.WCBalikovnaCheckout.applyBranchToFields === 'function') {
            console.log('WC Balíkovna DEBUG: global wrapper calling applyBranchToFields immediately', branchData);
            window.WCBalikovnaCheckout.applyBranchToFields(branchData);
            window.wcBalikovnaPendingBranch = null;
        } else {
            // uložíme do bufferu a aplikujeme později
            console.log('WC Balíkovna DEBUG: global wrapper buffering branchData until checkout JS ready', branchData);
            window.wcBalikovnaPendingBranch = branchData;
        }
    } catch (err) {
        console.error('WC Balíkovna DEBUG: wcBalikovnaApplyBranch error', err);
    }
};
// --- END: global wrapper + buffer ---
    var WCBalikovnaCheckout = {
        /**
         * Initialize
         */
		 // --- START: helper pro aplikaci branch dat do polí checkoutu ---
applyBranchToFields: function(branchData) {
    try {
        if (!branchData) return;
        console.log('WC Balíkovna DEBUG: applyBranchToFields called with', branchData);

        // Uložíme také do hidden pole (pokud existuje nebo ne)
        if ($('#wc_balikovna_branch').length) {
            $('#wc_balikovna_branch').val(JSON.stringify(branchData));
        } else {
            $('<input>').attr({
                type: 'hidden',
                id: 'wc_balikovna_branch',
                name: 'wc_balikovna_branch',
                value: JSON.stringify(branchData)
            }).appendTo('form.checkout');
        }

        // přepis polí — selektory zahrnují name a id varianty
        var $addr1 = $('input[name="shipping_address_1"], input#shipping_address_1');
        var $addr2 = $('input[name="shipping_address_2"], input#shipping_address_2');
        var $city  = $('input[name="shipping_city"], input#shipping_city');
        var $postcode = $('input[name="shipping_postcode"], input#shipping_postcode');

        if ($addr1.length) {
            $addr1.val(branchData.address || '').trigger('input').trigger('change');
            console.log('WC Balíkovna DEBUG: set shipping_address_1 ->', $addr1.val());
        }
        var branchNote = 'Balíkovna ID: ' + (branchData.id || '') + ' - ' + (branchData.name || '');
        if ($addr2.length) {
            $addr2.val(branchNote).trigger('input').trigger('change');
            console.log('WC Balíkovna DEBUG: set shipping_address_2 ->', $addr2.val());
        } else {
            if ($('#wc_balikovna_branch_note').length === 0) {
                $('<input>').attr({
                    type: 'hidden',
                    id: 'wc_balikovna_branch_note',
                    name: 'wc_balikovna_branch_note',
                    value: branchNote
                }).appendTo('form.checkout');
            } else {
                $('#wc_balikovna_branch_note').val(branchNote);
            }
            console.log('WC Balíkovna DEBUG: set hidden branch note ->', branchNote);
        }
        if ($city.length) {
            $city.val(branchData.city || '').trigger('input').trigger('change');
            console.log('WC Balíkovna DEBUG: set shipping_city ->', $city.val());
        }
        if ($postcode.length) {
            $postcode.val(branchData.zip || '').trigger('input').trigger('change');
            console.log('WC Balíkovna DEBUG: set shipping_postcode ->', $postcode.val());
        }

        // vyčistíme validační chyby
        $('.wc-balikovna-error').remove();

    } catch (e) {
        console.error('WC Balíkovna DEBUG: applyBranchToFields error', e);
    }
},

// Znovu aplikovat po každém update checkout (aby WooCommerce znovu nevynuloval pole)
bindReapplyOnUpdate: function() {
    var self = this;
    $(document.body).on('updated_checkout', function() {
        try {
            var raw = $('#wc_balikovna_branch').val();
            if (raw) {
                var parsed = null;
                try { parsed = JSON.parse(raw); } catch (ex) { parsed = null; }
                if (parsed) {
                    console.log('WC Balíkovna DEBUG: reapplying branch on updated_checkout', parsed);
                    self.applyBranchToFields(parsed);
                }
            }
        } catch (e) {
            console.error('WC Balíkovna DEBUG: error in reapplyOnUpdate', e);
        }
    });
},
// --- END: helper pro aplikaci branch dat do polí checkoutu ---
        init: function () {
            this.initSelect2();
            this.bindEvents();
        },

        /**
         * Initialize Select2 for branch selection
         */
        initSelect2: function () {
            var self = this;

            if ($('.wc-balikovna-branches').length === 0) {
                return;
            }

            $('.wc-balikovna-branches').select2({
                placeholder: wcBalikovnaData.selectPlaceholder,
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: wcBalikovnaData.apiUrl + '/search',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function (data) {
                        console.log('WC Balíkovna: API response:', data);
                        
                        if (!data.branches || data.branches.length === 0) {
                            console.warn('WC Balíkovna: No branches found');
                            return {
                                results: []
                            };
                        }

                        console.log('WC Balíkovna: Found ' + data.branches.length + ' branches');
                        
                        return {
                            results: $.map(data.branches, function (item) {
                                return {
                                    id: JSON.stringify({
                                        id: item.id,
                                        name: item.name,
                                        city: item.city,
                                        city_part: item.city_part,
                                        address: item.address,
                                        zip: item.zip,
                                        kind: item.kind
                                    }),
                                    text: item.name,
                                    branch: item
                                };
                            })
                        };
                    },
                    error: function(xhr, status, error) {
                        console.error('WC Balíkovna: AJAX error:', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });
                    },
                    cache: true
                },
                templateResult: self.formatBranch,
                templateSelection: self.formatBranchSelection,
                escapeMarkup: function (markup) {
                    return markup;
                }
            });
// --- START: Select2 -> naplnit shipping fields a hidden field při výběru pobočky ---
$('.wc-balikovna-branches').on('select2:select', function (e) {
    try {
        var data = e && e.params && e.params.data ? e.params.data : null;
        var branch = data && data.branch ? data.branch : null;
        if (!branch) {
            // někdy může být branch uložen v data.id jako JSON string
            if (data && typeof data.id === 'string') {
                try { branch = JSON.parse(data.id); } catch (ex) { branch = null; }
            }
        }

        if (!branch) return;

        var branchData = {
            id: branch.id || branchID || '',
            name: branch.name || '',
            city: branch.city || '',
            city_part: branch.city_part || '',
            address: branch.address || '',
            zip: branch.zip || '',
            kind: branch.kind || ''
        };

        // uložíme do skrytého pole (server-side očekává wc_balikovna_branch)
               // uložíme a okamžitě aplikujeme hodnoty do polí a pak zavoláme update_checkout
        if ($('#wc_balikovna_branch').length) {
            $('#wc_balikovna_branch').val(JSON.stringify(branchData));
        } else {
            $('<input>').attr({
                type: 'hidden',
                id: 'wc_balikovna_branch',
                name: 'wc_balikovna_branch',
                value: JSON.stringify(branchData)
            }).appendTo('form.checkout');
        }

        // Aplikujeme hodnoty lokálně do polí (zobrazí se uživateli)
        WCBalikovnaCheckout.applyBranchToFields(branchData);

        // Zavoláme update_checkout — reapplyOnUpdate zaručí, že po přegenerování znovu aplikujeme hodnoty
        $(document.body).trigger('update_checkout');

    } catch (err) {
        console.error('WC Balíkovna: Chyba při aplikaci pobočky na checkout:', err);
    }
});

// pokud uživatel volbu zruší, smažeme hidden field (nebo necháme původní adresu)
$('.wc-balikovna-branches').on('select2:unselect', function () {
    if ($('#wc_balikovna_branch').length) {
        $('#wc_balikovna_branch').val('');
    }
    // volitelně: odstranit branch note
    // $('#wc_balikovna_branch_note').remove();
    // $(document.body).trigger('update_checkout');
});
// --- END: Select2 -> naplnit shipping fields a hidden field při výběru pobočky ---
            // Trigger validation on change
            $('.wc-balikovna-branches').on('change', function () {
                self.validateBranchSelection();
            });
        },

        /**
         * Format branch for display in dropdown
         */
        formatBranch: function (data) {
            if (!data.branch) {
                return data.text;
            }

            var branch = data.branch;
            var kind = branch.kind === 'posta' ? wcBalikovnaData.kindPosta : wcBalikovnaData.kindBalikovna;

            var $container = $(
                '<div class="wc-balikovna-branch-item">' +
                '<div class="branch-name">' + branch.name + '</div>' +
                '<div class="branch-details">' +
                '<span class="branch-city">' + branch.city + '</span>' +
                (branch.city_part ? '<span class="branch-city-part"> - ' + branch.city_part + '</span>' : '') +
                '</div>' +
                '<div class="branch-address">' + branch.address + ', ' + branch.zip + '</div>' +
                '<div class="branch-kind">' + kind + '</div>' +
                '<div class="branch-hours-icon" data-branch-id="' + branch.id + '" title="' + wcBalikovnaData.openingHoursTitle + '">ⓘ</div>' +
                '</div>'
            );

            return $container;
        },

        /**
         * Format branch for display when selected
         */
        formatBranchSelection: function (data) {
            if (!data.branch) {
                return data.text;
            }

            var branch = data.branch;
            return branch.name + ' - ' + branch.city;
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            var self = this;

            // Show/hide branch selection based on shipping method
            $(document.body).on('updated_checkout', function () {
                self.toggleBranchSelection();
            });

            $(document.body).on('change', 'input[name^="shipping_method"]', function () {
                self.toggleBranchSelection();
            });

            // Opening hours tooltip
            $(document).on('mouseenter', '.branch-hours-icon', function (e) {
                self.showOpeningHours($(this), e);
            });

            $(document).on('mouseleave', '.branch-hours-icon', function () {
                self.hideOpeningHours();
            });

            $(document).on('mousemove', '.branch-hours-icon', function (e) {
                self.moveTooltip(e);
            });

            // Initialize on page load
            self.toggleBranchSelection();
        },

        /**
         * Toggle branch selection visibility
         */
        toggleBranchSelection: function () {
            var selectedShipping = $('input[name^="shipping_method"]:checked').val();
            var $branchSelection = $('.wc-balikovna-branch-selection');

            if (selectedShipping && selectedShipping.indexOf('balikovna') !== -1) {
                $branchSelection.show();
                // Re-initialize Select2 if needed
                if (!$('.wc-balikovna-branches').hasClass('select2-hidden-accessible')) {
                    this.initSelect2();
                }
            } else {
                $branchSelection.hide();
            }
        },

        /**
         * Validate branch selection
         */
        validateBranchSelection: function () {
            var selectedShipping = $('input[name^="shipping_method"]:checked').val();
            var $branchField = $('#wc_balikovna_branch');
            var $errorMsg = $('.wc-balikovna-error');

            if (selectedShipping && selectedShipping.indexOf('balikovna') !== -1) {
                if (!$branchField.val()) {
                    if ($errorMsg.length === 0) {
                        $branchField.parent().append(
                            '<span class="wc-balikovna-error" style="color: red; display: block; margin-top: 5px;">' +
                            wcBalikovnaData.validationError +
                            '</span>'
                        );
                    }
                    return false;
                } else {
                    $errorMsg.remove();
                    return true;
                }
            }

            return true;
        },

        /**
         * Show opening hours tooltip
         */
        showOpeningHours: function ($icon, event) {
            var branchId = $icon.data('branch-id');
            var self = this;

            if ($('#wc-balikovna-tooltip').length === 0) {
                $('body').append('<div id="wc-balikovna-tooltip" style="display: none; position: absolute; z-index: 9999; background: #fff; border: 1px solid #ccc; padding: 10px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); max-width: 300px;"></div>');
            }

            var $tooltip = $('#wc-balikovna-tooltip');
            $tooltip.html('<div style="text-align: center;">' + wcBalikovnaData.loadingText + '</div>');
            $tooltip.show();

            // Fetch opening hours
            $.ajax({
                url: wcBalikovnaData.apiUrl + '/hours/' + branchId,
                method: 'GET',
                success: function (data) {
                    $tooltip.html(data);
                },
                error: function () {
                    $tooltip.html('<div>' + wcBalikovnaData.openingHoursError + '</div>');
                }
            });

            self.moveTooltip(event);
        },

        /**
         * Hide opening hours tooltip
         */
        hideOpeningHours: function () {
            $('#wc-balikovna-tooltip').hide();
        },

        /**
         * Move tooltip with mouse
         */
        moveTooltip: function (event) {
            var $tooltip = $('#wc-balikovna-tooltip');
            $tooltip.css({
                top: event.pageY + 10 + 'px',
                left: event.pageX + 10 + 'px'
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
$(document).ready(function () {
    WCBalikovnaCheckout.init();

    // expose globally (takže inline skripty nebo jiné můžou najít objekt)
    window.WCBalikovnaCheckout = WCBalikovnaCheckout;

    // pokud byl někdy předtím volán wrapper a data v bufferu, aplikuj je nyní
    try {
        if (window.wcBalikovnaPendingBranch) {
            console.log('WC Balíkovna DEBUG: applying pending branch after init', window.wcBalikovnaPendingBranch);
            WCBalikovnaCheckout.applyBranchToFields(window.wcBalikovnaPendingBranch);
            window.wcBalikovnaPendingBranch = null;
        }
    } catch (e) {
        console.error('WC Balíkovna DEBUG: error applying pending branch after init', e);
    }
});    });

})(jQuery);
