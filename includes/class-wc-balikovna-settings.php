<?php
/**
 * Settings page for Balikovna
 *
 * @package WC_Balikovna_Komplet
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Balikovna_Settings', false)) {

    /**
     * WC_Balikovna_Settings Class
     */
    class WC_Balikovna_Settings extends WC_Settings_Page
    {
        /**
         * Constructor
         */
        public function __construct()
        {
            $this->id = 'balikovna';
            $this->label = __('Balíkovna', 'wc-balikovna-komplet');

            parent::__construct();
        }

        /**
         * Get sections
         *
         * @return array
         */
        public function get_sections()
        {
            $sections = array(
                '' => __('Nastavení', 'wc-balikovna-komplet'),
            );

            return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
        }

        /**
         * Output the settings
         */
        public function output()
        {
            global $current_section, $wpdb;

            $settings = $this->get_settings($current_section);

            // Add sync button and statistics before settings
            echo '<h2>' . esc_html__('Balíkovna České pošty', 'wc-balikovna-komplet') . '</h2>';

            // Get statistics
            $branches_table = $wpdb->prefix . 'balikovna_branches';
            $branches_count = $wpdb->get_var("SELECT COUNT(*) FROM $branches_table");
            $last_sync = get_transient('wc_balikovna_branches_timestamp');

            echo '<table class="form-table">';
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Počet poboček v databázi', 'wc-balikovna-komplet') . '</th>';
            echo '<td><strong>' . intval($branches_count) . '</strong></td>';
            echo '</tr>';
            
            if ($last_sync) {
                echo '<tr>';
                echo '<th scope="row">' . esc_html__('Poslední synchronizace', 'wc-balikovna-komplet') . '</th>';
                echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync))) . '</td>';
                echo '</tr>';
            }
            
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Synchronizace dat', 'wc-balikovna-komplet') . '</th>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="wc_balikovna_sync">';
            wp_nonce_field('wc_balikovna_sync');
            submit_button(__('Aktualizovat data poboček', 'wc-balikovna-komplet'), 'primary', 'submit', false);
            echo '<p class="description">' . esc_html__('Načte aktuální data poboček z API České pošty. Proces může trvat několik minut.', 'wc-balikovna-komplet') . '</p>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
            echo '</table>';

            echo '<hr>';

            WC_Admin_Settings::output_fields($settings);
        }

        /**
         * Save settings
         */
        public function save()
        {
            global $current_section;

            $settings = $this->get_settings($current_section);
            WC_Admin_Settings::save_fields($settings);
        }

        /**
         * Get settings array
         *
         * @param string $current_section
         * @return array
         */
        public function get_settings($current_section = '')
        {
            $settings = array();

            if ('' === $current_section) {
                $settings = array(
                    array(
                        'title' => __('Obecné nastavení', 'wc-balikovna-komplet'),
                        'type' => 'title',
                        'desc' => __('Základní nastavení pluginu Balíkovna', 'wc-balikovna-komplet'),
                        'id' => 'balikovna_general_settings',
                    ),
                    array(
                        'title' => __('Povolit Balíkovnu', 'wc-balikovna-komplet'),
                        'desc' => __('Povolit dopravu přes Balíkovnu České pošty', 'wc-balikovna-komplet'),
                        'id' => 'wc_balikovna_enabled',
                        'default' => 'yes',
                        'type' => 'checkbox',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'balikovna_general_settings',
                    ),
                );
            }

            return apply_filters('woocommerce_get_settings_' . $this->id, $settings, $current_section);
        }
    }

    return new WC_Balikovna_Settings();
}
