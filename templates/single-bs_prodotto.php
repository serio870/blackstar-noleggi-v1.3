<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) :
    the_post();

    $product_id = get_the_ID();
    $meta = bsn_get_public_product_meta( $product_id );
    $gallery_urls = bsn_get_public_product_gallery_urls( $product_id );
    $video_embeds = bsn_get_public_product_video_embeds( $product_id );
    $data_ritiro = isset( $_GET['data_ritiro'] ) ? sanitize_text_field( wp_unslash( $_GET['data_ritiro'] ) ) : '';
    $data_riconsegna = isset( $_GET['data_riconsegna'] ) ? sanitize_text_field( wp_unslash( $_GET['data_riconsegna'] ) ) : '';
    $quote_cart = function_exists( 'bsn_get_quote_cart' ) ? bsn_get_quote_cart() : [ 'dates' => [] ];
    if ( $data_ritiro === '' && ! empty( $quote_cart['dates']['data_ritiro'] ) ) {
        $data_ritiro = sanitize_text_field( (string) $quote_cart['dates']['data_ritiro'] );
    }
    if ( $data_riconsegna === '' && ! empty( $quote_cart['dates']['data_riconsegna'] ) ) {
        $data_riconsegna = sanitize_text_field( (string) $quote_cart['dates']['data_riconsegna'] );
    }
    $quote_cart_url = function_exists( 'bsn_get_quote_cart_page_url' ) ? bsn_get_quote_cart_page_url() : home_url( '/carrello-noleggio/' );
    $catalog_url = get_post_type_archive_link( 'bs_prodotto' );
    $availability_html = bsn_render_public_product_availability_html( $product_id, $data_ritiro, $data_riconsegna, [
        'title' => 'Disponibilita del prodotto',
    ] );
    $customer_category = function_exists( 'bsn_get_current_public_customer_category' ) ? bsn_get_current_public_customer_category() : 'standard';
    $customer_category_label = function_exists( 'bsn_get_public_customer_category_label' ) ? bsn_get_public_customer_category_label( $customer_category ) : 'Guest / standard';
    $price_standard = bsn_get_public_product_price_from( $product_id, 'standard' );
    $price_reserved = $customer_category !== 'standard' ? bsn_get_public_product_price_from( $product_id, $customer_category ) : null;
    $articoli_collegati = bsn_get_public_product_articles( $product_id );
    $min_qty = 1;
    foreach ( $articoli_collegati as $articolo_collegato ) {
        $min_qty = max( $min_qty, intval( $articolo_collegato['min_qty'] ?? 1 ) );
    }
    $categorie = get_the_terms( $product_id, 'bs_categoria_prodotto' );
    ?>
    <main class="bsn-public-page bsn-prodotto-page">
        <div class="bsn-public-shell">
            <nav class="bsn-breadcrumbs">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'bs_prodotto' ) ); ?>">Catalogo noleggio</a>
                <span>/</span>
                <span><?php the_title(); ?></span>
            </nav>

            <article <?php post_class( 'bsn-prodotto-layout' ); ?>>
                <div class="bsn-prodotto-hero">
                    <div class="bsn-prodotto-media">
                        <?php if ( ! empty( $gallery_urls ) ) : ?>
                            <div class="bsn-prodotto-main-image">
                                <img src="<?php echo esc_url( $gallery_urls[0] ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
                            </div>
                            <?php if ( count( $gallery_urls ) > 1 ) : ?>
                                <div class="bsn-prodotto-thumb-grid">
                                    <?php foreach ( array_slice( $gallery_urls, 1, 4 ) as $gallery_url ) : ?>
                                        <img src="<?php echo esc_url( $gallery_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else : ?>
                            <div class="bsn-prodotto-media-placeholder">Immagini in aggiornamento</div>
                        <?php endif; ?>

                        <?php if ( ! empty( $video_embeds ) ) : ?>
                            <div class="bsn-prodotto-video-list">
                                <h3>Video utili</h3>
                                <div class="bsn-video-embed-grid">
                                    <?php foreach ( $video_embeds as $index => $video_item ) : ?>
                                        <div class="bsn-video-embed-card">
                                            <?php if ( ! empty( $video_item['html'] ) ) : ?>
                                                <?php echo $video_item['html']; ?>
                                            <?php else : ?>
                                                <a href="<?php echo esc_url( $video_item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php echo esc_html( 'Apri video ' . ( $index + 1 ) ); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bsn-prodotto-summary">
                        <?php if ( ! empty( $categorie ) && ! is_wp_error( $categorie ) ) : ?>
                            <div class="bsn-chip-row">
                                <?php foreach ( $categorie as $categoria ) : ?>
                                    <a class="bsn-chip" href="<?php echo esc_url( get_term_link( $categoria ) ); ?>">
                                        <?php echo esc_html( $categoria->name ); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <h1 class="bsn-prodotto-title"><?php the_title(); ?></h1>

                        <?php if ( ! empty( $meta['sottotitolo_catalogo'] ) ) : ?>
                            <p class="bsn-prodotto-subtitle"><?php echo esc_html( $meta['sottotitolo_catalogo'] ); ?></p>
                        <?php endif; ?>

                        <?php if ( has_excerpt() ) : ?>
                            <div class="bsn-prodotto-excerpt"><?php echo wp_kses_post( wpautop( get_the_excerpt() ) ); ?></div>
                        <?php endif; ?>

                        <div class="bsn-prodotto-price-box">
            <div class="bsn-price-label">Prezzo 1 giorno</div>
    <?php if ( null !== $price_standard ) : ?>
        <?php if ( null !== $price_reserved && $price_reserved < $price_standard ) : ?>
            <div class="bsn-price-main bsn-price-main-strike">Tariffa standard: <?php echo esc_html( number_format_i18n( $price_standard, 2 ) ); ?> EUR</div>
            <div class="bsn-price-main">Prezzo riservato <?php echo esc_html( $customer_category_label ); ?>: <?php echo esc_html( number_format_i18n( $price_reserved, 2 ) ); ?> EUR</div>
            <div class="bsn-price-note">Prezzo dedicato al tuo profilo cliente. Per noleggi pi&ugrave; lunghi il costo medio si riduce con il calcolo scalare.</div>
        <?php else : ?>
            <div class="bsn-price-main">Tariffa 1 giorno: <?php echo esc_html( number_format_i18n( $price_standard, 2 ) ); ?> EUR</div>
            <div class="bsn-price-note">Per noleggi pi&ugrave; lunghi il costo medio si riduce con il calcolo scalare.</div>
        <?php endif; ?>
    <?php else : ?>
        <div class="bsn-price-note">Prezzo su richiesta</div>
    <?php endif; ?>
</div>

                        <div class="bsn-prodotto-logistics">
                            <div><strong>Sede:</strong> <?php echo esc_html( $meta['sede_operativa'] ?: 'Da confermare' ); ?></div>
                            <div><strong>Quantit&agrave; minima:</strong> <?php echo esc_html( $min_qty ); ?></div>
                            <div><strong>Veicolo minimo:</strong> <?php echo esc_html( bsn_get_articolo_veicolo_minimo_options()[ $meta['veicolo_minimo'] ] ?? 'Non specificato' ); ?></div>
                        </div>

                        <div class="bsn-prodotto-date-box">
                            <div id="bsn-public-quote-intro" class="bsn-public-quote-box bsn-public-quote-box-intro">
                                <div class="bsn-price-label">Anteprima prezzo</div>
                                <div class="bsn-price-note">Inserisci date e quantit&agrave; per ottenere una stima pre-carrello. Clicca poi Verifica disponibilit&agrave;.</div>
                            </div>

                            <div id="bsn-public-cart-note" class="bsn-public-inline-note" hidden></div>

                            <div class="bsn-date-grid">
                                <div class="bsn-public-date-field" data-date-lock-trigger="1">
                                    <label for="bsn-public-data-ritiro">Data ritiro</label>
                                    <div class="bsn-public-date-shell">
                                        <div class="bsn-public-date-display" id="bsn-public-data-ritiro-display">gg-mm-aaaa</div>
                                        <input type="date" id="bsn-public-data-ritiro" class="bsn-public-date-native" value="<?php echo esc_attr( $data_ritiro ); ?>">
                                    </div>
                                </div>
                                <div class="bsn-public-date-field" data-date-lock-trigger="1">
                                    <label for="bsn-public-data-riconsegna">Data riconsegna</label>
                                    <div class="bsn-public-date-shell">
                                        <div class="bsn-public-date-display" id="bsn-public-data-riconsegna-display">gg-mm-aaaa</div>
                                        <input type="date" id="bsn-public-data-riconsegna" class="bsn-public-date-native" value="<?php echo esc_attr( $data_riconsegna ); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="bsn-date-actions" id="bsn-public-check-actions">
                                <button type="button" class="bsn-public-btn" id="bsn-public-check-availability">Verifica disponibilit&agrave;</button>
                            </div>

                            <div id="bsn-public-availability-result" class="bsn-prodotto-availability-slot">
                                <?php echo $availability_html; ?>
                            </div>

                            <div class="bsn-public-qty-row" id="bsn-public-qty-row" hidden>
                                <div class="bsn-public-qty-field">
                                    <label for="bsn-public-qty">Quantita richiesta</label>
                                    <input type="number" id="bsn-public-qty" min="<?php echo esc_attr( $min_qty ); ?>" value="<?php echo esc_attr( $min_qty ); ?>">
                                </div>
                            </div>

                            <div id="bsn-public-quote-preview" class="bsn-public-quote-box bsn-public-quote-box-result" hidden>
                                <div class="bsn-price-label">Anteprima prezzo</div>
                                <div class="bsn-price-note">La stima comparir&agrave; qui dopo la verifica della disponibilit&agrave;.</div>
                            </div>

                            <div class="bsn-date-actions bsn-date-actions-add" id="bsn-public-add-actions" hidden>
                                <button type="button" class="bsn-public-btn bsn-public-btn-secondary bsn-public-btn-pending" id="bsn-public-add-to-cart" aria-disabled="true" disabled>Aggiungi al preventivo</button>
                            </div>
                            <div id="bsn-public-add-feedback" class="bsn-public-add-feedback" hidden></div>
                        </div>
                        <div class="bsn-public-disclaimer">
                            Il carrello preventivo usa date globali uniche. Dal carrello puoi completare la richiesta e scegliere anche modalit&agrave; di consegna e servizi.
                        </div>

                        <?php if ( ! empty( $meta['disclaimer_preventivo'] ) ) : ?>
                            <div class="bsn-public-disclaimer">
                                <?php echo nl2br( esc_html( $meta['disclaimer_preventivo'] ) ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bsn-prodotto-sections">
                    <section class="bsn-public-card">
                        <h2>Descrizione</h2>
                        <div class="bsn-rich-text">
                            <?php the_content(); ?>
                        </div>
                    </section>

                    <?php if ( ! empty( $meta['specifiche_tecniche'] ) ) : ?>
                        <section class="bsn-public-card">
                            <h2>Specifiche tecniche</h2>
                            <div class="bsn-rich-text"><?php echo wp_kses_post( wpautop( $meta['specifiche_tecniche'] ) ); ?></div>
                        </section>
                    <?php endif; ?>

                    <?php if ( ! empty( $meta['faq'] ) ) : ?>
                        <section class="bsn-public-card">
                            <h2>FAQ</h2>
                            <div class="bsn-rich-text"><?php echo wp_kses_post( wpautop( $meta['faq'] ) ); ?></div>
                        </section>
                    <?php endif; ?>

                    <?php if ( ! empty( $meta['consigliati_commerciali'] ) || ! empty( $meta['correlati_commerciali'] ) ) : ?>
                        <section class="bsn-public-card">
                            <h2>Correlati e consigliati</h2>
                            <?php if ( ! empty( $meta['consigliati_commerciali'] ) ) : ?>
                                <div class="bsn-rich-text">
                                    <strong>Consigliati</strong>
                                    <?php echo wp_kses_post( wpautop( $meta['consigliati_commerciali'] ) ); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $meta['correlati_commerciali'] ) ) : ?>
                                <div class="bsn-rich-text">
                                    <strong>Correlati</strong>
                                    <?php echo wp_kses_post( wpautop( $meta['correlati_commerciali'] ) ); ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    </main>

    <script>
    (function() {
        var productId = <?php echo (int) $product_id; ?>;
        var root = <?php echo wp_json_encode( esc_url_raw( rest_url( 'bsn/v1/' ) ) ); ?>;
        var restNonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
        var btn = document.getElementById('bsn-public-check-availability');
        var addBtn = document.getElementById('bsn-public-add-to-cart');
        var checkActions = document.getElementById('bsn-public-check-actions');
        var addActions = document.getElementById('bsn-public-add-actions');
        var introBox = document.getElementById('bsn-public-quote-intro');
        var inputRitiro = document.getElementById('bsn-public-data-ritiro');
        var inputRiconsegna = document.getElementById('bsn-public-data-riconsegna');
        var inputRitiroDisplay = document.getElementById('bsn-public-data-ritiro-display');
        var inputRiconsegnaDisplay = document.getElementById('bsn-public-data-riconsegna-display');
        var inputQty = document.getElementById('bsn-public-qty');
        var qtyRow = document.getElementById('bsn-public-qty-row');
        var result = document.getElementById('bsn-public-availability-result');
        var quoteBox = document.getElementById('bsn-public-quote-preview');
        var cartNote = document.getElementById('bsn-public-cart-note');
        var feedbackBox = document.getElementById('bsn-public-add-feedback');
        var cartUrl = <?php echo wp_json_encode( esc_url_raw( $quote_cart_url ) ); ?>;
        var catalogUrl = <?php echo wp_json_encode( esc_url_raw( $catalog_url ) ); ?>;
        var minQty = <?php echo (int) $min_qty; ?>;
        var dateFields = document.querySelectorAll('[data-date-lock-trigger="1"]');
        var dateShells = document.querySelectorAll('.bsn-public-date-shell');
        var lockedDatesMessage = 'Le date di questo noleggio sono gia state definite dal carrello. Per modificarle agisci dal carrello.';
        var state = {
            cartLocked: false,
            verified: false,
            lastAvailableUnits: 0
        };

        if (!btn || !addBtn || !checkActions || !addActions || !introBox || !inputRitiro || !inputRiconsegna || !inputRitiroDisplay || !inputRiconsegnaDisplay || !inputQty || !qtyRow || !result || !quoteBox || !cartNote || !feedbackBox) {
            return;
        }

        function escHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getQtyValue() {
            var qty = Math.max(minQty, parseInt(inputQty.value || String(minQty), 10) || minQty);
            inputQty.value = String(qty);
            return qty;
        }

        function formatDateForDisplay(isoDate) {
            var parts;
            if (!isoDate || !/^\d{4}-\d{2}-\d{2}$/.test(String(isoDate))) {
                return 'gg-mm-aaaa';
            }
            parts = String(isoDate).split('-');
            return [parts[2], parts[1], parts[0]].join('-');
        }

        function syncDateDisplay(input, display) {
            var formatted = formatDateForDisplay(input.value);
            display.textContent = formatted;
            display.classList.toggle('is-placeholder', formatted === 'gg-mm-aaaa');
        }

        function syncDateDisplays() {
            syncDateDisplay(inputRitiro, inputRitiroDisplay);
            syncDateDisplay(inputRiconsegna, inputRiconsegnaDisplay);
        }

        function resetQuotePreviewBox() {
            quoteBox.hidden = true;
            quoteBox.innerHTML =
                '<div class="bsn-price-label">Anteprima prezzo</div>' +
                '<div class="bsn-price-note">La stima comparira qui dopo la verifica della disponibilita.</div>';
        }

        function hasCompleteDates() {
            return !!(inputRitiro.value && inputRiconsegna.value);
        }

        function showIntroBox() {
            introBox.hidden = false;
        }

        function hideIntroBox() {
            introBox.hidden = true;
        }

        function showQtyRow() {
            qtyRow.hidden = false;
        }

        function hideQtyRow() {
            qtyRow.hidden = true;
        }

        function resetAvailabilityResult(message) {
            result.innerHTML =
                '<div class="bsn-public-availability-live bsn-public-availability-live-neutral">' +
                    '<div class="bsn-live-badge">Disponibilita da verificare</div>' +
                    '<div class="bsn-live-note">' + escHtml(message || 'Inserisci date e quantita, poi clicca Verifica disponibilita.') + '</div>' +
                '</div>';
        }

        function getCategoryLabel(code) {
            var map = {
                standard: 'Guest / standard',
                fidato: 'Cliente fidato',
                premium: 'Cliente premium',
                service: 'Cliente service',
                collaboratori: 'Collaboratori'
            };
            return map[code] || 'Guest / standard';
        }

        function setCartNote(message, warning) {
            if (!message) {
                cartNote.hidden = true;
                cartNote.textContent = '';
                cartNote.classList.remove('is-warning');
                return;
            }
            cartNote.hidden = false;
            cartNote.textContent = message;
            cartNote.classList.toggle('is-warning', !!warning);
        }

        function clearAddFeedback() {
            feedbackBox.hidden = true;
            feedbackBox.innerHTML = '';
        }

        function showAddSuccess(message) {
            feedbackBox.hidden = false;
            feedbackBox.innerHTML =
                '<div class="bsn-public-success-box">' +
                    '<strong>' + escHtml(message || 'Prodotto aggiunto al preventivo.') + '</strong>' +
                    '<div class="bsn-public-add-feedback-actions">' +
                        '<a class="bsn-public-btn bsn-public-btn-ghost" href="' + escHtml(catalogUrl || '#') + '">Continua a noleggiare</a>' +
                        '<a class="bsn-public-btn" href="' + escHtml(cartUrl || '#') + '">Vai al preventivo</a>' +
                    '</div>' +
                '</div>';
        }

        function showCheckButton() {
            checkActions.hidden = false;
            checkActions.style.display = '';
            btn.hidden = false;
            btn.disabled = false;
        }

        function hideCheckButton() {
            checkActions.hidden = true;
            checkActions.style.display = 'none';
            btn.hidden = true;
            btn.disabled = true;
        }

        function showAddButtonSection() {
            addActions.hidden = false;
        }

        function hideAddButtonSection() {
            addActions.hidden = true;
        }

        function disableAddButton() {
            addBtn.disabled = true;
            addBtn.classList.add('bsn-public-btn-pending');
            addBtn.setAttribute('aria-disabled', 'true');
        }

        function enableAddButton() {
            addBtn.disabled = false;
            addBtn.classList.remove('bsn-public-btn-pending');
            addBtn.setAttribute('aria-disabled', 'false');
        }

        function lockDateInputs(locked) {
            inputRitiro.disabled = !!locked;
            inputRiconsegna.disabled = !!locked;
            dateFields.forEach(function(field) {
                field.classList.toggle('is-locked', !!locked);
                field.setAttribute('title', locked ? lockedDatesMessage : '');
            });
        }

        function restoreDefaultNote() {
            if (state.cartLocked) {
                setCartNote(lockedDatesMessage, false);
            } else {
                setCartNote('', false);
            }
            clearAddFeedback();
        }

        function resetVerificationState() {
            state.verified = false;
            state.lastAvailableUnits = 0;
            resetAvailabilityResult(
                state.cartLocked
                    ? 'Aggiornamento automatico sulla disponibilita del periodo gia fissato nel carrello.'
                    : 'Inserisci date e quantita, poi clicca Verifica disponibilita.'
            );
            resetQuotePreviewBox();
            disableAddButton();
            if (!state.cartLocked) {
                showIntroBox();
                hideQtyRow();
                hideAddButtonSection();
                showCheckButton();
                setCartNote('', false);
            } else {
                hideIntroBox();
                showQtyRow();
                showAddButtonSection();
                hideCheckButton();
            }
            clearAddFeedback();
        }

        function validateDateRange() {
            if (!inputRitiro.value || !inputRiconsegna.value) {
                return true;
            }
            if (inputRitiro.value && inputRiconsegna.value && inputRitiro.value > inputRiconsegna.value) {
                disableAddButton();
                if (!state.cartLocked) {
                    hideAddButtonSection();
                }
                state.verified = false;
                state.lastAvailableUnits = 0;
                alert('La data di riconsegna deve essere uguale o successiva alla data di ritiro.');
                resetQuotePreviewBox();
                result.innerHTML = '<div class="bsn-public-warning-box">Le date inserite non sono valide: la riconsegna non puo essere precedente al ritiro.</div>';
                clearAddFeedback();
                return false;
            }
            return true;
        }

        function renderQuotePreview(data) {
            if (!data || data.success === false) {
                resetQuotePreviewBox();
                return false;
            }

            var qty = Number(data.qty || getQtyValue());
            var prezzoUnitario = Number(data.prezzo_netto || 0).toFixed(2);
            var totaleStimato = Number(data.totale_stimato || 0);
            var risparmioScalare = Number(data.risparmio_scalare || 0);
            var risparmioCategoria = Number(data.risparmio_categoria || 0);
            var totaleSenzaSconti = totaleStimato + risparmioCategoria;
            var typeLabel = data.noleggio_scalare ? 'Scalare' : 'Lineare';
            var extraMessage = data.message ? '<div class="bsn-price-note">' + escHtml(data.message) + '</div>' : '';

            quoteBox.hidden = false;
            quoteBox.innerHTML =
                '<div class="bsn-price-label">Anteprima prezzo</div>' +
                '<div class="bsn-public-quote-meta">' +
                    '<div><strong>Prezzo unitario:</strong> ' + escHtml(prezzoUnitario) + ' EUR</div>' +
                    '<div><strong>Categoria prezzo:</strong> ' + escHtml(getCategoryLabel(data.categoria_cliente || 'standard')) + '</div>' +
                    '<div><strong>Quantita:</strong> ' + escHtml(qty) + '</div>' +
                    '<div><strong>Giorni:</strong> ' + escHtml(data.giorni || 1) + '</div>' +
                    '<div><strong>Fattore:</strong> ' + escHtml(typeLabel) + '</div>' +
                    '<div><strong>Totale senza sconti:</strong> ' + escHtml(totaleSenzaSconti.toFixed(2)) + ' EUR</div>' +
                    '<div><strong>Risparmio noleggio scalare:</strong> ' + escHtml(risparmioScalare.toFixed(2)) + ' EUR</div>' +
                    '<div><strong>Risparmio categoria:</strong> ' + escHtml(risparmioCategoria.toFixed(2)) + ' EUR</div>' +
                '</div>' +
                '<div class="bsn-public-quote-total"><strong class="bsn-public-quote-total-label">Totale stimato finale:</strong> <strong class="bsn-public-quote-total-value">' + escHtml(totaleStimato.toFixed(2)) + ' EUR</strong></div>' +
                '<div class="bsn-price-note">Stima ' + escHtml(getCategoryLabel(data.categoria_cliente || 'standard')) + ', iva esclusa.</div>' +
                extraMessage;

            return true;
        }

        function renderAvailability(data, qty) {
            var badge = data.badge_marketing || data.badge || 'Non disponibile';
            var availableUnits = Number(data.available_units || 0);
            var rawAvailableUnits = Number(data.available_units_raw || availableUnits || 0);
            var cartReservedQty = Number(data.cart_reserved_qty || 0);
            var warningBlocks = [];

            if (Array.isArray(data.warning_messages) && data.warning_messages.length) {
                warningBlocks.push(
                    '<div class="bsn-public-warning-box"><strong>Avvisi sul materiale selezionabile</strong><ul>' +
                        data.warning_messages.map(function(item) {
                            return '<li>' + escHtml(item) + '</li>';
                        }).join('') +
                    '</ul></div>'
                );
            }

            if (availableUnits < qty) {
                if (cartReservedQty > 0) {
                    warningBlocks.push(
                        '<div class="bsn-public-warning-box"><strong>Hai gia nel preventivo ' + escHtml(cartReservedQty) + ' di questo prodotto.</strong><div>Disponibilita residua per queste date: ' + escHtml(availableUnits) + ' unita.</div></div>'
                    );
                    setCartNote('Hai gia nel preventivo ' + cartReservedQty + ' di questo prodotto. Disponibilita residua: ' + availableUnits + '.', true);
                } else {
                    warningBlocks.push(
                        '<div class="bsn-public-warning-box"><strong>Quantita richiesta superiore a quella disponibile per il periodo selezionato.</strong><div>Disponibili subito: ' + escHtml(availableUnits) + ' unita. Riduci la quantita oppure contattaci per una verifica manuale.</div></div>'
                    );
                    setCartNote('Quantita richiesta superiore a quella disponibile per il periodo selezionato.', true);
                }
            } else {
                restoreDefaultNote();
            }

            result.innerHTML =
                '<div class="bsn-public-availability-live">' +
                    '<div class="bsn-live-badge">' + escHtml(badge) + '</div>' +
                    '<div class="bsn-live-count">' + escHtml(availableUnits) + ' / ' + escHtml(data.total_units || 0) + ' unita disponibili per il periodo selezionato</div>' +
                    (cartReservedQty > 0 ? '<div class="bsn-live-note">Hai gia nel preventivo ' + escHtml(cartReservedQty) + ' unita di questo prodotto. Disponibilita reale del magazzino: ' + escHtml(rawAvailableUnits) + '.</div>' : '') +
                    '<div class="bsn-live-note">Se ti serve piu materiale, contattaci per una verifica dedicata.</div>' +
                '</div>' +
                warningBlocks.join('');

            return availableUnits;
        }

        function updateButtonsAfterCheck(quoteOk, enoughAvailability) {
            if (state.cartLocked) {
                hideCheckButton();
                showAddButtonSection();
            } else {
                showCheckButton();
                if (quoteOk) {
                    showAddButtonSection();
                } else {
                    hideAddButtonSection();
                }
            }

            if (quoteOk && enoughAvailability) {
                enableAddButton();
            } else {
                disableAddButton();
            }
        }

        function runAvailabilityCheck(options) {
            options = options || {};
            var qty = getQtyValue();
            var params = new URLSearchParams({ product_id: String(productId) });

            if (!hasCompleteDates()) {
                state.verified = false;
                state.lastAvailableUnits = 0;
                resetQuotePreviewBox();
                disableAddButton();
                if (!state.cartLocked) {
                    hideQtyRow();
                    hideAddButtonSection();
                }
                result.innerHTML = '<div class="bsn-public-warning-box">Seleziona data ritiro e data riconsegna prima di verificare la disponibilita.</div>';
                if (!options.auto) {
                    alert('Seleziona data ritiro e data riconsegna prima di verificare la disponibilita.');
                }
                return Promise.resolve(false);
            }

            if (inputRitiro.value) {
                params.set('data_ritiro', inputRitiro.value);
            }
            if (inputRiconsegna.value) {
                params.set('data_riconsegna', inputRiconsegna.value);
            }
            params.set('qty', String(qty));

            if (!validateDateRange()) {
                return Promise.resolve(false);
            }

            state.verified = false;
            state.lastAvailableUnits = 0;
            clearAddFeedback();
            resetQuotePreviewBox();
            disableAddButton();
            showQtyRow();
            if (state.cartLocked) {
                showAddButtonSection();
            } else {
                hideAddButtonSection();
            }

            if (!state.cartLocked) {
                btn.disabled = true;
                btn.textContent = options.auto ? 'Aggiornamento...' : 'Verifica in corso...';
            }

            return fetch(root + 'public-products/availability?' + params.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': restNonce
                }
            })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    state.lastAvailableUnits = renderAvailability(data, qty);
                    return fetch(root + 'public-products/quote-preview?' + params.toString(), {
                        credentials: 'same-origin',
                        headers: {
                            'X-WP-Nonce': restNonce
                        }
                    })
                        .then(function(response) { return response.json(); })
                        .then(function(quoteData) {
                            return {
                                availability: data,
                                quote: quoteData
                            };
                        });
                })
                .then(function(payload) {
                    var quoteOk = renderQuotePreview(payload.quote);
                    var enoughAvailability = state.lastAvailableUnits >= qty;
                    state.verified = !!quoteOk && enoughAvailability;
                    updateButtonsAfterCheck(quoteOk, enoughAvailability);
                    return state.verified;
                })
                .catch(function() {
                    disableAddButton();
                    state.verified = false;
                    state.lastAvailableUnits = 0;
                    result.innerHTML = '<div class="bsn-public-warning-box">Errore nel recupero della disponibilita.</div>';
                    resetQuotePreviewBox();
                    if (!state.cartLocked) {
                        hideAddButtonSection();
                    }
                    return false;
                })
                .finally(function() {
                    if (!state.cartLocked) {
                        btn.disabled = false;
                        btn.textContent = 'Verifica disponibilita';
                    }
                });
        }

        function applyCartState(cart, source) {
            var hasLockedCart = !!(cart && cart.item_count > 0 && cart.has_dates && !cart.invalid_dates);

            if (!hasLockedCart) {
                state.cartLocked = false;
                showIntroBox();
                lockDateInputs(false);
                showCheckButton();
                setCartNote('', false);
                resetVerificationState();
                return false;
            }

            var cartRitiro = String(cart.dates.data_ritiro || '');
            var cartRiconsegna = String(cart.dates.data_riconsegna || '');
            var staleDates = (
                (inputRitiro.value && inputRitiro.value !== cartRitiro) ||
                (inputRiconsegna.value && inputRiconsegna.value !== cartRiconsegna)
            );

            inputRitiro.value = cartRitiro;
            inputRiconsegna.value = cartRiconsegna;
            syncDateDisplays();
            state.cartLocked = true;
            state.verified = false;
            hideIntroBox();
            lockDateInputs(true);
            hideCheckButton();
            showAddButtonSection();
            showQtyRow();
            disableAddButton();

            if (staleDates || source === 'stale') {
                setCartNote('Le date di questa scheda erano diverse da quelle gia presenti nel carrello. Sono state riallineate al periodo globale del preventivo.', true);
            } else {
                setCartNote(lockedDatesMessage, false);
            }

            return true;
        }

        function fetchCartState() {
            return fetch(root + 'quote-cart', {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': restNonce
                }
            })
                .then(function(response) { return response.json(); })
                .catch(function() { return null; });
        }

        function submitAddToCart(forceDates) {
            var params = new URLSearchParams({
                product_id: String(productId),
                qty: String(getQtyValue())
            });

            if (inputRitiro.value) {
                params.set('data_ritiro', inputRitiro.value);
            }
            if (inputRiconsegna.value) {
                params.set('data_riconsegna', inputRiconsegna.value);
            }
            if (forceDates) {
                params.set('force_dates', '1');
            }

            addBtn.disabled = true;
            addBtn.textContent = 'Aggiunta in corso...';

            return fetch(root + 'quote-cart/add', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-WP-Nonce': restNonce
                },
                body: params.toString()
            })
            .then(function(response) {
                return response.json().then(function(data) {
                    if (!response.ok) {
                        throw data;
                    }
                    return data;
                });
            })
            .then(function(data) {
                if (data && data.success === false && data.code === 'dates_conflict') {
                    if (window.confirm(data.message || 'Il carrello usa gia un altro periodo. Vuoi aggiornarlo?')) {
                        return submitAddToCart(true);
                    }
                    return null;
                }
                if (data && data.success) {
                    showAddSuccess('Prodotto aggiunto al preventivo.');
                    return null;
                }
                throw data || { message: 'Errore durante l\'aggiunta al preventivo.' };
            })
            .catch(function(error) {
                alert((error && error.message) ? error.message : 'Errore durante l\'aggiunta al preventivo.');
                return null;
            })
            .finally(function() {
                addBtn.disabled = false;
                addBtn.textContent = 'Aggiungi al preventivo';
            });
        }

        btn.addEventListener('click', function() {
            runAvailabilityCheck({ auto: false });
        });

        dateFields.forEach(function(field) {
            field.addEventListener('click', function() {
                if (!state.cartLocked) {
                    return;
                }
                setCartNote(lockedDatesMessage, true);
            });
        });

        [inputRitiro, inputRiconsegna].forEach(function(input) {
            input.addEventListener('change', function() {
                if (state.cartLocked) {
                    setCartNote(lockedDatesMessage, true);
                    return;
                }
                resetVerificationState();
            });
        });

        function handleQtyChange() {
            getQtyValue();
            if (hasCompleteDates() && (state.cartLocked || !btn.hidden || !quoteBox.hidden || !qtyRow.hidden)) {
                runAvailabilityCheck({ auto: true });
                return;
            }
            resetVerificationState();
        }

        inputQty.addEventListener('change', handleQtyChange);
        inputQty.addEventListener('input', handleQtyChange);

        addBtn.addEventListener('click', function() {
            var qty = getQtyValue();

            if (addBtn.disabled || addBtn.getAttribute('aria-disabled') === 'true') {
                return;
            }

            if (!inputRitiro.value || !inputRiconsegna.value) {
                alert('Seleziona data ritiro e data riconsegna prima di aggiungere il prodotto al preventivo.');
                return;
            }
            if (!validateDateRange()) {
                return;
            }

            fetchCartState().then(function(cart) {
                if (cart && cart.item_count > 0 && cart.has_dates && !cart.invalid_dates) {
                    var cartRitiro = String(cart.dates.data_ritiro || '');
                    var cartRiconsegna = String(cart.dates.data_riconsegna || '');
                    if (inputRitiro.value !== cartRitiro || inputRiconsegna.value !== cartRiconsegna) {
                        applyCartState(cart, 'stale');
                        alert('Questo prodotto era aperto con date diverse da quelle del carrello. Le date sono state riallineate: verifica il prodotto e poi ripeti l\'aggiunta.');
                        runAvailabilityCheck({ auto: true });
                        return;
                    }
                }

                if (!state.cartLocked && !state.verified) {
                    alert('Prima di aggiungere al preventivo, verifica la disponibilita per le date selezionate.');
                    return;
                }
                if (qty > state.lastAvailableUnits) {
                    alert('Quantita richiesta superiore a quella disponibile per il periodo selezionato.');
                    return;
                }

                submitAddToCart(false);
            });
        });

        syncDateDisplays();
        resetQuotePreviewBox();
        disableAddButton();
        hideAddButtonSection();
        hideQtyRow();

        getQtyValue();
        fetchCartState().then(function(cart) {
            if (applyCartState(cart, 'load')) {
                runAvailabilityCheck({ auto: true });
            } else {
                lockDateInputs(false);
                showIntroBox();
                showCheckButton();
                hideQtyRow();
                hideAddButtonSection();
            }
        });

        function openDatePicker(input) {
            if (!input || input.disabled) {
                return;
            }

            try {
                if (typeof input.showPicker === 'function') {
                    input.showPicker();
                    return;
                }
            } catch (error) {
            }

            input.focus();
        }

        dateShells.forEach(function(shell) {
            var input = shell.querySelector('input[type="date"]');
            if (!input) {
                return;
            }

            shell.addEventListener('click', function(event) {
                if (input.disabled) {
                    return;
                }
                if (event.target === input) {
                    return;
                }
                event.preventDefault();
                openDatePicker(input);
            });
        });

        [inputRitiro, inputRiconsegna].forEach(function(input) {
            input.addEventListener('click', function() {
                openDatePicker(input);
            });
        });

        [inputRitiro, inputRiconsegna].forEach(function(input) {
            input.addEventListener('change', syncDateDisplays);
            input.addEventListener('input', syncDateDisplays);
        });
    })();
    </script>
    <?php
endwhile;

get_footer();
















