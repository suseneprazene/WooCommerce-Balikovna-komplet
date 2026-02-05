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
        $term = sanitize_text_field($request->get_param('q'));
        $branches = $this->get_branches($term);

        return new WP_REST_Response(array(
            'branches' => $branches,
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
     * Get branches from database with caching
     *
     * @param string $term Search term
     * @return array
     */
    private function get_branches($term = '')
    {
        global $wpdb;

        $branches_table = $wpdb->prefix . 'balikovna_branches';
        
        // DEBUG: Log search request
        error_log('WC Balíkovna API: Searching branches with term: ' . ($term ? $term : '(empty)'));
        
        // Try cache first for empty searches
        if (empty($term)) {
            $cache_key = 'wc_balikovna_branches_cache';
            $cached = get_transient($cache_key);
            
            if ($cached !== false) {
                error_log('WC Balíkovna API: Returning ' . count($cached) . ' cached branches');
                return $cached;
            }
        }

        $sql = "SELECT id, name, city, city_part, address, zip, kind FROM `{$branches_table}`";
        $where = array();
        $params = array();

        if (!empty($term)) {
            if (ctype_digit($term)) {
                // Search by ZIP code
                $where[] = "zip LIKE %s";
                $params[] = $term . '%';
            } else {
                // Search by city, city_part, or address
                $where[] = "(city LIKE %s OR city LIKE %s OR city_part LIKE %s OR city_part LIKE %s OR address LIKE %s OR address LIKE %s)";
                $params[] = $term . '%';
                $params[] = '% ' . $term . '%';
                $params[] = $term . '%';
                $params[] = '% ' . $term . '%';
                $params[] = $term . '%';
                $params[] = '% ' . $term . '%';
            }
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY city, city_part LIMIT 50";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        error_log('WC Balíkovna API: SQL query: ' . $sql);

        $results = $wpdb->get_results($sql, ARRAY_A);

        if ($wpdb->last_error) {
            error_log('WC Balíkovna API: SQL error: ' . $wpdb->last_error);
        }

        error_log('WC Balíkovna API: Found ' . count($results) . ' branches');

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

        // Cache empty search results
        if (empty($term)) {
            set_transient('wc_balikovna_branches_cache', $branches, DAY_IN_SECONDS);
        }

        return $branches;
    }
}
