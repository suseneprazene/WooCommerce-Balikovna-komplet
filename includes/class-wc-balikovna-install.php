<?php
/**
 * Installation and database setup
 *
 * @package WC_Balikovna_Komplet
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Balikovna_Install Class
 */
class WC_Balikovna_Install
{
    /**
     * Install plugin tables and data
     */
    public static function install()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table names
        $branches_table = $wpdb->prefix . 'balikovna_branches';
        $hours_table = $wpdb->prefix . 'balikovna_opening_hours';

        // SQL for branches table
        $sql_branches = "CREATE TABLE IF NOT EXISTS $branches_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            city varchar(255) NOT NULL,
            city_part varchar(255) DEFAULT '',
            address varchar(255) NOT NULL,
            zip varchar(10) NOT NULL,
            lat decimal(10,7) DEFAULT NULL,
            lng decimal(10,7) DEFAULT NULL,
            kind varchar(50) DEFAULT 'balikovna',
            PRIMARY KEY  (id),
            KEY city (city),
            KEY zip (zip)
        ) $charset_collate;";

        // SQL for opening hours table
        $sql_hours = "CREATE TABLE IF NOT EXISTS $hours_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            branch_id bigint(20) unsigned NOT NULL,
            day_name varchar(50) NOT NULL,
            open_from time DEFAULT NULL,
            open_to time DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY branch_id (branch_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_branches);
        dbDelta($sql_hours);

        // Set database version
        update_option('wc_balikovna_db_version', WC_BALIKOVNA_VERSION);

        // Trigger initial data sync
        self::maybe_sync_data();
    }

    /**
     * Sync data from Czech Post API if tables are empty
     */
    public static function maybe_sync_data()
    {
        global $wpdb;

        $branches_table = esc_sql($wpdb->prefix . 'balikovna_branches');
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$branches_table}`");

        // If no branches exist, trigger sync
        if ($count == 0) {
            // Don't sync during install, let admin do it manually
            // This prevents timeouts during plugin activation
        }
    }

    /**
     * Sync data from Czech Post API
     *
     * @return bool|WP_Error
     */
    public static function sync_data()
    {
        global $wpdb;

        $branches_table = $wpdb->prefix . 'balikovna_branches';
        $hours_table = $wpdb->prefix . 'balikovna_opening_hours';

        // DEBUG: Logging start
        error_log('=== WC Balíkovna: Začátek synchronizace ===');
        error_log('API URL: ' . WC_BALIKOVNA_API_URL);
        error_log('Branches table: ' . $branches_table);
        error_log('Hours table: ' . $hours_table);
        
        // Check if tables exist
        $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$branches_table}'");
        error_log('Table exists: ' . ($tables_exist ? 'YES' : 'NO'));

        // Fetch XML data from Czech Post
        error_log('Stahování XML z API...');
        $xml_data = wp_remote_get(WC_BALIKOVNA_API_URL, array(
            'timeout' => 60,
        ));

        if (is_wp_error($xml_data)) {
            error_log('CHYBA při stahování: ' . $xml_data->get_error_message());
            return $xml_data;
        }

        $body = wp_remote_retrieve_body($xml_data);
        error_log('XML staženo. Velikost: ' . strlen($body) . ' bytů');
        
        if (empty($body)) {
            error_log('CHYBA: Prázdná odpověď z API');
            return new WP_Error('empty_response', __('Prázdná odpověď z API České pošty', 'wc-balikovna-komplet'));
        }

        // Parse XML
        error_log('Parsování XML...');
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            $errors = libxml_get_errors();
            error_log('CHYBA při parsování XML:');
            foreach ($errors as $error) {
                error_log('  - ' . $error->message);
            }
            libxml_clear_errors();
            return new WP_Error('xml_parse_error', __('Chyba při parsování XML dat', 'wc-balikovna-komplet'));
        }

        $total_items = count($xml->row);
        error_log('XML úspěšně parsováno. Počet položek v XML: ' . $total_items);

        // Clear existing data
        error_log('Mazání starých dat...');
        $wpdb->query("TRUNCATE TABLE $hours_table");
        $wpdb->query("TRUNCATE TABLE $branches_table");

        $branches_count = 0;
        $hours_count = 0;

        // Process each branch
        error_log('Zpracování poboček...');
        foreach ($xml->row as $item) {
            // Insert branch
            $result = $wpdb->insert(
                $branches_table,
                array(
                    'name' => sanitize_text_field((string)$item->NAZEV),
                    'city' => sanitize_text_field((string)$item->OBEC),
                    'city_part' => sanitize_text_field((string)$item->C_OBCE),
                    'address' => sanitize_text_field((string)$item->ADRESA),
                    'zip' => sanitize_text_field((string)$item->PSC),
                    'lat' => floatval((string)$item->SOUR_X),
                    'lng' => floatval((string)$item->SOUR_Y),
                    'kind' => sanitize_text_field((string)$item->TYP),
                ),
                array('%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s')
            );

            if ($result === false) {
                error_log('CHYBA při vkládání pobočky: ' . $wpdb->last_error);
                continue;
            }

            $branch_id = $wpdb->insert_id;
            $branches_count++;

            // Log progress every 100 branches
            if ($branches_count % 100 === 0) {
                error_log('Zpracováno ' . $branches_count . ' / ' . $total_items . ' poboček...');
            }

            // Process opening hours
            if (isset($item->OTEV_DOBY) && isset($item->OTEV_DOBY->den)) {
                foreach ($item->OTEV_DOBY->den as $day) {
                    $day_name = (string)$day['name'];

                    if (isset($day->od_do)) {
                        // Handle multiple time ranges
                        if (is_array($day->od_do) || count($day->od_do) > 1) {
                            foreach ($day->od_do as $time_range) {
                                $from = (string)$time_range->od;
                                $to = (string)$time_range->do;
                                
                                if (!empty($from) && !empty($to)) {
                                    $wpdb->insert(
                                        $hours_table,
                                        array(
                                            'branch_id' => $branch_id,
                                            'day_name' => sanitize_text_field($day_name),
                                            'open_from' => sanitize_text_field($from),
                                            'open_to' => sanitize_text_field($to),
                                        ),
                                        array('%d', '%s', '%s', '%s')
                                    );
                                    $hours_count++;
                                }
                            }
                        } else {
                            // Single time range
                            $from = (string)$day->od_do->od;
                            $to = (string)$day->od_do->do;
                            
                            if (!empty($from) && !empty($to)) {
                                $wpdb->insert(
                                    $hours_table,
                                    array(
                                        'branch_id' => $branch_id,
                                        'day_name' => sanitize_text_field($day_name),
                                        'open_from' => sanitize_text_field($from),
                                        'open_to' => sanitize_text_field($to),
                                    ),
                                    array('%d', '%s', '%s', '%s')
                                );
                                $hours_count++;
                            }
                        }
                    }
                }
            }
        }

        error_log('=== Synchronizace dokončena ===');
        error_log('Importováno poboček: ' . $branches_count);
        error_log('Importováno otevíracích hodin: ' . $hours_count);

        // Update cache timestamp
        set_transient('wc_balikovna_branches_timestamp', current_time('mysql'), DAY_IN_SECONDS);
        delete_transient('wc_balikovna_branches_cache');

        return array(
            'branches' => $branches_count,
            'hours' => $hours_count,
        );
    }
}
