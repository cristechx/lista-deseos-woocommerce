lista-deseos-wc-es_ES.po/**
     * Método singleton para obtener la instancia
     * 
     * @return LDW_Frontend
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Hooks para tienda
        if (!empty($this->settings['show_in_shop'])) {
            add_action('woocommerce_after_shop_loop_item', [$this, 'display_wishlist_button_shop'], 15);
        }

        // Hooks para página de producto
        if (!empty($this->settings['show_in_product'])) {
            add_action('woocommerce_after_add_to_cart_button', [$this, 'display_wishlist_button_product'], 15);
        }

        // Añadir endpoint de lista de deseos
        add_action('init', [$this, 'add_wishlist_endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_wishlist_menu_item']);
        add_action('woocommerce_account_wishlist_endpoint', [$this, 'wishlist_endpoint_content']);

        // Shortcodes
        add_shortcode('ldw_wishlist_button', [$this, 'wishlist_button_shortcode']);
        add_shortcode('ldw_wishlist_page', [$this, 'wishlist_page_shortcode']);
    }

    /**
     * Mostrar botón de lista de deseos en tienda
     */
    public function display_wishlist_button_shop() {
        global $product;

        // Solo para usuarios logueados
        if (!is_user_logged_in() || !$product) {
            return;
        }

        $product_id = $product->get_id();
        $is_in_wishlist = $this->is_product_in_wishlist($product_id);

        $this->render_wishlist_button($product_id, $is_in_wishlist, 'shop');
    }

    /**
     * Mostrar botón de lista de deseos en página de producto
     */
    public function display_wishlist_button_product() {
        global $product;

        // Solo para usuarios logueados
        if (!is_user_logged_in() || !$product) {
            return;
        }

        $product_id = $product->get_id();
        $is_in_wishlist = $this->is_product_in_wishlist($product_id);

        $this->render_wishlist_button($product_id, $is_in_wishlist, 'product');
    }

    /**
     * Renderizar botón de lista de deseos
     * 
     * @param int $product_id ID del producto
     * @param bool $is_in_wishlist Si el producto está en la lista de deseos
     * @param string $context Contexto de visualización (shop o product)
     */
    private function render_wishlist_button($product_id, $is_in_wishlist, $context = 'shop') {
        $button_classes = [
            'ldw-wishlist-button',
            "ldw-wishlist-button-{$context}",
            $is_in_wishlist ? 'ldw-in-wishlist' : ''
        ];

        $button_text = $is_in_wishlist 
            ? __('Quitar de Lista de Deseos', 'lista-deseos-wc') 
            : __('Añadir a Lista de Deseos', 'lista-deseos-wc');

        $nonce = wp_create_nonce('ldw_wishlist_nonce');

        ?>
        <div class="ldw-wishlist-button-wrapper">
            <button 
                type="button" 
                class="<?php echo esc_attr(implode(' ', $button_classes)); ?>"
                data-product-id="<?php echo esc_attr($product_id); ?>"
                data-nonce="<?php echo esc_attr($nonce); ?>"
                aria-label="<?php echo esc_attr($button_text); ?>"
            >
                <span class="ldw-button-icon">
                    <?php $this->render_wishlist_icon($is_in_wishlist); ?>
                </span>
                <span class="ldw-button-text">
                    <?php echo esc_html($button_text); ?>
                </span>
            </button>
            <div class="ldw-wishlist-message"></div>
        </div>
        <?php
    }

    /**
     * Renderizar ícono de lista de deseos
     * 
     * @param bool $is_in_wishlist Si el producto está en la lista de deseos
     */
    private function render_wishlist_icon($is_in_wishlist = false) {
        $icon_type = $this->customization['icon_type'] ?? 'heart';
        $icon_color = $this->customization['icon_color'] ?? '#FF4E4E';

        switch ($icon_type) {
            case 'star':
                ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" 
                     fill="<?php echo $is_in_wishlist ? esc_attr($icon_color) : 'none'; ?>" 
                     stroke="<?php echo esc_attr($icon_color); ?>">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                </svg>
                <?php
                break;
            case 'bookmark':
                ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" 
                     fill="<?php echo $is_in_wishlist ? esc_attr($icon_color) : 'none'; ?>" 
                     stroke="<?php echo esc_attr($icon_color); ?>">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                </svg>
                <?php
                break;
            default: // heart
                ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" 
                     fill="<?php echo $is_in_wishlist ? esc_attr($icon_color) : 'none'; ?>" 
                     stroke="<?php echo esc_attr($icon_color); ?>" 
                     stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                </svg>
                <?php
        }
    }

    /**
     * Verificar si un producto está en la lista de deseos
     * 
     * @param int $product_id ID del producto
     * @return bool
     */
    private function is_product_in_wishlist($product_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $wishlist = get_user_meta($user_id, '_ldw_wishlist_items', true);
        
        return is_array($wishlist) && in_array($product_id, $wishlist);
    }

    /**
     * Añadir endpoint de lista de deseos
     */
    public function add_wishlist_endpoint() {
        add_rewrite_endpoint('wishlist', EP_ROOT | EP_PAGES);
    }

    /**
     * Añadir elemento de menú de lista de deseos
     * 
     * @param array $items Elementos del menú
     * @return array
     */
    public function add_wishlist_menu_item($items) {
        $items['wishlist'] = __('Lista de Deseos', 'lista-deseos-wc');
        return $items;
    }

    /**
     * Contenido del endpoint de lista de deseos
     */
    public function wishlist_endpoint_content() {
        // Verificar si el usuario está logueado
        if (!is_user_logged_in()) {
            wc_print_notice(__('Debes iniciar sesión para ver tu lista de deseos.', 'lista-deseos-wc'), 'error');
            return;
        }

        // Obtener lista de deseos
        $user_id = get_current_user_id();
        $wishlist_items = get_user_meta($user_id, '_ldw_wishlist_items', true);
        $wishlist_items = is_array($wishlist_items) ? $wishlist_items : [];

        // Renderizar plantilla de lista de deseos
        wc_get_template('wishlist/wishlist.php', [
            'wishlist_items' => $this->get_wishlist_products($wishlist_items),
            'wishlist_count' => count($wishlist_items)
        ], 'lista-deseos-wc/', LDW_PLUGIN_DIR . 'templates/');
    }

    /**
     * Obtener detalles de productos en la lista de deseos
     * 
     * @param array $product_ids IDs de productos
     * @return array
     */
    private function get_wishlist_products($product_ids) {
        $wishlist_products = [];

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            
            if (!$product) {
                continue;
            }

            $wishlist_products[] = [
                'id' => $product_id,
                'name' => $product->get_name(),
                'price' => $product->get_price_html(),
                'image' => wp_get_attachment_image_src($product->get_image_id(), 'woocommerce_thumbnail')[0],
                'url' => $product->get_permalink(),
                'add_to_cart_url' => $product->add_to_cart_url(),
                'add_to_cart_text' => $product->add_to_cart_text(),
                'is_purchasable' => $product->is_purchasable(),
                'is_in_stock' => $product->is_in_stock(),
                'added_date' => get_user_meta(
                    get_current_user_id(), 
                    "_ldw_wishlist_added_{$product_id}", 
                    true
                )
            ];
        }

        return $wishlist_products;
    }

    /**
     * Shortcode para botón de lista de deseos
     * 
     * @param array $atts Atributos del shortcode
     * @return string
     */
    public function wishlist_button_shortcode($atts) {
        // Parámetros predeterminados
        $atts = shortcode_atts([
            'product_id' => get_the_ID(),
            'context' => 'shortcode'
        ], $atts);

        // Verificar si hay ID de producto válido
        $product_id = intval($atts['product_id']);
        if (!$product_id) {
            return '';
        }

        // Verificar si usuario está logueado
        if (!is_user_logged_in()) {
            return '';
        }

        // Verificar si el producto existe
        $product = wc_get_product($product_id);
        if (!$product) {
            return '';
        }

        // Capturar salida del botón
        ob_start();
        $is_in_wishlist = $this->is_product_in_wishlist($product_id);
        $this->render_wishlist_button($product_id, $is_in_wishlist, $atts['context']);
        return ob_get_clean();
    }

    /**
     * Shortcode para página de lista de deseos
     * 
     * @return string
     */
    public function wishlist_page_shortcode() {
        // Capturar salida
        ob_start();
        $this->wishlist_endpoint_content();
        return ob_get_clean();
    }
}

// Inicializar frontend
$ldw_frontend = LDW_Frontend::get_instance();
<?php
/**
 * Clase Frontend para gestionar aspectos de interfaz de la lista de deseos
 */
class LDW_Frontend {
    // Instancia singleton
    private static $instance = null;

    // Configuraciones
    private $settings;
    private $customization;

    /**
     * Constructor privado para patrón singleton
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        $this->settings = get_option('ldw_wishlist_settings', [
            'enable_wishlist' => 1,
            'show_in_shop' => 1,
            'show_in_product' => 1
        ]);

        $this->customization = get_option('ldw_customization_settings', [
            'icon_type' => 'heart',
            'button_style' => 'default'
        ]);
    }

    /**
     * Método singleton para obtener la instancia
     * 
     * @return LDW_Frontend
     */