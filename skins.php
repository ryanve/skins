<?php 
/*
Plugin Name: Skins
Plugin URI: http://github.com/ryanve/skins
Description: Add custom CSS classes to your markup for usage in your CSS.
Version: 0.x
Author: Ryan Van Etten
Author URI: http://ryanve.com
License: MIT
*/

call_user_func(function() {

    $plugin = array('name' => 'Skins');
    $plugin['option'] = 'plugin:skins';
    $plugin['prefix'] = 'plugin:skins:';
    $plugin['get'] = function() use ($plugin) {
        return (array) get_option($plugin['option']);
    };
    
    $plugin['set'] = function($data) use ($plugin) {
        return (null === $data 
            ? delete_option($plugin['option'])
            : update_option($plugin['option'], $data)
        ) ? $data : false;
    };

    $plugin['hooks'] = array_unique(apply_filters($plugin['prefix'] . 'hooks'
      , array('body_class', 'post_class', 'comment_class')
    ));

    is_admin() ? add_action('admin_menu', function() use (&$plugin) {
    
        register_deactivation_hook(__FILE__, function() use ($plugin) {
            $plugin['set'](null); # removes all data
        });
        
        $section = 'default';
        $page = (array) apply_filters($plugin['prefix'] . 'page', array(
            'capability' => 'manage_options'
          , 'name' => $plugin['name']
          , 'slug' => basename(__FILE__)
          , 'add' => 'add_theme_page'
          , 'parent' => 'themes.php'
        ));

        empty($page['fn']) and $page['fn'] = function() use ($plugin, $page, $section) {
            echo '<div class="wrap">';
            function_exists('screen_icon') and screen_icon();
            echo '<h2>' . $plugin['name'] . '</h2>';
            echo '<p>' . __('List space-separated CSS classes for usage in your CSS:') . '</p>';
            echo '<form method="post" action="options.php">';
            settings_fields($page['slug']);
            do_settings_fields($page['slug'], $section);
            submit_button(__('Update'));
            echo '</form></div>';
        };
        
        # Create "Settings" link to appear on /wp-admin/plugins.php
        add_filter('plugin_action_links_' . basename(__FILE__), function($links) use ($page) {
            $href = $page['parent'] ? $page['parent'] . '?page=' . $page['slug'] : $page['slug'];
            array_unshift($links, '<a href="' . admin_url($href) . '">' . __('Settings') . '</a>');
            return $links;
        }, 10, 2);
        
        # add_theme_page or add_options_page
        call_user_func_array($page['add'], array(
            $page['name']
          , $page['name']
          , $page['capability']
          , $page['slug']
          , $page['fn']
        ));
        
        $curr = $plugin['get']();
        foreach ($plugin['hooks'] as $hook) {
            register_setting($page['slug'], $hook, function($str) use (&$curr, $hook, $plugin) {
                $curr[$hook] = $str ? esc_attr($str) : '';
                $plugin['set']($curr); # It'd be better to update only once for all, but where?
                return $curr[$hook];
            });

            add_settings_field($hook, "<code>'$hook'</code> classes:", function() use ($curr, $hook) {
                $value = \is_string($value = !isset($curr[$hook]) ?: $curr[$hook]) ? \trim($value) : '';
                $style = 'max-width:100%;min-width:20%';
                $place = 'layout-example color-example';
                echo "<div><textarea style='$style' placeholder='$place' name='$hook'>$value</textarea></div>";
            }, $page['slug'], $section); 
        }
    
    }) : array_reduce($plugin['hooks'], function($curr, $hook) {
            $hook and add_filter($hook, function($arr) use ($curr, $hook) {
                return isset($curr[$hook]) ? array_unique(array_filter(array_merge($arr,
                    is_string($curr[$hook]) ? preg_split('#\s+#', $curr[$hook]) : (array) $curr[$hook]
                ), 'strlen')) : $arr;
            });
            return $curr;
    }, $plugin['get']());
});