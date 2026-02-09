/**
 * WooCommerce Balikovna Panel Toggle
 *
 * Toggles visibility of branch selection panel and address delivery panel
 * based on selected shipping method.
 */

(function ($) {
    'use strict';

    var WCBalikovnaToggle = {
        /**
         * Initialize
         */
        init: function () {
            this.bindEvents();
            // Run on page load
            this.togglePanels();
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            var self = this;

            // Listen to shipping method changes
            $(document.body).on('change', 'input[name^="shipping_method"]', function () {
                self.togglePanels();
            });

            // Listen to WooCommerce checkout update event
            $(document.body).on('updated_checkout', function () {
                self.togglePanels();
            });
        },

        /**
         * Toggle panels based on selected shipping method
         */
        togglePanels: function () {
            var selectedMethod = $('input[name^="shipping_method"]:checked').val();
            
            // Find all Balíkovna panels
            var $branchPanel = $('.wc-balikovna-branch-selection');
            var $addressPanel = $('.wc-balikovna-address-notice');
            
            console.log('WC Balíkovna Toggle: Selected method:', selectedMethod);
            
            if (!selectedMethod) {
                // No method selected, hide both
                $branchPanel.hide();
                $addressPanel.hide();
                return;
            }
            
            // Check if it's a Balíkovna shipping method
            if (selectedMethod.indexOf('balikovna') === -1) {
                // Not a Balíkovna method, hide both panels
                $branchPanel.hide();
                $addressPanel.hide();
                console.log('WC Balíkovna Toggle: Not a Balíkovna method, hiding both panels');
                return;
            }
            
            // It's a Balíkovna method - determine which type
            // Check which panel exists - this tells us the delivery type
            if ($branchPanel.length > 0 && $addressPanel.length === 0) {
                // Only branch selection panel exists = "Do boxu" type
                $branchPanel.show();
                console.log('WC Balíkovna Toggle: Showing branch selection panel (box delivery)');
            } else if ($addressPanel.length > 0 && $branchPanel.length === 0) {
                // Only address panel exists = "Na adresu" type
                $addressPanel.show();
                console.log('WC Balíkovna Toggle: Showing address delivery panel');
            } else if ($branchPanel.length > 0 && $addressPanel.length > 0) {
                // Both panels exist (shouldn't happen in normal use, but handle it)
                // Check which one has the hidden delivery type field to determine which to show
                var $branchInput = $branchPanel.find('input[name="wc_balikovna_delivery_type"][value="box"]');
                var $addressInput = $addressPanel.find('input[name="wc_balikovna_delivery_type"][value="address"]');
                
                if ($branchInput.length > 0) {
                    $branchPanel.show();
                    $addressPanel.hide();
                    console.log('WC Balíkovna Toggle: Multiple panels found, showing branch panel');
                } else if ($addressInput.length > 0) {
                    $addressPanel.show();
                    $branchPanel.hide();
                    console.log('WC Balíkovna Toggle: Multiple panels found, showing address panel');
                } else {
                    // Fallback: show branch panel by default
                    $branchPanel.show();
                    $addressPanel.hide();
                    console.log('WC Balíkovna Toggle: Fallback - showing branch panel');
                }
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        WCBalikovnaToggle.init();
    });

})(jQuery);