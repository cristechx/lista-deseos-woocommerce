<?php
/**
 * Plantilla de Modal para Lista de Deseos
 *
 * Esta plantilla puede ser sobrescrita copiándola en:
 * woocommerce/wishlist/wishlist-modal.php
 *
 * @package ListaDeseosWooCommerce
 * @version 1.2.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Argumentos por defecto
$defaults = [
    'product_id' => null,
    'list_type' => 'default',
    'lists' => [],
    'list_name' => ''
];

// Mezclar argumentos proporcionados con defectos
$args = wp_parse_args($args ?? [], $defaults);

// Verificar si el usuario está logueado
if (!is_user_logged_in()) {
    return;
}

// Verificar si hay un producto válido
$product = wc_get_product($args['product_id']);
if (!$product) {
    return;
}

// Obtener configuraciones del plugin
$settings = get_option('ldw_wishlist_settings', [
    'multiple_lists' => false,
    'allow_notes' => false
]);
?>

<div id="ldw-wishlist-modal" class="ldw-modal" role="dialog" aria-labelledby="ldw-modal-title">
    <div class="ldw-modal-content">
        <div class="ldw-modal-header">
            <h2 id="ldw-modal-title" class="ldw-modal-title">
                <?php _e('Añadir a Lista de Deseos', 'lista-deseos-wc'); ?>
            </h2>
            <button type="button" class="ldw-modal-close" aria-label="<?php _e('Cerrar', 'lista-deseos-wc'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="ldw-modal-body">
            <div class="ldw-product-preview">
                <div class="ldw-product-image">
                    <?php echo $product->get_image('woocommerce_thumbnail'); ?>
                </div>
                <div class="ldw-product-details">
                    <h3><?php echo esc_html($product->get_name()); ?></h3>
                    <p class="ldw-product-price"><?php echo wp_kses_post($product->get_price_html()); ?></p>
                </div>
            </div>

            <form id="ldw-wishlist-form" class="ldw-wishlist-form">
                <?php if ($settings['multiple_lists']): ?>
                    <div class="ldw-form-group">
                        <label for="ldw-list-selector">
                            <?php _e('Seleccionar Lista', 'lista-deseos-wc'); ?>
                        </label>
                        <select id="ldw-list-selector" name="list_type">
                            <option value="default">
                                <?php _e('Lista de Deseos Principal', 'lista-deseos-wc'); ?>
                            </option>
                            <?php foreach ($args['lists'] as $list): ?>
                                <option value="<?php echo esc_attr($list['id']); ?>">
                                    <?php echo esc_html($list['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new">
                                <?php _e('Crear Nueva Lista', 'lista-deseos-wc'); ?>
                            </option>
                        </select>
                    </div>

                    <div id="ldw-new-list-section" class="ldw-form-group" style="display:none;">
                        <label for="ldw-new-list-name">
                            <?php _e('Nombre de Nueva Lista', 'lista-deseos-wc'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="ldw-new-list-name" 
                            name="new_list_name" 
                            placeholder="<?php _e('Nombre de la lista', 'lista-deseos-wc'); ?>"
                        >
                    </div>
                <?php endif; ?>

                <?php if ($settings['allow_notes']): ?>
                    <div class="ldw-form-group">
                        <label for="ldw-wishlist-note">
                            <?php _e('Nota (Opcional)', 'lista-deseos-wc'); ?>
                        </label>
                        <textarea 
                            id="ldw-wishlist-note" 
                            name="wishlist_note" 
                            rows="3" 
                            placeholder="<?php _e('Escribe una nota sobre este producto', 'lista-deseos-wc'); ?>"
                        ></textarea>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="product_id" value="<?php echo esc_attr($args['product_id']); ?>">
                <?php wp_nonce_field('ldw_wishlist_modal_action', 'ldw_wishlist_nonce'); ?>
            </form>
        </div>

        <div class="ldw-modal-footer">
            <button type="button" class="ldw-modal-cancel ldw-btn ldw-btn-secondary">
                <?php _e('Cancelar', 'lista-deseos-wc'); ?>
            </button>
            <button type="button" class="ldw-modal-submit ldw-btn ldw-btn-primary">
                <?php _e('Añadir a Lista de Deseos', 'lista-deseos-wc'); ?>
            </button>
        </div>
    </div>
</div>

<script>
(function($) {
    $(document).ready(function() {
        const $modal = $('#ldw-wishlist-modal');
        const $listSelector = $('#ldw-list-selector');
        const $newListSection = $('#ldw-new-list-section');
        const $submitButton = $('.ldw-modal-submit');
        const $closeButton = $('.ldw-modal-close, .ldw-modal-cancel');

        // Mostrar/ocultar sección de nueva lista
        $listSelector.on('change', function() {
            $newListSection.toggle($(this).val() === 'new');
        });

        // Cerrar modal
        $closeButton.on('click', function() {
            $modal.hide();
        });

        // Enviar formulario
        $submitButton.on('click', function() {
            const formData = $('#ldw-wishlist-form').serialize();
            
            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'ldw_add_to_wishlist_modal',
                    ...formData
                },
                success: function(response) {
                    if (response.success) {
                        // Mostrar mensaje de éxito
                        alert(response.data.message);
                        $modal.hide();
                    } else {
                        // Mostrar mensaje de error
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('<?php _e('Error al procesar la solicitud', 'lista-deseos-wc'); ?>');
                }
            });
        });
    });
})(jQuery);
</script>