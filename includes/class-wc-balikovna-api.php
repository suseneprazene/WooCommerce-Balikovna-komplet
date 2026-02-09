<?php
/**
 * REST API endpoints for Balikovna
 *
 * @package WC_Balikovna_Komplet
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Balikovna_API Class
 */
class WC_Balikovna_API
{
    /**
     * The single instance of the class
     *
     * @var WC_Balikovna_API
     */
    protected static $_instance = null;

    /**
     * Main WC_Balikovna_API Instance
     *
     * @return WC_Balikovna_API
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
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes()
    {
        register_rest_route('balikovna/v1', '/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'search_branches'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('balikovna/v1', '/hours/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_opening_hours'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));
    }

    /**
     * Search branches endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function search_branches($request)
    {
        global $wpdb;
        
        $term = sanitize_text_field($request->get_param('q'));
        
        // Debug: Log request
        error_log('WC Balíkovna API: Search request with term: "' . $term . '"');
        
        // Check if table exists
        $branches_table = $wpdb->prefix . 'balikovna_branches';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $branches_table
        ));
        
        if (!$table_exists) {
            error_log('WC Balíkovna API: ERROR - Table does not exist: ' . $branches_table);
            return new WP_REST_Response(array(
                'branches' => array(),
                'error' => 'Tabulka poboček neexistuje. Spusťte synchronizaci dat.',
                'debug' => array(
                    'table' => $branches_table,
                    'exists' => false
                )
            ), 200);
        }
        
        // Check row count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$branches_table}`");
        error_log('WC Balíkovna API: Table row count: ' . $count);
        
        if ($count == 0) {
            error_log('WC Balíkovna API: WARNING - Table is empty');
            return new WP_REST_Response(array(
                'branches' => array(),
                'error' => 'Databáze poboček je prázdná. Spusťte synchronizaci dat v nastavení.',
                'debug' => array(
                    'table' => $branches_table,
                    'exists' => true,
                    'count' => 0
                )
            ), 200);
        }
        
        $branches = $this->get_branches($term);
        
        error_log('WC Balíkovna API: Returned ' . count($branches) . ' branches');

        return new WP_REST_Response(array(
            'branches' => $branches,
            'debug' => array(
                'term' => $term,
                'count' => count($branches),
                'total_in_db' => $count
            )
        ), 200);
    }

    /**
     * Get opening hours endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_opening_hours($request)
    {
        global $wpdb;

        $branch_id = intval($request['id']);
        $hours_table = $wpdb->prefix . 'balikovna_opening_hours';

        $hours = $wpdb->get_results($wpdb->prepare(
            "SELECT day_name, open_from, open_to FROM `{$hours_table}` WHERE branch_id = %d ORDER BY id",
            $branch_id
        ));

        if (empty($hours)) {
            return new WP_REST_Response(array(
                'error' => __('Otevírací hodiny nenalezeny', 'wc-balikovna-komplet'),
            ), 404);
        }

        // Format hours for display
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

        $html = '<div class="balikovna-opening-hours">';
        foreach ($formatted_hours as $day => $times) {
            $html .= '<div class="day-hours">';
            $html .= '<strong>' . esc_html($day) . ':</strong> ';
            $time_strings = array();
            foreach ($times as $time) {
                $time_strings[] = esc_html($time['from']) . ' - ' . esc_html($time['to']);
            }
            $html .= implode(', ', $time_strings);
            $html .= '</div>';
        }
        $html .= '</div>';

        return new WP_REST_Response($html, 200);
    }

    /**
     * Get branches from database
     *
     * @param string $term Search term
     * @return array
     */
    private function get_branches($term = '')
    {
        global $wpdb;

        $branches_table = $wpdb->prefix . 'balikovna_branches';
        
        // Build query
        if (empty($term)) {
            // Return first 50 branches if no search term
            $sql = $wpdb->prepare(
                "SELECT id, name, city, city_part, address, zip, kind 
                FROM `{$branches_table}` 
                ORDER BY city, city_part 
                LIMIT %d",
                50
            );
        } elseif (ctype_digit($term)) {
            // Search by ZIP code
            $sql = $wpdb->prepare(
                "SELECT id, name, city, city_part, address, zip, kind 
                FROM `{$branches_table}` 
                WHERE zip LIKE %s 
                ORDER BY city, city_part",
                $term . '%'
            );
        } else {
            // Search by city, city_part, or address
            $search_term = '%' . $wpdb->esc_like($term) . '%';
            $search_start = $wpdb->esc_like($term) . '%';
            
            $sql = $wpdb->prepare(
                "SELECT id, name, city, city_part, address, zip, kind 
                FROM `{$branches_table}` 
                WHERE city LIKE %s 
                   OR city_part LIKE %s 
                   OR address LIKE %s 
                ORDER BY 
                    CASE 
                        WHEN city LIKE %s THEN 1
                        WHEN city_part LIKE %s THEN 2
                        ELSE 3
                    END,
                    city, city_part 
                LIMIT 50",
                $search_term,
                $search_term,
                $search_term,
                $search_start,
                $search_start
            );
        }

        $results = $wpdb->get_results($sql, ARRAY_A);
        
        error_log('WC Balíkovna get_branches: SQL returned ' . count($results) . ' rows');
        
        if (empty($results)) {
            return array();
        }

        // Format results
        $branches = array();
        foreach ($results as $row) {
            $branches[] = array(
                'id' => intval($row['id']),
                'name' => $row['name'],
                'city' => $row['city'],
                'city_part' => $row['city_part'],
                'address' => $row['address'],
                'zip' => $row['zip'],
                'kind' => $row['kind'],
            );
        }

        return $branches;
    }
}