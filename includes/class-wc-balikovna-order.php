<?php
/**
 * Order handling for Balikovna
 *
 * @package WC_Balikovna_Komplet
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Balikovna_Order Class
 */
class WC_Balikovna_Order
{
    /**
     * The single instance of the class
     *
     * @var WC_Balikovna_Order
     */
    protected static $_instance = null;

    /**
     * Main WC_Balikovna_Order Instance
     *
     * @return WC_Balikovna_Order
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
        // Add meta box to order admin page
        add_action('add_meta_boxes', array($this, 'add_meta_box'));

        // Display branch info in order details
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_branch_info_in_order'));

        // Add branch info to emails
        add_action('woocommerce_email_after_order_table', array($this, 'add_branch_info_to_email'), 10, 4);

        // Add branch info to order admin page
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_branch_info_in_admin'));

        // Add label button to order admin page
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'add_label_button_to_order'), 20);
    }

    /**
     * Add meta box to order admin page
     *
     * @param string $post_type
     */
    public function add_meta_box($post_type)
    {
        $order_types = wc_get_order_types('order-meta-boxes');
        
        if (in_array($post_type, $order_types)) {
            add_meta_box(
                'wc_balikovna_order_info',
                __('Balíkovna', 'wc-balikovna-komplet'),
                array($this, 'render_meta_box'),
                null,
                'side',
                'default'
            );
        }
    }

    /**
     * Render meta box content
     *
     * @param WP_Post $post
     */
    public function render_meta_box($post)
    {
        $order = wc_get_order($post->ID);
        $branch_id = $order->get_meta('_wc_balikovna_branch_id');

        if (empty($branch_id)) {
            echo '<p>' . esc_html__('Tato objednávka neobsahuje údaje o Balíkovně.', 'wc-balikovna-komplet') . '</p>';
            return;
        }

        $branch_name = $order->get_meta('_wc_balikovna_branch_name');
        $branch_city = $order->get_meta('_wc_balikovna_branch_city');
        $branch_city_part = $order->get_meta('_wc_balikovna_branch_city_part');
        $branch_address = $order->get_meta('_wc_balikovna_branch_address');
        $branch_zip = $order->get_meta('_wc_balikovna_branch_zip');
        $branch_kind = $order->get_meta('_wc_balikovna_branch_kind');

        echo '<div class="wc-balikovna-order-info">';
        echo '<p><strong>' . esc_html__('Pobočka:', 'wc-balikovna-komplet') . '</strong><br>';
        echo esc_html($branch_name) . '</p>';
        
        echo '<p><strong>' . esc_html__('Adresa:', 'wc-balikovna-komplet') . '</strong><br>';
        echo esc_html($branch_address) . '<br>';
        if (!empty($branch_city_part)) {
            echo esc_html($branch_city_part) . '<br>';
        }
        echo esc_html($branch_zip) . ' ' . esc_html($branch_city) . '</p>';

        if (!empty($branch_kind)) {
            $kind_label = $branch_kind === 'posta' ? __('Pošta', 'wc-balikovna-komplet') : __('Balíkovna', 'wc-balikovna-komplet');
            echo '<p><strong>' . esc_html__('Typ:', 'wc-balikovna-komplet') . '</strong> ' . esc_html($kind_label) . '</p>';
        }

        // Get opening hours
        $this->display_opening_hours($branch_id);

        echo '</div>';
    }

    /**
     * Display branch info in order details (customer view)
     *
     * @param WC_Order $order
     */
    public function display_branch_info_in_order($order)
    {
        $branch_id = $order->get_meta('_wc_balikovna_branch_id');

        if (empty($branch_id)) {
            return;
        }

        $branch_name = $order->get_meta('_wc_balikovna_branch_name');
        $branch_city = $order->get_meta('_wc_balikovna_branch_city');
        $branch_city_part = $order->get_meta('_wc_balikovna_branch_city_part');
        $branch_address = $order->get_meta('_wc_balikovna_branch_address');
        $branch_zip = $order->get_meta('_wc_balikovna_branch_zip');

        echo '<section class="woocommerce-balikovna-details">';
        echo '<h2>' . esc_html__('Místo doručení - Balíkovna', 'wc-balikovna-komplet') . '</h2>';
        echo '<address>';
        echo '<strong>' . esc_html($branch_name) . '</strong><br>';
        echo esc_html($branch_address) . '<br>';
        if (!empty($branch_city_part)) {
            echo esc_html($branch_city_part) . '<br>';
        }
        echo esc_html($branch_zip) . ' ' . esc_html($branch_city);
        echo '</address>';
        echo '</section>';
    }

    /**
     * Add branch info to email
     *
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     * @param WC_Email $email
     */
    public function add_branch_info_to_email($order, $sent_to_admin, $plain_text, $email)
    {
        $branch_id = $order->get_meta('_wc_balikovna_branch_id');

        if (empty($branch_id)) {
            return;
        }

        $branch_name = $order->get_meta('_wc_balikovna_branch_name');
        $branch_city = $order->get_meta('_wc_balikovna_branch_city');
        $branch_city_part = $order->get_meta('_wc_balikovna_branch_city_part');
        $branch_address = $order->get_meta('_wc_balikovna_branch_address');
        $branch_zip = $order->get_meta('_wc_balikovna_branch_zip');

        if ($plain_text) {
            echo "\n" . strtoupper(__('Místo doručení - Balíkovna', 'wc-balikovna-komplet')) . "\n\n";
            echo $branch_name . "\n";
            echo $branch_address . "\n";
            if (!empty($branch_city_part)) {
                echo $branch_city_part . "\n";
            }
            echo $branch_zip . ' ' . $branch_city . "\n";
        } else {
            echo '<h2>' . esc_html__('Místo doručení - Balíkovna', 'wc-balikovna-komplet') . '</h2>';
            echo '<address>';
            echo '<strong>' . esc_html($branch_name) . '</strong><br>';
            echo esc_html($branch_address) . '<br>';
            if (!empty($branch_city_part)) {
                echo esc_html($branch_city_part) . '<br>';
            }
            echo esc_html($branch_zip) . ' ' . esc_html($branch_city);
            echo '</address>';
        }
    }

    /**
     * Display branch info in admin order page
     *
     * @param WC_Order $order
     */
    public function display_branch_info_in_admin($order)
    {
        $branch_id = $order->get_meta('_wc_balikovna_branch_id');

        if (empty($branch_id)) {
            return;
        }

        $branch_name = $order->get_meta('_wc_balikovna_branch_name');
        $branch_city = $order->get_meta('_wc_balikovna_branch_city');
        $branch_city_part = $order->get_meta('_wc_balikovna_branch_city_part');
        $branch_address = $order->get_meta('_wc_balikovna_branch_address');
        $branch_zip = $order->get_meta('_wc_balikovna_branch_zip');

        echo '<div class="wc-balikovna-admin-order-info" style="margin-top: 20px;">';
        echo '<h3>' . esc_html__('Balíkovna', 'wc-balikovna-komplet') . '</h3>';
        echo '<p><strong>' . esc_html($branch_name) . '</strong><br>';
        echo esc_html($branch_address) . '<br>';
        if (!empty($branch_city_part)) {
            echo esc_html($branch_city_part) . '<br>';
        }
        echo esc_html($branch_zip) . ' ' . esc_html($branch_city) . '</p>';
        echo '</div>';
    }

    /**
     * Display opening hours
     *
     * @param int $branch_id
     */
    private function display_opening_hours($branch_id)
    {
        global $wpdb;

        $hours_table = esc_sql($wpdb->prefix . 'balikovna_opening_hours');

        $hours = $wpdb->get_results($wpdb->prepare(
            "SELECT day_name, open_from, open_to FROM `{$hours_table}` WHERE branch_id = %d ORDER BY id",
            $branch_id
        ));

        if (empty($hours)) {
            return;
        }

        echo '<div class="wc-balikovna-opening-hours">';
        echo '<p><strong>' . esc_html__('Otevírací hodiny:', 'wc-balikovna-komplet') . '</strong></p>';
        echo '<details style="margin-left: 10px;">';
        echo '<summary style="cursor: pointer;">' . esc_html__('Zobrazit otevírací hodiny', 'wc-balikovna-komplet') . '</summary>';
        echo '<div style="margin-top: 10px;">';

        $formatted_hours = array();
        foreach ($hours as $hour) {
            if (!isset($formatted_hours[$hour->day_name])) {
                $formatted_hours[$hour->day_name] = array();
            }
            $formatted_hours[$hour->day_name][] = array(
                'from' => $hour->open_from,
                'to' => $hour->open_to,
            );
        }

        foreach ($formatted_hours as $day => $times) {
            echo '<div style="margin-bottom: 5px;">';
            echo '<strong>' . esc_html($day) . ':</strong> ';
            $time_strings = array();
            foreach ($times as $time) {
                $time_strings[] = esc_html($time['from']) . ' - ' . esc_html($time['to']);
            }
            echo implode(', ', $time_strings);
            echo '</div>';
        }

        echo '</div>';
        echo '</details>';
        echo '</div>';
    }

    /**
     * Add label generation button to order admin page
     *
     * @param WC_Order $order
     */
    public function add_label_button_to_order($order)
    {
        // Check if order uses Balikovna shipping
        $shipping_methods = $order->get_shipping_methods();
        $is_balikovna = false;
        
        foreach ($shipping_methods as $shipping_method) {
            if (strpos($shipping_method->get_method_id(), 'balikovna') !== false) {
                $is_balikovna = true;
                break;
            }
        }
        
        if (!$is_balikovna) {
            return;
        }
        
        $order_id = $order->get_id();
        $tracking_number = $order->get_meta('_wc_balikovna_tracking_number');
        
        echo '<div class="wc-balikovna-admin-section" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3>' . esc_html__('Balíkovna - Štítky', 'wc-balikovna-komplet') . '</h3>';
        
        if ($tracking_number) {
            echo '<p><strong>' . esc_html__('Tracking:', 'wc-balikovna-komplet') . '</strong> ' . esc_html($tracking_number) . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?action=wc_balikovna_print_label&order_id=' . $order_id . '&_wpnonce=' . wp_create_nonce('wc_balikovna_print_label'))) . '" class="button button-secondary" target="_blank">' . esc_html__('Stáhnout štítek (PDF)', 'wc-balikovna-komplet') . '</a>';
        } else {
            echo '<a href="' . esc_url(admin_url('admin.php?action=wc_balikovna_generate_label&order_id=' . $order_id . '&_wpnonce=' . wp_create_nonce('wc_balikovna_generate_label'))) . '" class="button button-primary">' . esc_html__('Vygenerovat štítek', 'wc-balikovna-komplet') . '</a>';
        }
        
        echo '</div>';
    }
}
