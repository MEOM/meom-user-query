<?php
/**
 * Plugin Name: MEOM user query
 * Description: Adds new route /wp-json/user_query/args/ to REST API.
 * Author: MEOM
 * Author URI: https://meom.fi/
 * Version: 0.1.0
 * License: GPL2+
 **/

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

class WP_User_Query_To_REST_API extends WP_REST_Posts_Controller {

    /**
     * Constructor
     */
    public function __construct() {
        // Plugin compatibility.
        add_filter( 'wp_user_query_to_rest_api_allowed_args', [ $this, 'plugin_compatibility_args' ] );
        add_action( 'wp_user_query_to_rest_api_after_query', [ $this, 'plugin_compatibility_after_query' ] );

        // Register REST route.
        $this->register_routes();
    }

    /**
     * Register read-only /user_query/args/ route
     */
    public function register_routes() {
        register_rest_route(
            'user_query',
            'args',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_items' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
            ]
        );
    }

    /**
     * Check if a given request has access to get items
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|bool
     */

    public function get_items_permissions_check( $request ) {
        return apply_filters( 'wp_user_query_to_rest_api_permissions_check', true, $request );
    }

    /**
     * Get a collection of items
     *
     * @param WP_REST_Request $request Full data about the request.
     */

    public function get_items( $request ) {
        $parameters = $request->get_query_params();

        $default_args = [
            'role'   => 'Author',
            'number' => 10,
        ];

        $default_args = apply_filters( 'wp_user_query_to_rest_api_default_args', $default_args );

        // Allow these args => what isn't explicitly allowed, is forbidden
        $allowed_args = [
            'blog_id',
            'role',
            'role__in',
            'role__not_in',
            'capability',
            'capability__in',
            'capability__not_in',
            'include',
            'exclude',
            'orderby',
            'order',
            'offset',
            'number',
            'paged',
            'count_total',
            'fields',
            'who',
            'has_published_posts',
            'nicename',
            'nicename__in',
            'nicename__not_in',
            'login',
            'login__in',
            'login__not_in',
            'lang', // Polylang
        ];

        // Allow filtering by meta: default true.
        if ( apply_filters( 'wp_user_query_to_rest_api_allow_meta', true ) ) {
            $allowed_args[] = 'meta_key';
            $allowed_args[] = 'meta_value';
            $allowed_args[] = 'meta_compare';
            $allowed_args[] = 'meta_compare_key';
            $allowed_args[] = 'meta_type';
            $allowed_args[] = 'meta_type_key';
            $allowed_args[] = 'meta_query';
        }

        // Allow search: default true.
        if ( apply_filters( 'wp_user_query_to_rest_api_allow_search', true ) ) {
            $allowed_args[] = 'search';
            $allowed_args[] = 'search_columns';
        }

        // Let themes and plugins ultimately decide what to allow.
        $allowed_args = apply_filters( 'wp_user_query_to_rest_api_allowed_args', $allowed_args );

        // Args from url.
        $query_args = [];

        foreach ( $parameters as $key => $value ) {
            // Skip keys that are not explicitly allowed.
            if ( in_array( $key, $allowed_args, true ) ) {
                switch ( $key ) {
                    // Set given value.
                    default:
                        $query_args[ $key ] = $value;
                        break;
                }
            }
        }

        // Combine defaults and query_args.
        $args = wp_parse_args( $query_args, $default_args );

        // Make all the values filterable
        foreach ( $args as $key => $value ) {
            $args[ $key ] = apply_filters( 'wp_user_query_to_rest_api_arg_value', $value, $key, $args );
        }

        // Before query: hook your plugins here.
        do_action( 'wp_user_query_to_rest_api_before_query', $args );

        // Run query.
        $user_query = new WP_User_Query( $args );

        // After query: hook your plugins here.
        do_action( 'wp_user_query_to_rest_api_after_query', $user_query );

        $data = [];
        $data = apply_filters( 'wp_user_query_to_rest_api_default_data', $data );

        if ( ! empty( $user_query->get_results() ) ) {
            // Allow query: default true.
            if ( apply_filters( 'wp_user_query_to_rest_api_allow_query', true ) ) {
                foreach ( $user_query->get_results() as $user ) {
                    $data[] = $user;
                }
            }

            // After loop hook.
            $data = apply_filters( 'wp_user_query_to_rest_api_after_loop_data', $data, $user_query, $args );
        }

        return $this->get_response( $request, $args, $user_query, $data );
    }

    /**
     * Get response
     *
     * @access protected
     *
     * @param WP_REST_Request $request Full details about the request
     * @param array $args WP_User_Query args
     * @param WP_Query $user_query
     * @param array $data response data
     *
     * @return WP_REST_Response
     */

    protected function get_response( $request, $args, $user_query, $data ) {
        // Prepare data.
        $response = new WP_REST_Response( $data, 200 );

        // Total amount of users.
        $total_count = intval( $user_query->total_users );
        $response->header( 'X-WP-Total', $total_count );

        // Total number of pages.
        $max_pages = ( absint( $args['number'] ) === 0 ) ? 1 : ceil( $total_count / $args['number'] );
        $response->header( 'X-WP-TotalPages', intval( $max_pages ) );

        return $response;
    }

    /**
     * Plugin compatibility args
     *
     * @param array $args
     *
     * @return array $args
     */

    public function plugin_compatibility_args( $args ) {
        // Polylang compatibility
        $args[] = 'lang';

        return $args;
    }

    /**
     * Plugin compatibility after query
     *
     * @param WP_Query $user_query
     */

    public function plugin_compatibility_after_query( $user_query ) {
        // Relevanssi compatibility.
        if ( function_exists( 'relevanssi_do_query' ) && ! empty( $user_query->query_vars['s'] ) ) {
            relevanssi_do_query( $user_query );
        }
    }

}

/**
 * Init only when needed
 */
function wp_user_query_to_rest_api_init() {
    new WP_User_Query_To_REST_API();
}
add_action( 'rest_api_init', 'wp_user_query_to_rest_api_init' );

