// Cache globale articoli (usata per ricerche e popup articoli)
var BSN_ARTICOLI_CACHE = [];

// Cache globale clienti (se ti serve anche fuori dal ready, lasciala qui, altrimenti la puoi mettere dentro)
var BSN_CLIENTI_CACHE = [];

jQuery(document).ready(function($) {

    // ==== TABS ====
    $('.tab-btn').on('click', function() {
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');

        $('.tab-content').removeClass('active');
        $('#' + $(this).data('tab')).addClass('active');
    });

    // Utility per formattare i correlati nella tabella articoli
    function bsnFormattaCorrelati(correlati_json) {
        try {
            var correlati = JSON.parse(correlati_json);
            if (!Array.isArray(correlati) || correlati.length === 0) return '-';

            var testo = correlati.map(function(c) {
                return c.qty + 'x ' + c.nome;
            }).join(', ');

            return testo.length > 30 ? testo.substring(0, 30) + '...' : testo;
        } catch (e) {
            return 'JSON errore';
        }
    }

    // Cache per ricerca nel form noleggi
    // (QUI NON ridichiarare con var, usiamo le globali)
    BSN_CLIENTI_CACHE = [];
    BSN_ARTICOLI_CACHE = [];

    /* ==== CLIENTI ==== */

    // Submit form cliente (create / future update)
    $('#bsn-form-cliente').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);

        var dati = {
    cliente_id:         $form.find('input[name="cliente_id"]').val(),
    nome:               $form.find('input[name="nome"]').val(),
    cf_piva:            $form.find('input[name="cf_piva"]').val(),
    telefono:           $form.find('input[name="telefono"]').val(),
    indirizzo:          $form.find('input[name="indirizzo"]').val(),
    cap:                $form.find('input[name="cap"]').val(),
    citta:              $form.find('input[name="citta"]').val(),
    email:              $form.find('input[name="email"]').val(),
    categoria_cliente:  $form.find('select[name="categoria_cliente"]').val(),
    regime_percentuale: $form.find('input[name="regime_percentuale"]').val(),
    regime_note:        $form.find('input[name="regime_note"]').val(),
    doc_fronte:         $form.find('input[name="doc_fronte"]').val(),
    doc_retro:          $form.find('input[name="doc_retro"]').val(),
    // NUOVO:
    tipo_documento:     $form.find('select[name="tipo_documento"]').val(),
    numero_documento:   $form.find('input[name="numero_documento"]').val(),
    note:               $form.find('textarea[name="note"]').val()
};


        if (!dati.nome) {
            alert('Inserisci il nome / ragione sociale.');
            return;
        }
        if (!dati.cf_piva) {
            alert('Inserisci CF / P.IVA.');
            return;
        }
        if (!dati.telefono) {
            alert('Inserisci il telefono.');
            return;
        }
        if (!dati.email) {
            alert('Inserisci la email.');
            return;
        }

                // Decide se creare o aggiornare
        var urlEndpoint = BSN_API.root + 'clienti';
        var azione      = 'creato';

        if (dati.cliente_id) {
            urlEndpoint = BSN_API.root + 'clienti/update';
            azione      = 'aggiornato';
        }

        $.ajax({
            url: urlEndpoint,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            data: dati,
            success: function(response) {
                alert('Cliente ' + azione + ' correttamente.');
                $form[0].reset();
                $('#bsn-cliente-id').val('');
                $('#bsn-doc-fronte-preview').empty();
                $('#bsn-doc-retro-preview').empty();
                bsnCaricaClienti();
            },
            error: function(err) {
                console.error(err);
                alert('Errore nel salvataggio cliente.');
            }
        });
    });

    function bsnCaricaClienti() {
        $.ajax({
            url: BSN_API.root + 'clienti',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            success: function(response) {
                // cache per ricerca clienti
                BSN_CLIENTI_CACHE = response.clienti || [];

                var wrapper = $('#bsn-lista-clienti');
                wrapper.empty();

                if (!response.clienti || !response.clienti.length) {
                    wrapper.html('<p>Nessun cliente salvato.</p>');
                    return;
                }

                var html = '' +
                    '<table class="bsn-tabella" id="bsn-clienti-table">' +
                        '<thead><tr>' +
                            '<th>ID</th>' +
                            '<th>Nome</th>' +
                            '<th>CF/P.IVA</th>' +
                            '<th>Telefono</th>' +
                            '<th>Email</th>' +
                            '<th>Categoria</th>' +
                            '<th>Azioni</th>' +
                        '</tr></thead>' +
                        '<tbody>';

                var maxRows = 12;
                var lista = response.clienti.slice(0, maxRows);

                lista.forEach(function(c) {
                    html += '' +
                        '<tr class="bsn-cliente-row" data-id="' + c.id + '">' +
                            '<td>' + c.id + '</td>' +
                            '<td>' + (c.nome || '') + '</td>' +
                            '<td>' + (c.cf_piva || '') + '</td>' +
                            '<td>' + (c.telefono || '') + '</td>' +
                            '<td>' + (c.email || '') + '</td>' +
                            '<td>' + (c.categoria_cliente || '') + '</td>' +
                            '<td>' +
                                '<button type="button" class="btn bsn-cliente-details">Dettagli</button> ' +
                                '<button type="button" class="btn bsn-cliente-edit">Modifica</button> ' +
                                '<button type="button" class="btn bsn-cliente-delete">Elimina</button>' +
                            '</td>' +
                        '</tr>' +
                        '<tr class="bsn-cliente-row-details" data-id="' + c.id + '" style="display:none;">' +
                            '<td colspan="7">' +
                                '<div class="bsn-cliente-details-container"></div>' +
                            '</td>' +
                        '</tr>';
                });

                html += '</tbody></table>';
                wrapper.html(html);
            },
            error: function(err) {
                console.error(err);
                $('#bsn-lista-clienti').html('<p>Errore nel caricare i clienti.</p>');
            }
        });
    }

    // Ricerca live sui clienti
    $(document).on('input', '#bsn-clienti-search', function() {
        var testo = ($(this).val() || '').toLowerCase();

        $('#bsn-clienti-table tbody tr.bsn-cliente-row').each(function() {
            var $row = $(this);
            var text = $row.text().toLowerCase();
            var visibile = !testo || text.indexOf(testo) !== -1;
            $row.toggle(visibile);

            var id = $row.data('id');
            var $details = $('.bsn-cliente-row-details[data-id="' + id + '"]');
            if (!visibile) {
                $details.hide();
            }
        });
    });

    // Dettagli cliente
    $(document).on('click', '.bsn-cliente-details', function() {
        var $row = $(this).closest('.bsn-cliente-row');
        var id   = $row.data('id');
        var $detailsTr = $('.bsn-cliente-row-details[data-id="' + id + '"]');
        var $container = $detailsTr.find('.bsn-cliente-details-container');

        if (!id) return;

        if ($detailsTr.is(':visible')) {
            $detailsTr.hide();
            return;
        }
        $detailsTr.show();

        // Recupera cliente dalla cache
        var cliente = BSN_CLIENTI_CACHE.find(function(c){ return parseInt(c.id,10) === parseInt(id,10); });
        if (!cliente) {
            $container.html('<em>Dati cliente non trovati.</em>');
            return;
        }

                var html = '';
        html += '<div><strong>Nome:</strong> ' + (cliente.nome || '') + '</div>';

        if (cliente.indirizzo || cliente.cap || cliente.citta) {
            html += '<div><strong>Indirizzo:</strong> ' +
                (cliente.indirizzo || '') +
                '</div>';
        }

        if (cliente.categoria_cliente) {
            html += '<div><strong>Categoria:</strong> ' + cliente.categoria_cliente + '</div>';
        }

        // Regime fiscale: percentuale + note, se presenti
        if (cliente.regime_percentuale !== null && cliente.regime_percentuale !== undefined) {
            html += '<div><strong>Regime fiscale:</strong> ' +
                cliente.regime_percentuale + '%' +
                (cliente.regime_note ? ' (' + cliente.regime_note + ')' : '') +
                '</div>';
        } else if (cliente.regime_note) {
            html += '<div><strong>Regime fiscale:</strong> ' + cliente.regime_note + '</div>';
        }

        if (cliente.note) {
            html += '<div style="margin-top:5px;"><strong>Note:</strong><br>' +
                cliente.note.replace(/\n/g, '<br>') +
                '</div>';
        }

        // Documenti
if (cliente.doc_fronte || cliente.doc_retro || cliente.tipo_documento || cliente.numero_documento) {
    html += '<div style="margin-top:5px;"><strong>Documento:</strong><br>';

    // Riga tipo + numero
    if (cliente.tipo_documento || cliente.numero_documento) {
        var tipoLabel = '';
        switch (cliente.tipo_documento) {
            case 'carta_identita':
                tipoLabel = 'Carta d\'Identità';
                break;
            case 'patente':
                tipoLabel = 'Patente di guida';
                break;
            case 'passaporto':
                tipoLabel = 'Passaporto';
                break;
            case 'permesso_soggiorno':
                tipoLabel = 'Permesso di soggiorno';
                break;
            default:
                tipoLabel = cliente.tipo_documento || '';
                break;
        }

        html += '<div style="margin-bottom:4px;">';
        if (tipoLabel) {
            html += '<strong>Tipo:</strong> ' + tipoLabel;
        }
        if (cliente.numero_documento) {
            html += (tipoLabel ? ' – ' : '') + '<strong>Numero:</strong> ' + cliente.numero_documento;
        }
        html += '</div>';
    }

    // Immagini fronte/retro
    if (cliente.doc_fronte) {
        html += '<img src="' + cliente.doc_fronte + '" alt="Documento fronte" style="max-width:160px; margin-right:10px;">';
    }
    if (cliente.doc_retro) {
        html += '<img src="' + cliente.doc_retro + '" alt="Documento retro" style="max-width:160px;">';
    }

    html += '</div>';
}

        $container.html(html);
    });

    // Dettagli articolo
$(document).on('click', '.bsn-articolo-details', function() {
    var $row      = $(this).closest('.bsn-articolo-row');
    var id        = $row.data('id');
    var $detailsTr = $('.bsn-articolo-row-details[data-id="' + id + '"]');
    var $container = $detailsTr.find('.bsn-articolo-details-container');

    if (!id) return;

    // toggle base
    if ($detailsTr.is(':visible')) {
        $detailsTr.hide();
        return;
    }
    $detailsTr.show();

    // recupera articolo dalla cache
    var art = BSN_ARTICOLI_CACHE.find(function(a) {
        return parseInt(a.id, 10) === parseInt(id, 10);
    });

    if (!art) {
        $container.html('<em>Dati articolo non trovati.</em>');
        return;
    }

    var html = '';

    html += '<div><strong>Nome:</strong> ' + (art.nome || '') + '</div>';
    html += '<div><strong>Codice:</strong> ' + (art.codice || '') + '</div>';

    html += '<div><strong>Prezzo/giorno:</strong> ' +
        (art.prezzo_giorno || '0') + ' €</div>';

    html += '<div><strong>Valore del bene:</strong> ' +
        (art.valore_bene || '0') + ' €</div>';

    html += '<div><strong>Quantità disponibile:</strong> ' +
        (art.qty_disponibile || '0') + '</div>';

    if (art.ubicazione) {
        html += '<div><strong>Ubicazione:</strong> ' +
            art.ubicazione + '</div>';
    }

    // Sconti
    html += '<div style="margin-top:5px;"><strong>Sconti per categoria:</strong><br>' +
        'Standard: ' + (art.sconto_standard || 0) + '% – ' +
        'Fidato: ' + (art.sconto_fidato || 0) + '% – ' +
        'Premium: ' + (art.sconto_premium || 0) + '% – ' +
        'Service: ' + (art.sconto_service || 0) + '% – ' +
        'Collaboratori: ' + (art.sconto_collaboratori || 0) + '%' +
        '</div>';

    // Correlati dettagliati
    if (art.correlati) {
        try {
            var corr = JSON.parse(art.correlati);
            if (Array.isArray(corr) && corr.length) {
                html += '<div style="margin-top:5px;"><strong>Correlati:</strong><ul style="margin:3px 0 0 18px; padding:0;">';
                corr.forEach(function(c) {
                    var q = c.qty || 1;
                    var n = c.nome || '';
                    html += '<li>' + q + 'x ' + n + '</li>';
                });
                html += '</ul></div>';
            }
        } catch (e) {}
    }

    if (art.note) {
        html += '<div style="margin-top:5px;"><strong>Note:</strong><br>' +
            art.note.replace(/\n/g, '<br>') +
            '</div>';
    }

    $container.html(html);
    });

    // Modifica cliente: carica dati nel form
    $(document).on('click', '.bsn-cliente-edit', function() {
        var $row = $(this).closest('.bsn-cliente-row');
        var id   = $row.data('id');

        if (!id) return;

        var cliente = BSN_CLIENTI_CACHE.find(function(c){ return parseInt(c.id,10) === parseInt(id,10); });
        if (!cliente) {
            alert('Dati cliente non trovati.');
            return;
        }

        var $form = $('#bsn-form-cliente');

        $('#bsn-cliente-id').val(cliente.id);
        $form.find('input[name="nome"]').val(cliente.nome || '');
        $form.find('input[name="cognome"]').val(cliente.cognome || '');
        $form.find('input[name="cf_piva"]').val(cliente.cf_piva || '');
        $form.find('input[name="telefono"]').val(cliente.telefono || '');
        $form.find('input[name="indirizzo"]').val(cliente.indirizzo || '');
        $form.find('input[name="cap"]').val(cliente.cap || '');
        $form.find('input[name="citta"]').val(cliente.citta || '');
        $form.find('input[name="email"]').val(cliente.email || '');
        $form.find('select[name="categoria_cliente"]').val(cliente.categoria_cliente || 'standard');
        $form.find('input[name="regime_percentuale"]').val(cliente.regime_percentuale || 22.00);
        $form.find('input[name="regime_note"]').val(cliente.regime_note || '');
        $form.find('textarea[name="note"]').val(cliente.note || '');

        // Documenti
        $('#bsn-doc-fronte-path').val(cliente.doc_fronte || '');
        $('#bsn-doc-retro-path').val(cliente.doc_retro || '');

        var $prevF = $('#bsn-doc-fronte-preview').empty();
        var $prevR = $('#bsn-doc-retro-preview').empty();
        if (cliente.doc_fronte) {
            $prevF.html('<img src="' + cliente.doc_fronte + '" alt="Documento fronte" style="max-width:160px;">');
        }
        if (cliente.doc_retro) {
            $prevR.html('<img src="' + cliente.doc_retro + '" alt="Documento retro" style="max-width:160px;">');
        }

        $('html, body').animate({
            scrollTop: $('#clienti').offset().top - 20
        }, 300);
    });

        // Elimina cliente
    $(document).on('click', '.bsn-cliente-delete', function() {
        var $row = $(this).closest('.bsn-cliente-row');
        var id   = $row.data('id');

        if (!id) return;

        if (!confirm('Vuoi eliminare definitivamente questo cliente?')) {
            return;
        }

        $.ajax({
            url: BSN_API.root + 'clienti/delete',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            data: { id: id },
            success: function(response) {
                alert('Cliente eliminato.');
                bsnCaricaClienti();
            },
            error: function(err) {
                console.error(err);
                alert('Errore nell\'eliminazione cliente.');
            }
        });
    });

        // Upload documento FRONTE
    $(document).on('change', '#bsn-doc-fronte-file', function() {
        var file = this.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('file', file);

        $.ajax({
            url: BSN_API.root + 'upload-doc',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            success: function(response) {
                if (response && response.url) {
                    $('#bsn-doc-fronte-path').val(response.url);
                    $('#bsn-doc-fronte-preview').html(
                        '<img src="' + response.url + '" alt="Documento fronte" style="max-width:160px;">'
                    );
                } else {
                    alert('Upload documento fronte riuscito ma URL non trovato.');
                }
            },
            error: function(err) {
                console.error(err);
                alert('Errore nell\'upload del documento fronte.');
            }
        });
    });

    // Upload documento RETRO
    $(document).on('change', '#bsn-doc-retro-file', function() {
        var file = this.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('file', file);

        $.ajax({
            url: BSN_API.root + 'upload-doc',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            success: function(response) {
                if (response && response.url) {
                    $('#bsn-doc-retro-path').val(response.url);
                    $('#bsn-doc-retro-preview').html(
                        '<img src="' + response.url + '" alt="Documento retro" style="max-width:160px;">'
                    );
                } else {
                    alert('Upload documento retro riuscito ma URL non trovato.');
                }
            },
            error: function(err) {
                console.error(err);
                alert('Errore nell\'upload del documento retro.');
            }
        });
    });

    /* ==== ARTICOLI ==== */

    // Gestione UI correlati (aggiungi/rimuovi righe)
    $('#bsn-correlato-add').on('click', function() {
        var $wrapper = $('#bsn-correlati-wrapper');
        var $row = $(
            '<div class="bsn-correlato-row">' +
                '<input type="text" name="correlato_nome[]" placeholder="Nome correlato">' +
                '<input type="number" name="correlato_qty[]" placeholder="Q.tà" min="1" value="1" style="max-width:80px;">' +
                '<button type="button" class="btn bsn-correlato-remove">X</button>' +
            '</div>'
        );
        $wrapper.append($row);
    });

    $(document).on('click', '.bsn-correlato-remove', function() {
        $(this).closest('.bsn-correlato-row').remove();
    });

        // Autogenerazione codice da nome (se codice vuoto)
    $(document).on('blur', '#bsn-form-articolo input[name="nome"]', function() {
        var nome = $(this).val().trim();
        var $codice = $('#bsn-form-articolo input[name="codice"]');
        var codiceAttuale = $codice.val().trim();

        if (!nome || codiceAttuale) {
            return;
        }

        var base = nome.replace(/\s+/g, '').toUpperCase();
        $codice.val(base + '#1');
    });

        // Submit form articolo (create / update con correlati)
    $('#bsn-form-articolo').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);

        var dati = {
        nome:           $form.find('input[name="nome"]').val(),
        codice:         $form.find('input[name="codice"]').val(),
        prezzo_giorno:  $form.find('input[name="prezzo_giorno"]').val(),
        valore_bene:    $form.find('input[name="valore_bene"]').val(),

        sconto_standard:      $form.find('input[name="sconto_standard"]').val(),
        sconto_fidato:        $form.find('input[name="sconto_fidato"]').val(),
        sconto_premium:       $form.find('input[name="sconto_premium"]').val(),
        sconto_service:       $form.find('input[name="sconto_service"]').val(),
        sconto_collaboratori: $form.find('input[name="sconto_collaboratori"]').val(),

        noleggio_scalare: $form.find('input[name="noleggio_scalare"]').is(':checked') ? 1 : 0,

        qty_disponibile: $form.find('input[name="qty_disponibile"]').val(),
        ubicazione:      $form.find('input[name="ubicazione"]').val(),
        note:            $form.find('textarea[name="note"]').val()
    };

        // Costruisci array correlati da input multipli
        var correlati = [];
        $('#bsn-correlati-wrapper .bsn-correlato-row').each(function() {
            var nomeCorr = $(this).find('input[name="correlato_nome[]"]').val();
            var qtyCorr = $(this).find('input[name="correlato_qty[]"]').val();
            if (nomeCorr && qtyCorr > 0) {
                correlati.push({
                    nome: nomeCorr,
                    qty: parseInt(qtyCorr, 10) || 1
                });
            }
        });
        dati.correlati = JSON.stringify(correlati);

        if (!dati.nome || !dati.codice) {
            alert('Nome e codice articolo sono obbligatori.');
            return;
        }

        // Controlla se siamo in modalità UPDATE (modifica)
        var articoloId = $form.data('articolo-id');
        var urlEndpoint = BSN_API.root + 'articoli';
        var method = 'POST';
        var azione = 'creato';

        if (articoloId) {
            urlEndpoint = BSN_API.root + 'articoli';
            method = 'PUT';
            dati.id = articoloId;
            azione = 'aggiornato';
        }

        $.ajax({
            url: urlEndpoint,
            method: method,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            data: dati,
            success: function(response) {
                alert('Articolo ' + azione + ' correttamente.');

                // reset form
                $form[0].reset();
                $form.removeData('articolo-id');

                // ricrea una riga correlato base
                $('#bsn-correlati-wrapper').html(
                    '<div class="bsn-correlato-row">' +
                        '<input type="text" name="correlato_nome[]" placeholder="Nome correlato">' +
                        '<input type="number" name="correlato_qty[]" placeholder="Q.tà" min="1" value="1" style="max-width:80px;">' +
                        '<button type="button" class="btn bsn-correlato-remove">X</button>' +
                    '</div>'
                );

                bsnCaricaArticoli();
            },
            error: function(err) {
                console.error(err);
                alert('Errore nel salvataggio articolo.');
            }
        });
    });

    function bsnCaricaArticoli() {
    $.ajax({
        url: BSN_API.root + 'articoli',
        method: 'GET',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
        },
        success: function(response) {
            // cache per ricerca articoli nel noleggio
            BSN_ARTICOLI_CACHE = response.articoli || [];

            var wrapper = $('#bsn-lista-articoli');
            wrapper.empty();

            if (!response.articoli || !response.articoli.length) {
                wrapper.html('<p>Nessun articolo salvato.</p>');
                return;
            }

            var html = '' +
                '<table class="bsn-tabella" id="bsn-articoli-table">' +
                    '<thead><tr>' +
                        '<th>ID</th>' +
                        '<th>Nome</th>' +
                        '<th>Codice</th>' +
                        '<th>Prezzo/giorno</th>' +
                        '<th>Valore bene</th>' +
                        '<th>Q.tà</th>' +
                        '<th>Ubicazione</th>' +
                        '<th>Correlati</th>' +
                        '<th>Azioni</th>' +
                    '</tr></thead>' +
                    '<tbody>';

            var maxRows = 12;
            var lista = response.articoli.slice(0, maxRows);

            lista.forEach(function(a) {
        html += '' +
        '<tr class="bsn-articolo-row" data-id="' + a.id + '" data-codice="' + (a.codice || '') + '">' +
            '<td>' + a.id + '</td>' +
            '<td>' + (a.nome || '') + '</td>' +
            '<td>' + (a.codice || '') + '</td>' +
            '<td style="text-align:right;">' + (a.prezzo_giorno || '0') + '</td>' +
            '<td style="text-align:right;">' + (a.valore_bene || '0') + '</td>' +
            '<td style="text-align:center;">' + (a.qty_disponibile || '0') + '</td>' +
            '<td>' + (a.ubicazione || '') + '</td>' +
            '<td>' + (a.correlati ? bsnFormattaCorrelati(a.correlati) : '-') + '</td>' +
            '<td style="white-space:nowrap;">' +
                '<button type="button" class="btn bsn-articolo-details">Dettagli</button> ' +
                '<button type="button" class="btn bsn-articolo-qr" data-codice="' + (a.codice || '') + '">📱 QR</button> ' +
                '<button type="button" class="btn bsn-articolo-edit">✏️ Modifica</button> ' +
                '<button type="button" class="btn bsn-articolo-duplica">📄 Duplica</button> ' +
                '<button type="button" class="btn bsn-articolo-clona">🔁 Clona</button> ' +
                '<button type="button" class="btn bsn-articolo-delete" style="color:#b00;">🗑️ Elimina</button>' +
            '</td>' +
        '</tr>' +
        '<tr class="bsn-articolo-row-details" data-id="' + a.id + '" style="display:none;">' +
            '<td colspan="9">' +
                '<div class="bsn-articolo-details-container"></div>' +
            '</td>' +
        '</tr>';
        });

            html += '</tbody></table>';
            wrapper.html(html);

        },
        error: function(err) {
            console.error(err);
            $('#bsn-lista-articoli').html('<p>Errore nel caricare gli articoli.</p>');
        }
    });
}

/// Click pulsante "Genera QR"
$(document).on('click', '.bsn-articolo-qr', function() {
    var codice = $(this).data('codice');

    if (!codice) {
        alert('Codice articolo non trovato.');
        return;
    }

    // Pulisci il container precedente
    $('#bsn-qr-container').empty();
    $('#bsn-qr-code-text').text(codice);

    // Genera QR code dentro il box 180x180 (dimensione normale)
    new QRCode(document.getElementById('bsn-qr-container'), {
        text: codice,
        width: 150,
        height: 150,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });

    // Mostra il modal centrato (flex)
    $('#bsn-modal-qr')
        .css('display', 'flex')
        .hide()
        .fadeIn();
});

   // Click pulsante "Chiudi" nel modal
    $(document).on('click', '#bsn-qr-close', function() {
    $('#bsn-modal-qr').fadeOut();
});



    // Click pulsante "Stampa QR" (versione semplice)
    $(document).on('click', '#bsn-qr-print', function() {
    var qrHtml  = $('#bsn-qr-container').html();
    var qrLabel = $('#bsn-qr-code-text').text() || '';

    var printWindow = window.open('', '', 'width=400,height=400');

    printWindow.document.write('<html><head><title>Stampa QR</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { margin: 20px; padding: 0; text-align:center; font-family: Arial, sans-serif; }');
    printWindow.document.write('.qr-img { margin-bottom: 10px; }');
    printWindow.document.write('.qr-label { font-size: 12px; font-weight: bold; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');

    printWindow.document.write('<div class="qr-img">' + qrHtml + '</div>');
    if (qrLabel) {
        printWindow.document.write('<div class="qr-label">' + qrLabel + '</div>');
    }

    printWindow.document.write('</body></html>');
    printWindow.document.close();

    setTimeout(function() {
        printWindow.focus();
        printWindow.print();
    }, 250);
});

    // ===== HANDLER AZIONI ARTICOLI =====

    // Modifica: carica i dati nel form articoli
    $(document).on('click', '.bsn-articolo-edit', function() {
        var $row = $(this).closest('tr');
        var id   = $row.data('id');

        if (!id) return;

        var art = BSN_ARTICOLI_CACHE.find(function(a){
            return parseInt(a.id,10) === parseInt(id,10);
        });
        if (!art) {
            alert('Articolo non trovato in cache.');
            return;
        }

        var $form = $('#bsn-form-articolo');

        $form.find('input[name="nome"]').val(art.nome || '');
        $form.find('input[name="codice"]').val(art.codice || '');
        $form.find('input[name="prezzo_giorno"]').val(art.prezzo_giorno || '');

        $form.find('input[name="sconto_standard"]').val(art.sconto_standard || 0);
        $form.find('input[name="sconto_fidato"]').val(art.sconto_fidato || 0);
        $form.find('input[name="sconto_premium"]').val(art.sconto_premium || 0);
        $form.find('input[name="sconto_service"]').val(art.sconto_service || 0);
        $form.find('input[name="sconto_collaboratori"]').val(art.sconto_collaboratori || 0);

        $form.find('input[name="qty_disponibile"]').val(art.qty_disponibile || 1);
        $form.find('input[name="ubicazione"]').val(art.ubicazione || '');
        $form.find('textarea[name="note"]').val(art.note || '');
        $form.data('articolo-id', id);

        // Ricostruisci correlati nel form
        $('#bsn-correlati-wrapper').empty();
        if (art.correlati) {
            try {
                var corr = JSON.parse(art.correlati);
                if (Array.isArray(corr) && corr.length) {
                    corr.forEach(function(c) {
                        var rowHtml =
                            '<div class="bsn-correlato-row">' +
                                '<input type="text" name="correlato_nome[]" value="' + (c.nome || '') + '" placeholder="Nome correlato">' +
                                '<input type="number" name="correlato_qty[]" value="' + (c.qty || 1) + '" placeholder="Q.tà" min="1" style="max-width:80px;">' +
                                '<button type="button" class="btn bsn-correlato-remove">X</button>' +
                            '</div>';
                        $('#bsn-correlati-wrapper').append(rowHtml);
                    });
                } else {
                    $('#bsn-correlati-wrapper').html(
                        '<div class="bsn-correlato-row">' +
                            '<input type="text" name="correlato_nome[]" placeholder="Nome correlato">' +
                            '<input type="number" name="correlato_qty[]" placeholder="Q.tà" min="1" value="1" style="max-width:80px;">' +
                            '<button type="button" class="btn bsn-correlato-remove">X</button>' +
                        '</div>'
                    );
                }
            } catch(e) {
                $('#bsn-correlati-wrapper').html(
                    '<div class="bsn-correlato-row">' +
                        '<input type="text" name="correlato_nome[]" placeholder="Nome correlato">' +
                        '<input type="number" name="correlato_qty[]" placeholder="Q.tà" min="1" value="1" style="max-width:80px;">' +
                        '<button type="button" class="btn bsn-correlato-remove">X</button>' +
                    '</div>'
                );
            }
        } else {
            $('#bsn-correlati-wrapper').html(
                '<div class="bsn-correlato-row">' +
                    '<input type="text" name="correlato_nome[]" placeholder="Nome correlato">' +
                    '<input type="number" name="correlato_qty[]" placeholder="Q.tà" min="1" value="1" style="max-width:80px;">' +
                    '<button type="button" class="btn bsn-correlato-remove">X</button>' +
                '</div>'
            );
        }

        // Flag scalare
        $form.find('input[name="noleggio_scalare"]').prop('checked', parseInt(art.noleggio_scalare,10) === 1);

        $('html, body').animate({
            scrollTop: $('#articoli').offset().top - 20
        }, 300);
    });

    // Duplica: copia dati nel form ma SENZA id (nuovo articolo)
    $(document).on('click', '.bsn-articolo-duplica', function() {
        var $row = $(this).closest('tr');
        var id   = $row.data('id');

        if (!id) return;

        var art = BSN_ARTICOLI_CACHE.find(function(a){
            return parseInt(a.id,10) === parseInt(id,10);
        });
        if (!art) {
            alert('Articolo non trovato in cache.');
            return;
        }

        var $form = $('#bsn-form-articolo');

        $form.find('input[name="nome"]').val(art.nome || '');
        $form.find('input[name="codice"]').val(art.codice || '');
        $form.find('input[name="prezzo_giorno"]').val(art.prezzo_giorno || '');

        $form.find('input[name="sconto_standard"]').val(art.sconto_standard || 0);
        $form.find('input[name="sconto_fidato"]').val(art.sconto_fidato || 0);
        $form.find('input[name="sconto_premium"]').val(art.sconto_premium || 0);
        $form.find('input[name="sconto_service"]').val(art.sconto_service || 0);
        $form.find('input[name="sconto_collaboratori"]').val(art.sconto_collaboratori || 0);

        $form.find('input[name="qty_disponibile"]').val(art.qty_disponibile || 1);
        $form.find('input[name="ubicazione"]').val(art.ubicazione || '');
        $form.find('textarea[name="note"]').val(art.note || '');
        $form.removeData('articolo-id'); // nuovo record

        // Correlati come sopra (riuso la logica, semplifico)
        $('#bsn-correlati-wrapper').empty();
        if (art.correlati) {
            try {
                var corr = JSON.parse(art.correlati);
                if (Array.isArray(corr) && corr.length) {
                    corr.forEach(function(c) {
                        var rowHtml =
                            '<div class="bsn-correlato-row">' +
                                '<input type="text" name="correlato_nome[]" value="' + (c.nome || '') + '" placeholder="Nome correlato">' +
                                '<input type="number" name="correlato_qty[]" value="' + (c.qty || 1) + '" placeholder="Q.tà" min="1" style="max-width:80px;">' +
                                '<button type="button" class="btn bsn-correlato-remove">X</button>' +
                            '</div>';
                        $('#bsn-correlati-wrapper').append(rowHtml);
                    });
                }
            } catch(e) {}
        }

        $form.find('input[name="noleggio_scalare"]').prop('checked', parseInt(art.noleggio_scalare,10) === 1);

        $('html, body').animate({
            scrollTop: $('#articoli').offset().top - 20
        }, 300);
    });

    // Clona lato server (incrementa codice: CDJ2000#1 -> CDJ2000#2)
    $(document).on('click', '.bsn-articolo-clona', function() {
        var $row = $(this).closest('tr');
        var id   = $row.data('id');

        if (!id) return;

        if (!confirm('Vuoi clonare questo articolo incrementando il codice?')) {
            return;
        }

        $.ajax({
            url: BSN_API.root + 'articoli/clone',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            data: { id: id },
            success: function(response) {
                alert('Articolo clonato. Nuovo ID: ' + response.id + (response.codice ? ' (codice: ' + response.codice + ')' : ''));
                bsnCaricaArticoli();
            },
            error: function(err) {
                console.error(err);
                alert('Errore nella clonazione articolo.');
            }
        });
    });

    // Elimina articolo
    $(document).on('click', '.bsn-articolo-delete', function() {
        var $row = $(this).closest('tr');
        var id   = $row.data('id');

        if (!id) return;

        if (!confirm('Vuoi eliminare definitivamente questo articolo?')) {
            return;
        }

        $.ajax({
            url: BSN_API.root + 'articoli/delete',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            data: { id: id },
            success: function(response) {
                alert('Articolo eliminato.');
                bsnCaricaArticoli();
            },
            error: function(err) {
                console.error(err);
                alert('Errore nell\'eliminazione articolo.');
            }
        });
    });

    /* ==== NOLEGGI ==== */

    /* ==== FUNZIONE CALCOLO TOTALE NOLEGGIO (preview) ==== */
    
    /**
     * Calcola il totale noleggio in JS con la stessa logica di PHP:
     * - articoli scalari: prezzo_netto * qty * sqrt(giorni)
     * - articoli non scalari: prezzo_netto * qty
     * - regime fiscale applicato al subtotale
     */
        /* ==== FUNZIONE CALCOLO TOTALE NOLEGGIO (preview) ==== */
    
    function bsnCalcolaTotaleNoleggio() {
        // 1. Recupera cliente_id
        var clienteId = $('#bsn-noleggio-cliente-id').val();
        if (!clienteId) {
            $('#bsn-noleggio-preview').hide();
            return;
        }

        // Trova il cliente in cache
        var cliente = BSN_CLIENTI_CACHE.find(function(c) {
            return parseInt(c.id, 10) === parseInt(clienteId, 10);
        });

        if (!cliente) {
            $('#bsn-noleggio-preview').hide();
            return;
        }

        // 2. Recupera date e calcola giorni
        var dataInizio = $('#bsn-form-noleggio input[name="data_inizio"]').val();
        var dataFine   = $('#bsn-form-noleggio input[name="data_fine"]').val();

        if (!dataInizio || !dataFine) {
            $('#bsn-noleggio-preview').hide();
            return;
        }

        var tsInizio = new Date(dataInizio + 'T00:00:00').getTime();
        var tsFine   = new Date(dataFine + 'T00:00:00').getTime();

        if (tsInizio >= tsFine) {
            $('#bsn-noleggio-preview').hide();
            return;
        }

        var giorni = Math.max(1, Math.ceil((tsFine - tsInizio) / (1000 * 86400)));
        var fattoreGiorni = Math.sqrt(giorni);

        // 3. Raccogli articoli dal form
        var articoli = [];
        $('#bsn-noleggio-articoli-wrapper .bsn-noleggio-articolo-row').each(function() {
            var id     = $(this).find('.bsn-noleggio-articolo-id').val();
            var qty    = parseInt($(this).find('.bsn-noleggio-articolo-qty').val(), 10) || 0;
            var prezzo = parseFloat($(this).find('.bsn-noleggio-articolo-prezzo').val()) || null;

            if (id && qty > 0) {
                articoli.push({
                    id:     parseInt(id, 10),
                    qty:    qty,
                    prezzo: prezzo  // prezzo custom dalla riga
                });
            }
        });

        if (articoli.length === 0) {
            $('#bsn-noleggio-preview').hide();
            return;
        }

        // 4. Popola intestazione cliente
        $('#bsn-preview-cliente-nome').text(cliente.nome || '-');
        $('#bsn-preview-cliente-cf').text(cliente.cf_piva || '-');
        $('#bsn-preview-cliente-categoria').text((cliente.categoria_cliente || 'standard').toUpperCase());
        
        // Note cliente
        if (cliente.note) {
            $('#bsn-preview-cliente-note').text(cliente.note);
            $('#bsn-preview-note-cliente').show();
        } else {
            $('#bsn-preview-note-cliente').hide();
        }

        // Date in formato italiano
        var dataInizioIT = bsnConvertiDataPerPrintStr(dataInizio); // es. 16/01/2026
        var dataFineIT   = bsnConvertiDataPerPrintStr(dataFine);

        $('#bsn-preview-data-da').text(dataInizioIT);
        $('#bsn-preview-data-a').text(dataFineIT);
        $('#bsn-preview-giorni').text(giorni);

        // Popola dati logistica/trasporto
        var luogoDest = $('#bsn-form-noleggio input[name="luogo_destinazione"]').val() || '';
        var trasporto = $('#bsn-form-noleggio input[name="trasporto_mezzo"]').val() || '';
        var cauzione = $('#bsn-form-noleggio input[name="cauzione"]').val() || '';
        var causale = $('#bsn-form-noleggio input[name="causale_trasporto"]').val() || '';

        if (luogoDest || trasporto || cauzione || causale) {
            $('#bsn-preview-luogo-dest').text(luogoDest || '-');
            $('#bsn-preview-trasporto').text(trasporto || '-');
            $('#bsn-preview-cauzione').text(cauzione || '-');
            $('#bsn-preview-causale').text(causale || '-');
            $('#bsn-preview-logistica').show();
        } else {
            $('#bsn-preview-logistica').hide();
        }

        // Regime
        var regime = parseFloat(cliente.regime_percentuale) || 0.0;
        $('#bsn-preview-regime-perc').text(regime.toFixed(2));

        // 5. Calcola articoli e popola tabella
        var categoria = cliente.categoria_cliente || 'standard';
        var subtotale = 0.0;
        var htmlTabella = '';
        var htmlDettagli = '';

        articoli.forEach(function(a) {
            var art = BSN_ARTICOLI_CACHE.find(function(x) {
                return parseInt(x.id, 10) === parseInt(a.id, 10);
            });

            if (!art) {
                return;
            }

            // Usa prezzo custom se presente, altrimenti prezzo base articolo
            var prezzo = (a.prezzo !== null && a.prezzo !== undefined) ? a.prezzo : (parseFloat(art.prezzo_giorno) || 0);
            var qty = a.qty;


            // Sconto categoria
            var sconto = 0;
            switch (categoria) {
                case 'fidato':
                    sconto = parseFloat(art.sconto_fidato) || 0;
                    break;
                case 'premium':
                    sconto = parseFloat(art.sconto_premium) || 0;
                    break;
                case 'service':
                    sconto = parseFloat(art.sconto_service) || 0;
                    break;
                case 'collaboratori':
                    sconto = parseFloat(art.sconto_collaboratori) || 0;
                    break;
                case 'standard':
                default:
                    sconto = parseFloat(art.sconto_standard) || 0;
                    break;
            }

            var prezzoNetto = prezzo * (1 - (Math.max(0, Math.min(100, sconto)) / 100));

            // Fattore giorni
            var noleggio_scalare = parseInt(art.noleggio_scalare, 10);
            var fattore = '';
            var subtotalArticolo = 0;

            if (noleggio_scalare === 1) {
                fattore = '√' + giorni + ' (' + fattoreGiorni.toFixed(3) + ')';
                subtotalArticolo = prezzoNetto * qty * fattoreGiorni;
            } else {
                fattore = 'Lineare';
                subtotalArticolo = prezzoNetto * qty;
            }

            subtotale += subtotalArticolo;

                        // Ubicazione
            var ubicazione = art.ubicazione || '-';

            // Correlati (moltiplicati per qty articolo noleggiato)
                var correlatiTesto = '-';
                if (art.correlati) {
                    try {
                        var correlati = JSON.parse(art.correlati);
                        if (Array.isArray(correlati) && correlati.length > 0) {
                            var corrLabels = correlati.map(function(c) {
                                var qtyCorrelato = (c.qty || 1) * qty; // MOLTIPLICA per qty articolo
                                return qtyCorrelato + 'x ' + c.nome;
                            });
                            correlatiTesto = corrLabels.join(', ');
                        }
                    } catch (e) {
                        // JSON parse fallito
                    }
                }

            // Riga tabella
            htmlTabella += '<tr>' +
                '<td style="border:1px solid #ccc; padding:5px; text-align:left;">' +
                    '[' + (art.codice || '') + '] ' + (art.nome || '') +
                '</td>' +
                '<td style="border:1px solid #ccc; padding:5px; text-align:center;">€ ' + prezzo.toFixed(2).replace('.', ',') + '</td>' +
                '<td style="border:1px solid #ccc; padding:5px; text-align:center;">' + qty + '</td>' +
                '<td style="border:1px solid #ccc; padding:5px; text-align:center;">' + fattore + '</td>' +
                '<td style="border:1px solid #ccc; padding:5px; text-align:center;">-' + sconto.toFixed(2).replace('.', ',') + '%</td>' +
                '<td style="border:1px solid #ccc; padding:5px; text-align:right;">€ ' + subtotalArticolo.toFixed(2).replace('.', ',') + '</td>' +
                '<td style="border:1px solid #ccc; padding:5px; text-align:left;">' + ubicazione + '</td>' +
                '<td style="border:1px solid #ccc; padding:5px; text-align:left;">' + correlatiTesto + '</td>' +
                '</tr>';
        });

        // Popola tabella
        $('#bsn-preview-articoli-tbody').html(htmlTabella);
        $('#bsn-preview-dettagli-articoli').html(htmlDettagli);

                // 6. Calcola totale con regime
        var totaleConRegime = subtotale;
        var importoRegime = 0;

        if (regime > 0) {
            importoRegime = subtotale * (regime / 100);
            totaleConRegime = subtotale + importoRegime;
        }

        // 7. Sconto globale
        var scontoGlobale = parseFloat($('#bsn-noleggio-sconto-globale').val()) || 0;
        var totaleFinale = totaleConRegime + scontoGlobale;

        // Popola totali
        $('#bsn-preview-subtotale').text('€ ' + subtotale.toFixed(2).replace('.', ','));
        $('#bsn-preview-regime-importo').text('€ ' + importoRegime.toFixed(2).replace('.', ','));
        $('#bsn-preview-totale').text('€ ' + totaleConRegime.toFixed(2).replace('.', ','));

        // Mostra sconto solo se valorizzato
        if (scontoGlobale !== 0) {
            $('#bsn-preview-sconto').text('€ ' + scontoGlobale.toFixed(2).replace('.', ','));
            $('#bsn-preview-sconto-block').show();
        } else {
            $('#bsn-preview-sconto-block').hide();
        }

        $('#bsn-preview-totale-finale').text('€ ' + totaleFinale.toFixed(2).replace('.', ','));

        // Mostra la preview
        $('#bsn-noleggio-preview').show();
    }

    // Helper per convertire data YYYY-MM-DD in DD/MM/YYYY
    function bsnConvertiDataPerPrintStr(val) {
        if (!val) return '-';
        var parti = val.split('-');
        if (parti.length !== 3) return '-';
        return parti[2] + '/' + parti[1] + '/' + parti[0];
    }

    // Aggiungi riga articolo nel form noleggio
    $('#bsn-noleggio-articolo-add').on('click', function() {
        var $wrapper = $('#bsn-noleggio-articoli-wrapper');

            var $row = $(
                '<div class="bsn-noleggio-articolo-row">' +
                '<input type="text" class="bsn-noleggio-articolo-search" placeholder="Cerca articolo..." autocomplete="off" style="flex:1 1 200px; min-width:150px;">' +
                '<input type="hidden" class="bsn-noleggio-articolo-id" name="articoli_id[]">' +
                '<input type="number" class="bsn-noleggio-articolo-prezzo" name="articoli_prezzo[]" min="0" step="0.01" placeholder="Prezzo" style="max-width:90px;">' +
                '<input type="number" class="bsn-noleggio-articolo-qty" name="articoli_qty[]" min="1" value="1" style="max-width:60px;">' +
                '<button type="button" class="btn bsn-noleggio-articolo-remove">X</button>' +
                '<div class="bsn-noleggio-articoli-risultati" ' +
                     'style="border:1px solid #ccc; max-height:150px; overflow-y:auto; display:none; ' +
                            'background:#fff; position:relative; z-index:10;"></div>' +
            '</div>'
        );

        $wrapper.append($row);

        // Aggiorna preview
        bsnCalcolaTotaleNoleggio();
    });

    // Rimuovi riga articolo nel form noleggio
    $(document).on('click', '.bsn-noleggio-articolo-remove', function() {
        $(this).closest('.bsn-noleggio-articolo-row').remove();

        // Aggiorna preview
        bsnCalcolaTotaleNoleggio();
    });

    // Ricerca articoli per ogni riga
    $(document).on('input', '.bsn-noleggio-articolo-search', function() {
        var $input    = $(this);
        var query     = $input.val().toLowerCase();
        var $row      = $input.closest('.bsn-noleggio-articolo-row');
        var $box      = $row.find('.bsn-noleggio-articoli-risultati');
        var $hiddenId = $row.find('.bsn-noleggio-articolo-id');

        // se sto digitando, azzero l'ID selezionato
        $hiddenId.val('');

        if (!query || query.length < 2) {
            $box.hide().empty();
            return;
        }

        var risultati = BSN_ARTICOLI_CACHE.filter(function(a) {
            var nome   = (a.nome || '').toLowerCase();
            var codice = (a.codice || '').toLowerCase();
            return nome.indexOf(query) !== -1 || codice.indexOf(query) !== -1;
        });

        $box.empty();

        if (!risultati.length) {
            $box.append('<div style="padding:5px;">Nessun articolo trovato.</div>');
            $box.show();
            return;
        }

        risultati.slice(0, 20).forEach(function(a) {
            var label = '';
            if (a.codice) {
                label += '[' + a.codice + '] ';
            }
            label += (a.nome || ('Articolo #' + a.id));

            var html = '' +
                '<div class="bsn-risultato-articolo" data-id="' + a.id + '" ' +
                     'style="padding:5px; cursor:pointer; border-bottom:1px solid #eee;">' +
                    label +
                '</div>';
            $box.append(html);
        });

        $box.show();
    });

        // Trova e mette il focus sulla prima riga articoli realmente vuota (senza ID né testo)
    function bsnFocusPrimaRigaArticoloVuota() {
        var $righe = $('#bsn-noleggio-articoli-wrapper .bsn-noleggio-articolo-row');
        var $trovata = null;

        $righe.each(function() {
            var $r = $(this);
            var idRiga = parseInt($r.find('.bsn-noleggio-articolo-id').val(), 10);
            var testo  = ($r.find('.bsn-noleggio-articolo-search').val() || '').trim();
            if (!idRiga && !testo) {
                $trovata = $r;
                return false; // break
            }
        });

        if ($trovata) {
            $trovata.find('.bsn-noleggio-articolo-search').focus();
        } else {
            // Se per qualche motivo non c'è riga vuota, ne creiamo una e mettiamo lì il focus
            $('#bsn-noleggio-articolo-add').click();
            var $nuova = $('#bsn-noleggio-articoli-wrapper .bsn-noleggio-articolo-row').last();
            $nuova.find('.bsn-noleggio-articolo-search').focus();
        }
    }

   // ENTER sulla search: usa solo il codice crudo per sommare qty o creare riga
$(document).on('keydown', '.bsn-noleggio-articolo-search', function(e) {
    if (e.key !== 'Enter' && e.keyCode !== 13) {
        return;
    }

    e.preventDefault();

    var $input = $(this);
    var $row   = $input.closest('.bsn-noleggio-articolo-row');
    var $box   = $row.find('.bsn-noleggio-articoli-risultati');
    var $hiddenId = $row.find('.bsn-noleggio-articolo-id');
    var $qty   = $row.find('.bsn-noleggio-articolo-qty');

    var rawVal = ($input.val() || '').trim();
    if (!rawVal) {
        return;
    }

    // Estrai un "codice pulito" dal valore:
    // - se è del tipo "[CODICE] Nome", prendi CODICE
    // - altrimenti prendi la prima parola (prima dello spazio)
    var query = rawVal.toLowerCase();
    if (query[0] === '[') {
        var chiude = query.indexOf(']');
        if (chiude > 1) {
            query = query.substring(1, chiude);
        }
    } else {
        var spazio = query.indexOf(' ');
        if (spazio > 0) {
            query = query.substring(0, spazio);
        }
    }
    if (!query) {
        return;
    }

    // Trova l'articolo in cache, privilegiando match esatto sul codice
    var risultati = BSN_ARTICOLI_CACHE.filter(function(a) {
        var codice = (a.codice || '').toLowerCase();
        var nome   = (a.nome   || '').toLowerCase();
        return codice.indexOf(query) !== -1 || nome.indexOf(query) !== -1;
    });
    if (!risultati.length) {
        return;
    }

    var art = null;
    if (risultati.length === 1) {
        art = risultati[0];
    } else {
        art = risultati.find(function(a) {
            return (a.codice || '').toLowerCase() === query;
        }) || risultati[0];
    }

    var idArticolo = parseInt(art.id, 10);
    if (!idArticolo) {
        return;
    }

    // CERCA SEMPRE SE ESISTE GIÀ UNA RIGA PER QUESTO ARTICOLO
    var $righe = $('#bsn-noleggio-articoli-wrapper .bsn-noleggio-articolo-row');
    var $rigaEsistente = null;

    $righe.each(function() {
        var $r = $(this);
        var idRiga = parseInt($r.find('.bsn-noleggio-articolo-id').val(), 10);
        if (idRiga === idArticolo) {
            $rigaEsistente = $r;
            return false; // break
        }
    });

        if ($rigaEsistente) {
        // Somma qty SOLO sulla riga esistente
        var $qtyEsistente = $rigaEsistente.find('.bsn-noleggio-articolo-qty');
        var qtyAttuale = parseInt($qtyEsistente.val(), 10) || 0;
        $qtyEsistente.val(qtyAttuale + 1);

        bsnCalcolaTotaleNoleggio();

        // Porta SEMPRE il focus sulla prima riga realmente vuota
        bsnFocusPrimaRigaArticoloVuota();

        // Pulisci la riga corrente se è diversa
        if ($rigaEsistente[0] !== $row[0]) {
            $hiddenId.val('');
            $input.val('');
            $qty.val(1);
            $box.hide().empty();
        }

        return;
    }

    // Se NON esiste ancora una riga per questo articolo, usa la riga corrente

    $box.hide().empty();

    var label = '';
    if (art.codice) {
        label += '[' + art.codice + '] ';
    }
    label += (art.nome || ('Articolo #' + art.id));

        $hiddenId.val(art.id);
    $input.val(label);
    $qty.val(1);
    
    // Auto-compila prezzo da articolo
    var $prezzo = $row.find('.bsn-noleggio-articolo-prezzo');
    var prezzoBase = parseFloat(art.prezzo_giorno) || 0;
    $prezzo.val(prezzoBase.toFixed(2));

    bsnCalcolaTotaleNoleggio();

    // Crea nuova riga e porta il focus sulla prima riga vuota
    $('#bsn-noleggio-articolo-add').click();
    bsnFocusPrimaRigaArticoloVuota();
});

        // Click su un risultato articolo
    $(document).on('click', '.bsn-risultato-articolo', function() {
        var $item = $(this);
        var id    = $item.data('id');
        var label = $item.text();

        var $row = $item.closest('.bsn-noleggio-articolo-row');
        $row.find('.bsn-noleggio-articolo-id').val(id);
        $row.find('.bsn-noleggio-articolo-search').val(label);

        // Auto-compila prezzo da articolo
        var art = BSN_ARTICOLI_CACHE.find(function(a) {
            return parseInt(a.id, 10) === parseInt(id, 10);
        });
        if (art) {
            var prezzoBase = parseFloat(art.prezzo_giorno) || 0;
            $row.find('.bsn-noleggio-articolo-prezzo').val(prezzoBase.toFixed(2));
        }

        $row.find('.bsn-noleggio-articoli-risultati').hide().empty();

        // Aggiorna preview
        bsnCalcolaTotaleNoleggio();
    });

    // Chiudi lista risultati articoli quando il campo perde il focus
    $(document).on('blur', '.bsn-noleggio-articolo-search', function() {
        var $row = $(this).closest('.bsn-noleggio-articolo-row');
        setTimeout(function() {
            $row.find('.bsn-noleggio-articoli-risultati').hide();
        }, 200);
    });

    // Ricerca clienti nel form noleggio
    $('#bsn-noleggio-cliente-search').on('input', function() {
        var query     = $(this).val().toLowerCase();
        var $box      = $('#bsn-noleggio-clienti-risultati');
        var $hiddenId = $('#bsn-noleggio-cliente-id');

        // se sto digitando, azzero l'ID selezionato
        $hiddenId.val('');

        if (!query || query.length < 2) {
            $box.hide().empty();
            return;
        }

        // Filtra i clienti in cache
        var risultati = BSN_CLIENTI_CACHE.filter(function(c) {
            var nome = (c.nome || '').toLowerCase();
            var cf   = (c.cf_piva || '').toLowerCase();
            return nome.indexOf(query) !== -1 || cf.indexOf(query) !== -1;
        });

        $box.empty();

        if (!risultati.length) {
            $box.append('<div style="padding:5px;">Nessun cliente trovato.</div>');
            $box.show();
            return;
        }

        risultati.slice(0, 20).forEach(function(c) {
            var label = (c.nome || ('Cliente #' + c.id));
            if (c.cf_piva) {
                label += ' (' + c.cf_piva + ')';
            }
            var html = '' +
                '<div class="bsn-risultato-cliente" data-id="' + c.id + '" ' +
                     'style="padding:5px; cursor:pointer; border-bottom:1px solid #eee;">' +
                    label +
                '</div>';
            $box.append(html);
        });

        $box.show();
    });

    // Click su un risultato della ricerca cliente
    $(document).on('click', '.bsn-risultato-cliente', function() {
        var id    = $(this).data('id');
        var label = $(this).text();

        $('#bsn-noleggio-cliente-id').val(id);
        $('#bsn-noleggio-cliente-search').val(label);

        $('#bsn-noleggio-clienti-risultati').hide().empty();

        // Aggiorna preview
        bsnCalcolaTotaleNoleggio();
    });

    // Chiudi la lista clienti quando il campo perde il focus (con un piccolo delay per permettere il click)
    $('#bsn-noleggio-cliente-search').on('blur', function() {
        setTimeout(function() {
            $('#bsn-noleggio-clienti-risultati').hide();
        }, 200);
    });

    // Trigger calcolo quando cambiano le date
    $(document).on('change', '#bsn-form-noleggio input[name="data_inizio"], #bsn-form-noleggio input[name="data_fine"]', function() {
        bsnCalcolaTotaleNoleggio();
    });

    // Trigger calcolo quando cambia qty
    $(document).on('change input', '.bsn-noleggio-articolo-qty', function() {
        bsnCalcolaTotaleNoleggio();
    });

        // Trigger calcolo quando cambia prezzo riga
    $(document).on('change input', '.bsn-noleggio-articolo-prezzo', function() {
        bsnCalcolaTotaleNoleggio();
    });

    // Trigger calcolo quando cambia sconto globale
    $(document).on('change input', '#bsn-noleggio-sconto-globale', function() {
        bsnCalcolaTotaleNoleggio();
    });

        // Blocca ENTER dentro il wrapper articoli noleggio
    $(document).on('keydown', '#bsn-noleggio-articoli-wrapper input', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            // Qui metteremo la logica avanzata per la pistola
            return false;
        }
    });

    // Submit form noleggio (crea o modifica, con articoli e warning sovrapposizioni)
    $('#bsn-form-noleggio').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        // ID noleggio: se valorizzato, siamo in modalità "modifica"
        var noleggioId = $('#bsn-noleggio-id').val();

        // Articoli: costruisci array [{id, qty, prezzo}, ...]
        var articoli = [];
        $('#bsn-noleggio-articoli-wrapper .bsn-noleggio-articolo-row').each(function() {
            var id = $(this).find('.bsn-noleggio-articolo-id').val();
            var qty = $(this).find('.bsn-noleggio-articolo-qty').val();
            var prezzo = $(this).find('.bsn-noleggio-articolo-prezzo').val();
            if (id && qty > 0) {
                articoli.push({
                    id: parseInt(id, 10),
                    qty: parseInt(qty, 10) || 1,
                    prezzo: prezzo ? parseFloat(prezzo) : null
                });
            }
        });

        // almeno un articolo obbligatorio
        if (articoli.length === 0) {
            alert('Devi aggiungere almeno un articolo al noleggio.');
            return;
        }

                var dati = {
            noleggio_id:       noleggioId,
            cliente_id:        $form.find('input[name="cliente_id"]').val(),
            data_inizio:       $form.find('input[name="data_inizio"]').val(),
            data_fine:         $form.find('input[name="data_fine"]').val(),
            stato:             $form.find('select[name="stato"]').val(),
            note:              $form.find('textarea[name="note"]').val(),
            articoli:          JSON.stringify(articoli),
            consenti_overbook: $form.find('#bsn-noleggio-consenti-overbook').is(':checked') ? 1 : 0,
            // nuovi campi logistica/trasporto
            luogo_destinazione: $form.find('input[name="luogo_destinazione"]').val(),
            trasporto_mezzo:    $form.find('input[name="trasporto_mezzo"]').val(),
            cauzione:           $form.find('input[name="cauzione"]').val(),
            causale_trasporto:  $form.find('input[name="causale_trasporto"]').val(),
            sconto_globale: $form.find('#bsn-noleggio-sconto-globale').val()

        };


        if (!dati.cliente_id || !dati.data_inizio || !dati.data_fine) {
            alert('Cliente, data inizio e data fine sono obbligatori.');
            return;
        }

        // Decidi endpoint: crea vs aggiorna
        var urlEndpoint;
        var azioneTesto;
        if (noleggioId) {
            urlEndpoint = BSN_API.root + 'noleggi/update';
            azioneTesto = 'aggiornato';
        } else {
            urlEndpoint = BSN_API.root + 'noleggi';
            azioneTesto = 'salvato';
        }

            $.ajax({
            url: urlEndpoint,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            data: dati,
            success: function(response) {
                // Reset warning box
                var $warnBox  = $('#bsn-noleggi-warning');
                var $warnList = $('#bsn-noleggi-warning-list');
                $warnList.empty();
                $warnBox.hide();

                // Mostra warning sovrapposizioni se presenti (non bloccanti)
                if (response && response.sovrapposizioni && response.sovrapposizioni.length) {
                    response.sovrapposizioni.forEach(function(msg) {
                        $warnList.append('<li>' + msg + '</li>');
                    });
                    $warnBox.show();
                    alert('Noleggio ' + azioneTesto + ', ma ci sono sovrapposizioni. Controlla il riquadro di attenzione sotto al form.');
                } else {
                    alert('Noleggio ' + azioneTesto + ' correttamente.');
                }

                // reset completa: esce dalla modalità "modifica"
                $form[0].reset();
                $('#bsn-noleggio-id').val('');
                $('#bsn-noleggio-preview').hide(); // nascondi anche la preview
                bsnCaricaNoleggi();
            },
            error: function(err) {
                console.error(err);

                // Gestione errore blocco sovrapposizioni
                if (err.responseJSON && err.responseJSON.code === 'bsn_sovrapposizioni_blocco') {
                    var messaggioErrore = err.responseJSON.message || 'Sovrapposizioni rilevate';
                    
                    // Pulisci il messaggio per estrarre solo le righe degli articoli
                    var righe = messaggioErrore.split('\n').filter(function(riga) {
                        return riga.trim().startsWith('-');
                    });
                    
                    var $warnBox  = $('#bsn-noleggi-warning');
                    var $warnList = $('#bsn-noleggi-warning-list');
                    $warnList.empty();
                    
                    righe.forEach(function(riga) {
                        // Rimuovi il "- " iniziale
                        var msg = riga.trim().substring(2);
                        $warnList.append('<li>' + msg + '</li>');
                    });
                    
                    $warnBox.show();
                    
                    alert('ATTENZIONE: Sovrapposizioni rilevate!\n\nPer salvare comunque questo noleggio, spunta la casella "Consenti comunque il superamento della disponibilità (overbooking)" e riprova.');
                } else {
                    // Errore generico
                    var msgErrore = 'Errore nel salvataggio noleggio.';
                    if (err.responseJSON && err.responseJSON.message) {
                        msgErrore += '\n\n' + err.responseJSON.message;
                    }
                    alert(msgErrore);
                }
            }
        });

    });

    function bsnCaricaNoleggi() {
        $.ajax({
            url: BSN_API.root + 'noleggi',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            success: function(response) {
                var wrapper = $('#bsn-lista-noleggi');
                wrapper.empty();

                if (!response.noleggi || !response.noleggi.length) {
                    wrapper.html('<p>Nessun noleggio salvato.</p>');
                    return;
                }

                var html = '' +
                    '<div style="margin-bottom:10px;">' +
                        '<input type="text" id="bsn-noleggi-search" placeholder="Cerca nei noleggi..." ' +
                            'style="width:100%; max-width:300px; padding:5px;">' +
                    '</div>' +
                    '<table class="bsn-tabella" id="bsn-noleggi-table">' +
                        '<thead><tr>' +
                            '<th>ID</th>' +
                            '<th>Cliente</th>' +
                            '<th>Dal</th>' +
                            '<th>Al</th>' +
                            '<th>Stato</th>' +
                            '<th>Articoli</th>' +
                            '<th>Azioni</th>' +
                        '</tr></thead>' +
                        '<tbody>';

                var maxRows = 12;
                var lista = response.noleggi.slice(0, maxRows);

                lista.forEach(function(n) {
                    var stato       = (n.stato || '').toLowerCase();
                    var classeStato = '';

                    if (stato === 'bozza') {
                        classeStato = 'bsn-stato-bozza';
                    } else if (stato === 'attivo') {
                        classeStato = 'bsn-stato-attivo';
                    } else if (stato === 'chiuso') {
                        classeStato = 'bsn-stato-chiuso';
                    } else if (stato === 'ritardo') {
                        classeStato = 'bsn-stato-ritardo';
                    }

                    // Riga principale
                    html += '' +
                        '<tr class="bsn-noleggio-row ' + classeStato + '" data-id="' + n.id + '">' +
                            '<td>' + n.id + '</td>' +
                            '<td>' + (n.cliente_nome || '') + '</td>' +
                            '<td>' + (n.data_da || '') + '</td>' +
                            '<td>' + (n.data_a || '') + '</td>' +
                            '<td>' + (n.stato || '') + '</td>' +
                            '<td>' + (n.articoli_riassunto || '-') + '</td>' +
                            '<td>' +
                                                    '<td>' +
                        // Dettagli
                        '<button type="button" class="btn bsn-noleggio-details" title="Dettagli" ' +
                            'style="margin-right:3px;">🔍 Dettagli</button> ' +
                        // Duplica
                        '<button type="button" class="btn bsn-noleggio-duplica" title="Duplica noleggio" ' +
                            'style="margin-right:3px;">📄 Duplica</button> ' +
                        // Modifica (solo se non chiuso)
                        (stato !== 'chiuso'
                            ? '<button type="button" class="btn bsn-noleggio-edit" title="Modifica noleggio" ' +
                                'style="margin-right:3px;">✏️ Modifica</button> '
                            : ''
                        ) +
                        // Finalizza solo per bozza
                        (stato === 'bozza'
                            ? '<a href="' + window.location.origin +
                                '/wp-admin/admin.php?page=bsn-finalizza-noleggio&id=' +
                                encodeURIComponent(n.id) +
                                '" class="button button-primary" ' +
                                'title="Finalizza noleggio" ' +
                                'style="margin-right:3px;">✅ Finalizza</a> '
                            : ''
                        ) +
                        // Rientro (Chiudi) solo per attivo o ritardo
                        ((stato === 'attivo' || stato === 'ritardo')
                            ? '<button type="button" class="button button-secondary bsn-noleggio-rientro" ' +
                                'title="Chiudi / Rientro noleggio" ' +
                                'style="margin-right:3px;">🚚 Rientro / Chiudi</button> '
                            : ''
                        ) +
                        // Elimina
                        '<button type="button" class="btn bsn-noleggio-delete" title="Elimina noleggio" ' +
                            'style="color:#b00;">🗑️ Elimina</button>' +
                    '</td>' +

                        '</tr>' +
                        // Riga dettagli, inizialmente vuota/nascosta
                        '<tr class="bsn-noleggio-row-details" data-id="' + n.id + '" style="display:none;">' +
                            '<td colspan="7">' +
                                '<div class="bsn-noleggio-details-container">' +
                                    '<em>Caricamento dettagli...</em>' +
                                '</div>' +
                            '</td>' +
                        '</tr>';

                });

                html += '</tbody></table>';
                wrapper.html(html);
            },
            error: function(err) {
                console.error(err);
                $('#bsn-lista-noleggi').html('<p>Errore nel caricare i noleggi.</p>');
            }
        });
    }

         // Click su "Dettagli" noleggio (mostra note + articoli con ubicazione e correlati)
        $(document).on('click', '.bsn-noleggio-details', function() {
        var $btn   = $(this);
        var $row   = $btn.closest('.bsn-noleggio-row');
        var id     = $row.data('id');
        var $dRow  = $('.bsn-noleggio-row-details[data-id="' + id + '"]');
        var $box   = $dRow.find('.bsn-noleggio-details-container');

        // Toggle base: se già visibile, nascondi
        if ($dRow.is(':visible')) {
            $dRow.hide();
            return;
        }

        // Mostra riga dettagli e metti un messaggio di caricamento
        $dRow.show();
        $box.html('<em>Caricamento dettagli...</em>');

        $.ajax({
            url: BSN_API.root + 'noleggi/dettaglio',
            method: 'GET',
            data: { id: id },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            success: function(response) {
                var html = '';

                // Note noleggio
                html += '<div style="margin-bottom:8px;">';
                html += '<strong>Note noleggio:</strong> ';
                if (response.note) {
                    html += '<span>' + response.note + '</span>';
                } else {
                    html += '<span>-</span>';
                }
                html += '</div>';

                // Articoli
                if (response.articoli && response.articoli.length) {
                    html += '<div style="margin-top:5px;">';
                    html += '<strong>Articoli noleggiati:</strong>';
                    html += '<ul style="margin:5px 0 0 15px; padding:0;">';

                    response.articoli.forEach(function(a) {
                        var label = '';

                        // [CODICE] Nome
                        if (a.codice) {
                            label += '[' + a.codice + '] ';
                        }
                        label += (a.nome || ('Articolo #' + a.id));

                        // Qty
                        label += ' – qty: ' + a.qty;

                        // Ubicazione
                        var ubic = a.ubicazione || '-';
                        label += ' – ubicazione: ' + ubic;

                        // Correlati (moltiplicati per qty articolo)
                        var corrTesto = '-';
                        if (a.correlati && a.correlati.length) {
                            var pezzi = a.correlati.map(function(c) {
                                var qtyCorrelato = (c.qty || 1) * a.qty; // MOLTIPLICA per qty articolo
                                var n = c.nome || '';
                                return qtyCorrelato + 'x ' + n;
                            });
                            corrTesto = pezzi.join(', ');
                        }
                        label += ' – correlati: ' + corrTesto;

                        html += '<li>' + label + '</li>';
                    });

                    html += '</ul>';
                    html += '</div>';
                } else {
                    html += '<div><em>Nessun articolo associato a questo noleggio.</em></div>';
                }

                $box.html(html);
            },
            error: function(err) {
                console.error(err);
                $box.html('<em>Errore nel caricare i dettagli del noleggio.</em>');
            }
        });
    });

    // Click su "Modifica" nella tabella noleggi
$(document).on('click', '.bsn-noleggio-edit', function() {
    var $row = $(this).closest('.bsn-noleggio-row');
    var id   = $row.data('id');

    if (!id) return;

    // Dati base dalla riga (testo in formato dd/mm/YYYY)
    var clienteNome = $row.find('td').eq(1).text();
    var dataDaTxt   = $row.find('td').eq(2).text(); // es. 18/01/2026
    var dataATxt    = $row.find('td').eq(3).text(); // es. 20/01/2026
    var stato       = $row.find('td').eq(4).text();

    // Conversione dd/mm/YYYY -> YYYY-MM-DD per <input type="date">
    function bsnConvertiDataPerInput(val) {
        if (!val) return '';
        var parti = val.split('/');
        if (parti.length !== 3) return '';
        var giorno = parti[0];
        var mese   = parti[1];
        var anno   = parti[2];
        return anno + '-' +
               (mese.length === 1 ? '0' + mese : mese) + '-' +
               (giorno.length === 1 ? '0' + giorno : giorno);
    }

    var dataDaISO = bsnConvertiDataPerInput(dataDaTxt);
    var dataAISO  = bsnConvertiDataPerInput(dataATxt);

    // Imposta ID noleggio in hidden e modalità "edit"
    $('#bsn-noleggio-id').val(id);

    // Compila i campi base del form (nome cliente, date, stato)
    $('#bsn-noleggio-cliente-search').val(clienteNome);
    $('input[name="data_inizio"]').val(dataDaISO);
    $('input[name="data_fine"]').val(dataAISO);
    $('select[name="stato"]').val(stato.toLowerCase());

    // Prima svuotiamo il wrapper articoli
    var $wrapper = $('#bsn-noleggio-articoli-wrapper');
    $wrapper.empty();

    // Chiamata API dettaglio per avere articoli, note e cliente_id corretto
    $.ajax({
        url: BSN_API.root + 'noleggi/dettaglio',
        method: 'GET',
        data: { id: id },
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
        },
        success: function(response) {
            // cliente_id reale
            if (response.cliente_id) {
                $('#bsn-noleggio-cliente-id').val(response.cliente_id);
            }

            // luogo, trasporto, cauzione, causale
            if (typeof response.luogo_destinazione !== 'undefined') {
                $('input[name="luogo_destinazione"]').val(response.luogo_destinazione || '');
            }
            if (typeof response.trasporto_mezzo !== 'undefined') {
                $('input[name="trasporto_mezzo"]').val(response.trasporto_mezzo || '');
            }
            if (typeof response.cauzione !== 'undefined') {
                $('input[name="cauzione"]').val(response.cauzione || '');
            }
            if (typeof response.causale_trasporto !== 'undefined') {
                $('input[name="causale_trasporto"]').val(response.causale_trasporto || '');
            }

            // note
            if (typeof response.note !== 'undefined') {
                $('textarea[name="note"]').val(response.note);
            }

            // sconto globale
            if (typeof response.sconto_globale !== 'undefined' && response.sconto_globale !== null) {
                $('#bsn-noleggio-sconto-globale').val(response.sconto_globale);
            } else {
                $('#bsn-noleggio-sconto-globale').val('0');
            }

            // ricostruisci righe articoli
            if (response.articoli && response.articoli.length) {
                response.articoli.forEach(function(a) {
                    var $rowArt = $(
                        '<div class="bsn-noleggio-articolo-row">' +
                            '<input type="text" class="bsn-noleggio-articolo-search" ' +
                                'placeholder="Cerca articolo per nome o codice..." autocomplete="off" ' +
                                'style="flex:1 1 200px; min-width:150px;">' +
                            '<input type="hidden" class="bsn-noleggio-articolo-id" name="articoli_id[]">' +
                            '<input type="number" class="bsn-noleggio-articolo-prezzo" ' +
                                'name="articoli_prezzo[]" min="0" step="0.01" placeholder="Prezzo" ' +
                                'style="max-width:90px;">' +
                            '<input type="number" class="bsn-noleggio-articolo-qty" ' +
                                'name="articoli_qty[]" min="1" value="1" style="max-width:60px;">' +
                            '<button type="button" class="btn bsn-noleggio-articolo-remove">X</button>' +
                            '<div class="bsn-noleggio-articoli-risultati" ' +
                                'style="border:1px solid #ccc; max-height:150px; overflow-y:auto; ' +
                                       'display:none; background:#fff; position:relative; z-index:10;"></div>' +
                        '</div>'
                    );

                    $rowArt.find('.bsn-noleggio-articolo-id').val(a.id);
                    $rowArt.find('.bsn-noleggio-articolo-qty').val(a.qty);

                    // prezzo custom (se presente nel JSON del noleggio)
                    if (typeof a.prezzo !== 'undefined' && a.prezzo !== null && a.prezzo !== '') {
                        $rowArt.find('.bsn-noleggio-articolo-prezzo')
                               .val(parseFloat(a.prezzo).toFixed(2));
                    }

                    var label = '';
                    if (a.codice) {
                        label += '[' + a.codice + '] ';
                    }
                    label += a.nome || ('Articolo #' + a.id);
                    $rowArt.find('.bsn-noleggio-articolo-search').val(label);

                    $wrapper.append($rowArt);
                });
            } else {
                // riga vuota fallback, con anche il campo prezzo
                var $rowVuota = $(
                    '<div class="bsn-noleggio-articolo-row">' +
                        '<input type="text" class="bsn-noleggio-articolo-search" ' +
                            'placeholder="Cerca articolo per nome o codice..." autocomplete="off" ' +
                            'style="flex:1 1 200px; min-width:150px;">' +
                        '<input type="hidden" class="bsn-noleggio-articolo-id" name="articoli_id[]">' +
                        '<input type="number" class="bsn-noleggio-articolo-prezzo" ' +
                            'name="articoli_prezzo[]" min="0" step="0.01" placeholder="Prezzo" ' +
                            'style="max-width:90px;">' +
                        '<input type="number" class="bsn-noleggio-articolo-qty" ' +
                            'name="articoli_qty[]" min="1" value="1" style="max-width:60px;">' +
                        '<button type="button" class="btn bsn-noleggio-articolo-remove">X</button>' +
                        '<div class="bsn-noleggio-articoli-risultati" ' +
                            'style="border:1px solid #ccc; max-height:150px; overflow-y:auto; ' +
                                   'display:none; background:#fff; position:relative; z-index:10;"></div>' +
                    '</div>'
                );
                $wrapper.append($rowVuota);
            }

            // Scroll al form per comodità
            $('html, body').animate({
                scrollTop: $('#bsn-form-noleggio').offset().top - 20
            }, 300);
        },
        error: function(err) {
            console.error(err);
            alert('Errore nel caricare i dettagli del noleggio.');

            // In caso di errore, ricreiamo almeno una riga articolo vuota
            var $rowVuota = $(
                '<div class="bsn-noleggio-articolo-row">' +
                    '<input type="text" class="bsn-noleggio-articolo-search" ' +
                        'placeholder="Cerca articolo per nome o codice..." autocomplete="off">' +
                    '<input type="hidden" class="bsn-noleggio-articolo-id" name="articoli_id[]">' +
                    '<input type="number" class="bsn-noleggio-articolo-prezzo" ' +
                        'name="articoli_prezzo[]" min="0" step="0.01" placeholder="Prezzo" ' +
                        'style="max-width:90px;">' +
                    '<input type="number" class="bsn-noleggio-articolo-qty" ' +
                        'name="articoli_qty[]" min="1" value="1" style="max-width:60px;">' +
                    '<button type="button" class="btn bsn-noleggio-articolo-remove">X</button>' +
                    '<div class="bsn-noleggio-articoli-risultati" ' +
                        'style="border:1px solid #ccc; max-height:150px; overflow-y:auto; ' +
                               'display:none; background:#fff; position:relative; z-index:10;"></div>' +
                '</div>'
            );
            $wrapper.append($rowVuota);

            $('html, body').animate({
                scrollTop: $('#bsn-form-noleggio').offset().top - 20
            }, 300);
        }
    });
});
    /*
    // Cambio stato da menu a tendina (usa endpoint /noleggi/stato)
    
    $(document).on('change', '.bsn-noleggio-stato-select', function() {
        var $select    = $(this);
        var nuovoStato = $select.val();
        var $row       = $select.closest('.bsn-noleggio-row');
        var id         = $row.data('id');

        if (!id || !nuovoStato) return;

        if (!confirm('Vuoi impostare il noleggio #' + id + ' come "' + nuovoStato + '"?')) {
            $select.val('');
            return;
        }

        $.ajax({
            url: BSN_API.root + 'noleggi/stato',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            data: {
                id_noleggio: id,
                nuovo_stato: nuovoStato
            },
            success: function(response) {
                alert('Stato noleggio aggiornato a "' + nuovoStato + '".');
                bsnCaricaNoleggi();
            },
            error: function(err) {
                console.error(err);
                alert('Errore nell\'aggiornare lo stato del noleggio.');
                $select.val('');
            }
        });
    });
    */

            // Click su "Rientro / Chiudi" nella tabella noleggi → apre pagina admin Rientro
        $(document).on('click', '.bsn-noleggio-rientro', function() {
        var $row = $(this).closest('.bsn-noleggio-row');
        var id   = $row.data('id');

        if (!id) return;

        var urlRientro = window.location.origin +
            '/wp-admin/admin.php?page=bsn-rientro-noleggio&id=' +
            encodeURIComponent(id);

        window.location.href = urlRientro;
    });


        // Duplica noleggio
    $(document).on('click', '.bsn-noleggio-duplica', function() {
        console.log('Click su duplica'); // DEBUG

        var $row = $(this).closest('.bsn-noleggio-row');
        var id   = $row.data('id');

        if (!id) return;

        if (!confirm('Vuoi duplicare il noleggio #' + id + ' come nuova bozza?')) {
            return;
        }

        $.ajax({
            url: BSN_API.root + 'noleggi/duplica',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            data: { id: id },
            success: function(response) {
                console.log('duplica OK', response);
                if (response && response.id) {
                    alert('Noleggio duplicato correttamente. Nuovo ID: ' + response.id);
                } else {
                    alert('Noleggio duplicato correttamente.');
                }
                bsnCaricaNoleggi();
            },
            error: function(err) {
                console.error('errore duplica', err);
                alert('Errore nel duplicare il noleggio.');
            }
        });
    });

        // Elimina noleggio
    $(document).on('click', '.bsn-noleggio-delete', function() {
        var $row = $(this).closest('.bsn-noleggio-row');
        var id   = $row.data('id');

        if (!id) return;

        if (!confirm('Vuoi eliminare definitivamente il noleggio #' + id + '?')) {
            return;
        }

        $.ajax({
            url: BSN_API.root + 'noleggi/delete',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
            },
            data: { id: id },
            success: function(response) {
                alert('Noleggio eliminato.');
                bsnCaricaNoleggi();
            },
            error: function(err) {
                console.error(err);
                alert('Errore nell\'eliminazione noleggio.');
            }
        });
    });

        // Filtro per stato nella tabella noleggi
    $(document).on('change', '#bsn-filtro-stato', function() {
        bsnFiltraNoleggi();
    });

    // Ricerca testuale nella tabella noleggi
    $(document).on('input', '#bsn-noleggi-search', function() {
        bsnFiltraNoleggi();
    });

    // Funzione comune di filtro (stato + testo)
    function bsnFiltraNoleggi() {
        var statoFiltro = ($('#bsn-filtro-stato').val() || '').toLowerCase();
        var testoFiltro = ($('#bsn-noleggi-search').val() || '').toLowerCase();

        $('#bsn-noleggi-table tbody tr.bsn-noleggio-row').each(function() {
            var $row  = $(this);
            var stato = ($row.find('td').eq(4).text() || '').toLowerCase();
            var text  = $row.text().toLowerCase();

            var matchStato = !statoFiltro || stato === statoFiltro;
            var matchTesto = !testoFiltro || text.indexOf(testoFiltro) !== -1;

            var visibile = matchStato && matchTesto;
            $row.toggle(visibile);

            // Nascondo eventuale riga dettagli associata se la principale è nascosta
            var id = $row.data('id');
            var $detailsTr = $('.bsn-noleggio-row-details[data-id="' + id + '"]');
            if (!visibile) {
                $detailsTr.hide();
            }
        });
    }
    // ==== WEBCAM DOCUMENTO FRONTE ====

    let bsnDocFronteStream = null;

    $(document).on('click', '#bsn-doc-fronte-webcam-start', function() {
        const video = document.getElementById('bsn-doc-fronte-video');
        const area  = document.getElementById('bsn-doc-fronte-webcam-area');
        const btnCapture = document.getElementById('bsn-doc-fronte-webcam-capture');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('La webcam non è supportata da questo browser.');
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: true })
            .then(function(stream) {
                bsnDocFronteStream = stream;
                video.srcObject = stream;
                area.style.display = 'block';
                btnCapture.style.display = 'inline-block';
            })
            .catch(function(err) {
                console.error(err);
                alert('Impossibile accedere alla webcam.');
            });
    });

    $(document).on('click', '#bsn-doc-fronte-webcam-capture', function() {
        const video  = document.getElementById('bsn-doc-fronte-video');
        const canvas = document.getElementById('bsn-doc-fronte-canvas');
        const ctx    = canvas.getContext('2d');

        // Disegna il frame del video sul canvas (ridimensionato se vuoi)
        const width  = 640;
        const height = 480;
        canvas.width  = width;
        canvas.height = height;
        ctx.drawImage(video, 0, 0, width, height);

        // Converte il canvas in blob e lo manda all'endpoint upload-doc
        canvas.toBlob(function(blob) {
            if (!blob) {
                alert('Errore nello scatto della foto.');
                return;
            }

            var formData = new FormData();
            formData.append('file', blob, 'doc-fronte-webcam.jpg');

            $.ajax({
                url: BSN_API.root + 'upload-doc',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
                },
                success: function(response) {
                    if (response && response.url) {
                        $('#bsn-doc-fronte-path').val(response.url);
                        $('#bsn-doc-fronte-preview').html(
                            '<img src="' + response.url + '" alt="Documento fronte" style="max-width:160px;">'
                        );
                        // ferma la webcam
                        if (bsnDocFronteStream) {
                            bsnDocFronteStream.getTracks().forEach(function(t){ t.stop(); });
                            bsnDocFronteStream = null;
                        }
                        $('#bsn-doc-fronte-webcam-area').hide();
                        $('#bsn-doc-fronte-webcam-capture').hide();
                    } else {
                        alert('Upload da webcam riuscito ma URL non trovato.');
                    }
                },
                error: function(err) {
                    console.error(err);
                    alert('Errore nell\'upload del documento fronte (webcam).');
                }
            });
        }, 'image/jpeg', 0.8);
    });

    // ==== WEBCAM DOCUMENTO RETRO ====

    let bsnDocRetroStream = null;

    $(document).on('click', '#bsn-doc-retro-webcam-start', function() {
        const video = document.getElementById('bsn-doc-retro-video');
        const area  = document.getElementById('bsn-doc-retro-webcam-area');
        const btnCapture = document.getElementById('bsn-doc-retro-webcam-capture');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('La webcam non è supportata da questo browser.');
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: true })
            .then(function(stream) {
                bsnDocRetroStream = stream;
                video.srcObject = stream;
                area.style.display = 'block';
                btnCapture.style.display = 'inline-block';
            })
            .catch(function(err) {
                console.error(err);
                alert('Impossibile accedere alla webcam.');
            });
    });

    $(document).on('click', '#bsn-doc-retro-webcam-capture', function() {
        const video  = document.getElementById('bsn-doc-retro-video');
        const canvas = document.getElementById('bsn-doc-retro-canvas');
        const ctx    = canvas.getContext('2d');

        const width  = 640;
        const height = 480;
        canvas.width  = width;
        canvas.height = height;
        ctx.drawImage(video, 0, 0, width, height);

        canvas.toBlob(function(blob) {
            if (!blob) {
                alert('Errore nello scatto della foto.');
                return;
            }

            var formData = new FormData();
            formData.append('file', blob, 'doc-retro-webcam.jpg');

            $.ajax({
                url: BSN_API.root + 'upload-doc',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
                },
                success: function(response) {
                    if (response && response.url) {
                        $('#bsn-doc-retro-path').val(response.url);
                        $('#bsn-doc-retro-preview').html(
                            '<img src="' + response.url + '" alt="Documento retro" style="max-width:160px;">'
                        );
                        if (bsnDocRetroStream) {
                            bsnDocRetroStream.getTracks().forEach(function(t){ t.stop(); });
                            bsnDocRetroStream = null;
                        }
                        $('#bsn-doc-retro-webcam-area').hide();
                        $('#bsn-doc-retro-webcam-capture').hide();
                    } else {
                        alert('Upload da webcam riuscito ma URL non trovato.');
                    }
                },
                error: function(err) {
                    console.error(err);
                    alert('Errore nell\'upload del documento retro (webcam).');
                }
            });
        }, 'image/jpeg', 0.8);
    });

    // Avvio iniziale 
    bsnCaricaClienti();
    bsnCaricaArticoli();
    bsnCaricaNoleggi();
});
