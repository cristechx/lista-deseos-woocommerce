<?php
/**
 * Plantilla para botón de Lista de Deseos
 *
 * Esta plantilla puede ser sobrescrita copiándola en:
 * woocommerce/wishlist/wishlist-button.php
 *
 * @package ListaDeseosWooCommerce
 * @version 1.2.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Argumentos por defecto para el botón
$defaults = [
    'product_id' => get_the_ID(),
    'custom_class' => '',
    'icon_type' => 'heart',
    'show_text' => true,
    'context' => 'product'
];

// Mezclar argumentos proporcionados con defectos
$args = wp_parse_args($args ?? [], $defaults);

// Verificar si el usuario está logueado
if (!is_user_logged_in()) {
    return;
}

// Obtener ID del producto
$product_id = intval($args['product_id']);

// Verificar si el producto existe
$product = wc_get_product($product_id);
if (!$product) {
    return;
}

// Verificar si el producto está en la lista de deseos
$is_in_wishlist = ldw_is_in_wishlist($product_id);

// Preparar clases del botón
$button_classes = [
    'ldw-wishlist-button',
    'ldw-wishlist-button-' . esc_attr($args['context']),
    $is_in_wishlist ? 'ldw-in-wishlist' : '',
    esc_attr($args['custom_class'])
];

// Texto del botón
$button_text = $is_in_wishlist 
    ? __('Quitar de Lista de Deseos', 'lista-deseos-wc') 
    : __('Añadir a Lista de Deseos', 'lista-deseos-wc');

// Crear nonce para seguridad
$nonce = wp_create_nonce('ldw_wishlist_nonce');

// Obtener configuraciones de personalización
$customization = get_option('ldw_customization_settings', [
    'icon_type' => 'heart',
    'icon_color' => '#FF4E4E'
]);
?>

<div class="ldw-wishlist-button-wrapper">
    <button 
        type="button" 
        class="<?php echo esc_attr(implode(' ', $button_classes)); ?>"
        data-product-id="<?php echo esc_attr($product_id); ?>"
        data-nonce="<?php echo esc_attr($nonce); ?>"
        data-action="<?php echo $is_in_wishlist ? 'remove' : 'add'; ?>"
        aria-label="<?php echo esc_attr($button_text); ?>"
    >
        <?php if ($args['icon_type'] === 'heart'): ?>
            <span class="ldw-button-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" 
                     fill="<?php echo $is_in_wishlist ? esc_attr($customization['icon_color']) : 'none'; ?>" 
                     stroke="<?php echo esc_attr($customization['icon_color']); ?>" 
                     stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                </svg>
            </span>
        <?php elseif ($args['icon_type'] === 'star'): ?>
            <span class="ldw-button-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" 
                     fill="<?php echo $is_in_wishlist ? esc_attr($customization['icon_color']) : 'none'; ?>" 
                     stroke="<?php echo esc_attr($customization['icon_color']); ?>">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                </svg>
            </span>
        <?php elseif ($args['icon_type'] === 'bookmark'): ?>
            <span class="ldw-button-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" 
                     fill="<?php echo $is_in_wishlist ? esc_attr($customization['icon_color']) : 'none'; ?>" 
                     stroke="<?php echo esc_attr($customization['icon_color']); ?>">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                </svg>
            </span>
        <?php endif; ?>

        <?php if ($args['show_text']): ?>
            <span class="ldw-button-text">
                <?php echo esc_html($button_text); ?>
            </span>
        <?php endif; ?>
    </button>

    <div class="ldw-wishlist-message" aria-live="polite"></div>
</div>

<?php
/**
 * Acción después de renderizar el botón de lista de deseos
 * 
 * @param int $product_id ID del producto
 * @param bool $is_in_wishlist Si el producto está en la lista de deseos
 */
do_action('ldw_after_wishlist_button', $product_id, $is_in_wishlist);