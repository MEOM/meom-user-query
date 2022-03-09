# MEOM user query

This plugin is similar as [WP_Query Route To REST API](https://github.com/aucor/wp_query-route-to-rest-api) but for [WP User Query](https://developer.wordpress.org/reference/classes/wp_user_query/).

MEOM user query adds new route `/wp-json/user_query/args/` to REST API. You can query users with `WP_User_Query` args.

## Return HTML from request

Sometimes it's usefull to return `HTML` from request and use PHP templates for generating markup.

### Disable REST query.

```php
add_filter( 'wp_user_query_to_rest_api_allow_query', '__return_false' );
```

### Set default data

```php
/**
 * Modify default data.
 *
 * @param array $data Default data.
 * @return array Modified default data.
 */
function prefix_default_data( $data ) {
    $data = array(
        'html'     => false,
        'messages' => array(
            'empty' => esc_html__( 'No results found.', 'textdomain' ),
        ),
    );

    return $data;
}
add_filter( 'wp_user_query_to_rest_api_default_data', 'prefix_default_data' );
```
### Modify the query to return `HTML`.

```php
/**
 * Modify WP User Query data to return HTML.
 *
 * @param array  $data Data inside loop.
 * @param object $user_query WP User Query.
 * @param array  $args Arguments.
 * @return array Modified data.
 */
function prefix_modify_user_data( $data, $user_query, $args ) {
    if ( ! empty( $user_query->get_results() ) ) {
        $html = '';

        ob_start();

        foreach ( $user_query->get_results() as $user ) {
            // Change this to your needs.
            // In this example we pass user ID to the template and do the markup in there.
            get_template_part( 'partials/user/user-item', null, [ 'author_id' => $user->ID ] );
        }

        $html .= ob_get_clean();

        $data['html'] = $html;

        wp_reset_postdata();
    }

    return $data;
}
add_filter( 'wp_user_query_to_rest_api_after_loop_data', 'prefix_modify_user_data', 10, 3 );
```
