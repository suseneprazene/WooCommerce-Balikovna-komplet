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

        error_log('WC Balíkovna: Tables created');
    }

    /**
     * Sync data from Czech Post API
     *
     * @return bool|WP_Error
     */
    public static function sync_data()
    {
        global $wpdb;

        error_log('=== WC Balíkovna: Starting sync_data ===');

        $branches_table = $wpdb->prefix . 'balikovna_branches';
        $hours_table = $wpdb->prefix . 'balikovna_opening_hours';

        // Ensure tables exist
        self::install();

        // Check if API URL is defined
        if (!defined('WC_BALIKOVNA_API_URL')) {
            error_log('WC Balíkovna ERROR: WC_BALIKOVNA_API_URL is not defined');
            $error_msg = 'Konstanta WC_BALIKOVNA_API_URL není definována';
            update_option('balikovna_last_update_error', $error_msg);
            return new WP_Error('missing_constant', $error_msg);
        }

        error_log('WC Balíkovna: API URL: ' . WC_BALIKOVNA_API_URL);

        // Fetch XML data from Czech Post
        error_log('WC Balíkovna: Downloading XML...');
        $xml_data = wp_remote_get(WC_BALIKOVNA_API_URL, array(
            'timeout' => 60,
            'sslverify' => false, // Note: Czech Post certificate may have issues on some servers
        ));

        if (is_wp_error($xml_data)) {
            error_log('WC Balíkovna ERROR: ' . $xml_data->get_error_message());
            $error_msg = 'Chyba při stahování dat: ' . $xml_data->get_error_message();
            update_option('balikovna_last_update_error', $error_msg);
            return new WP_Error('download_error', $error_msg);
        }

        $response_code = wp_remote_retrieve_response_code($xml_data);
        error_log('WC Balíkovna: Response code: ' . $response_code);

        if ($response_code !== 200) {
            $error_msg = sprintf('HTTP chyba: %d', $response_code);
            error_log('WC Balíkovna ERROR: ' . $error_msg);
            update_option('balikovna_last_update_error', $error_msg);
            return new WP_Error('http_error', $error_msg);
        }

        $body = wp_remote_retrieve_body($xml_data);
        error_log('WC Balíkovna: Downloaded ' . strlen($body) . ' bytes');
        
        if (empty($body)) {
            error_log('WC Balíkovna ERROR: Empty response body');
            $error_msg = 'Prázdná odpověď z API České pošty';
            update_option('balikovna_last_update_error', $error_msg);
            return new WP_Error('empty_response', $error_msg);
        }

        // Log first 500 characters of response for debugging
        error_log('WC Balíkovna: Response preview: ' . substr($body, 0, 500));

        // Parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array();
            foreach ($errors as $error) {
                $error_messages[] = trim($error->message);
            }
            libxml_clear_errors();
            $error_msg = 'Chyba při parsování XML dat: ' . implode(', ', $error_messages);
            error_log('WC Balíkovna ERROR: ' . $error_msg);
            update_option('balikovna_last_update_error', $error_msg);
            return new WP_Error('xml_parse_error', $error_msg);
        }

        error_log('WC Balíkovna: XML parsed successfully');
        
        // Count rows in XML
        $xml_row_count = isset($xml->row) ? count($xml->row) : 0;
        error_log('WC Balíkovna: Found ' . $xml_row_count . ' rows in XML');

        if ($xml_row_count === 0) {
            $error_msg = 'XML neobsahuje žádné pobočky (0 rows)';
            error_log('WC Balíkovna ERROR: ' . $error_msg);
            update_option('balikovna_last_update_error', $error_msg);
            return new WP_Error('no_data', $error_msg);
        }

        // Clear existing data
        $wpdb->query("TRUNCATE TABLE `{$hours_table}`");
        $wpdb->query("TRUNCATE TABLE `{$branches_table}`");
        error_log('WC Balíkovna: Tables truncated');

        $branches_count = 0;
        $hours_count = 0;
        $errors_count = 0;

        // Process each branch
        foreach ($xml->row as $item) {
            // Validate required fields
            if (empty((string)$item->NAZEV) || empty((string)$item->OBEC)) {
                error_log('WC Balíkovna WARNING: Skipping branch with missing required fields');
                $errors_count++;
                continue;
            }

            // Insert branch
            $insert_result = $wpdb->insert(
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

            if ($insert_result === false) {
                error_log('WC Balíkovna ERROR: Failed to insert branch: ' . $wpdb->last_error);
                $errors_count++;
                continue;
            }

            $branch_id = $wpdb->insert_id;
            $branches_count++;

            // Process opening hours
            if (isset($item->OTEV_DOBY) && isset($item->OTEV_DOBY->den)) {
                foreach ($item->OTEV_DOBY->den as $day) {
                    $day_name = (string)$day['name'];

                    if (isset($day->od_do)) {
                        foreach ($day->od_do as $hours) {
                            $open_from = (string)$hours['od'];
                            $open_to = (string)$hours['do'];

                            if (!empty($open_from) && !empty($open_to)) {
                                $wpdb->insert(
                                    $hours_table,
                                    array(
                                        'branch_id' => $branch_id,
                                        'day_name' => $day_name,
                                        'open_from' => $open_from,
                                        'open_to' => $open_to,
                                    ),
                                    array('%d', '%s', '%s', '%s')
                                );
                                $hours_count++;
                            }
                        }
                    }
                }
            }

            // Log progress every 100 branches
            if ($branches_count % 100 == 0) {
                error_log('WC Balíkovna: Processed ' . $branches_count . ' branches...');
            }
        }

        error_log('WC Balíkovna: Sync completed - ' . $branches_count . ' branches, ' . $hours_count . ' opening hours, ' . $errors_count . ' errors');
        error_log('=== WC Balíkovna: Sync finished successfully ===');

        // Save timestamp and count
        $current_time = time();
        update_option('balikovna_last_update', $current_time);
        update_option('balikovna_last_count', $branches_count);
        update_option('wc_balikovna_last_sync', $current_time);
        
        // Clear error message on success
        delete_option('balikovna_last_update_error');
        
        // Clear any cached branch data
        delete_transient('wc_balikovna_branches_cache');
        delete_transient('wc_balikovna_branches_timestamp');

        return true;
    }
}