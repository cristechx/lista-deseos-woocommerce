/**
     * Renderizar contador de lista de deseos
     */
    public function render_wishlist_counter() {
        if (!is_user_logged_in()) {
            return;
        }

        $count = $this->get_wishlist_count();
        ?>
        <span class="ldw-wishlist-counter <?php echo $count > 0 ? '' : 'ldw-hidden'; ?>">
            <?php echo esc_html($count); ?>
        </span>
        <?php
    }

    /**
     * Obtener configuraciones del plugin
     * 
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Actualizar configuraciones del plugin
     * 
     * @param array $new_settings Nuevas configuraciones
     * @return array Configuraciones actualizadas
     */
    public function update_settings($new_settings) {
        $updated_settings = wp_parse_args($new_settings, $this->settings);
        
        // Sanitizar configuraciones
        $updated_settings['enable_wishlist'] = isset($updated_settings['enable_wishlist']) ? 1 : 0;
        $updated_settings['show_in_shop'] = isset($updated_settings['show_in_shop']) ? 1 : 0;
        $updated_settings['show_in_product'] = isset($updated_settings['show_in_product']) ? 1 : 0;
        $updated_settings['multiple_lists'] = isset($updated_settings['multiple_lists']) ? 1 : 0;
        $updated_settings['share_wishlist'] = isset($updated_settings['share_wishlist']) ? 1 : 0;

        // Validar tipo de ícono
        $allowed_icons = ['heart', 'star', 'bookmark'];
        $updated_settings['icon_type'] = in_array($updated_settings['icon_type'], $allowed_icons) 
            ? $updated_settings['icon_type'] 
            : 'heart';

        // Sanitizar color
        $updated_settings['icon_color'] = sanitize_hex_color($updated_settings['icon_color'] ?? '#FF4E4E');

        // Actualizar opciones
        update_option(self::OPTION_NAME, $updated_settings);

        // Actualizar instancia actual
        $this->settings = $updated_settings;

        return $updated_settings;
    }

    /**
     * Restaurar configuraciones predeterminadas
     * 
     * @return array Configuraciones predeterminadas
     */
    public function restore_default_settings() {
        $default_settings = [
            'enable_wishlist' => 1,
            'show_in_shop' => 1,
            'show_in_product' => 1,
            'icon_type' => 'heart',
            'icon_color' => '#FF4E4E',
            'multiple_lists' => 0,
            'share_wishlist' => 0
        ];

        update_option(self::OPTION_NAME, $default_settings);
        $this->settings = $default_settings;

        return $default_settings;
    }
}

// Inicializar el núcleo del plugin
$ldw_core = LDW_Core::get_instance();
<?php
/**
 * Clase Core para gestionar funcionalidades principales del plugin de Lista de Deseos
 */
class LDW_Core {
    // Constantes
    const OPTION_NAME = 'ldw_wishlist_settings';
    const USER_META_KEY = '_ldw_wishlist_items';

    // Instancia singleton
    private static $instance = null;

    // Configuraciones del plugin
    private $settings;

    /**
     * Constructor privado para patrón singleton
     */
    private function __construct() {
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Método singleton para obtener la instancia
     * 
     * @return LDW_Core
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Cargar configuraciones del plugin
     */
    private function load_settings() {
        $this->settings = get_option(self::OPTION_NAME, [
            'enable_wishlist' => 1,
            'show_in_shop' => 1,
            'show_in_product' => 1,
            'icon_type' => 'heart',
            'icon_color' => '#FF4E4E',
            'multiple_lists' => 0,
            'share_wishlist' => 0
        ]);
    }

    /**
     * Inicializar hooks principales
     */
    private function init_hooks() {
        // Hooks de frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Hooks de WooCommerce
        add_action('woocommerce_after_add_to_cart_button', [$this, 'display_wishlist_button']);
        
        // Hooks de AJAX
        add_action('wp_ajax_ldw_update_wishlist', [$this, 'handle_wishlist_ajax']);
        add_action('wp_ajax_nopriv_ldw_update_wishlist', [$this, 'handle_wishlist_ajax']);

        // Hooks adicionales
        add_action('init', [$this, 'register_wishlist_endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_wishlist_menu_item']);
    }

    /**
     * Encolar assets de frontend
     */
    public function enqueue_frontend_assets() {
        // Estilos de lista de deseos
        wp_enqueue_style(
            'ldw-frontend-style', 
            LDW_PLUGIN_URL . 'assets/css/frontend.css', 
            [], 
            LDW_VERSION
        );

        // Script de lista de deseos
        wp_enqueue_script(
            'ldw-frontend-script', 
            LDW_PLUGIN_URL . 'assets/js/frontend.js', 
            ['jquery'], 
            LDW_VERSION, 
            true
        );

        // Localizar script con parámetros
        wp_localize_script(
            'ldw-frontend-script', 
            'ldw_params', 
            $this->get_frontend_localization()
        );
    }

    /**
     * Obtener parámetros de localización para frontend
     * 
     * @return array
     */
    private function get_frontend_localization() {
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ldw_wishlist_nonce'),
            'labels' => [
                'add' => __('Añadir a Lista de Deseos', 'lista-deseos-wc'),
                'remove' => __('Quitar de Lista de Deseos', 'lista-deseos-wc'),
                'confirm_clear' => __('¿Estás seguro de que quieres limpiar tu lista de deseos?', 'lista-deseos-wc')
            ]
        ];
    }

    /**
     * Mostrar botón de lista de deseos en producto
     */
    public function display_wishlist_button() {
        // Solo mostrar si está habilitado y el usuario está logueado
        if (!$this->settings['show_in_product'] || !is_user_logged_in()) {
            return;
        }

        global $product;
        
        if (!$product) {
            return;
        }

        $product_id = $product->get_id();
        $is_in_wishlist = $this->is_in_wishlist($product_id);

        // Renderizar botón de lista de deseos
        ?>
        <div class="ldw-wishlist-button-wrapper">
            <button 
                type="button" 
                class="ldw-wishlist-button <?php echo $is_in_wishlist ? 'ldw-in-wishlist' : ''; ?>"
                data-product-id="<?php echo esc_attr($product_id); ?>"
                aria-label="<?php echo $is_in_wishlist 
                    ? esc_attr__('Quitar de Lista de Deseos', 'lista-deseos-wc') 
                    : esc_attr__('Añadir a Lista de Deseos', 'lista-deseos-wc'); ?>"
            >
                <span class="ldw-button-icon">
                    <?php $this->render_wishlist_icon($is_in_wishlist); ?>
                </span>
                <span class="ldw-button-text">
                    <?php echo $is_in_wishlist 
                        ? esc_html__('Quitar de Lista de Deseos', 'lista-deseos-wc') 
                        : esc_html__('Añadir a Lista de Deseos', 'lista-deseos-wc'); ?>
                </span>
                <span class="ldw-spinner ldw-hidden">
                    <img src="<?php echo esc_url(LDW_PLUGIN_URL . 'assets/images/spinner.gif'); ?>" alt="Cargando...">
                </span>
            </button>
            <div class="ldw-wishlist-message" style="display:none;"></div>
        </div>
        <?php
    }

    /**
     * Renderizar ícono de lista de deseos
     * 
     * @param bool $is_in_wishlist Estado del producto en la lista de deseos
     */
    private function render_wishlist_icon($is_in_wishlist = false) {
        $icon_type = $this->settings['icon_type'] ?? 'heart';
        $icon_color = $this->settings['icon_color'] ?? '#FF4E4E';

        switch ($icon_type) {
            case 'star':
                ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="<?php echo $is_in_wishlist ? esc_attr($icon_color) : 'none'; ?>" stroke="<?php echo esc_attr($icon_color); ?>">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                </svg>
                <?php
                break;
            case 'bookmark':
                ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="<?php echo $is_in_wishlist ? esc_attr($icon_color) : 'none'; ?>" stroke="<?php echo esc_attr($icon_color); ?>">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                </svg>
                <?php
                break;
            default: // heart
                ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="<?php echo $is_in_wishlist ? esc_attr($icon_color) : 'none'; ?>" stroke="<?php echo esc_attr($icon_color); ?>" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                </svg>
                <?php
        }
    }

    /**
     * Manejar solicitud AJAX de lista de deseos
     */
    public function handle_wishlist_ajax() {
        // Verificar nonce
        check_ajax_referer('ldw_wishlist_nonce', 'nonce');

        // Verificar si el usuario está logueado
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('Debes iniciar sesión para usar la lista de deseos.', 'lista-deseos-wc')
            ]);
        }

        // Obtener datos de la solicitud
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $action = isset($_POST['wishlist_action']) ? sanitize_text_field($_POST['wishlist_action']) : '';

        // Validar datos
        if (!$product_id || !in_array($action, ['add', 'remove'])) {
            wp_send_json_error([
                'message' => __('Datos inválidos.', 'lista-deseos-wc')
            ]);
        }

        // Verificar si el producto existe
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error([
                'message' => __('Producto no encontrado.', 'lista-deseos-wc')
            ]);
        }

        // Procesar acción
        $result = $action === 'add' 
            ? $this->add_to_wishlist($product_id) 
            : $this->remove_from_wishlist($product_id);

        // Enviar respuesta
        if ($result) {
            wp_send_json_success([
                'message' => $action === 'add' 
                    ? __('Producto añadido a tu lista de deseos.', 'lista-deseos-wc')
                    : __('Producto eliminado de tu lista de deseos.', 'lista-deseos-wc'),
                'wishlist_count' => $this->get_wishlist_count()
            ]);
        } else {
            wp_send_json_error([
                'message' => __('No se pudo procesar la solicitud.', 'lista-deseos-wc')
            ]);
        }
    }

    /**
     * Añadir producto a la lista de deseos
     * 
     * @param int $product_id ID del producto
     * @return bool
     */
    private function add_to_wishlist($product_id) {
        $user_id = get_current_user_id();
        
        // Obtener lista de deseos actual
        $wishlist = get_user_meta($user_id, self::USER_META_KEY, true);
        $wishlist = is_array($wishlist) ? $wishlist : [];

        // Verificar si ya está en la lista
        if (!in_array($product_id, $wishlist)) {
            $wishlist[] = $product_id;
            update_user_meta($user_id, self::USER_META_KEY, $wishlist);
            
            // Guardar fecha de adición
            update_user_meta(
                $user_id, 
                "_ldw_wishlist_added_{$product_id}", 
                current_time('mysql')
            );

            return true;
        }

        return false;
    }

    /**
     * Eliminar producto de la lista de deseos
     * 
     * @param int $product_id ID del producto
     * @return bool
     */
    private function remove_from_wishlist($product_id) {
        $user_id = get_current_user_id();
        
        // Obtener lista de deseos actual
        $wishlist = get_user_meta($user_id, self::USER_META_KEY, true);
        $wishlist = is_array($wishlist) ? $wishlist : [];

        // Buscar y eliminar el producto
        $key = array_search($product_id, $wishlist);
        if ($key !== false) {
            unset($wishlist[$key]);
            $wishlist = array_values($wishlist); // Reindexar
            
            update_user_meta($user_id, self::USER_META_KEY, $wishlist);
            
            // Eliminar metadato de fecha
            delete_user_meta($user_id, "_ldw_wishlist_added_{$product_id}");

            return true;
        }

        return false;
    }

    /**
     * Verificar si un producto está en la lista de deseos
     * 
     * @param int $product_id ID del producto
     * @return bool
     */
    private function is_in_wishlist($product_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $wishlist = get_user_meta($user_id, self::USER_META_KEY, true);
        
        return is_array($wishlist) && in_array($product_id, $wishlist);
    }

    /**
     * Obtener número de elementos en la lista de deseos
     * 
     * @return int
     */
    private function get_wishlist_count() {
        if (!is_user_logged_in()) {
            return 0;
        }

        $user_id = get_current_user_id();
        $wishlist = get_user_meta($user_id, self::USER_META_KEY, true);
        
        return is_array($wishlist) ? count($wishlist) : 0;
    }

    /**
     * Registrar endpoint de lista de deseos
     */
    public function register_wishlist_endpoint() {
        add_rewrite_endpoint('wishlist', EP_ROOT | EP_PAGES);
    }

    /**
     * Añadir elemento de lista de deseos al menú de mi cuenta
     * 
     * @param array $items Elementos del menú
     * @return array
     */
    public function add_wishlist_menu_item($items) {
        // Solo añadir si está habilitado y el usuario está logueado
        if (!is_user_logged_in()) {
            return $items;
        }

        $items['wishlist'] = __('Lista de Deseos', 'lista-deseos-wc');
        return $items;
    }

    /**
     * Renderizar contador de lista de deseos
     */
    public function render_wishlist_counter() {
        if (!is_user_logged_in()) {
            return;
        }

        $count = $this