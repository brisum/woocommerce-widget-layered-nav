<?php

namespace Brisum\Wordpress\Woocommerce\Widget\LayeredNav;

class WcQuery
{
    // copy method from WC_Query
    public function layered_nav_query( $filtered_posts ) {
        global $_chosen_attributes;

        if ( sizeof( $_chosen_attributes ) > 0 ) {

            $matched_products   = array(
                'and' => array(),
                'or'  => array()
            );
            $filtered_attribute = array(
                'and' => false,
                'or'  => false
            );

            foreach ( $_chosen_attributes as $attribute => $data ) {
                $matched_products_from_attribute = array();
                $filtered = false;

                if ( sizeof( $data['terms'] ) > 0 ) {
                    foreach ( $data['terms'] as $value ) {

                        $posts = get_posts(
                            array(
                                'post_type' 	=> 'product',
                                'numberposts' 	=> -1,
                                'post_status' 	=> 'publish',
                                'fields' 		=> 'ids',
                                'no_found_rows' => true,
                                'tax_query' => array(
                                    array(
                                        'taxonomy' 	=> $attribute,
                                        'terms' 	=> $value,
                                        'field' 	=> 'term_id'
                                    )
                                )
                            )
                        );

                        if ( ! is_wp_error( $posts ) ) {

                            if ( sizeof( $matched_products_from_attribute ) > 0 || $filtered ) {
                                $matched_products_from_attribute = $data['query_type'] == 'or' ? array_merge( $posts, $matched_products_from_attribute ) : array_intersect( $posts, $matched_products_from_attribute );
                            } else {
                                $matched_products_from_attribute = $posts;
                            }

                            $filtered = true;
                        }
                    }
                }

                if ( sizeof( $matched_products[ $data['query_type'] ] ) > 0 || $filtered_attribute[ $data['query_type'] ] === true ) {
                    /* BEGIN RIGHT FILTER */
                    // $matched_products[ $data['query_type'] ] = ( $data['query_type'] == 'or' ) ? array_merge( $matched_products_from_attribute, $matched_products[ $data['query_type'] ] ) : array_intersect( $matched_products_from_attribute, $matched_products[ $data['query_type'] ] );
                    $matched_products[ $data['query_type'] ] = array_intersect( $matched_products_from_attribute, $matched_products[ $data['query_type'] ] );
                    /* END RIGHT FILTER */
                } else {
                    $matched_products[ $data['query_type'] ] = $matched_products_from_attribute;
                }

                $filtered_attribute[ $data['query_type'] ] = true;

                WC()->query->filtered_product_ids_for_taxonomy[ $attribute ] = $matched_products_from_attribute;
            }

            // Combine our AND and OR result sets
            if ( $filtered_attribute['and'] && $filtered_attribute['or'] )
                $results = array_intersect( $matched_products[ 'and' ], $matched_products[ 'or' ] );
            else
                $results = array_merge( $matched_products[ 'and' ], $matched_products[ 'or' ] );

            if ( $filtered ) {

                WC()->query->layered_nav_post__in   = $results;

                if ( sizeof( $filtered_posts ) == 0 ) {
                    $filtered_posts   = $results;
                    $filtered_posts[] = 0;
                } else {
                    $filtered_posts   = array_intersect( $filtered_posts, $results );
                    $filtered_posts[] = 0;
                }

            }
        }
        return (array) $filtered_posts;
    }
}