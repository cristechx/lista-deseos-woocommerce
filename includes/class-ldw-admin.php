/**
     * Restaurar configuraciones predeterminadas mediante AJAX
     */
    public function ajax_restore_default_settings() {
        // Verificar nonce
        check_ajax_referer('ldw_admin_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Acceso denegado', 'lista-deseos-wc'));
        }

        // Restaurar configuraciones
        $default_settings = $this->core->restore_default_settings();

        wp_send_json_success([
            'message' => __('Configuraciones restauradas correctamente', 'lista-deseos-wc'),
            'settings' => $default_settings
        ]);
    }

    /**
     * Exportar lista de deseos mediante AJAX
     */
    public function ajax_export_wishlist() {
        // Verificar nonce
        check_ajax_referer('ldw_admin_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Acceso denegado', 'lista-deseos-wc'));
        }

        // Obtener datos de lista de deseos
        $wishlist_data = $this->get_all_wishlist_data();

        // Generar archivo de exportación
        $export_data = [
            'version' => '1.0.0',
            'export_date' => current_time('mysql'),
            'wishlist_items' => $wishlist_data
        ];

        // Enviar respuesta con datos de exportación
        wp_send_json_success([
            'message' => __('Datos de lista de deseos exportados correctamente', 'lista-deseos-wc'),
            'export_data' => $export_data
        ]);
    }

    /**
     * Importar lista de deseos mediante AJAX
     */
    public function ajax_import_wishlist() {
        // Verificar nonce
        check_ajax_referer('ldw_admin_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Acceso denegado', 'lista-deseos-wc'));
        }

        // Obtener datos de importación
        $import_data = isset($_POST['import_data']) ? json_decode(wp_unslash($_POST['import_data']), true) : null;

        // Validar datos
        if (!$import_data || !isset($import_data['wishlist_items'])) {
            wp_send_json_error(__('Datos de importación inválidos', 'lista-deseos-wc'));
        }

        // Procesar importación
        $import_result = $this->process_wishlist_import($import_data['wishlist_items']);

        wp_send_json_success([
            'message' => sprintf(
                __('Importación completada. %d elementos añadidos, %d elementos omitidos.', 'lista-deseos-wc'),
                $import_result['added'],
                $import_result['skipped']
            ),
            'details' => $import_result
        ]);
    }

    /**
     * Obtener todos los datos de lista de deseos
     * 
     * @return array
     */
    private function get_all_wishlist_data() {
        global $wpdb;

        // Consulta para obtener todas las listas de deseos
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_value 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = %s",
                LDW_Core::USER_META_KEY
            ),
            ARRAY_A
        );

        $wishlist_data = [];

        foreach ($results as $wishlist) {
            $user_id = $wishlist['user_id'];
            $product_ids = maybe_unserialize($wishlist['meta_value']);

            if (!is_array($product_ids)) {
                continue;
            }

            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    continue;
                }

                $wishlist_data[] = [
                    'user_id' => $user_id,
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'added_date' => get_user_meta(
                        $user_id, 
                        "_ldw_wishlist_added_{$product_id}", 
                        true
                    ) ?: current_time('mysql')
                ];
            }
        }

        return $wishlist_data;
    }

    /**
     * Procesar importación de lista de deseos
     * 
     * @param array $import_items Elementos a importar
     * @return array Resultado de la importación
     */
    private function process_wishlist_import($import_items) {
        $added = 0;
        $skipped = 0;

        foreach ($import_items as $item) {
            // Validar datos de elemento
            if (!isset($item['user_id'], $item['product_id'])) {
                $skipped++;
                continue;
            }

            // Verificar si el producto existe
            $product = wc_get_product($item['product_id']);
            if (!$product) {
                $skipped++;
                continue;
            }

            // Verificar si el usuario existe
            $user = get_user_by('ID', $item['user_id']);
            if (!$user) {
                $skipped++;
                continue;
            }

            // Obtener lista de deseos actual
            $wishlist = get_user_meta($item['user_id'], LDW_Core::USER_META_KEY, true);
            $wishlist = is_array($wishlist) ? $wishlist : [];

            // Añadir producto si no está en la lista
            if (!in_array($item['product_id'], $wishlist)) {
                $wishlist[] = $item['product_id'];
                update_user_meta($item['user_id'], LDW_Core::USER_META_KEY, $wishlist);
                
                // Guardar fecha de adición
                update_user_meta(
                    $item['user_id'], 
                    "_ldw_wishlist_added_{$item['product_id']}", 
                    $item['added_date'] ?? current_time('mysql')
                );

                $added++;
            } else {
                $skipped++;
            }
        }

        return [
            'added' => $added,
            'skipped' => $skipped
        ];
    }

    /**
     * Obtener estadísticas de lista de deseos
     * 
     * @return array
     */
    private function get_wishlist_stats() {
        global $wpdb;

        // Consulta para obtener productos más añadidos a lista de deseos
        $most_wished = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.post_id as product_id, 
                        p.post_title as product_name, 
                        COUNT(DISTINCT um.user_id) as wishlist_count
                FROM {$wpdb->usermeta} um
                JOIN {$wpdb->postmeta} pm ON FIND_IN_SET(pm.post_id, um.meta_value)
                JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE um.meta_key = %s
                AND p.post_type = 'product'
                GROUP BY pm.post_id
                ORDER BY wishlist_count DESC
                LIMIT 10",
                LDW_Core::USER_META_KEY
            ),
            ARRAY_A
        );

        // Contar usuarios con lista de deseos
        $users_with_wishlist = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = %s 
                AND meta_value != ''",
                LDW_Core::USER_META_KEY
            )
        );

        // Total de elementos en listas de deseos
        $total_wishlist_items = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(
                    (LENGTH(meta_value) - LENGTH(REPLACE(meta_value, ',', ''))) + 1
                ) 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = %s",
                LDW_Core::USER_META_KEY
            )
        );

        return [
            'users_with_wishlist' => intval($users_with_wishlist),
            'total_wishlist_items' => intval($total_wishlist_items),
            'most_wished_products' => $most_wished
        ];
    }

    /**
     * Renderizar página de estadísticas
     */
    public function render_stats_page() {
        // Obtener estadísticas
        $stats = $this->get_wishlist_stats();
        ?>
        <div class="wrap ldw-admin-wrapper">
            <h1><?php _e('Estadísticas de Lista de Deseos', 'lista-deseos-wc'); ?></h1>
            
            <div class="ldw-stats-grid">
                <div class="ldw-stat-card">
                    <h3><?php _e('Usuarios con Lista de Deseos', 'lista-deseos-wc'); ?></h3>
                    <div class="ldw-stat-number">
                        <?php echo esc_html($stats['users_with_wishlist']); ?>
                    </div>
                </div>

                <div class="ldw-stat-card">
                    <h3><?php _e('Total de Elementos en Listas', 'lista-deseos-wc'); ?></h3>
                    <div class="ldw-stat-number">
                        <?php echo esc_html($stats['total_wishlist_items']); ?>
                    </div>
                </div>

                <div class="ldw-stat-card">
                    <h3><?php _e('Productos Más Deseados', 'lista-deseos-wc'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Producto', 'lista-deseos-wc'); ?></th>
                                <th><?php _e('Veces en Lista', 'lista-deseos-wc'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['most_wished_products'] as $product): ?>
                                <tr>
                                    <td><?php echo esc_html($product['product_name']); ?></td>
                                    <td><?php echo esc_html($product['wishlist_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}

// Inicializar la clase de administración
$ldw_admin = LDW_Admin::get_instance();
<?php
/**
 * Clase de Administración para gestionar la configuración del plugin
 */
class LDW_Admin {
    // Constantes
    const MENU_SLUG = 'lista-deseos-settings';
    const OPTION_NAME = 'ldw_wishlist_settings';

    // Instancia singleton
    private static $instance = null;

    // Núcleo del plugin
    private $core;

    /**
     * Constructor privado para patrón singleton
     */
    private function __construct() {
        $this->core = LDW_Core::get_instance();
        $this->init_hooks();
    }

    /**
     * Método singleton para obtener la instancia
     * 
     * @return LDW_Admin
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks de administración
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Hooks de AJAX para administración
        add_action('wp_ajax_ldw_restore_default_settings', [$this, 'ajax_restore_default_settings']);
        add_action('wp_ajax_ldw_export_wishlist', [$this, 'ajax_export_wishlist']);
        add_action('wp_ajax_ldw_import_wishlist', [$this, 'ajax_import_wishlist']);
    }

    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        // Menú principal
        add_menu_page(
            __('Lista de Deseos', 'lista-deseos-wc'),
            __('Lista de Deseos', 'lista-deseos-wc'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_settings_page'],
            'dashicons-heart',
            56
        );

        // Submenús
        add_submenu_page(
            self::MENU_SLUG,
            __('Configuración', 'lista-deseos-wc'),
            __('Configuración', 'lista-deseos-wc'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Personalización', 'lista-deseos-wc'),
            __('Personalización', 'lista-deseos-wc'),
            'manage_options',
            'lista-deseos-customize',
            [$this, 'render_customization_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Estadísticas', 'lista-deseos-wc'),
            __('Estadísticas', 'lista-deseos-wc'),
            'manage_options',
            'lista-deseos-stats',
            [$this, 'render_stats_page']
        );
    }

    /**
     * Encolar assets de administración
     * 
     * @param string $hook Página actual
     */
    public function enqueue_admin_assets($hook) {
        // Verificar que estamos en páginas del plugin
        if (strpos($hook, 'lista-deseos') === false) {
            return;
        }

        // Estilos de administración
        wp_enqueue_style(
            'ldw-admin-style',
            LDW_PLUGIN_URL . 'assets/css/admin-settings.css',
            [],
            LDW_VERSION
        );

        // Scripts de administración
        wp_enqueue_script(
            'ldw-admin-script',
            LDW_PLUGIN_URL . 'assets/js/admin-settings.js',
            ['jquery', 'wp-color-picker'],
            LDW_VERSION,
            true
        );

        // Localizar script
        wp_localize_script(
            'ldw-admin-script', 
            'ldw_admin_params', 
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ldw_admin_nonce')
            ]
        );

        // Color picker
        wp_enqueue_style('wp-color-picker');
    }

    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting(
            'ldw_settings_group', 
            self::OPTION_NAME, 
            [$this, 'sanitize_settings']
        );

        // Secciones de configuración
        add_settings_section(
            'ldw_general_section', 
            __('Configuración General', 'lista-deseos-wc'), 
            [$this, 'render_general_section_description'], 
            self::MENU_SLUG
        );

        // Campos de configuración
        $this->add_settings_fields();
    }

    /**
     * Añadir campos de configuración
     */
    private function add_settings_fields() {
        $fields = [
            [
                'id' => 'enable_wishlist',
                'title' => __('Activar Lista de Deseos', 'lista-deseos-wc'),
                'type' => 'checkbox',
                'default' => 1
            ],
            [
                'id' => 'show_in_shop',
                'title' => __('Mostrar en Tienda', 'lista-deseos-wc'),
                'type' => 'checkbox',
                'default' => 1
            ],
            [
                'id' => 'show_in_product',
                'title' => __('Mostrar en Página de Producto', 'lista-deseos-wc'),
                'type' => 'checkbox',
                'default' => 1
            ],
            [
                'id' => 'icon_type',
                'title' => __('Tipo de Ícono', 'lista-deseos-wc'),
                'type' => 'select',
                'options' => [
                    'heart' => __('Corazón', 'lista-deseos-wc'),
                    'star' => __('Estrella', 'lista-deseos-wc'),
                    'bookmark' => __('Marcador', 'lista-deseos-wc')
                ],
                'default' => 'heart'
            ],
            [
                'id' => 'icon_color',
                'title' => __('Color de Ícono', 'lista-deseos-wc'),
                'type' => 'color',
                'default' => '#FF4E4E'
            ]
        ];

        foreach ($fields as $field) {
            add_settings_field(
                $field['id'], 
                $field['title'], 
                [$this, 'render_settings_field'], 
                self::MENU_SLUG, 
                'ldw_general_section', 
                $field
            );
        }
    }

    /**
     * Renderizar campo de configuración
     * 
     * @param array $args Argumentos del campo
     */
    public function render_settings_field($args) {
        $settings = $this->core->get_settings();
        $value = $settings[$args['id']] ?? $args['default'];

        switch ($args['type']) {
            case 'checkbox':
                ?>
                <label>
                    <input 
                        type="checkbox" 
                        name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($args['id']); ?>]" 
                        value="1"
                        <?php checked(1, $value); ?> 
                    />
                    <?php echo esc_html($args['title']); ?>
                </label>
                <?php
                break;

            case 'select':
                ?>
                <select 
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($args['id']); ?>]"
                >
                    <?php foreach ($args['options'] as $option_value => $option_label): ?>
                        <option 
                            value="<?php echo esc_attr($option_value); ?>"
                            <?php selected($option_value, $value); ?>
                        >
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;

            case 'color':
                ?>
                <input 
                    type="color" 
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($args['id']); ?>]" 
                    value="<?php echo esc_attr($value); ?>" 
                />
                <?php
                break;
        }
    }

    /**
     * Sanitizar configuraciones
     * 
     * @param array $input Configuraciones de entrada
     * @return array Configuraciones sanitizadas
     */
    public function sanitize_settings($input) {
        return $this->core->update_settings($input);
    }

    /**
     * Renderizar descripción de sección general
     */
    public function render_general_section_description() {
        echo '<p>' . esc_html__('Configura las opciones generales para tu Lista de Deseos.', 'lista-deseos-wc') . '</p>';
    }

    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        ?>
        <div class="wrap ldw-admin-wrapper">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post" id="ldw-settings-form">
                <?php
                // Generar campos de seguridad
                settings_fields('ldw_settings_group');
                
                // Renderizar secciones y campos
                do_settings_sections(self::MENU_SLUG);
                
                // Botón de envío
                submit_button(__('Guardar Configuración', 'lista-deseos-wc'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renderizar página de personalización
     */
    public function render_customization_page() {
        ?>
        <div class="wrap ldw-admin-wrapper">
            <h1><?php _e('Personalización de Lista de Deseos', 'lista-deseos-wc'); ?></h1>
            <div class="ldw-customization-container">
                <!-- Secciones de personalización avanzada -->
                <div class="ldw-customization-section">
                    <h2><?php _e('Estilos Personalizados', 'lista-deseos-wc'); ?></h2>
                    <!-- Campos de personalización -->
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar página de estadísticas
     */
    public function render_stats_page() {
        ?>
        <div class="wrap ldw-admin-wrapper">
            <h1><?php _e('Estadísticas de Lista de Deseos', 'lista-deseos-wc'); ?></h1>
            <div class="ldw-stats-container">
                <!-- Contenido de estadísticas -->
            </div>
        </div>
        <?php
    }