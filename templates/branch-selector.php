<?php
/**
 * Branch Selector Template
 *
 * @package WC_Balikovna
 */

defined('ABSPATH') || exit;
?>

<div class="balikovna-branch-selector" id="balikovna-branch-selector" style="display:none;">
    <h3><?php _e('Vyberte výdejní místo Balíkovna', 'wc-balikovna'); ?></h3>
    
    <div class="branch-search">
        <input type="text" id="branch-search-input" placeholder="<?php _e('Zadejte PSČ nebo město', 'wc-balikovna'); ?>" />
        <button type="button" id="branch-search-btn" class="button"><?php _e('Hledat', 'wc-balikovna'); ?></button>
    </div>
    
    <div class="branch-list-container">
        <div id="branch-loading" style="display:none;">
            <p><?php _e('Načítání výdejních míst...', 'wc-balikovna'); ?></p>
        </div>
        
        <div class="branch-list" id="branch-list">
            <!-- Seznam výdejních míst se načte přes AJAX -->
        </div>
    </div>
    
    <input type="hidden" name="balikovna_branch_id" id="balikovna_branch_id" value="" />
    <input type="hidden" name="balikovna_branch_name" id="balikovna_branch_name" value="" />
    <input type="hidden" name="balikovna_branch_address" id="balikovna_branch_address" value="" />
    
    <div id="selected-branch-info" class="selected-branch-info" style="display:none;">
        <h4><?php _e('Vybrané výdejní místo:', 'wc-balikovna'); ?></h4>
        <div id="selected-branch-details"></div>
    </div>
</div>
