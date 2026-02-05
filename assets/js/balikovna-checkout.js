/**
 * WooCommerce Balikovna Checkout JavaScript
 *
 * Handles branch selection using Select2
 */

(function ($) {
    'use strict';

    var WCBalikovnaCheckout = {
        /**
         * Initialize
         */
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
                        if (!data.branches || data.branches.length === 0) {
                            return {
                                results: []
                            };
                        }

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
                    cache: true
                },
                templateResult: self.formatBranch,
                templateSelection: self.formatBranchSelection,
                escapeMarkup: function (markup) {
                    return markup;
                }
            });

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
        WCBalikovnaCheckout.init();
    });

})(jQuery);
