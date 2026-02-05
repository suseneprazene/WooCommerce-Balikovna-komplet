/**
 * Balíkovna Admin JavaScript
 */

(function($) {
    'use strict';
    
    var BalikovnaAdmin = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Create shipment
            $(document).on('click', '#balikovna-create-shipment', function(e) {
                e.preventDefault();
                self.createShipment($(this));
            });
            
            // Download label
            $(document).on('click', '#balikovna-download-label', function(e) {
                e.preventDefault();
                self.downloadLabel($(this));
            });
            
            // Cancel shipment
            $(document).on('click', '#balikovna-cancel-shipment', function(e) {
                e.preventDefault();
                
                if (confirm(wcBalikovnaAdmin.strings.confirm_cancel)) {
                    self.cancelShipment($(this));
                }
            });
        },
        
        createShipment: function($button) {
            var orderId = $button.data('order-id');
            
            $button.prop('disabled', true).text(wcBalikovnaAdmin.strings.creating_shipment);
            this.clearMessages();
            
            $.ajax({
                url: wcBalikovnaAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'balikovna_create_shipment',
                    nonce: wcBalikovnaAdmin.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $button.prop('disabled', false).text(wcBalikovnaAdmin.strings.error);
                        BalikovnaAdmin.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(wcBalikovnaAdmin.strings.error);
                    BalikovnaAdmin.showMessage('AJAX error', 'error');
                }
            });
        },
        
        downloadLabel: function($button) {
            var orderId = $button.data('order-id');
            
            $button.prop('disabled', true).text(wcBalikovnaAdmin.strings.downloading_label);
            this.clearMessages();
            
            $.ajax({
                url: wcBalikovnaAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'balikovna_download_label',
                    nonce: wcBalikovnaAdmin.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    $button.prop('disabled', false).text(wcBalikovnaAdmin.strings.success);
                    
                    if (response.success) {
                        // Convert base64 to blob and download
                        var binary = atob(response.data.pdf);
                        var array = [];
                        
                        for (var i = 0; i < binary.length; i++) {
                            array.push(binary.charCodeAt(i));
                        }
                        
                        var blob = new Blob([new Uint8Array(array)], {type: 'application/pdf'});
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = response.data.filename;
                        link.click();
                        
                        setTimeout(function() {
                            $button.text($button.data('original-text') || 'Stáhnout štítek');
                        }, 2000);
                    } else {
                        BalikovnaAdmin.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(wcBalikovnaAdmin.strings.error);
                    BalikovnaAdmin.showMessage('AJAX error', 'error');
                }
            });
        },
        
        cancelShipment: function($button) {
            var orderId = $button.data('order-id');
            
            $button.prop('disabled', true).text(wcBalikovnaAdmin.strings.canceling_shipment);
            this.clearMessages();
            
            $.ajax({
                url: wcBalikovnaAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'balikovna_cancel_shipment',
                    nonce: wcBalikovnaAdmin.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $button.prop('disabled', false).text(wcBalikovnaAdmin.strings.error);
                        BalikovnaAdmin.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(wcBalikovnaAdmin.strings.error);
                    BalikovnaAdmin.showMessage('AJAX error', 'error');
                }
            });
        },
        
        showMessage: function(message, type) {
            var $container = $('#balikovna-metabox-messages');
            var className = type === 'error' ? 'error' : 'updated';
            
            $container.html('<div class="' + className + '"><p>' + message + '</p></div>');
        },
        
        clearMessages: function() {
            $('#balikovna-metabox-messages').html('');
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        BalikovnaAdmin.init();
    });
    
})(jQuery);
