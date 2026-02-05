/**
 * Balíkovna Branch Selector - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    var BalikovnaBranchSelector = {
        
        init: function() {
            this.bindEvents();
            this.checkShippingMethod();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Search button click
            $('#branch-search-btn').on('click', function(e) {
                e.preventDefault();
                self.searchBranches();
            });
            
            // Search on Enter key
            $('#branch-search-input').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.searchBranches();
                }
            });
            
            // Branch selection
            $(document).on('click', '.branch-item-select', function(e) {
                e.preventDefault();
                self.selectBranch($(this));
            });
            
            // Watch for shipping method changes
            $(document.body).on('updated_checkout', function() {
                self.checkShippingMethod();
            });
        },
        
        checkShippingMethod: function() {
            var selectedMethod = $('input[name^="shipping_method"]:checked').val();
            
            if (selectedMethod && selectedMethod.indexOf('balikovna') !== -1) {
                $('#balikovna-branch-selector').slideDown();
                
                // Auto-load branches if not loaded yet
                if ($('#branch-list').is(':empty')) {
                    this.searchBranches();
                }
            } else {
                $('#balikovna-branch-selector').slideUp();
            }
        },
        
        searchBranches: function() {
            var self = this;
            var query = $('#branch-search-input').val();
            
            $('#branch-loading').show();
            $('#branch-list').html('');
            
            $.ajax({
                url: wcBalikovnaFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'balikovna_get_branches',
                    nonce: wcBalikovnaFrontend.nonce,
                    query: query
                },
                success: function(response) {
                    $('#branch-loading').hide();
                    
                    if (response.success && response.data.branches) {
                        self.renderBranches(response.data.branches);
                    } else {
                        $('#branch-list').html('<p class="no-results">' + wcBalikovnaFrontend.strings.no_results + '</p>');
                    }
                },
                error: function() {
                    $('#branch-loading').hide();
                    $('#branch-list').html('<p class="error">' + wcBalikovnaFrontend.strings.search_error + '</p>');
                }
            });
        },
        
        renderBranches: function(branches) {
            var html = '';
            
            if (branches.length === 0) {
                html = '<p class="no-results">' + wcBalikovnaFrontend.strings.no_results + '</p>';
            } else {
                html = '<ul class="branches-list">';
                
                $.each(branches, function(index, branch) {
                    html += '<li class="branch-item" data-branch-id="' + branch.id + '">';
                    html += '<div class="branch-info">';
                    html += '<strong class="branch-name">' + branch.name + '</strong>';
                    html += '<div class="branch-address">' + branch.address + ', ' + branch.zip + ' ' + branch.city + '</div>';
                    
                    if (branch.opening_hours) {
                        html += '<div class="branch-hours"><small>' + branch.opening_hours + '</small></div>';
                    }
                    
                    html += '</div>';
                    html += '<button type="button" class="button branch-item-select" data-branch-id="' + branch.id + '" data-branch-name="' + branch.name + '" data-branch-address="' + branch.address + ', ' + branch.zip + ' ' + branch.city + '">';
                    html += wcBalikovnaFrontend.strings.select_branch;
                    html += '</button>';
                    html += '</li>';
                });
                
                html += '</ul>';
            }
            
            $('#branch-list').html(html);
        },
        
        selectBranch: function($button) {
            var branchId = $button.data('branch-id');
            var branchName = $button.data('branch-name');
            var branchAddress = $button.data('branch-address');
            
            // Set hidden fields
            $('#balikovna_branch_id').val(branchId);
            $('#balikovna_branch_name').val(branchName);
            $('#balikovna_branch_address').val(branchAddress);
            
            // Update visual selection
            $('.branch-item').removeClass('selected');
            $button.closest('.branch-item').addClass('selected');
            
            // Show selected branch info
            var infoHtml = '<p><strong>' + branchName + '</strong><br>' + branchAddress + '</p>';
            $('#selected-branch-details').html(infoHtml);
            $('#selected-branch-info').slideDown();
            
            // Trigger checkout update
            $(document.body).trigger('update_checkout');
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        BalikovnaBranchSelector.init();
    });
    
})(jQuery);
