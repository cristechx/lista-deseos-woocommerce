Selector.find('.ldw-icon-option').removeClass('selected');
                $(this).addClass('selected');
            });
        }

        /**
         * Inicializar sistema de importación/exportación
         */
        initImportExportSystem() {
            const $importInput = $('#ldw-import-input');
            const $importButton = $('#ldw-import-button');
            const $exportButton = $('#ldw-export-button');

            $importButton.on('click', () => {
                const file = $importInput[0].files[0];
                if (!file) {
                    this.showNotification('Por favor, selecciona un archivo', 'error');
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        const importData = JSON.parse(e.target.result);
                        this.processImportData(importData);
                    } catch (error) {
                        console.error('Error importing data:', error);
                        this.showNotification('Error al importar datos. Asegúrate de que el archivo es válido.', 'error');
                    }
                };
                reader.readAsText(file);
            });

            $exportButton.on('click', () => {
                const exportData = this.prepareExportData();
                const blob = new Blob([JSON.stringify(exportData, null, 2)], {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = `ldw-export-${new Date().toISOString().slice(0,10)}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                this.showNotification('Configuraciones exportadas correctamente', 'success');
            });
        }

        /**
         * Preparar datos para exportación
         */
        prepareExportData() {
            const settings = {};
            
            $('#ldw-settings-form').find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                let value;

                if ($field.attr('type') === 'checkbox') {
                    value = $field.is(':checked');
                } else if ($field.attr('type') === 'radio') {
                    if ($field.is(':checked')) {
                        value = $field.val();
                    }
                } else {
                    value = $field.val();
                }

                if (name) {
                    const matches = name.match(/(\w+)\[(\w+)\]/);
                    if (matches) {
                        if (!settings[matches[1]]) {
                            settings[matches[1]] = {};
                        }
                        settings[matches[1]][matches[2]] = value;
                    } else {
                        settings[name] = value;
                    }
                }
            });

            return {
                version: '1.2.0',
                exportDate: new Date().toISOString(),
                settings: settings
            };
        }

        /**
         * Procesar datos importados
         */
        processImportData(importData) {
            // Validar estructura de datos
            if (!importData.settings) {
                this.showNotification('Formato de datos inválido', 'error');
                return;
            }

            // Confirmar importación
            if (!confirm('¿Estás seguro de que quieres importar estas configuraciones? Esto sobrescribirá la configuración actual.')) {
                return;
            }

            // Aplicar configuraciones
            Object.keys(importData.settings).forEach(key => {
                const value = importData.settings[key];
                
                if (typeof value === 'object') {
                    // Manejar configuraciones anidadas
                    Object.keys(value).forEach(subKey => {
                        const $field = $(`[name="${key}[${subKey}]"]`);
                        this.setFieldValue($field, value[subKey]);
                    });
                } else {
                    // Campos simples
                    const $field = $(`[name="${key}"]`);
                    this.setFieldValue($field, value);
                }
            });

            // Notificar éxito
            this.showNotification('Configuraciones importadas correctamente', 'success');
        }

        /**
         * Establecer valor de campo de formulario
         */
        setFieldValue($field, value) {
            if ($field.length === 0) return;

            switch ($field.attr('type')) {
                case 'checkbox':
                    $field.prop('checked', value);
                    break;
                case 'radio':
                    $field.filter(`[value="${value}"]`).prop('checked', true);
                    break;
                case 'color':
                    $field.val(value);
                    // Actualizar vista previa de color
                    $field.trigger('change');
                    break;
                default:
                    $field.val(value);
            }
        }

        /**
         * Inicializar herramientas de prueba
         */
        initTestingTools() {
            const $testButtons = $('.ldw-test-feature');

            $testButtons.on('click', function() {
                const feature = $(this).data('feature');
                
                // Realizar prueba según la característica
                switch(feature) {
                    case 'wishlist-add':
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ldw_test_add_to_wishlist',
                                nonce: ldw_admin_params.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('Prueba de añadir a lista de deseos exitosa');
                                } else {
                                    alert('Error en prueba: ' + response.data.message);
                                }
                            }
                        });
                        break;
                    case 'wishlist-remove':
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ldw_test_remove_from_wishlist',
                                nonce: ldw_admin_params.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('Prueba de eliminar de lista de deseos exitosa');
                                } else {
                                    alert('Error en prueba: ' + response.data.message);
                                }
                            }
                        });
                        break;
                    case 'notification':
                        this.showNotification('Notificación de prueba', 'success');
                        break;
                }
            });
        }

        /**
         * Mostrar notificación
         */
        showNotification(message, type = 'success') {
            // Crear contenedor de notificaciones si no existe
            if ($('#ldw-notifications').length === 0) {
                $('body').append('<div id="ldw-notifications"></div>');
            }

            // Crear notificación
            const $notification = $(`
                <div class="ldw-notification ldw-notification-${type}">
                    <div class="ldw-notification-content">${message}</div>
                </div>
            `);

            // Añadir y eliminar notificación
            $('#ldw-notifications')
                .append($notification)
                .find('.ldw-notification')
                .last()
                .fadeIn(300)
                .delay(3000)
                .fadeOut(300, function() {
                    $(this).remove();
                });
        }

        /**
         * Restaurar configuraciones predeterminadas
         */
        restoreDefaultSettings() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ldw_restore_default_settings',
                    nonce: ldw_admin_params.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Configuraciones restauradas a valores predeterminados', 'success');
                        // Recargar página para reflejar cambios
                        location.reload();
                    } else {
                        this.showNotification('Error al restaurar configuraciones', 'error');
                    }
                }
            });
        }
    }

    // Inicializar gestor de administración
    const ldwAdminManager = new LDWAdminManager();

    // Exponer algunas funciones globalmente
    window.LDWAdmin = {
        resetToDefaults: () => {
            if (confirm('¿Estás seguro de que quieres restaurar la configuración predeterminada?')) {
                ldwAdminManager.restoreDefaultSettings();
            }
        },
        importSettings: () => {
            $('#ldw-import-input').click();
        },
        exportSettings: () => {
            ldwAdminManager.prepareExportData();
        }
    };
});
jQuery(document).ready(function($) {
    // Clase para gestionar la interfaz de administración de la lista de deseos
    class LDWAdminManager {
        constructor() {
            this.initColorPickers();
            this.initTabSystem();
            this.initExportButtons();
            this.initCustomizationPreview();
            this.initResponsivePreview();
            this.initIconSelector();
            this.initImportExportSystem();
            this.initTestingTools();
            this.initTooltips();
            this.initConditionalFields();
        }

        /**
         * Inicializar selectores de color
         */
        initColorPickers() {
            $('.ldw-color-picker').wpColorPicker({
                change: (event, ui) => {
                    this.updateLivePreview(event.target);
                },
                clear: (event) => {
                    this.updateLivePreview(event.target);
                }
            });
        }

        /**
         * Inicializar sistema de pestañas
         */
        initTabSystem() {
            $('.ldw-tab-nav').on('click', 'li', function() {
                const tabId = $(this).data('tab');
                
                // Cambiar pestaña activa
                $('.ldw-tab-nav li').removeClass('active');
                $(this).addClass('active');

                // Mostrar contenido correspondiente
                $('.ldw-tab-content').removeClass('active');
                $(`#${tabId}`).addClass('active');
            });
        }

        /**
         * Inicializar tooltips
         */
        initTooltips() {
            $('.ldw-tooltip').hover(
                function() {
                    const tooltipText = $(this).data('tooltip');
                    $('<div class="ldw-tooltip-popup">')
                        .text(tooltipText)
                        .appendTo('body')
                        .css({
                            top: $(this).offset().top - 40,
                            left: $(this).offset().left
                        })
                        .fadeIn(200);
                },
                function() {
                    $('.ldw-tooltip-popup').remove();
                }
            );
        }

        /**
         * Inicializar campos condicionales
         */
        initConditionalFields() {
            $('[data-conditional-field]').each(function() {
                const $field = $(this);
                const masterField = $field.data('conditional-field');
                const masterValue = $field.data('conditional-value');

                // Función para mostrar/ocultar campo
                const toggleField = () => {
                    const $master = $(`[name="${masterField}"]`);
                    const currentValue = $master.attr('type') === 'checkbox' 
                        ? $master.is(':checked') 
                        : $master.val();

                    $field.closest('.ldw-form-control')
                        .toggle(currentValue == masterValue);
                };

                // Vincular eventos
                $(`[name="${masterField}"]`).on('change', toggleField);
                
                // Ejecutar al inicio
                toggleField();
            });
        }

        /**
         * Inicializar botones de exportación
         */
        initExportButtons() {
            $('#ldw-export-wishlist').on('click', function(e) {
                e.preventDefault();
                
                // Mostrar confirmación
                if (confirm('¿Estás seguro de que quieres exportar todas las listas de deseos?')) {
                    window.location.href = $(this).attr('href');
                }
            });

            $('#ldw-cleanup-wishlist').on('click', function(e) {
                e.preventDefault();
                
                // Mostrar confirmación
                if (confirm('Esta acción eliminará productos inválidos de las listas de deseos. ¿Continuar?')) {
                    window.location.href = $(this).attr('href');
                }
            });
        }

        /**
         * Vista previa en vivo de personalización
         */
        initCustomizationPreview() {
            const $preview = $('#ldw-customization-preview');
            const $controls = $('.ldw-customization-control');

            $controls.on('change input', function() {
                const property = $(this).data('property');
                const value = $(this).val();

                // Aplicar cambios en la vista previa
                switch(property) {
                    case 'primary-color':
                        $preview.css('--ldw-primary-color', value);
                        break;
                    case 'icon-type':
                        $preview.attr('data-icon-type', value);
                        break;
                    case 'button-style':
                        $preview.attr('data-button-style', value);
                        break;
                }
            });
        }

        /**
         * Vista previa responsiva
         */
        initResponsivePreview() {
            const $preview = $('#ldw-responsive-preview');
            const $deviceButtons = $('.ldw-device-selector button');

            $deviceButtons.on('click', function() {
                const device = $(this).data('device');
                
                // Cambiar tamaño de vista previa
                $deviceButtons.removeClass('active');
                $(this).addClass('active');

                // Aplicar clases de dispositivo
                $preview.removeClass('mobile tablet desktop')
                        .addClass(device);
            });
        }

        /**
         * Actualizar vista previa en vivo
         */
        updateLivePreview(target) {
            const $target = $(target);
            const property = $target.data('property');
            const value = $target.val();

            // Implementar lógica de actualización de vista previa
            const $preview = $('#ldw-live-preview');

            switch(property) {
                case 'primary-color':
                    $preview.css('--ldw-primary-color', value);
                    break;
                case 'secondary-color':
                    $preview.css('--ldw-secondary-color', value);
                    break;
                case 'icon-style':
                    $preview.attr('data-icon-style', value);
                    break;
            }
        }

        /**
         * Inicializar selector de iconos
         */
        initIconSelector() {
            const $iconSelector = $('#ldw-icon-selector');
            const $iconPreview = $('#ldw-icon-preview');
            const $hiddenInput = $('#ldw-selected-icon');

            $iconSelector.on('click', '.ldw-icon-option', function() {
                const iconType = $(this).data('icon');
                
                // Actualizar vista previa
                $iconPreview.attr('data-icon', iconType);
                
                // Actualizar input oculto
                $hiddenInput.val(iconType);
                
                // Resaltar icono seleccionado
                $icon