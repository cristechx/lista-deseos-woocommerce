lista de deseos copiado al portapapeles', 'lista-deseos-wc'); ?>');
                        }, function(err) {
                            console.error('Error al copiar enlace: ', err);
                            alert('<?php _e('Error al copiar enlace', 'lista-deseos-wc'); ?>');
                        });
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Compartir producto individual
        $('.ldw-share-item').on('click', function() {
            const productId = $(this).data('product-id');

            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'ldw_generate_product_share_link',
                    product_id: productId,
                    list_type: '<?php echo esc_js($args['list_type']); ?>',
                    nonce: '<?php echo wp_create_nonce('ldw_wishlist_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Copiar al portapapeles
                        navigator.clipboard.writeText(response.data.share_link).then(function() {
                            alert('<?php _e('Enlace de producto copiado al portapapeles', 'lista-deseos-wc'); ?>');
                        }, function(err) {
                            console.error('Error al copiar enlace: ', err);
                            alert('<?php _e('Error al copiar enlace', 'lista-deseos-wc'); ?>');
                        });
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Vista rápida de producto
        $('.ldw-quick-view').on('click', function() {
            const productId = $(this).data('product-id');

            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'ldw_quick_view_product',
                    product_id: productId,
                    nonce: '<?php echo wp_create_nonce('ldw_wishlist_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Mostrar modal de vista rápida
                        const $modal = $('<div>').addClass('ldw-quick-view-modal');
                        $modal.html(response.data.html);
                        $('body').append($modal);

                        // Cerrar modal al hacer clic fuera o en botón de cierre
                        $modal.on('click', function(e) {
                            if ($(e.target).hasClass('ldw-quick-view-modal') || 
                                $(e.target).hasClass('ldw-quick-view-close')) {
                                $modal.remove();
                            }
                        });
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Manejar productos variables
        $('.ldw-variable-product-select').on('change', function() {
            const $select = $(this);
            const productId = $select.data('product-id');
            const variationId = $select.val();
            const $addToCartBtn = $select.closest('.ldw-wishlist-item')
                                         .find('.ldw-add-to-cart-btn');

            // Actualizar botón de añadir al carrito con datos de variación
            if (variationId) {
                $addToCartBtn.attr({
                    'data-product_id': productId,
                    'data-variation_id': variationId
                });
            }
        });

        // Añadir al carrito desde lista de deseos
        $('.ldw-add-to-cart-btn').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const productId = $button.data('product_id');
            const variationId = $button.data('variation_id');

            const data = {
                action: 'ldw_add_to_cart_from_wishlist',
                product_id: productId,
                nonce: '<?php echo wp_create_nonce('ldw_wishlist_nonce'); ?>'
            };

            // Añadir ID de variación si es un producto variable
            if (variationId) {
                data.variation_id = variationId;
            }

            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        // Redirigir al carrito o mostrar mensaje de éxito
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            alert(response.data.message);
                            
                            // Opcional: actualizar fragmentos del carrito
                            $(document.body).trigger('added_to_cart', [
                                response.data.fragments, 
                                response.data.cart_hash
                            ]);
                        }
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('<?php _e('Error al añadir al carrito', 'lista-deseos-wc'); ?>');
                }
            });
        });
    });
})(jQuery);
</script>

<?php
/**
 * Acción después de renderizar la página de lista de deseos
 * 
 * @param array $args Argumentos de la lista de deseos
 */
do_action('ldw_after_wishlist_page', $args);
<?php
/**
 * Plantilla de Página de Lista de Deseos
 *
 * Esta plantilla puede ser sobrescrita copiándola en:
 * woocommerce/wishlist/wishlist-page.php
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
    'wishlist_items' => [],
    'wishlist_count' => 0,
    'list_type' => 'default',
    'list_name' => __('Mi Lista de Deseos', 'lista-deseos-wc')
];

// Mezclar argumentos proporcionados con defectos
$args = wp_parse_args($args ?? [], $defaults);

// Obtener configuraciones del plugin
$settings = get_option('ldw_wishlist_settings', [
    'multiple_lists' => false,
    'share_wishlist' => false
]);

// Verificar si el usuario está logueado
if (!is_user_logged_in()) {
    wc_print_notice(__('Debes iniciar sesión para ver tu lista de deseos.', 'lista-deseos-wc'), 'error');
    return;
}
?>

<div class="ldw-wishlist-container" data-list-type="<?php echo esc_attr($args['list_type']); ?>">
    <div class="ldw-wishlist-header">
        <h1 class="ldw-wishlist-title">
            <?php echo esc_html($args['list_name']); ?>
            <span class="ldw-wishlist-count">
                (<?php echo esc_html($args['wishlist_count']); ?>)
            </span>
        </h1>

        <?php if ($settings['multiple_lists']): ?>
            <div class="ldw-list-switcher">
                <select id="ldw-list-selector">
                    <option value="default">
                        <?php _e('Lista Principal', 'lista-deseos-wc'); ?>
                    </option>
                    <?php 
                    // Obtener listas adicionales del usuario
                    $additional_lists = apply_filters('ldw_get_user_wishlist_lists', []);
                    foreach ($additional_lists as $list): 
                    ?>
                        <option 
                            value="<?php echo esc_attr($list['id']); ?>"
                            <?php selected($list['id'], $args['list_type']); ?>
                        >
                            <?php echo esc_html($list['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($args['wishlist_items'])): ?>
        <div class="ldw-empty-wishlist">
            <div class="ldw-empty-wishlist-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                </svg>
            </div>
            
            <h2 class="ldw-empty-wishlist-title">
                <?php _e('Tu Lista de Deseos está Vacía', 'lista-deseos-wc'); ?>
            </h2>
            
            <p class="ldw-empty-wishlist-description">
                <?php _e('Parece que aún no has añadido ningún producto a tu lista de deseos.', 'lista-deseos-wc'); ?>
            </p>
            
            <div class="ldw-empty-wishlist-actions">
                <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button ldw-continue-shopping">
                    <?php _e('Continuar Comprando', 'lista-deseos-wc'); ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="ldw-wishlist-grid">
            <?php foreach ($args['wishlist_items'] as $item): ?>
                <div class="ldw-wishlist-item" data-product-id="<?php echo esc_attr($item['id']); ?>">
                    <div class="ldw-wishlist-item-image">
                        <a href="<?php echo esc_url($item['url']); ?>">
                            <img 
                                src="<?php echo esc_url($item['image']); ?>" 
                                alt="<?php echo esc_attr($item['name']); ?>"
                            >
                        </a>
                        
                        <?php if (!$item['is_in_stock']): ?>
                            <span class="ldw-out-of-stock-badge">
                                <?php _e('Agotado', 'lista-deseos-wc'); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="ldw-wishlist-item-details">
                        <h3 class="ldw-wishlist-item-title">
                            <a href="<?php echo esc_url($item['url']); ?>">
                                <?php echo esc_html($item['name']); ?>
                            </a>
                        </h3>

                        <div class="ldw-wishlist-item-price">
                            <?php echo wp_kses_post($item['price']); ?>
                        </div>

                        <?php if (!empty($item['added_date'])): ?>
                            <div class="ldw-wishlist-item-date">
                                <?php 
                                printf(
                                    __('Añadido el %s', 'lista-deseos-wc'), 
                                    wp_date(get_option('date_format'), strtotime($item['added_date']))
                                ); 
                                ?>
                            </div>
                        <?php endif; ?>

                        <div class="ldw-wishlist-item-actions">
                            <?php if ($item['is_purchasable'] && $item['is_in_stock']): ?>
                                <a 
                                    href="<?php echo esc_url($item['add_to_cart_url']); ?>" 
                                    class="ldw-add-to-cart-btn button"
                                    data-product_id="<?php echo esc_attr($item['id']); ?>"
                                >
                                    <?php echo esc_html($item['add_to_cart_text']); ?>
                                </a>
                            <?php endif; ?>

                            <button 
                                type="button" 
                                class="ldw-remove-from-wishlist button alt"
                                data-product-id="<?php echo esc_attr($item['id']); ?>"
                                data-list-type="<?php echo esc_attr($args['list_type']); ?>"
                            >
                                <?php _e('Eliminar', 'lista-deseos-wc'); ?>
                            </button>
                        </div>
                    </div>

                    <?php if ($settings['share_wishlist'] && !empty($item['id'])): ?>
                        <div class="ldw-wishlist-item-share">
                            <button 
                                type="button" 
                                class="ldw-share-item button"
                                data-product-id="<?php echo esc_attr($item['id']); ?>"
                            >
                                <?php _e('Compartir', 'lista-deseos-wc'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="ldw-wishlist-actions">
            <?php if ($settings['multiple_lists']): ?>
                <button type="button" class="ldw-create-list button">
                    <?php _e('Crear Nueva Lista', 'lista-deseos-wc'); ?>
                </button>
            <?php endif; ?>

            <button type="button" class="ldw-clear-wishlist button alt">
                <?php _e('Limpiar Lista de Deseos', 'lista-deseos-wc'); ?>
            </button>

            <?php if ($settings['share_wishlist']): ?>
                <button type="button" class="ldw-share-wishlist button">
                    <?php _e('Compartir Lista', 'lista-deseos-wc'); ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function($) {
    $(document).ready(function() {
        // Cambiar lista
        $('#ldw-list-selector').on('change', function() {
            const listType = $(this).val();
            // Aquí iría la lógica AJAX para cargar la lista seleccionada
            window.location.href = '<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>wishlist/' + listType;
        });

        // Eliminar producto de la lista
        $('.ldw-remove-from-wishlist').on('click', function() {
            const $button = $(this);
            const productId = $button.data('product-id');
            const listType = $button.data('list-type');

            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'ldw_remove_from_wishlist',
                    product_id: productId,
                    list_type: listType,
                    nonce: '<?php echo wp_create_nonce('ldw_wishlist_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('.ldw-wishlist-item').fadeOut(300, function() {
                            $(this).remove();
                            // Actualizar contador
                            const $count = $('.ldw-wishlist-count');
                            const currentCount = parseInt($count.text().replace(/[()]/g, ''));
                            $count.text(`(${currentCount - 1})`);
                        });
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Limpiar lista de deseos
        $('.ldw-clear-wishlist').on('click', function() {
            if (confirm('<?php _e('¿Estás seguro de que quieres eliminar todos los productos de tu lista de deseos?', 'lista-deseos-wc'); ?>')) {
                $.ajax({
                    url: wc_add_to_cart_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ldw_clear_wishlist',
                        list_type: '<?php echo esc_js($args['list_type']); ?>',
                        nonce: '<?php echo wp_create_nonce('ldw_wishlist_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.ldw-wishlist-grid').empty();
                            $('.ldw-wishlist-count').text('(0)');
                            
                            // Mostrar mensaje de lista vacía
                            $('.ldw-wishlist-container').html(`
                                <div class="ldw-empty-wishlist">
                                    <div class="ldw-empty-wishlist-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                                        </svg>
                                    </div>
                                    
                                    <h2 class="ldw-empty-wishlist-title">
                                        <?php _e('Tu Lista de Deseos está Vacía', 'lista-deseos-wc'); ?>
                                    </h2>
                                    
                                    <p class="ldw-empty-wishlist-description">
                                        <?php _e('Parece que aún no has añadido ningún producto a tu lista de deseos.', 'lista-deseos-wc'); ?>
                                    </p>
                                    
                                    <div class="ldw-empty-wishlist-actions">
                                        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button ldw-continue-shopping">
                                            <?php _e('Continuar Comprando', 'lista-deseos-wc'); ?>
                                        </a>
                                    </div>
                                </div>
                            `);
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        });

        // Crear nueva lista
        $('.ldw-create-list').on('click', function() {
            // Mostrar modal o prompt para crear nueva lista
            const listName = prompt('<?php _e('Introduce un nombre para la nueva lista', 'lista-deseos-wc'); ?>');
            
            if (listName) {
                $.ajax({
                    url: wc_add_to_cart_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ldw_create_wishlist_list',
                        list_name: listName,
                        nonce: '<?php echo wp_create_nonce('ldw_wishlist_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Recargar página o actualizar selector de listas
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }
        });

        // Compartir lista de deseos
        $('.ldw-share-wishlist').on('click', function() {
            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'ldw_generate_wishlist_share_link',
                    list_type: '<?php echo esc_js($args['list_type']); ?>',
                    nonce: '<?php echo wp_create_nonce('ldw_wishlist_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Copiar al portapapeles
                        navigator.clipboard.writeText(response.data.share_link).then(function() {
                            alert('<?php _e('Enlace de<?php
/**
 * Plantilla de Página de Lista de Deseos
 *
 * Esta plantilla puede ser sobrescrita copiándola en:
 * woocommerce/wishlist/wishlist-page.php
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
    'wishlist_items' => [],
    'wishlist_count' => 0,
    'list_type' => 'default',
    'list_name' => __('Mi Lista de Deseos', 'lista-deseos-wc')
];

// Mezclar argumentos proporcionados con