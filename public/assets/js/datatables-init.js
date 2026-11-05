/**
 * datatables-init.js
 * Padrão global oficial do DataTables no modelo AdminLTE 3 com extensão Responsive oficial.
 * Referências oficiais:
 * - https://datatables.net/manual/extensions/responsive/
 * - https://adminlte.io/themes/v3/pages/tables/data.html
 * 100% em Português do Brasil (pt-BR), com botões de exportação, busca instantânea e responsividade nativa com botão '+' sem scroll horizontal.
 */
(function (global) {
    'use strict';

    // Desativa alerts nativos do navegador globalmente para DataTables (regra do sistema)
    const initJQuery = global.jQuery || global.$;
    if (initJQuery && initJQuery.fn && initJQuery.fn.DataTable) {
        initJQuery.fn.dataTable.ext.errMode = 'none';
        initJQuery(document).off('error.dt.app').on('error.dt.app', function (e, settings, techNote, message) {
            console.warn('[DataTables Ignored Warning]', { techNote, message });
        });
    }

    // Localização padrão em Português do Brasil (AdminLTE 3)
    window.DATATABLES_PTBR = {
        sEmptyTable: "Nenhum registro encontrado",
        sInfo: "Mostrando de _START_ até _END_ de _TOTAL_ registros",
        sInfoEmpty: "Mostrando 0 até 0 de 0 registros",
        sInfoFiltered: "(filtrado de _MAX_ registros no total)",
        sInfoThousands: ".",
        sLengthMenu: "Exibir _MENU_ registros",
        sLoadingRecords: "Carregando...",
        sProcessing: "Processando...",
        sZeroRecords: "Nenhum registro correspondente encontrado",
        sSearch: "Pesquisar:",
        sSearchPlaceholder: "",
        oPaginate: {
            sNext: "Próximo",
            sPrevious: "Anterior",
            sFirst: "Primeiro",
            sLast: "Último"
        },
        oAria: {
            sSortAscending: ": Ordenar colunas de forma ascendente",
            sSortDescending: ": Ordenar colunas de forma descendente"
        },
        buttons: {
            copy: '<i class="bi bi-clipboard me-1"></i> Copiar',
            csv: '<i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV',
            excel: '<i class="bi bi-file-earmark-excel me-1"></i> Excel',
            pdf: '<i class="bi bi-file-earmark-pdf me-1"></i> PDF',
            print: '<i class="bi bi-printer me-1"></i> Imprimir',
            colvis: '<i class="bi bi-eye me-1"></i> Colunas'
        }
    };

    /**
     * Inicializa uma tabela como DataTable no modelo exato do AdminLTE 3 Responsive.
     * @param {string|HTMLElement|jQuery} selector 
     * @param {Object} userOptions 
     * @returns {DataTables.Api|null}
     */
    window.initAppDataTable = function (selector, userOptions = {}) {
        const $ = global.jQuery || global.$;
        if (!$ || !$.fn || !$.fn.DataTable) {
            console.warn('[DataTables] jQuery ou DataTables não carregado.');
            return null;
        }

        if ($.fn.dataTable.ext) {
            $.fn.dataTable.ext.errMode = 'none';
        }

        const $table = $(selector);
        if (!$table.length) return null;

        // Se a tabela possui linhas com colspan no tbody (ex: empty-state do Blade @empty),
        // o DataTables do jQuery é incompatível com colspan no body e lançaria o erro tn/18 (Incorrect column count).
        // Preservamos o empty-state limpo do Blade sem quebrar o layout nem exibir alertas.
        const hasColspan = $table.find('tbody tr td[colspan]').length > 0;
        if (hasColspan) {
            return null;
        }

        // Documentação oficial DataTables Responsive:
        // Contêineres com overflow-x impedem o cálculo de largura do Responsive.
        // Removemos a classe table-responsive do elemento ancestral imediato.
        const $responsiveParent = $table.closest('.table-responsive');
        if ($responsiveParent.length) {
            $responsiveParent.removeClass('table-responsive').addClass('dt-responsive-container');
        }

        // Se já foi inicializado, destruir com segurança para recriar
        if ($.fn.DataTable.isDataTable($table)) {
            $table.DataTable().destroy();
        }

        // Garante as classes oficiais do AdminLTE 3 e largura total
        $table.addClass('table table-bordered table-striped dt-responsive nowrap');
        $table.css('width', '100%');

        const pageLength = parseInt($table.data('page-length') || userOptions.pageLength || 25, 10);
        const exportTitle = $table.data('export-title') || userOptions.exportTitle || document.title;

        // Helper para notificações toasty/sweetalert2
        function notifyExport(msg, type = 'success') {
            if (typeof window.toast !== 'undefined' && typeof window.toast[type] === 'function') {
                window.toast[type](msg);
            } else if (typeof Toastify === 'function') {
                Toastify({
                    text: msg,
                    duration: 3000,
                    gravity: 'top',
                    position: 'right',
                    style: { background: type === 'error' ? '#dc3545' : (type === 'warning' ? '#ffc107' : '#198754') }
                }).showToast();
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
                    title: type === 'error' ? 'Erro' : (type === 'warning' ? 'Atenção' : 'Sucesso!'),
                    text: msg,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }

        // Botões oficiais AdminLTE 3 mantendo estritamente CSV, Excel, PDF, Imprimir e Colunas
        const defaultButtons = [
            {
                extend: 'csvHtml5',
                text: '<i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV',
                className: 'btn btn-secondary btn-sm',
                charset: 'utf-8',
                bom: true,
                exportOptions: { columns: ':not(.no-export)' },
                action: function (e, dt, button, config) {
                    try {
                        if ($.fn.dataTable.ext.buttons.html5 && $.fn.dataTable.ext.buttons.html5.action) {
                            $.fn.dataTable.ext.buttons.html5.action.call(this, e, dt, button, config);
                        }
                        notifyExport('Exportação CSV gerada com sucesso!');
                    } catch (err) {
                        console.error('[DataTables CSV Error]', err);
                        notifyExport('Falha ao gerar arquivo CSV.', 'error');
                    }
                }
            },
            {
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-excel me-1"></i> Excel',
                className: 'btn btn-secondary btn-sm',
                title: exportTitle,
                exportOptions: { columns: ':not(.no-export)' },
                action: function (e, dt, button, config) {
                    if (typeof window.JSZip === 'undefined' && typeof JSZip === 'undefined') {
                        notifyExport('Biblioteca JSZip não carregada para gerar Excel.', 'warning');
                        return;
                    }
                    try {
                        if ($.fn.dataTable.ext.buttons.html5 && $.fn.dataTable.ext.buttons.html5.action) {
                            $.fn.dataTable.ext.buttons.html5.action.call(this, e, dt, button, config);
                        }
                        notifyExport('Planilha Excel gerada com sucesso!');
                    } catch (err) {
                        console.error('[DataTables Excel Error]', err);
                        notifyExport('Falha ao gerar planilha Excel.', 'error');
                    }
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="bi bi-file-earmark-pdf me-1"></i> PDF',
                className: 'btn btn-secondary btn-sm',
                title: exportTitle,
                exportOptions: { columns: ':not(.no-export)' },
                action: function (e, dt, button, config) {
                    if (typeof window.pdfMake === 'undefined' && typeof pdfMake === 'undefined') {
                        notifyExport('Biblioteca pdfMake não carregada para gerar PDF.', 'warning');
                        return;
                    }
                    try {
                        if ($.fn.dataTable.ext.buttons.html5 && $.fn.dataTable.ext.buttons.html5.action) {
                            $.fn.dataTable.ext.buttons.html5.action.call(this, e, dt, button, config);
                        }
                        notifyExport('Documento PDF gerado com sucesso!');
                    } catch (err) {
                        console.error('[DataTables PDF Error]', err);
                        notifyExport('Falha ao gerar documento PDF.', 'error');
                    }
                }
            },
            {
                extend: 'print',
                text: '<i class="bi bi-printer me-1"></i> Imprimir',
                className: 'btn btn-secondary btn-sm',
                exportOptions: { columns: ':not(.no-export)' }
            },
            {
                extend: 'colvis',
                text: '<i class="bi bi-eye me-1"></i> Colunas',
                className: 'btn btn-secondary btn-sm'
            }
        ];

        // Configuração oficial do DataTables Responsive no AdminLTE 3 com DOM oficial:
        // Coluna 1 (col-md-6): Botões B à esquerda
        // Coluna 2 (col-md-6): Pesquisa f à direita (justify-content-md-end)
        const defaultOptions = {
            dom: "<'row dt-top-row align-items-center mb-3'<'col-12 col-md-6 d-flex align-items-center flex-wrap'B><'col-12 col-md-6 d-flex align-items-center justify-content-md-end justify-content-start'f>>" +
                 "<'row'<'col-12'tr>>" +
                 "<'row dt-bottom-row align-items-center mt-3'<'col-12 col-md-5'i><'col-12 col-md-7 d-flex justify-content-md-end justify-content-center'p>>",
            responsive: {
                details: {
                    type: 'inline'
                }
            },
            lengthChange: false,
            autoWidth: false,
            language: window.DATATABLES_PTBR,
            pageLength: pageLength,
            buttons: defaultButtons,
            order: [], // Mantém ordem fornecida pelo backend
            stateSave: false
        };

        const finalOptions = $.extend(true, {}, defaultOptions, userOptions);
        const dt = $table.DataTable(finalOptions);

        // Fallback: se os botões não foram injetados pelo DOM 'B', anexa no contêiner da esquerda
        const $wrapper = $table.closest('.dataTables_wrapper');
        if ($wrapper.length && !$wrapper.find('.dt-buttons').length) {
            dt.buttons().container().prependTo($wrapper.find('.col-md-6:eq(0)'));
        }

        // Recalcula responsividade quando a janela é redimensionada
        $(window).off('resize.dt-app-' + ($table.attr('id') || 'gen')).on('resize.dt-app-' + ($table.attr('id') || 'gen'), function () {
            if ($table.is(':visible')) {
                dt.columns.adjust().responsive.recalc();
            }
        });

        return dt;
    };

    // Auto-inicialização em todas as tabelas marcadas
    function autoInit() {
        const $ = global.jQuery || global.$;
        if (!$ || !$.fn || !$.fn.DataTable) {
            // Tenta novamente caso jQuery ainda esteja terminando de carregar
            setTimeout(autoInit, 100);
            return;
        }

        $.fn.dataTable.ext.errMode = 'none';
        $(document).off('error.dt.app').on('error.dt.app', function (e, settings, techNote, message) {
            console.warn('[DataTables Ignored Warning]', { techNote, message });
        });

        $(function () {
            $('table.app-datatable, table[data-datatable="true"]').each(function () {
                window.initAppDataTable(this);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }

})(window);
