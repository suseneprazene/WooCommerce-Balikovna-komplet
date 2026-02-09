<?php
/**
 * Admin functionality for Balikovna
 *
 * @package WC_Balikovna_Komplet
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Balikovna_Admin Class
 */
class WC_Balikovna_Admin
{
    /**
     * The single instance of the class
     *
     * @var WC_Balikovna_Admin
     */
    protected static $_instance = null;

    /**
     * Main WC_Balikovna_Admin Instance
     *
     * @return WC_Balikovna_Admin
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add settings tab
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_balikovna', array($this, 'settings_tab'));
        add_action('woocommerce_update_options_balikovna', array($this, 'update_settings'));

        // Handle sync data action
        add_action('admin_post_wc_balikovna_sync', array($this, 'handle_sync_data'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Add print label action to orders
        add_filter('woocommerce_admin_order_actions', array($this, 'add_print_label_action'), 10, 2);
        add_action('admin_post_wc_balikovna_print_label', array($this, 'handle_print_label'));
    }

    /**
     * Add settings tab to WooCommerce settings
     *
     * @param array $settings_tabs
     * @return array
     */
    public function add_settings_tab($settings_tabs)
    {
        $settings_tabs['balikovna'] = __('Balíkovna', 'wc-balikovna-komplet');
        return $settings_tabs;
    }

    /**
     * Settings tab content
     */
    public function settings_tab()
    {
        woocommerce_admin_fields($this->get_settings());
        $this->output_sync_section();
    }

    /**
     * Update settings
     */
    public function update_settings()
    {
        woocommerce_update_options($this->get_settings());
    }

    /**
     * Get settings array
     *
     * @return array
     */
    public function get_settings()
    {
        $settings = array(
            'section_title' => array(
                'name' => __('Balíkovna České pošty - Nastavení', 'wc-balikovna-komplet'),
                'type' => 'title',
                'desc' => __('Nastavení pro tisk štítků a odesílatelské údaje', 'wc-balikovna-komplet'),
                'id' => 'wc_balikovna_section_title'
            ),
            
            // API credentials
            'api_section' => array(
                'name' => __('API přístupové údaje', 'wc-balikovna-komplet'),
                'type' => 'title',
                'desc' => __('Přihlašovací údaje k API České pošty pro generování štítků', 'wc-balikovna-komplet'),
                'id' => 'wc_balikovna_api_section'
            ),
            'api_token' => array(
                'name' => __('API Token', 'wc-balikovna-komplet'),
                'type' => 'text',
                'desc' => __('Token z API České pošty', 'wc-balikovna-komplet'),
                'id' => 'wc_balikovna_api_token',
                'css' => 'min-width:400px;',
            ),
            'api_private_key' => array(
                'name' => __('Privátní klíč', 'wc-balikovna-komplet'),
                'type' => 'textarea',
                'desc' => __('Privátní klíč z API České pošty', 'wc-balikovna-komplet'),
                'id' => 'wc_balikovna_api_private_key',
                'css' => 'min-width:400px; min-height:100px;',
            ),
            'api_section_end' => array(
                'type' => 'sectionend',
                'id' => 'wc_balikovna_api_section_end'
            ),
            
            // Sender information
            'sender_section' => array(
                'name' => __('Údaje odesílatele', 'wc-balikovna-komplet'),
                'type' => 'title',
                'desc' => __('Tyto údaje budou vytištěny na štítku jako odesílatel', 'wc-balikovna-komplet'),
                'id' => 'wc_balikovna_sender_section'
            ),
            'sender_name' => array(
                'name' => __('Jméno odesílatele', 'wc-balikovna-komplet'),
                'type' => 'text',
                'desc' => __('Název firmy nebo jméno', 'wc-balikovna-komplet'),
                'id' => 'wc_balikovna_sender_name',
                'default' => get_bloginfo('name'),
            ),
            'sender_street' => array(
                'name' => __('Ulice a č.p.', 'wc-balikovna-komplet'),
                'type' => 'text',
                'id' => 'wc_balikovna_sender_street',
            ),
            'sender_city' => array(
                'name' => __('Město', 'wc-balikovna-komplet'),
                'type' => 'text',
                'id' => 'wc_balikovna_sender_city',
            ),
            'sender_zip' => array(
                'name' => __('PSČ', 'wc-balikovna-komplet'),
                'type' => 'text',
                'id' => 'wc_balikovna_sender_zip',
            ),
            'sender_phone' => array(
                'name' => __('Telefon', 'wc-balikovna-komplet'),
                'type' => 'text',
                'id' => 'wc_balikovna_sender_phone',
            ),
            'sender_email' => array(
                'name' => __('Email', 'wc-balikovna-komplet'),
                'type' => 'email',
                'id' => 'wc_balikovna_sender_email',
                'default' => get_option('admin_email'),
            ),
            'sender_section_end' => array(
                'type' => 'sectionend',
                'id' => 'wc_balikovna_sender_section_end'
            ),
            
            'section_end' => array(
                'type' => 'sectionend',
                'id' => 'wc_balikovna_section_end'
            )
        );

        return apply_filters('wc_balikovna_settings', $settings);
    }

    /**
     * Output sync section
     */
    public function output_sync_section()
    {
        global $wpdb;

        $branches_table = $wpdb->prefix . 'balikovna_branches';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $branches_table
        ));
        
        $branches_count = 0;
        if ($table_exists) {
            $branches_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$branches_table}`");
        }
        
        $last_sync = get_option('balikovna_last_update');
        $last_count = get_option('balikovna_last_count');
        $last_error = get_option('balikovna_last_update_error');

        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label><?php esc_html_e('Statistiky', 'wc-balikovna-komplet'); ?></label>
                </th>
                <td class="forminp">
                    <?php if (!$table_exists) : ?>
                        <p style="color: #dc3232;">
                            <strong><?php esc_html_e('⚠ Databázová tabulka neexistuje!', 'wc-balikovna-komplet'); ?></strong><br>
                            <?php esc_html_e('Klikněte na tlačítko "Aktualizovat data poboček" níže pro vytvoření tabulky a import dat.', 'wc-balikovna-komplet'); ?>
                        </p>
                    <?php else : ?>
                        <p>
                            <strong><?php esc_html_e('Počet poboček v databázi:', 'wc-balikovna-komplet'); ?></strong>
                            <span style="<?php echo $branches_count === 0 ? 'color: #dc3232; font-weight: bold;' : 'color: #46b450; font-weight: bold;'; ?>">
                                <?php echo intval($branches_count); ?>
                            </span>
                            <?php if ($branches_count === 0) : ?>
                                <span style="color: #dc3232;">
                                    <?php esc_html_e('(Nebyla provedena synchronizace nebo importována žádná data)', 'wc-balikovna-komplet'); ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($last_sync) : ?>
                        <p>
                            <strong><?php esc_html_e('Poslední aktualizace:', 'wc-balikovna-komplet'); ?></strong>
                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_sync)); ?>
                            <?php if ($last_count) : ?>
                                <br>
                                <em><?php echo sprintf(esc_html__('(Importováno %d poboček)', 'wc-balikovna-komplet'), intval($last_count)); ?></em>
                            <?php endif; ?>
                        </p>
                    <?php else : ?>
                        <p>
                            <strong><?php esc_html_e('Poslední aktualizace:', 'wc-balikovna-komplet'); ?></strong>
                            <span style="color: #dc3232;"><?php esc_html_e('Nikdy', 'wc-balikovna-komplet'); ?></span>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($last_error) : ?>
                        <p style="padding: 10px; background: #fff3cd; border-left: 4px solid #dc3232; margin: 10px 0;">
                            <strong style="color: #dc3232;"><?php esc_html_e('Poslední chyba:', 'wc-balikovna-komplet'); ?></strong><br>
                            <?php echo esc_html($last_error); ?>
                        </p>
                    <?php endif; ?>
                    
                    <p>
                        <strong><?php esc_html_e('API URL:', 'wc-balikovna-komplet'); ?></strong>
                        <code><?php echo esc_html(WC_BALIKOVNA_API_URL); ?></code>
                    </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label><?php esc_html_e('Synchronizace dat', 'wc-balikovna-komplet'); ?></label>
                </th>
                <td class="forminp">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="wc_balikovna_sync">
                        <?php wp_nonce_field('wc_balikovna_sync'); ?>
                        <?php submit_button(__('Aktualizovat pobočky', 'wc-balikovna-komplet'), 'primary', 'submit', false); ?>
                    </form>
                    <p class="description">
                        <?php esc_html_e('Načte aktuální data poboček z API České pošty. Proces může trvat několik minut.', 'wc-balikovna-komplet'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Handle sync data request
     */
    public function handle_sync_data()
    {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Nemáte oprávnění k provedení této akce.', 'wc-balikovna-komplet'));
        }

        // Check nonce
        check_admin_referer('wc_balikovna_sync');

        error_log('WC Balíkovna: Starting manual sync...');

        // Run sync
        $result = WC_Balikovna_Install::sync_data();

        if (is_wp_error($result)) {
            error_log('WC Balíkovna: Sync failed - ' . $result->get_error_message());
            
            set_transient('wc_balikovna_admin_notice', array(
                'type' => 'error',
                'message' => sprintf(
                    __('Chyba při synchronizaci: %s', 'wc-balikovna-komplet'),
                    $result->get_error_message()
                )
            ), 30);
        } else {
            error_log('WC Balíkovna: Sync completed successfully');
            
            update_option('wc_balikovna_last_sync', time());
            
            set_transient('wc_balikovna_admin_notice', array(
                'type' => 'success',
                'message' => __('Data poboček byla úspěšně aktualizována.', 'wc-balikovna-komplet')
            ), 30);
        }

        // Redirect back to settings page
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=balikovna'));
        exit;
    }

    /**
     * Add print label action to orders list
     *
     * @param array $actions
     * @param WC_Order $order
     * @return array
     */
    public function add_print_label_action($actions, $order)
    {
        $branch_id = $order->get_meta('_wc_balikovna_branch_id');
        
        if (!empty($branch_id)) {
            $actions['balikovna_print_label'] = array(
                'url' => wp_nonce_url(admin_url('admin-post.php?action=wc_balikovna_print_label&order_id=' . $order->get_id()), 'wc_balikovna_print_label_' . $order->get_id()),
                'name' => __('Vytisknout štítek Balíkovny', 'wc-balikovna-komplet'),
                'action' => 'balikovna_print'
            );
        }
        
        return $actions;
    }

    /**
     * Handle print label request
     */
    public function handle_print_label()
    {
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('Nemáte oprávnění k provedení této akce.', 'wc-balikovna-komplet'));
        }

        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        
        if (!$order_id) {
            wp_die(__('Neplatné ID objednávky.', 'wc-balikovna-komplet'));
        }

        // Check nonce
        check_admin_referer('wc_balikovna_print_label_' . $order_id);

        // Load label generator
        require_once WC_BALIKOVNA_PLUGIN_DIR . 'includes/class-wc-balikovna-label.php';
        
        $label_generator = new WC_Balikovna_Label();
        $result = $label_generator->generate_label($order_id);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        // PDF is already output by the generator
        exit;
    }

    /**
     * Display admin notices
     */
    public function admin_notices()
    {
        $notice = get_transient('wc_balikovna_admin_notice');
        
        if ($notice) {
            delete_transient('wc_balikovna_admin_notice');
            
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }
}