<?php

use Brisum\Lib\ObjectManager;

add_action( 'widgets_init', 'brisum_woocommerce_layered_nav_widgets_init' );
function brisum_woocommerce_layered_nav_widgets_init() {
    register_widget( 'Brisum\Wordpress\Woocommerce\Widget\LayeredNav\LayeredNav2' );
}

add_filter('woocommerce_is_layered_nav_active', 'brisum_woocommerce_layered_nav_active');
function brisum_woocommerce_layered_nav_active($idBase)
{
    return is_active_widget(false, false, 'brisum_woocommerce_layered_nav', true);
}

add_filter('woocommerce_layered_nav_default_query_type', 'brisum_woocommerce_layered_nav_default_query_type');
function brisum_woocommerce_layered_nav_default_query_type()
{
    return 'or';
}

//add_filter('loop_shop_post_in', 'brisum_woocommerce_layered_nav_query', 0);
//function brisum_woocommerce_layered_nav_query()
//{
//    remove_filter('loop_shop_post_in', [WC()->query, 'layered_nav_query']);
//}
//$objectManager = ObjectManager::getInstance();
//$wcQuery = $objectManager->create('Brisum\Wordpress\Woocommerce\Widget\LayeredNav\WcQuery');
//add_filter('loop_shop_post_in', array($wcQuery, 'layered_nav_query'));
//

ObjectManager::getInstance()->create('\Brisum\Wordpress\Woocommerce\Widget\LayeredNav\Admin\Cache');

