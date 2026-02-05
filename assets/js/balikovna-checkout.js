/**
 * WooCommerce Balikovna Checkout JavaScript
 *
 * Handles branch selection using iframe picker
 */

(function ($) {
    'use strict';

    var WCBalikovnaCheckout = {
        /**
         * Initialize
         */
        init: function () {
            this.setupIframeListener();
            this.bindEvents();
        },

        /**
         * Setup iframe message listener for branch picker
         */
        setupIframeListener: function () {
            var self = this;
            
            window.addEventListener('message', function(event) {
                // Handle picker result from iframe
                if (typeof event.data === 'object' && event.data.message === 'pickerResult') {
                    const pointData = event.data.point;
                    
                    if (!pointData) {
                        return;
                    }
                    
                    // Create branch data object
                    const branchInfo = JSON.stringify({
                        id: pointData.id,
                        name: pointData.name,
                        address: pointData.address,
                        city: pointData.municipality_name || pointData.city,
                        zip: pointData.zip
                    });
                    
                    // Update hidden field
                    $('#balikovna_branch_data').val(branchInfo);
                    
                    // Display selected branch
                    const displayText = '<strong>' + wcBalikovnaData.selectedLabel + ':</strong> ' + 
                                      pointData.name + ', ' + pointData.address;
                    
                    $('#balikovna_selected_info').html(displayText).css({
                        'padding': '10px',
                        'background': '#f9f9f9',
                        'border': '1px solid #ddd',
                        'margin-top': '10px'
                    });
                    
                    // Trigger checkout update
                    $(document.body).trigger('update_checkout');
                }
            });
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

            // Initialize on page load
            self.toggleBranchSelection();
        },

        /**
         * Toggle branch selection visibility
         */
        toggleBranchSelection: function () {
            var selectedShipping = $('input[name^="shipping_method"]:checked').val();
            var $branchSelection = $('#balikovna_iframe_container');

            if (selectedShipping && selectedShipping.indexOf('balikovna') !== -1 && selectedShipping.indexOf('address') === -1) {
                $branchSelection.show();
            } else {
                $branchSelection.hide();
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        WCBalikovnaCheckout.init();
    });

})(jQuery);
