<?php

namespace Brisum\Wordpress\Woocommerce\Widget\LayeredNav;

use WC_Query;
use WC_Widget;
use WC_Widget_Layered_Nav;
use WP_Meta_Query;
use WP_Tax_Query;

class LayeredNav2 extends WC_Widget_Layered_Nav
{
    const CACHE_GROUP_TERM_LINK = 'laynav/term-link';

    /**
     * @constructor
     */
    public function __construct()
    {
        $this->widget_cssclass    = 'woocommerce widget_layered_nav';
        $this->widget_description = __( 'Shows a custom attribute in a widget which lets you narrow down the list of products when viewing product categories.', 'woocommerce' );
        $this->widget_id          = 'brisum_woocommerce_layered_nav';
        $this->widget_name        = __( 'Custom WooCommerce Layered Nav');

        WC_Widget::__construct();
    }

    /**
     * Get current page URL for layered nav items.
     * @return string
     */
    protected function get_page_base_url( $taxonomy ) {
        if ( defined( 'SHOP_IS_ON_FRONT' ) ) {
            $link = home_url();
        } elseif ( is_post_type_archive( 'product' ) || is_page( wc_get_page_id( 'shop' ) ) ) {
            $link = get_post_type_archive_link( 'product' );
        } elseif ( is_product_category() ) {
            $link = get_term_link( get_query_var( 'product_cat' ), 'product_cat' );
        } elseif ( is_product_tag() ) {
            $link = get_term_link( get_query_var( 'product_tag' ), 'product_tag' );
        } else {
            $queried_object = get_queried_object();
            $link = get_term_link( $queried_object->slug, $queried_object->taxonomy );
        }

        // Min/Max
        if ( isset( $_GET['min_price'] ) ) {
            $link = add_query_arg( 'min_price', wc_clean( $_GET['min_price'] ), $link );
        }

        if ( isset( $_GET['max_price'] ) ) {
            $link = add_query_arg( 'max_price', wc_clean( $_GET['max_price'] ), $link );
        }

        // Orderby
        if ( isset( $_GET['orderby'] ) ) {
            $link = add_query_arg( 'orderby', wc_clean( $_GET['orderby'] ), $link );
        }

        /**
         * Search Arg.
         * To support quote characters, first they are decoded from &quot; entities, then URL encoded.
         */
        if ( get_search_query() ) {
            $link = add_query_arg( 's', rawurlencode( htmlspecialchars_decode( get_search_query() ) ), $link );
        }

        // Post Type Arg
        if ( isset( $_GET['post_type'] ) ) {
            $link = add_query_arg( 'post_type', wc_clean( $_GET['post_type'] ), $link );
        }

        // Min Rating Arg
        if ( isset( $_GET['min_rating'] ) ) {
            $link = add_query_arg( 'min_rating', wc_clean( $_GET['min_rating'] ), $link );
        }

        // All current filters
        if ( $_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes() ) {
            asort($_chosen_attributes);
            foreach ( $_chosen_attributes as $name => $data ) {
                if ( $name === $taxonomy ) {
                    continue;
                }
                $filter_name = sanitize_title( str_replace( 'pa_', '', $name ) );
                if ( ! empty( $data['terms'] ) ) {
                    $link = add_query_arg( 'filter_' . $filter_name, implode( ',', $data['terms'] ), $link );
                }
                // RIGHT FILTER
                // if ( 'or' == $data['query_type'] ) {
                // 	$link = add_query_arg( 'query_type_' . $filter_name, 'or', $link );
                // }
            }
        }

        return $link;
    }

    /**
     * Count products within certain terms, taking the main WP query into consideration.
     * @param  array $term_ids
     * @param  string $taxonomy
     * @param  string $query_type
     * @return array
     */
    protected function get_filtered_term_product_counts( $term_ids, $taxonomy, $query_type ) {
        global $wpdb;

        $tax_query  = WC_Query::get_main_tax_query();
        $meta_query = WC_Query::get_main_meta_query();

        // RIGHT FILTER
		//if ( 'or' === $query_type ) {
            foreach ( $tax_query as $key => $query ) {
                if ( $taxonomy === $query['taxonomy'] ) {
                    unset( $tax_query[ $key ] );
                }
            }
		//}

        $meta_query      = new WP_Meta_Query( $meta_query );
        $tax_query       = new WP_Tax_Query( $tax_query );
        $meta_query_sql  = $meta_query->get_sql( 'post', $wpdb->posts, 'ID' );
        $tax_query_sql   = $tax_query->get_sql( $wpdb->posts, 'ID' );

        // Generate query
        $query           = array();
        $query['select'] = "SELECT COUNT( DISTINCT {$wpdb->posts}.ID ) as term_count, terms.term_id as term_count_id";
        $query['from']   = "FROM {$wpdb->posts}";
        $query['join']   = "
			INNER JOIN {$wpdb->term_relationships} AS term_relationships ON {$wpdb->posts}.ID = term_relationships.object_id
			INNER JOIN {$wpdb->term_taxonomy} AS term_taxonomy USING( term_taxonomy_id )
			INNER JOIN {$wpdb->terms} AS terms USING( term_id )
			" . $tax_query_sql['join'] . $meta_query_sql['join'];

        $query['where']   = "
			WHERE {$wpdb->posts}.post_type IN ( 'product' )
			AND {$wpdb->posts}.post_status = 'publish'
			" . $tax_query_sql['where'] . $meta_query_sql['where'] . "
			AND terms.term_id IN (" . implode( ',', array_map( 'absint', $term_ids ) ) . ")
		";

        if ( $search = WC_Query::get_main_search_query_sql() ) {
            $query['where'] .= ' AND ' . $search;
        }

        $query['group_by'] = "GROUP BY terms.term_id";
        $query             = apply_filters( 'woocommerce_get_filtered_term_product_counts_query', $query );
        $query             = implode( ' ', $query );
        $results           = $wpdb->get_results( $query );

        return wp_list_pluck( $results, 'term_count', 'term_count_id' );
    }

    /**
     * Show list based layered nav.
     * @param  array $terms
     * @param  string $taxonomy
     * @param  string $query_type
     * @return bool Will nav display?
     */
    protected function layered_nav_list( $terms, $taxonomy, $query_type ) {
        // List display
        echo '<ul>';

        $term_counts        = $this->get_filtered_term_product_counts( wp_list_pluck( $terms, 'term_id' ), $taxonomy, $query_type );
        $_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes();
        $found              = false;

        foreach ( $terms as $term ) {
            $current_values    = isset( $_chosen_attributes[ $taxonomy ]['terms'] ) ? $_chosen_attributes[ $taxonomy ]['terms'] : array();
            $option_is_set     = in_array( $term->slug, $current_values );
            $count             = isset( $term_counts[ $term->term_id ] ) ? $term_counts[ $term->term_id ] : 0;

            // skip the term for the current archive
            if ( $this->get_current_term_id() === $term->term_id ) {
                continue;
            }

            // Only show options with count > 0
            if ( 0 < $count ) {
                $found = true;
            } elseif ( /* 'and' === $query_type &&*/  0 === $count  /* && ! $option_is_set */ ) {
                continue;
            }

            $filter_name    = 'filter_' . sanitize_title( str_replace( 'pa_', '', $taxonomy ) );
            $current_filter = isset( $_GET[ $filter_name ] ) ? explode( ',', wc_clean( $_GET[ $filter_name ] ) ) : array();
            $current_filter = array_map( 'sanitize_title', $current_filter );

            if ( ! in_array( $term->slug, $current_filter ) ) {
                $current_filter[] = $term->slug;
            }

            $link = $this->get_page_base_url( $taxonomy );

            // Add current filters to URL.
            foreach ( $current_filter as $key => $value ) {
                // Exclude query arg for current term archive term
                if ( $value === $this->get_current_term_slug() ) {
                    unset( $current_filter[ $key ] );
                }

                // Exclude self so filter can be unset on click.
                if ( $option_is_set && $value === $term->slug ) {
                    unset( $current_filter[ $key ] );
                }
            }

            if ( ! empty( $current_filter ) ) {
                sort($current_filter);
                $link = add_query_arg( $filter_name, implode( ',', $current_filter ), $link );

                // Add Query type Arg to URL
                // RIGHT FILTER
				//if ( $query_type === 'or' && ! ( 1 === sizeof( $current_filter ) && $option_is_set ) ) {
				//	$link = add_query_arg( 'query_type_' . sanitize_title( str_replace( 'pa_', '', $taxonomy ) ), 'or', $link );
				//}
            }

            echo '<li class="wc-layered-nav-term ' . ( $option_is_set ? 'chosen' : '' ) . '">';

            echo sprintf('<input type="checkbox" %s />', $option_is_set ? 'checked' : '');

            echo ( $count > 0 || $option_is_set ) ? '<a href="' . esc_url( apply_filters( 'woocommerce_layered_nav_link', $link ) ) . '">' : '<span>';

            echo esc_html( $term->name );

            echo ( $count > 0 || $option_is_set ) ? '</a> ' : '</span> ';

            echo apply_filters( 'woocommerce_layered_nav_count', '<span class="count">(' . absint( $count ) . ')</span>', $count, $term );

            echo '</li>';
        }

        echo '</ul>';

        return $found;
    }

    /**
     * Output the html at the start of a widget
     *
     * @param  array $args
     * @return string
     */
    public function widget_start( $args, $instance ) {
        echo $args['before_widget'];

        if ( $title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base ) ) {
            $title = $args['before_title'] . $title . $args['after_title'];
        }

        ?>
        <div class="filter-title" data-target="#filter-<?php echo $instance['attribute']; ?>" data-toggle
             aria-expanded="true">
            <?php echo $title; ?>
        </div>
        <div id="filter-<?php echo $instance['attribute']; ?>" class="filter-list collapse in"
         aria-expanded="true">
        <?php
    }

    /**
     * Output the html at the end of a widget
     *
     * @param  array $args
     * @return string
     */
    public function widget_end( $args ) {
        echo '</div>';
        echo $args['after_widget'];
    }
}
