<?php
/**
 * Plugin Name:       Lista Deseos WooCommerce
 * Plugin URI:        https://minitownbay.com/
 * Description:       Plugin de lista de deseos personalizable para WooCommerce
 * Version:           1.3.0
 * Author:            Cristch-X
 * Author URI:        https://minitownbay.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lista-deseos-wc
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * WC requires at least: 5.0
 * WC tested up to:   8.9
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
defined('LDW_VERSION') || define('LDW_VERSION', '1.2.0');
defined('LDW_PLUGIN_DIR') || define('LDW_PLUGIN_DIR', plugin_dir_path(__FILE__));
defined('LDW_PLUGIN_URL') || define('LDW_PLUGIN_URL', plugin_dir_url(__FILE__));
defined('LDW_PLUGIN_BASENAME') || define('LDW_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Incluir archivos necesarios
require_once LDW_PLUGIN_DIR . 'includes/class-ldw-core.php';
require_once LDW_PLUGIN_DIR . 'includes/class-ldw-frontend.php';
require_once LDW_PLUGIN_DIR . 'includes/class-ldw-admin.php';

/**
 * Función de activación del plugin
 */
function ldw_activate_plugin() {
    // Verificar dependencias
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Este plugin requiere WooCommerce para funcionar. Por favor, instala y activa WooCommerce.', 'lista-deseos-wc'),
            'Requisito de Plugin',
            ['back_link' => true]
        );
    }

    // Crear tablas personalizadas si es necesario
    ldw_create_database_tables();

    // Configuraciones iniciales
    ldw_set_default_settings();
}
register_activation_hook(__FILE__, 'ldw_activate_plugin');

/**
 * Función de desactivación del plugin
 */
function ldw_deactivate_plugin() {
    // Limpiar caché
    delete_option('ldw_plugin_version');

    // Opcional: Eliminar tablas personalizadas
    // global $wpdb;
    // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ldw_wishlist");

    // Opcional: Eliminar opciones del plugin
    // delete_option('ldw_wishlist_settings');
}
register_deactivation_hook(__FILE__, 'ldw_deactivate_plugin');

/**
 * Crear tablas personalizadas en la base de datos
 */
function ldw_create_database_tables() {
    global $wpdb;

    // Definir conjunto de caracteres
    $charset_collate = $wpdb->get_charset_collate();

    // SQL para crear tabla de listas de deseos extendida
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ldw_wishlist (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        product_id bigint(20) NOT NULL,
        date_added datetime DEFAULT CURRENT_TIMESTAMP,
        list_name varchar(100) DEFAULT 'default',
        notes text,
        PRIMARY KEY  (id),
        UNIQUE KEY user_product (user_id, product_id)
    ) {$charset_collate};";

    // Requerir bibliotecas de WordPress para dbDelta
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Ejecutar creación de tabla
    dbDelta($sql);
}

/**
 * Establecer configuraciones predeterminadas
 */
function ldw_set_default_settings() {
    $default_settings = [
        'version' => LDW_VERSION,
        'enable_wishlist' => 1,
        'show_in_shop' => 1,
        'show_in_product' => 1,
        'icon_type' => 'heart',
        'icon_color' => '#FF4E4E',
        'multiple_lists' => 0,
        'share_wishlist' => 0
    ];

    // Añadir opciones solo si no existen
    if (false === get_option('ldw_wishlist_settings')) {
        add_option('ldw_wishlist_settings', $default_settings);
    }
}

// Inicializar el plugin
function ldw_init_plugin() {
    // Cargar traduciones
    load_plugin_textdomain('lista-deseos-wc', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    // Inicializar clases principales
    LDW_Core::get_instance();
}
add_action('plugins_loaded', 'ldw_init_plugin');