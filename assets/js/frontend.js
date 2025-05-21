/**
 * Frontend JavaScript para Lista de Deseos WooCommerce
 * Maneja interacciones AJAX y funcionalidades de lista de deseos
 */
(function($) {
    // Objeto principal de gestión de lista de deseos
    const WishlistManager = {
        // Configuraciones y selectores
        config: {
            selectors: {
                button: '.ldw-wishlist-button',
                counter: '.ldw-wishlist-counter',
                message: '.ldw-wishlist-message'
            },
            classes: {
                processing: 'ldw-processing',
                added: 'ldw-in-wishlist',
                animate: 'ldw-button-animate'
            },
            ajaxUrl: ldw_params.ajax_url,
            nonce: ldw_params.nonce
        },

        /**
         * Inicializar gestión de lista de deseos
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Enlazar eventos de interacción
         */
        bindEvents: function() {
            $(document).on('click', this.config.selectors.button, (event) => {
                event.preventDefault();
                const $button = $(event.currentTarget);
                
                // Prevenir múltiples clics
                if ($button.hasClass(this.config.classes.processing)) {
                    return;
                }

                const productId = $button.data('product-id');
                const action = $button.hasClass(this.config.classes.added) ? 'remove' : 'add';

                this.toggleWishlist($button, productId, action);
            });
        },

        /**
         * Alternar producto en lista de deseos
         */
        toggleWishlist: function($button, productId, action) {
            // Añadir estado de procesamiento
            $button.addClass(this.config.classes.processing);
            this.showSpinner($button);

            // Realizar solicitud AJAX
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ldw_update_wishlist',
                    product_id: productId,
                    wishlist_action: action,
                    nonce: this.config.nonce
                },
                dataType: 'json',
                success: (response) => {
                    this.handleAjaxSuccess($button, response, action);
                },
                error: (xhr, status, error) => {
                    this.handleAjaxError($button, xhr, status, error);
                },
                complete: () => {
                    $button.removeClass(this.config.classes.processing);
                    this.hideSpinner($button);
                }
            });
        },

        /**
         * Manejar respuesta exitosa de AJAX
         */
        handleAjaxSuccess: function($button, response, action) {
            if (response.success) {
                this.updateButtonState($button, action);
                this.updateWishlistCounter(response.data.wishlist_count);
                this.showMessage(response.data.message, 'success');

                // Disparar evento personalizado
                $(document.body).trigger('ldw_wishlist_updated', [
                    $button.data('product-id'), 
                    action === 'add'
                ]);
            } else {
                this.showMessage(response.data.message || 'Error', 'error');
            }
        },

        /**
         * Manejar error de AJAX
         */
        handleAjaxError: function($button, xhr, status, error) {
            console.error('Wishlist AJAX error:', status, error);
            this.showMessage('Error de comunicación', 'error');
        },

        /**
         * Actualizar estado visual del botón
         */
        updateButtonState: function($button, action) {
            if (action === 'add') {
                $button.addClass(this.config.classes.added);
                $button.attr('aria-label', ldw_params.labels.remove);
                $button.find('.ldw-button-text').text(ldw_params.labels.remove);
            } else {
                $button.removeClass(this.config.classes.added);
                $button.attr('aria-label', ldw_params.labels.add);
                $button.find('.ldw-button-text').text(ldw_params.labels.add);
            }

            // Añadir animación
            $button.addClass(this.config.classes.animate);
            setTimeout(() => $button.removeClass(this.config.classes.animate), 300);
        },

        /**
         * Actualizar contador de lista de deseos
         */
        updateWishlistCounter: function(count) {
            const $counter = $(this.config.selectors.counter);
            $counter.text(count);
            
            if (count > 0) {
                $counter.removeClass('ldw-hidden');
            } else {
                $counter.addClass('ldw-hidden');
            }
        },

        /**
         * Mostrar spinner de carga
         */
        showSpinner: function($button) {
            $button.find('.ldw-spinner').removeClass('ldw-hidden');
        },

        /**
         * Ocultar spinner de carga
         */
        hideSpinner: function($button) {
            $button.find('.ldw-spinner').addClass('ldw-hidden');
        },

        /**
         * Mostrar mensajes de notificación
         */
        showMessage: function(message, type = 'success') {
            const $messageContainer = $(this.config.selectors.message);
            
            $messageContainer
                .removeClass('ldw-success ldw-error')
                .addClass(`ldw-${type}`)
                .text(message)
                .fadeIn(300)
                .delay(3000)
                .fadeOut(300);
        },

        /**
         * Gestionar lista de deseos en página de lista de deseos
         */
        initWishlistPage: function() {
            // Manejar eliminación de productos
            $('.ldw-remove-from-wishlist').on('click', (event) => {
                const $button = $(event.currentTarget);
                const productId = $button.data('product-id');

                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ldw_remove_from_wishlist',
                        product_id: productId,
                        nonce: this.config.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            // Eliminar elemento de la lista
                            $button.closest('.ldw-wishlist-item').fadeOut(300, function() {
                                $(this).remove();
                            });

                            // Actualizar contador
                            this.updateWishlistCounter(response.data.wishlist_count);
                        } else {
                            this.showMessage(response.data.message, 'error');
                        }
                    }
                });
            });

            // Manejar limpieza de lista de deseos
            $('.ldw-clear-wishlist').on('click', () => {
                if (confirm(ldw_params.labels.confirm_clear)) {
                    $.ajax({
                        url: this.config.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ldw_clear_wishlist',
                            nonce: this.config.nonce
                        },
                        success: (response) => {
                            if (response.success) {
                                // Limpiar lista
                                $('.ldw-wishlist-items').empty();
                                this.updateWishlistCounter(0);
                                this.showMessage(response.data.message, 'success');
                            } else {
                                this.showMessage(response.data.message, 'error');
                            }
                        }
                    });
                }
            });
        }
    };

    // Inicializar al cargar el DOM
    $(document).ready(() => {
        WishlistManager.init();
        
        // Inicializar página de lista de deseos si está presente
        if ($('.ldw-wishlist-page').length) {
            WishlistManager.initWishlistPage();
        }
    });

    // Exponer métodos globalmente
    window.WishlistManager = WishlistManager;
})(jQuery);