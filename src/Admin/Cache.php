<?php

namespace Brisum\Wordpress\Woocommerce\Widget\LayeredNav\Admin;

class Cache {
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addSubMenuPages'], 100);
    }

    public function addSubMenuPages()
    {
        add_submenu_page(
            'woocommerce',
            __('Layered Navigation Cache', 'woocommerce' ),
            __('Layered Navigation Cache', 'woocommerce' ) ,
            'manage_woocommerce',
            'brisum-wc-laynav-cache',
            [$this, 'page']
        );
    }

    public function page()
    {
        if (!current_user_can('manage_woocommerce')) {
            die('deny access');
        }

        if (isset($_POST['laynav_is_clear_cache'])) {
            global $wpdb;
            $affected = $wpdb->query("delete from {$wpdb->options} where option_name like '%wc_uf_pid_%'");
            $affected += $wpdb->query("delete from {$wpdb->options} where option_name like '%wc_ln_count_%'");
        }

        ?>
            <div class="wrap">
                <h2><?php echo __('Layered Navigation Cache', 'woocommerce' ); ?></h2>
                <br />

                <?php if(isset($_POST['laynav_is_clear_cache'])) : ?>
                    <div class="notice notice-success">
                        Удалено <?php echo $affected; ?> записей;
                    </div>
                <?php endif; ?>

                <form action="" method="post">
                    <input type="submit" name="laynav_is_clear_cache" value="Очистить кеш">
                </form>
            </div>

        <?php
    }
}

