<?php
/**
 * Plugin Name: Black Star Noleggi
 * Description: Gestione noleggi audio/video/luci per Black Star Service SRL (bozze, correlati, clonazione).
 * Version: 1.2.0
 * Author: Perplexity AI + Black Star
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) exit;

define('BSN_VERSION', '1.2.0');
define('BSN_PATH', plugin_dir_path(__FILE__));
define('BSN_URL', plugin_dir_url(__FILE__));

/**
 * Attivazione: crea tabelle
 */
register_activation_hook(__FILE__, 'bsn_install_tables');
function bsn_install_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

            // Clienti
    $table_clienti = $wpdb->prefix . 'bs_clienti';
    $sql_clienti = "CREATE TABLE $table_clienti (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        -- Anagrafica
        nome varchar(150) NOT NULL,              -- Nome / ragione sociale
        cognome varchar(100),                    -- Cognome (se serve separato)
        cf_piva varchar(32) NOT NULL,            -- CF o P.IVA
        telefono varchar(30) NOT NULL,
        indirizzo varchar(255),
        cap varchar(10),
        citta varchar(100),
        email varchar(150) NOT NULL,

        -- Categoria cliente (per scontistica)
        categoria_cliente enum('standard','fidato','premium','service','collaboratori') DEFAULT 'standard',

        -- Regime fiscale
        regime_percentuale decimal(5,2) DEFAULT 22.00,  -- es. 22, 10, 0
        regime_note varchar(150),                      -- es. \"esente art. 10\"

        -- Documenti identità (salveremo path file, non l'immagine in sé)
        doc_fronte varchar(255),
        doc_retro varchar(255),
        tipo_documento varchar(50),
        numero_documento varchar(50),

        -- Note interne cliente
        note text,

        data_creazione datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

   // Articoli (con futuro uso correlati JSON)

$table_articoli = $wpdb->prefix . 'bs_articoli';
$sql_articoli = "CREATE TABLE $table_articoli (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    nome varchar(150) NOT NULL,
    codice varchar(50) UNIQUE,
    prezzo_giorno decimal(10,2) NOT NULL DEFAULT 0,
    valore_bene  decimal(10,2) NOT NULL DEFAULT 0,
    -- Sconti per categoria cliente
    sconto_standard decimal(5,2) DEFAULT 0,
    sconto_fidato decimal(5,2) DEFAULT 0,
    sconto_premium decimal(5,2) DEFAULT 0,
    sconto_service decimal(5,2) DEFAULT 0,
    sconto_collaboratori decimal(5,2) DEFAULT 0,
    -- Flag: se 1, il noleggio di questo articolo usa formula scalare (√giorni) invece che lineare
    noleggio_scalare tinyint(1) NOT NULL DEFAULT 0,
    ubicazione varchar(100),
    qty_disponibile int DEFAULT 1,
    correlati longtext, -- JSON: [{'id_articolo':1,'qty':1}, ...]
    note text,
    data_creazione datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset;";

    // Noleggi (bozza/attivo/chiuso/ritardo)
    $table_noleggi = $wpdb->prefix . 'bs_noleggi';
    $sql_noleggi = "CREATE TABLE $table_noleggi (
        id varchar(20) NOT NULL, -- es. 2026/001
        cliente_id mediumint(9),
        data_richiesta datetime,
        data_inizio datetime,
        data_fine datetime,
        stato enum('bozza','attivo','chiuso','ritardo') DEFAULT 'bozza',
        operatore_richiesta varchar(50),
        operatore_verifica varchar(50),
        articoli longtext, -- JSON con qty + correlati espansi
        totale_calcolato decimal(10,2),
        sconto_globale decimal(10,2) DEFAULT 0,
        note text,
        -- Nuovi campi logistica/trasporto
        luogo_destinazione varchar(255),
        trasporto_mezzo varchar(255),
        cauzione varchar(255),
        causale_trasporto varchar(255),
        PRIMARY KEY (id)
    ) $charset;";


    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_clienti);
    dbDelta($sql_articoli);
    dbDelta($sql_noleggi);
}

// === STEP A2: Ruolo e capability per l'app noleggi ===
function bsn_add_roles_and_caps() {
    $cap = 'bsn_manage_noleggi';

    // Aggiunge la capability ad administrator
    if ( $admin = get_role( 'administrator' ) ) {
        $admin->add_cap( $cap );
    }

    // Aggiunge la capability anche all'editor (se usi gli editor sull'app)
    if ( $editor = get_role( 'editor' ) ) {
        $editor->add_cap( $cap );
    }

    // Crea ruolo dedicato Operatore Noleggi, se non esiste
    if ( ! get_role( 'bsn_operatore' ) ) {
        add_role(
            'bsn_operatore',
            'Operatore Noleggi',
            array(
                'read'             => true,
                $cap               => true,
                // niente manage_options, niente edit_posts, ecc.
            )
        );
    }
}
register_activation_hook( __FILE__, 'bsn_add_roles_and_caps' );


/**
 * Menu admin base
 */
add_action('admin_menu', 'bsn_admin_menu');
function bsn_admin_menu() {
    add_menu_page(
        'Black Star Noleggi',
        'BS Noleggi',
        'manage_options',
        'bs-noleggi',
        'bsn_admin_page'
    );
}

add_action('admin_menu', function() {
    // Pagina nascosta, raggiungibile solo via URL (non appare nel menu)
    add_submenu_page(
        null, // niente voce di menu
        'Finalizza noleggio',
        'Finalizza noleggio',
        'bsn_manage_noleggi', // ORA GLI OPERATORI POSSONO ACCEDERE
        'bsn-finalizza-noleggio',
        'bsn_render_finalizza_noleggio_page'
    );

        // Pagina nascosta: Rientro noleggio (come Finalizza, ma per chiusura)
    add_submenu_page(
        null,
        'Rientro noleggio',
        'Rientro noleggio',
        'bsn_manage_noleggi',
        'bsn-rientro-noleggio',
        'bsn_render_rientro_noleggio_page'
    );

});


function bsn_render_finalizza_noleggio_page( $args = [] ) {
    global $wpdb;

    $args = wp_parse_args(
        $args,
        [
            'back_url' => admin_url( 'admin.php?page=blackstar-noleggi' ),
        ]
    );

    // ===== RECUPERA ID NOLEGGIO =====
    $noleggio_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    
    if (empty($noleggio_id)) {
        echo '<div class="wrap"><h1>Errore</h1><p>ID noleggio mancante.</p></div>';
        return;
    }
    
    // ===== RECUPERA DATI NOLEGGIO DAL DB =====
    $table_noleggi = $wpdb->prefix . 'bs_noleggi';
    $noleggio = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_noleggi WHERE id = %s",
        $noleggio_id
    ), ARRAY_A);
    
    if (!$noleggio) {
        echo '<div class="wrap"><h1>Errore</h1><p>Noleggio non trovato.</p></div>';
        return;
    }
    
    // ===== VERIFICA STATO (solo bozze possono essere finalizzate) =====
    if ($noleggio['stato'] !== 'bozza') {
        echo '<div class="wrap"><h1>Attenzione</h1><p>Questo noleggio è già in stato: <strong>' . esc_html($noleggio['stato']) . '</strong>. Solo le bozze possono essere finalizzate.</p>';
        echo '<p><a href="admin.php?page=blackstar-noleggi" class="button">← Torna ai noleggi</a></p></div>';
        return;
    }
    
    // ===== RECUPERA DATI CLIENTE =====
    $table_clienti = $wpdb->prefix . 'bs_clienti';
    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_clienti WHERE id = %d",
        $noleggio['cliente_id']
    ), ARRAY_A);
    
    if (!$cliente) {
        echo '<div class="wrap"><h1>Errore</h1><p>Cliente non trovato.</p></div>';
        return;
    }
    
    // ===== DECODIFICA ARTICOLI JSON =====
    $articoli = json_decode($noleggio['articoli'], true);
    if (!is_array($articoli)) {
        $articoli = [];
    }
    
    // ===== PREPARA DATE E CALCOLO GIORNI (IGNORE ORARIO, COME NEL FORM) =====
    // Prendiamo solo la parte data (YYYY-MM-DD), così siamo coerenti anche dopo gli update
    $data_inizio_raw = substr($noleggio['data_inizio'], 0, 10);
    $data_fine_raw   = substr($noleggio['data_fine'], 0, 10);

    $data_inizio_formattata = date('d/m/Y', strtotime($data_inizio_raw));
    $data_fine_formattata   = date('d/m/Y', strtotime($data_fine_raw));

    $ts_inizio = strtotime($data_inizio_raw . ' 00:00:00');
    $ts_fine   = strtotime($data_fine_raw   . ' 00:00:00');

    // stessa logica del form noleggio: differenza in giorni interi, minimo 1
    $giorni = 1;
    if ($ts_inizio && $ts_fine && $ts_fine > $ts_inizio) {
        $giorni = max(1, (int)ceil(($ts_fine - $ts_inizio) / 86400));
    }

    $fattore_giorni_scalare = sqrt($giorni);
    
    // ===== RECUPERA TUTTI GLI ARTICOLI DAL DB =====
    $table_articoli = $wpdb->prefix . 'bs_articoli';
    $ids_articoli = array_map(function($a) { return (int)$a['id']; }, $articoli);
    $ids_articoli = array_values(array_unique($ids_articoli));
    
    $mappa_articoli = [];
    if (!empty($ids_articoli)) {
        $placeholders = implode(',', array_fill(0, count($ids_articoli), '%d'));
        $sql_art = "SELECT * FROM $table_articoli WHERE id IN ($placeholders)";
        $rows_art = $wpdb->get_results($wpdb->prepare($sql_art, $ids_articoli), ARRAY_A);
        
        foreach ($rows_art as $row) {
            $mappa_articoli[(int)$row['id']] = $row;
        }
    }
    
    // ===== CALCOLO TOTALI (come in JS) =====
    $categoria = $cliente['categoria_cliente'] ?? 'standard';
    $regime = (float)($cliente['regime_percentuale'] ?? 22.00);
    $subtotale = 0.0;
    
    ?>
    
    <div class="wrap bsn-finalizza-wrap">
        
        <!-- ===== HEADER AZIONI ===== -->
        <div class="bsn-finalizza-header" style="background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccc;">
            <a href="<?php echo esc_url( $args['back_url'] ); ?>" class="button">← Torna ai noleggi</a>
            <button id="bsn-salva-finalizza" class="button button-primary" style="float: right; margin-left: 10px;">💾 Salva e Attiva Noleggio</button>
            <button id="bsn-stampa-documento" class="button" style="float: right;">🖨️ Stampa/Scarica PDF</button>
            <div style="clear: both;"></div>
        </div>
        
        <!-- ===== DOCUMENTO PRINCIPALE ===== -->
        <div id="bsn-documento-finalizzazione" style="background: #fff; padding: 30px; max-width: 21cm; margin: 0 auto; border: 1px solid #ddd;">
            
            <!-- ===== LOGO E INTESTAZIONE (STESSA RIGA) ===== -->
            <div class="bsn-doc-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px;">
                <div style="flex: 0 0 auto;">
                    <img src="http://www.blackstarservice.it/wp-content/uploads/2021/04/logo-1500px.jpg" 
                         alt="Black Star Service" 
                         style="max-width: 280px; height: auto;">
                </div>
                <div style="flex: 1; text-align: right; font-size: 10px; line-height: 1.4; padding-left: 20px;">
                    <strong style="font-size: 11px;">BLACK STAR SERVICE S.R.L.</strong><br>
                    Sede Legale: Via Repubblica Argentina, 54 - 25124 Brescia<br>
                    Sede Operativa: Via Cerca, 28 - 25135 Brescia<br>
                    Partita IVA: 04130270988
                </div>
            </div>
            
            <!-- ===== TIPOLOGIA DOCUMENTO ===== -->
            <div style="text-align: center; font-size: 9px; font-style: italic; margin-bottom: 10px;">
                Documento di responsabilità e di trasporto ai sensi<br>
                (D.P.R. 472 del 14-08-1996 - D.P.R. 696 del 21-12-1996)
            </div>
            
            <!-- ===== SEPARATORE ===== -->
            <div style="border-bottom: 2px solid #000; margin-bottom: 8px;"></div>
            
            <!-- ===== NUMERO NOLEGGIO ===== -->
            <div style="text-align: right; margin-bottom: 15px;">
                <strong style="font-size: 15px;">NOLEGGIO N° <?php echo esc_html($noleggio_id); ?></strong><br>
                <span style="font-size: 10px;">Data richiesta: <?php echo date('d/m/Y', strtotime($noleggio['data_richiesta'])); ?></span>
            </div>
            
            <!-- ===== DATI CLIENTE E NOLEGGIO (DUE COLONNE) ===== -->
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                
                <!-- COLONNA SINISTRA: DATI CLIENTE -->
                <div style="flex: 1; border: 1px solid #000; padding: 10px;">
                    <h3 style="margin: 0 0 8px 0; font-size: 12px; background: #000; color: #fff; padding: 3px 8px; margin: -10px -10px 8px -10px;">DATI CLIENTE</h3>
                    <table style="width: 100%; font-size: 10px; line-height: 1.3;">
                        <tr>
                            <td style="width: 35%; padding: 2px 0;"><strong>Nome/Rag. Soc.:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html($cliente['nome']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>CF/P.IVA:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html($cliente['cf_piva'] ?? 'N/D'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Telefono:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html($cliente['telefono'] ?? 'N/D'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Email:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html($cliente['email'] ?? 'N/D'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Documento:</strong></td>
                            <td style="padding: 2px 0;">
                                <?php 
                                $doc_tipo = !empty($cliente['tipo_documento']) ? esc_html($cliente['tipo_documento']) : 'N/D';
                                $doc_numero = !empty($cliente['numero_documento']) ? ' - ' . esc_html($cliente['numero_documento']) : '';
                                echo $doc_tipo . $doc_numero;
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Categoria:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html(strtoupper($cliente['categoria_cliente'] ?? 'Standard')); ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- COLONNA DESTRA: DATI NOLEGGIO -->
                <div style="flex: 1; border: 1px solid #000; padding: 10px;">
                    <h3 style="margin: 0 0 8px 0; font-size: 12px; background: #000; color: #fff; padding: 3px 8px; margin: -10px -10px 8px -10px;">DATI NOLEGGIO</h3>
                    <table style="width: 100%; font-size: 10px; line-height: 1.3;">
                        <tr>
                            <td style="width: 35%; padding: 2px 0;"><strong>Periodo:</strong></td>
                            <td style="padding: 2px 0;">dal <strong><?php echo $data_inizio_formattata; ?></strong> al <strong><?php echo $data_fine_formattata; ?></strong></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Luogo Dest.:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html($noleggio['luogo_destinazione'] ?? 'N/D'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Trasporto:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html($noleggio['trasporto_mezzo'] ?? 'N/D'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Cauzione:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html($noleggio['cauzione'] ?? 'N/D'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Causale:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html($noleggio['causale_trasporto'] ?? 'N/D'); ?></td>
                        </tr>
                        <?php if (!empty($noleggio['note'])): ?>
                        <tr>
                            <td style="padding: 2px 0; vertical-align: top;"><strong>Note:</strong></td>
                            <td style="padding: 2px 0;"><?php echo nl2br(esc_html($noleggio['note'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
            </div>
            
            <!-- ===== TABELLA ARTICOLI COMPLETA ===== -->
            <div class="bsn-doc-section" style="margin-bottom: 15px;">
                <h3 style="font-size: 12px; background: #000; color: #fff; padding: 3px 8px; margin-bottom: 8px;">ARTICOLI NOLEGGIATI</h3>
                
                <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
                    <thead>
                        <tr style="background: #f0f0f0;">
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 8%;">Codice</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 15%;">Descrizione</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 15%;">Correlati</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 10%;">Ubicazione</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: right; width: 7%;">Prezzo</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 5%;">Q.tà</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 8%;">Fattore</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 7%;">Sconto</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: right; width: 8%;">Subtot.</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: right; width: 9%;">Val. Bene</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($articoli as $art) {
                            $qty = isset($art['qty']) ? (int)$art['qty'] : 1;
                            $articolo_id = isset($art['id']) ? (int)$art['id'] : 0;
                            $prezzo_custom = isset($art['prezzo']) ? (float)$art['prezzo'] : null;
                            
                            if (!isset($mappa_articoli[$articolo_id])) {
                                continue; // Salta se articolo non trovato
                            }
                            
                            $articolo_db = $mappa_articoli[$articolo_id];
                            
                            // Dati articolo
                            $codice = $articolo_db['codice'] ?? 'N/D';
                            $nome = $articolo_db['nome'] ?? 'Articolo #' . $articolo_id;
                            $prezzo_base = (float)($articolo_db['prezzo_giorno'] ?? 0);
                            $valore_bene = (float)($articolo_db['valore_bene'] ?? 0);
                            $ubicazione = $articolo_db['ubicazione'] ?? '-';
                            $noleggio_scalare = (int)($articolo_db['noleggio_scalare'] ?? 0);
                            
                            // Usa prezzo custom se presente
                            $prezzo = $prezzo_custom !== null ? $prezzo_custom : $prezzo_base;
                            
                            // Sconto categoria
                            $sconto = 0;
                            switch ($categoria) {
                                case 'fidato':
                                    $sconto = (float)($articolo_db['sconto_fidato'] ?? 0);
                                    break;
                                case 'premium':
                                    $sconto = (float)($articolo_db['sconto_premium'] ?? 0);
                                    break;
                                case 'service':
                                    $sconto = (float)($articolo_db['sconto_service'] ?? 0);
                                    break;
                                case 'collaboratori':
                                    $sconto = (float)($articolo_db['sconto_collaboratori'] ?? 0);
                                    break;
                                default:
                                    $sconto = (float)($articolo_db['sconto_standard'] ?? 0);
                                    break;
                            }
                            
                            $prezzo_netto = $prezzo * (1 - ($sconto / 100));
                            
                            // Calcolo fattore e subtotale
                            if ($noleggio_scalare === 1) {
                                $fattore_display = '√' . $giorni;
                                $subtotale_riga = $prezzo_netto * $qty * $fattore_giorni_scalare;
                            } else {
                                $fattore_display = 'Lineare';
                                $subtotale_riga = $prezzo_netto * $qty;
                            }
                            
                            $subtotale += $subtotale_riga;
                            
                            // Correlati
                            $correlati_testo = '-';
                            if (!empty($articolo_db['correlati'])) {
                                try {
                                    $correlati = json_decode($articolo_db['correlati'], true);
                                    if (is_array($correlati) && !empty($correlati)) {
                                        $corrLabels = array_map(function($c) use ($qty) {
                                            $qtyCorr = ((int)($c['qty'] ?? 1)) * $qty;
                                            return $qtyCorr . 'x ' . ($c['nome'] ?? '');
                                        }, $correlati);
                                        $correlati_testo = implode(', ', $corrLabels);
                                    }
                                } catch (Exception $e) {}
                            }
                            
                            ?>
                            <tr>
                                <td style="border: 1px solid #ccc; padding: 3px;"><?php echo esc_html($codice); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px;"><?php echo esc_html($nome); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; font-size: 8px;"><?php echo esc_html($correlati_testo); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px;"><?php echo esc_html($ubicazione); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: right;">€ <?php echo number_format($prezzo, 2, ',', '.'); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: center;"><?php echo $qty; ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: center; font-size: 8px;"><?php echo $fattore_display; ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: center;">-<?php echo number_format($sconto, 0); ?>%</td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: right;">€ <?php echo number_format($subtotale_riga, 2, ',', '.'); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: right; color: #666;">€ <?php echo number_format($valore_bene, 2, ',', '.'); ?></td>
                            </tr>
                            <?php
                        }
                        
                        // ===== CALCOLO TOTALI FINALI =====
                        $importo_regime = $subtotale * ($regime / 100);
                        $totale_noleggio = $subtotale + $importo_regime;

                        // Totale finale (già calcolato e salvato)
                        $totale_finale = (float)($noleggio['totale_calcolato'] ?? 0);

                        // Calcola lo sconto globale (differenza tra totale_noleggio con regime e totale_finale)
                        $sconto_globale = $totale_finale - $totale_noleggio;

                        // Lo sconto globale non lo ricalcoliamo, è già incluso in totale_calcolato
                        // Se vuoi mostrarlo separatamente, devi aggiungerlo come campo nel DB

                        ?>
                    </tbody>
                </table>
                
                <!-- ===== TOTALI (COME ANTEPRIMA) ===== -->
                <div style="margin-top: 10px; text-align: right; font-size: 11px; line-height: 1.6;">
                    <div><strong>Subtotale:</strong> € <?php echo number_format($subtotale, 2, ',', '.'); ?></div>
                    <div><strong>Regime (<?php echo number_format($regime, 2, ',', '.'); ?>%):</strong> € <?php echo number_format($importo_regime, 2, ',', '.'); ?></div>
                    <div><strong>Totale noleggio:</strong> € <?php echo number_format($totale_noleggio, 2, ',', '.'); ?></div>
                    <?php if (abs($sconto_globale) > 0.01): // Mostra sconto solo se significativo ?>
                    <div><strong>Sconto globale:</strong> € <?php echo number_format($sconto_globale, 2, ',', '.'); ?></div>
                    <?php endif; ?>
                    <div style="font-size: 14px; background: #f9f9f9; padding: 8px; display: inline-block; border: 2px solid #000; margin-top: 5px;">
                        <strong>TOTALE FINALE:</strong> € <?php echo number_format($totale_finale, 2, ',', '.'); ?>
                    </div>
                </div>
            </div>
            
            <!-- ===== CONSENSI E RESPONSABILITÀ ===== -->
            <div class="bsn-doc-section" style="margin-bottom: 15px; border: 2px solid #000; padding: 12px; background: #fffbf0;">
                <h3 style="margin-top: 0; font-size: 12px; margin-bottom: 8px;">CONSENSI E RESPONSABILITÀ</h3>
                
                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-size: 11px; cursor: pointer;">
                        <input type="checkbox" id="bsn-consenso-privacy" style="margin-right: 8px; transform: scale(1.3);">
                        Dichiaro di aver letto e accettato l'
                        <a href="https://www.blackstarservice.it/privacy/" target="_blank" style="color: #0073aa; text-decoration: underline;">
                            <strong>Informativa Privacy</strong>
                        </a>
                    </label>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-size: 11px; cursor: pointer;">
                        <input type="checkbox" id="bsn-consenso-condizioni" style="margin-right: 8px; transform: scale(1.3);">
                        Dichiaro di aver preso visione delle
                        <a href="https://www.blackstarservice.it/modalita-noleggio/" target="_blank" style="color: #0073aa; text-decoration: underline;">
                            <strong>Modalità di Noleggio e Responsabilità</strong>
                        </a>
                    </label>
                </div>
            </div>
            
                        <!-- ===== FIRMA CLIENTE ===== -->
            <div class="bsn-doc-section" style="margin-bottom: 15px; border: 2px solid #000; padding: 12px;">
                <h3 style="margin-top: 0; font-size: 12px; margin-bottom: 8px;">FIRMA DEL CLIENTE PER ACCETTAZIONE</h3>
                
                <div style="margin-bottom: 8px;">
                    <label style="font-size: 10px; display: block; margin-bottom: 3px;">
                        <strong>Data e ora firma:</strong>
                    </label>
                    <?php
                        // Timestamp firma allineato +1 ora rispetto a current_time
                        $firma_ts = current_time( 'timestamp' ) + HOUR_IN_SECONDS;
                    ?>
                    <input type="text" id="bsn-data-firma"
                           value="<?php echo date_i18n( 'd/m/Y H:i', $firma_ts ); ?>"
                           readonly 
                           style="width: 180px; padding: 4px; background: #f9f9f9; border: 1px solid #ccc; font-size: 10px;">
                </div>
                
                <div style="margin-top: 10px;">
                    <label style="font-size: 10px; display: block; margin-bottom: 5px;">
                        <strong>Firma (utilizzare touchscreen o mouse):</strong>
                    </label>
                    <div style="border: 2px solid #000; background: #fff; display: inline-block;">
                        <canvas id="bsn-canvas-firma" width="500" height="150" style="display: block; cursor: crosshair;"></canvas>
                    </div>
                    <br>
                    <button type="button" id="bsn-cancella-firma" class="button" style="margin-top: 5px; font-size: 11px;">🗑️ Cancella Firma</button>
                </div>
            </div>
            
            <!-- ===== OPERATORI ===== -->
            <div class="bsn-doc-section" style="margin-bottom: 15px; border: 1px solid #000; padding: 12px; background: #f9f9f9;">
                <h3 style="margin-top: 0; font-size: 12px; margin-bottom: 8px;">OPERATORI RESPONSABILI</h3>
                
                <table style="width: 100%; font-size: 10px;">
                    <tr>
                        <td style="width: 38%; padding: 3px 0;"><strong>Op. Prep. Documento:</strong></td>
                        <td style="padding: 3px 0;">
                            <input type="text" id="bsn-op-documento" 
                                   style="width: 100%; padding: 4px; border: 1px solid #ccc; font-size: 10px;"
                                   placeholder="Nome operatore">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 3px 0;"><strong>Op. Prep. Materiale:</strong></td>
                        <td style="padding: 3px 0;">
                            <input type="text" id="bsn-op-materiale" 
                                   style="width: 100%; padding: 4px; border: 1px solid #ccc; font-size: 10px;"
                                   placeholder="Nome operatore">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 3px 0;"><strong>Op. Consegna Materiale:</strong></td>
                        <td style="padding: 3px 0;">
                            <input type="text" id="bsn-op-consegna" 
                                   style="width: 100%; padding: 4px; border: 1px solid #ccc; font-size: 10px;"
                                   placeholder="Nome operatore">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 3px 0;"><strong>Op. Rientro Materiale:</strong></td>
                        <td style="padding: 3px 0;">
                            <input type="text" id="bsn-op-rientro" 
                                   style="width: 100%; padding: 4px; border: 1px solid #ccc; font-size: 10px;"
                                   placeholder="Compilabile dopo">
                        </td>
                    </tr>
                </table>
            </div>
            
        </div><!-- fine documento -->
        
        <!-- ===== FOOTER AZIONI RIPETUTO ===== -->
        <div class="bsn-finalizza-footer" style="background: #fff; padding: 15px; margin-top: 20px; border: 1px solid #ccc; text-align: center;">
            <button id="bsn-salva-finalizza-2" class="button button-primary button-hero">💾 Salva e Attiva Noleggio</button>
            <button id="bsn-stampa-documento-2" class="button button-hero" style="margin-left: 10px;">🖨️ Stampa/Scarica PDF</button>
        </div>
        
    </div><!-- fine wrap -->
    
    <!-- SCRIPT INLINE PER FIRMA E VALIDAZIONE -->
    <script>
    jQuery(document).ready(function($) {
        
        // ===== INIZIALIZZA CANVAS FIRMA =====
        var canvas = document.getElementById('bsn-canvas-firma');
        var ctx = canvas.getContext('2d');
        var isDrawing = false;
        var lastX = 0;
        var lastY = 0;
        
        // Stile pennello
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        // Event listeners per disegno
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        
        // Touch events per tablet
        canvas.addEventListener('touchstart', handleTouchStart);
        canvas.addEventListener('touchmove', handleTouchMove);
        canvas.addEventListener('touchend', stopDrawing);
        
        function startDrawing(e) {
            isDrawing = true;
            var rect = canvas.getBoundingClientRect();
            lastX = e.clientX - rect.left;
            lastY = e.clientY - rect.top;
        }
        
        function draw(e) {
            if (!isDrawing) return;
            
            var rect = canvas.getBoundingClientRect();
            var currentX = e.clientX - rect.left;
            var currentY = e.clientY - rect.top;
            
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(currentX, currentY);
            ctx.stroke();
            
            lastX = currentX;
            lastY = currentY;
        }
        
        function stopDrawing() {
            isDrawing = false;
        }
        
        function handleTouchStart(e) {
            e.preventDefault();
            var touch = e.touches[0];
            var rect = canvas.getBoundingClientRect();
            lastX = touch.clientX - rect.left;
            lastY = touch.clientY - rect.top;
            isDrawing = true;
        }
        
        function handleTouchMove(e) {
            e.preventDefault();
            if (!isDrawing) return;
            
            var touch = e.touches[0];
            var rect = canvas.getBoundingClientRect();
            var currentX = touch.clientX - rect.left;
            var currentY = touch.clientY - rect.top;
            
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(currentX, currentY);
            ctx.stroke();
            
            lastX = currentX;
            lastY = currentY;
        }
        
        // ===== PULSANTE CANCELLA FIRMA =====
        $('#bsn-cancella-firma').on('click', function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        });
        
        // ===== VALIDAZIONE E SALVATAGGIO =====
        function validaESalva() {
            // Verifica consensi
            if (!$('#bsn-consenso-privacy').is(':checked')) {
                alert('❌ Devi accettare l\'Informativa Privacy per continuare.');
                return false;
            }
            
            if (!$('#bsn-consenso-condizioni').is(':checked')) {
                alert('❌ Devi accettare le Modalità di Noleggio per continuare.');
                return false;
            }
            
            // Verifica firma (canvas non vuoto)
            var canvasData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            var firmaPresente = false;
            for (var i = 0; i < canvasData.data.length; i += 4) {
                if (canvasData.data[i+3] !== 0) {
                    firmaPresente = true;
                    break;
                }
            }
            
            if (!firmaPresente) {
                alert('❌ Devi apporre la firma per continuare.');
                return false;
            }
            
            // Verifica operatori (almeno documento e materiale)
            if (!$('#bsn-op-documento').val().trim()) {
                alert('❌ Inserisci l\'Operatore Preparazione Documento.');
                $('#bsn-op-documento').focus();
                return false;
            }
            
            if (!$('#bsn-op-materiale').val().trim()) {
                alert('❌ Inserisci l\'Operatore Preparazione Materiale.');
                $('#bsn-op-materiale').focus();
                return false;
            }
            
                        // TODO: Qui andrà la chiamata AJAX per salvare
            var payload = {
                id_noleggio: '<?php echo esc_js($noleggio_id); ?>',
                consenso_privacy: 1,
                consenso_condizioni: 1,
                firma_data: $('#bsn-data-firma').val(),
                firma_base64: canvas.toDataURL('image/png'),
                op_documento: $('#bsn-op-documento').val(),
                op_materiale: $('#bsn-op-materiale').val(),
                op_consegna: $('#bsn-op-consegna').val(),
                op_rientro: $('#bsn-op-rientro').val()
            };

            $.ajax({
                url: BSN_API.root + 'noleggi/finalizza',
                method: 'POST',
                data: payload,
                beforeSend: function(xhr) {
                    if (BSN_API && BSN_API.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
                    }
                    $('#bsn-salva-finalizza, #bsn-salva-finalizza-2')
                        .prop('disabled', true)
                        .text('⏳ Salvataggio in corso...');
                },
                success: function(res) {
                alert('✅ Noleggio finalizzato correttamente.\nID: ' + res.id + '\nStato: ' + res.stato);
                window.location.href = '<?php echo esc_url( home_url( '/app-noleggi/' ) ); ?>';
                 },

                error: function(err) {
                    console.error('Errore finalizzazione', err);
                    var msg = 'Errore durante la finalizzazione del noleggio.';
                    if (err.responseJSON && err.responseJSON.message) {
                        msg += '\n\nDettagli: ' + err.responseJSON.message;
                    }
                    alert('❌ ' + msg);
                },
                complete: function() {
                    $('#bsn-salva-finalizza, #bsn-salva-finalizza-2')
                        .prop('disabled', false)
                        .text('💾 Salva e Attiva Noleggio');
                }
            });

        }
        
        // Associa funzione a entrambi i pulsanti Salva
        $('#bsn-salva-finalizza, #bsn-salva-finalizza-2').on('click', validaESalva);
        
                // ===== STAMPA/PDF (funzionalità base) =====
        $('#bsn-stampa-documento, #bsn-stampa-documento-2').on('click', function() {
            window.print();
        });

        
    });
    </script>
    
    <?php
}

function bsn_render_ispeziona_noleggio_page( $args = [] ) {
    global $wpdb;

    $args = wp_parse_args(
        $args,
        [
            'back_url' => home_url( '/app-noleggi/' ),
        ]
    );

    $noleggio_id = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';

    if ( empty( $noleggio_id ) ) {
        echo '<div class="wrap"><h1>Errore</h1><p>ID noleggio mancante.</p></div>';
        return;
    }

    $table_noleggi = $wpdb->prefix . 'bs_noleggi';
    $noleggio = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_noleggi WHERE id = %s",
            $noleggio_id
        ),
        ARRAY_A
    );

    if ( ! $noleggio ) {
        echo '<div class="wrap"><h1>Errore</h1><p>Noleggio non trovato.</p></div>';
        return;
    }

    $table_clienti = $wpdb->prefix . 'bs_clienti';
    $cliente = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_clienti WHERE id = %d",
            $noleggio['cliente_id']
        ),
        ARRAY_A
    );

    if ( ! $cliente ) {
        echo '<div class="wrap"><h1>Errore</h1><p>Cliente non trovato.</p></div>';
        return;
    }

    $articoli = json_decode( $noleggio['articoli'], true );
    if ( ! is_array( $articoli ) ) {
        $articoli = [];
    }

    $data_inizio_raw = substr( $noleggio['data_inizio'], 0, 10 );
    $data_fine_raw   = substr( $noleggio['data_fine'], 0, 10 );

    $data_inizio_formattata = $data_inizio_raw ? date( 'd/m/Y', strtotime( $data_inizio_raw ) ) : '';
    $data_fine_formattata   = $data_fine_raw ? date( 'd/m/Y', strtotime( $data_fine_raw ) ) : '';

    $table_articoli = $wpdb->prefix . 'bs_articoli';
    $ids_articoli = array_map(
        function ( $a ) {
            return (int) $a['id'];
        },
        $articoli
    );
    $ids_articoli = array_values( array_unique( $ids_articoli ) );

    $mappa_articoli = [];
    if ( ! empty( $ids_articoli ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $ids_articoli ), '%d' ) );
        $sql_art = "SELECT * FROM $table_articoli WHERE id IN ($placeholders)";
        $rows_art = $wpdb->get_results( $wpdb->prepare( $sql_art, $ids_articoli ), ARRAY_A );

        foreach ( $rows_art as $row ) {
            $mappa_articoli[ (int) $row['id'] ] = $row;
        }
    }

    ?>

    <div class="wrap bsn-ispeziona-wrap">
        <div class="bsn-ispeziona-header" style="background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccc;">
            <a href="<?php echo esc_url( $args['back_url'] ); ?>" class="button">← Torna ai noleggi</a>
            <button id="bsn-stampa-documento" class="button" style="float: right;">🖨️ Stampa/Scarica PDF</button>
            <div style="clear: both;"></div>
        </div>

        <div id="bsn-documento-ispezione" style="background: #fff; padding: 30px; max-width: 21cm; margin: 0 auto; border: 1px solid #ddd;">
            <div class="bsn-doc-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px;">
                <div style="flex: 0 0 auto;">
                    <img src="http://www.blackstarservice.it/wp-content/uploads/2021/04/logo-1500px.jpg"
                         alt="Black Star Service"
                         style="max-width: 280px; height: auto;">
                </div>
                <div style="flex: 1; text-align: right; font-size: 10px; line-height: 1.4; padding-left: 20px;">
                    <strong style="font-size: 11px;">BLACK STAR SERVICE S.R.L.</strong><br>
                    Sede Legale: Via Repubblica Argentina, 54 - 25124 Brescia<br>
                    Sede Operativa: Via Cerca, 28 - 25135 Brescia<br>
                    Partita IVA: 04130270988
                </div>
            </div>

            <div style="text-align: center; font-size: 9px; font-style: italic; margin-bottom: 10px;">
                Documento di ispezione noleggio - solo consultazione
            </div>

            <div style="border-bottom: 2px solid #000; margin-bottom: 8px;"></div>

            <div style="text-align: right; margin-bottom: 15px;">
                <strong style="font-size: 15px;">NOLEGGIO N° <?php echo esc_html( $noleggio_id ); ?></strong><br>
                <span style="font-size: 10px;">Data richiesta: <?php echo date( 'd/m/Y', strtotime( $noleggio['data_richiesta'] ) ); ?></span>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1; border: 1px solid #000; padding: 10px;">
                    <h3 style="margin: 0 0 8px 0; font-size: 12px; background: #000; color: #fff; padding: 3px 8px; margin: -10px -10px 8px -10px;">DATI CLIENTE</h3>
                    <table style="width: 100%; font-size: 10px; line-height: 1.3;">
                        <tr>
                            <td style="width: 35%; padding: 2px 0;"><strong>Nome/Rag. Soc.:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html( $cliente['nome'] ); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>CF/P.IVA:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html( $cliente['cf_piva'] ?? 'N/D' ); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Telefono:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html( $cliente['telefono'] ?? 'N/D' ); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Email:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html( $cliente['email'] ?? 'N/D' ); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Documento:</strong></td>
                            <td style="padding: 2px 0;">
                                <?php
                                $doc_tipo = ! empty( $cliente['tipo_documento'] ) ? esc_html( $cliente['tipo_documento'] ) : 'N/D';
                                $doc_numero = ! empty( $cliente['numero_documento'] ) ? ' - ' . esc_html( $cliente['numero_documento'] ) : '';
                                echo $doc_tipo . $doc_numero;
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Categoria:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html( strtoupper( $cliente['categoria_cliente'] ?? 'Standard' ) ); ?></td>
                        </tr>
                    </table>
                </div>

                <div style="flex: 1; border: 1px solid #000; padding: 10px;">
                    <h3 style="margin: 0 0 8px 0; font-size: 12px; background: #000; color: #fff; padding: 3px 8px; margin: -10px -10px 8px -10px;">DATI NOLEGGIO</h3>
                    <table style="width: 100%; font-size: 10px; line-height: 1.3;">
                        <tr>
                            <td style="width: 35%; padding: 2px 0;"><strong>Periodo:</strong></td>
                            <td style="padding: 2px 0;">dal <strong><?php echo esc_html( $data_inizio_formattata ); ?></strong> al <strong><?php echo esc_html( $data_fine_formattata ); ?></strong></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Luogo Dest.:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html( $noleggio['luogo_destinazione'] ?? 'N/D' ); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Trasporto:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html( $noleggio['trasporto_mezzo'] ?? 'N/D' ); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Cauzione:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html( $noleggio['cauzione'] ?? 'N/D' ); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;"><strong>Causale:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html( $noleggio['causale_trasporto'] ?? 'N/D' ); ?></td>
                        </tr>
                        <?php if ( ! empty( $noleggio['note'] ) ) : ?>
                        <tr>
                            <td style="padding: 2px 0; vertical-align: top;"><strong>Note:</strong></td>
                            <td style="padding: 2px 0;"><?php echo nl2br( esc_html( $noleggio['note'] ) ); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <div class="bsn-doc-section" style="margin-bottom: 15px;">
                <h3 style="font-size: 12px; background: #000; color: #fff; padding: 3px 8px; margin-bottom: 8px;">ARTICOLI NOLEGGIATI</h3>

                <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
                    <thead>
                        <tr style="background: #f0f0f0;">
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 10%;">Codice</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 25%;">Descrizione</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 25%;">Correlati</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 20%;">Ubicazione</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 10%;">Q.tà</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ( empty( $articoli ) ) {
                            echo '<tr><td colspan="5" style="border: 1px solid #ccc; padding: 6px;">Nessun articolo associato.</td></tr>';
                        } else {
                            foreach ( $articoli as $art ) {
                                $qty = isset( $art['qty'] ) ? (int) $art['qty'] : 1;
                                $articolo_id = isset( $art['id'] ) ? (int) $art['id'] : 0;

                                if ( ! isset( $mappa_articoli[ $articolo_id ] ) ) {
                                    continue;
                                }

                                $articolo_db = $mappa_articoli[ $articolo_id ];

                                $codice = $articolo_db['codice'] ?? 'N/D';
                                $nome = $articolo_db['nome'] ?? 'Articolo #' . $articolo_id;
                                $ubicazione = $articolo_db['ubicazione'] ?? '-';

                                $correlati_testo = '-';
                                if ( ! empty( $articolo_db['correlati'] ) ) {
                                    $correlati = json_decode( $articolo_db['correlati'], true );
                                    if ( is_array( $correlati ) && ! empty( $correlati ) ) {
                                        $corrLabels = array_map(
                                            function ( $c ) use ( $qty ) {
                                                $qtyCorr = ( (int) ( $c['qty'] ?? 1 ) ) * $qty;
                                                return $qtyCorr . 'x ' . ( $c['nome'] ?? '' );
                                            },
                                            $correlati
                                        );
                                        $correlati_testo = implode( ', ', $corrLabels );
                                    }
                                }
                                ?>
                                <tr>
                                    <td style="border: 1px solid #ccc; padding: 3px;"><?php echo esc_html( $codice ); ?></td>
                                    <td style="border: 1px solid #ccc; padding: 3px;"><?php echo esc_html( $nome ); ?></td>
                                    <td style="border: 1px solid #ccc; padding: 3px; font-size: 8px;"><?php echo esc_html( $correlati_testo ); ?></td>
                                    <td style="border: 1px solid #ccc; padding: 3px;"><?php echo esc_html( $ubicazione ); ?></td>
                                    <td style="border: 1px solid #ccc; padding: 3px; text-align: center;"><?php echo esc_html( $qty ); ?></td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bsn-ispeziona-footer" style="background: #fff; padding: 15px; margin-top: 20px; border: 1px solid #ccc; text-align: center;">
            <button id="bsn-stampa-documento-2" class="button button-hero">🖨️ Stampa/Scarica PDF</button>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#bsn-stampa-documento, #bsn-stampa-documento-2').on('click', function() {
            window.print();
        });
    });
    </script>
    <?php
}

// === PAGINA ADMIN: Rientro noleggio (scheletro base) ===
function bsn_render_rientro_noleggio_page() {
    if ( ! bsn_check_admin() ) {
        wp_die( 'Accesso negato.' );
    }

    global $wpdb;
    $table_noleggi  = $wpdb->prefix . 'bs_noleggi';
    $table_clienti  = $wpdb->prefix . 'bs_clienti';
    $table_articoli = $wpdb->prefix . 'bs_articoli';

    $id_noleggio = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';

    echo '<div class="wrap">';

    if ( ! $id_noleggio ) {
        echo '<h1>Rientro noleggio</h1>';
        echo '<p>ID noleggio mancante.</p>';
        echo '</div>';
        return;
    }

    // Recupera noleggio + cliente (stessa logica di bsn_api_noleggi_dettaglio)
    $sql = $wpdb->prepare(
        "SELECT n.*, c.nome AS cliente_nome
         FROM $table_noleggi n
         LEFT JOIN $table_clienti c ON n.cliente_id = c.id
         WHERE n.id = %s
         LIMIT 1",
        $id_noleggio
    );
    $n = $wpdb->get_row( $sql );

    if ( ! $n ) {
        echo '<h1>Rientro noleggio #' . esc_html( $id_noleggio ) . '</h1>';
        echo '<p>Noleggio non trovato.</p>';
        echo '</div>';
        return;
    }

    // Date grezze per visualizzazione
    $data_inizio_iso = '';
    $data_fine_iso   = '';

    if ( ! empty( $n->data_inizio ) ) {
        $ts = strtotime( $n->data_inizio );
        if ( $ts ) {
            $data_inizio_iso = date( 'Y-m-d', $ts );
        }
    }
    if ( ! empty( $n->data_fine ) ) {
        $ts = strtotime( $n->data_fine );
        if ( $ts ) {
            $data_fine_iso = date( 'Y-m-d', $ts );
        }
    }

    // Decodifica articoli JSON
    $articoli = [];
    if ( ! empty( $n->articoli ) ) {
        $decoded = json_decode( $n->articoli, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $articoli = $decoded;
        }
    }

    // Recupera dati base articoli (nome, codice, ubicazione)
    $ids_articoli = [];
    foreach ( $articoli as $a ) {
        if ( ! empty( $a['id'] ) ) {
            $ids_articoli[] = intval( $a['id'] );
        }
    }
    $ids_articoli = array_values( array_unique( array_filter( $ids_articoli ) ) );

    $mappa_articoli = [];
    if ( ! empty( $ids_articoli ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $ids_articoli ), '%d' ) );
        $sql_art = "SELECT id, codice, nome, ubicazione
                    FROM $table_articoli
                    WHERE id IN ($placeholders)";
        $rows_art = $wpdb->get_results( $wpdb->prepare( $sql_art, $ids_articoli ) );
        foreach ( $rows_art as $r ) {
            $mappa_articoli[ intval( $r->id ) ] = [
                'codice'     => $r->codice,
                'nome'       => $r->nome,
                'ubicazione' => $r->ubicazione,
            ];
        }
    }

    $current_user  = wp_get_current_user();
    $op_default    = $current_user ? $current_user->user_login : '';

    echo '<h1>Rientro noleggio #' . esc_html( $id_noleggio ) . '</h1>';

    // Blocco info noleggio
    echo '<h2>Dati noleggio</h2>';
    echo '<p><strong>Cliente:</strong> ' . esc_html( $n->cliente_nome ) . '</p>';
    echo '<p><strong>Periodo:</strong> ' . esc_html( $data_inizio_iso ) . ' → ' . esc_html( $data_fine_iso ) . '</p>';
    echo '<p><strong>Note:</strong> ' . nl2br( esc_html( $n->note ) ) . '</p>';

    // Campo operatore rientro
    echo '<h2>Rientro materiali</h2>';
    echo '<p>';
    echo '<label for="bsn-op-rientro">Operatore rientro materiali:</label> ';
    echo '<input type="text" id="bsn-op-rientro" value="' . esc_attr( $op_default ) . '" style="width: 250px;">';
    echo '</p>';

    // Tabella articoli
    echo '<table class="widefat striped" id="bsn-tabella-rientro-articoli">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Articolo</th>';
    echo '<th>Qty noleggiata</th>';
    echo '<th>Rientrato</th>';
    echo '<th>Stato rientro</th>';
    echo '<th>Note</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    if ( empty( $articoli ) ) {
        echo '<tr><td colspan="5">Nessun articolo associato a questo noleggio.</td></tr>';
    } else {
        foreach ( $articoli as $riga ) {
            $art_id = isset( $riga['id'] ) ? intval( $riga['id'] ) : 0;
            $qty    = isset( $riga['qty'] ) ? intval( $riga['qty'] ) : 0;

            $nome   = '';
            $codice = '';
            if ( isset( $mappa_articoli[ $art_id ] ) ) {
                $nome   = $mappa_articoli[ $art_id ]['nome'];
                $codice = $mappa_articoli[ $art_id ]['codice'];
            }

            echo '<tr data-articolo-id="' . esc_attr( $art_id ) . '">';
            echo '<td>';
            echo esc_html( $nome );
            if ( $codice ) {
                echo '<br><small>Codice: ' . esc_html( $codice ) . '</small>';
            }
            echo '</td>';
            echo '<td>' . esc_html( $qty ) . '</td>';

            // Checkbox rientrato
            echo '<td style="text-align:center;">';
            echo '<input type="checkbox" class="bsn-rientrato-checkbox" checked>';
            echo '</td>';

            // Select stato_rientro
            echo '<td>';
            echo '<select class="bsn-stato-rientro">';
            echo '<option value="ok">OK</option>';
            echo '<option value="danneggiato">Danneggiato</option>';
            echo '<option value="mancante">Mancante</option>';
            echo '</select>';
            echo '</td>';

            // Note
            echo '<td>';
            echo '<input type="text" class="bsn-note-rientro" style="width: 100%;">';
            echo '</td>';

            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';

        // Pulsante salvataggio
    echo '<p style="margin-top:20px;">';
    echo '<button class="button button-primary" id="bsn-salva-rientro">Salva Rientro e Chiudi Noleggio</button>';
    echo '</p>';

    // JS inline per salvataggio rientro
    ?>
    <script>
    (function(){
        const btn = document.getElementById('bsn-salva-rientro');
        if (!btn) return;

        btn.addEventListener('click', function(e){
            e.preventDefault();

            const idNoleggio = '<?php echo esc_js( $id_noleggio ); ?>';
            const opInput = document.getElementById('bsn-op-rientro');
            const opRientro = opInput ? opInput.value.trim() : '';

            if (!opRientro) {
                alert('Inserisci l\'operatore rientro materiali.');
                return;
            }

            const rows = document.querySelectorAll('#bsn-tabella-rientro-articoli tbody tr');
            const articoli = [];
            let rientratiCount = 0;

            rows.forEach(function(row){
                const articoloId = row.getAttribute('data-articolo-id');
                const qtyCell = row.querySelector('td:nth-child(2)');
                const qty = qtyCell ? parseInt(qtyCell.textContent.trim(), 10) || 0 : 0;
                const chkRientrato = row.querySelector('.bsn-rientrato-checkbox');
                const selStato = row.querySelector('.bsn-stato-rientro');
                const inpNote = row.querySelector('.bsn-note-rientro');

                const rientrato = chkRientrato && chkRientrato.checked ? 1 : 0;
                const statoRientro = selStato ? selStato.value : 'ok';
                const note = inpNote ? inpNote.value.trim() : '';

                if (rientrato) {
                    rientratiCount++;
                }

                articoli.push({
                    articolo_id: articoloId,
                    qty: qty,
                    rientrato: rientrato,
                    stato_rientro: statoRientro,
                    note: note
                });
            });

            if (rientratiCount === 0) {
                alert('Seleziona almeno un articolo come rientrato.');
                return;
            }

            if (typeof BSN_API === 'undefined' || !BSN_API.root || !BSN_API.nonce) {
                alert('Configurazione API non disponibile.');
                return;
            }

            const payload = {
                id_noleggio: idNoleggio,
                op_rientro: opRientro,
                articoli: articoli
            };

            fetch(BSN_API.root + 'noleggi/rientro', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': BSN_API.nonce
                },
                body: JSON.stringify(payload)
            })
            .then(function(res){
                if (!res.ok) {
                    return res.json().then(function(err){
                        throw err;
                    }).catch(function(){
                        throw { message: 'Errore sconosciuto (' + res.status + ')' };
                    });
                }
                return res.json();
            })
            .then(function(data){
                alert('Rientro salvato e noleggio chiuso.');
                window.location.href = '<?php echo esc_js( home_url( '/app-noleggi/' ) ); ?>';
            })
            .catch(function(err){
                if (err && err.message) {
                    alert(err.message);
                } else {
                    alert('Errore durante il salvataggio del rientro.');
                }
            });
        });
    })();
    </script>
    <?php

    echo '</div>';
}

function bsn_admin_page() {
    echo '<h1>Black Star Noleggi Admin</h1>';
    echo '<p>Usa lo shortcode <code>[blackstar_noleggi]</code> nelle pagine.</p>';
}

/**
 * Shortcode [blackstar_noleggi]
 */
add_shortcode('blackstar_noleggi', 'bsn_shortcode');
function bsn_shortcode() {

    // === FORCE NO CACHE ===
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    }
    
    // === STEP A3: anche il frontend dell'app usa bsn_check_admin ===
    if ( ! bsn_check_admin() ) {
        return "<p>Accesso negato. Permesso riservato agli operatori autorizzati.</p>";
    }

    // === STEP A3: anche il frontend dell'app usa bsn_check_admin ===
    if ( ! bsn_check_admin() ) {
        return "\n\nAccesso negato. Permesso riservato agli operatori autorizzati.\n\n";
    }


    ob_start();
    ?>
    <div id="bsn-app" style="max-width:1200px; margin:20px auto;">
        <h2>Black Star Noleggi Management</h2>

        <div id="bsn-tabs">
            <button class="tab-btn active" data-tab="clienti">Clienti</button>
            <button class="tab-btn" data-tab="articoli">Articoli</button>
            <button class="tab-btn" data-tab="noleggi">Noleggi</button>
        </div>

        <div id="bsn-content">

    <!-- CLIENTI -->
        <div id="clienti" class="tab-content active">
        <h3>Modulo Clienti</h3>
        <form id="bsn-form-cliente">
            <input type="hidden" name="cliente_id" id="bsn-cliente-id" value="">
            
            <!-- RIGA 1: Nome + CF/P.IVA + Categoria -->
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 2;">
                    <label>Nome e cognome / Ragione sociale *</label>
                    <input type="text" name="nome" required style="width:100%;">
                </div>
                <div style="flex: 1;">
                    <label>CF / P.IVA *</label>
                    <input type="text" name="cf_piva" required style="width:100%;">
                </div>
                <div style="flex: 0 0 180px;">
                    <label>Categoria cliente</label>
                    <select name="categoria_cliente" style="width:100%;">
                        <option value="standard">Standard</option>
                        <option value="fidato">Fidato</option>
                        <option value="premium">Premium</option>
                        <option value="service">Service</option>
                        <option value="collaboratori">Collaboratori</option>
                    </select>
                </div>
            </div>

            <!-- RIGA 2: Telefono + Email -->
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label>Telefono *</label>
                    <input type="text" name="telefono" required style="width:100%;">
                </div>
                <div style="flex: 1;">
                    <label>Email *</label>
                    <input type="email" name="email" required style="width:100%;">
                </div>
            </div>

            <!-- RIGA 3: Indirizzo + CAP + Città -->
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 2;">
                    <label>Indirizzo</label>
                    <input type="text" name="indirizzo" placeholder="Via e numero civico" style="width:100%;">
                </div>
                <div style="flex: 0 0 100px;">
                    <label>CAP</label>
                    <input type="text" name="cap" style="width:100%;">
                </div>
                <div style="flex: 1;">
                    <label>Città</label>
                    <input type="text" name="citta" style="width:100%;">
                </div>
            </div>

            <!-- RIGA 4: Regime fiscale % + Note -->
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 0 0 150px;">
                    <label>Regime fiscale (%)</label>
                    <input type="number" step="0.01" name="regime_percentuale" value="22.00" style="width:100%;">
                </div>
                <div style="flex: 1;">
                    <label>Regime fiscale - note</label>
                    <input type="text" name="regime_note" placeholder="es. esente art. 10, reverse charge" style="width:100%;">
                </div>
            </div>

            <!-- RIGA 5: Tipo documento + Numero documento -->
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 0 0 200px;">
                    <label>Tipo documento</label>
                    <select name="tipo_documento" style="width:100%;">
                        <option value="">Seleziona...</option>
                        <option value="carta_identita">Carta d'Identità</option>
                        <option value="patente">Patente di guida</option>
                        <option value="passaporto">Passaporto</option>
                        <option value="permesso_soggiorno">Permesso di soggiorno</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label>Numero documento</label>
                    <input type="text" name="numero_documento" placeholder="Es. CA12345678" style="width:100%;">
                </div>
            </div>

            <!-- RIGA 6: Documento fronte + retro -->
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label>Documento identità (fronte)</label>
                    <input type="file" id="bsn-doc-fronte-file" accept="image/*" style="width:100%;">
                    <input type="hidden" name="doc_fronte" id="bsn-doc-fronte-path">
                    <div id="bsn-doc-fronte-preview" style="margin-top:5px;"></div>
                    <button type="button" id="bsn-doc-fronte-webcam-start" class="btn btn-secondary" style="margin-top:5px;">📷 Apri webcam (fronte)</button>
                    <button type="button" id="bsn-doc-fronte-webcam-capture" class="btn btn-primary" style="display:none; margin-top:5px;">Scatta fronte</button>
                    <div id="bsn-doc-fronte-webcam-area" style="display:none; margin-top:10px;">
                        <video id="bsn-doc-fronte-video" width="320" height="240" autoplay style="border:1px solid #ccc;"></video>
                        <canvas id="bsn-doc-fronte-canvas" style="display:none;"></canvas>
                    </div>
                </div>
                <div style="flex: 1;">
                    <label>Documento identità (retro)</label>
                    <input type="file" id="bsn-doc-retro-file" accept="image/*" style="width:100%;">
                    <input type="hidden" name="doc_retro" id="bsn-doc-retro-path">
                    <div id="bsn-doc-retro-preview" style="margin-top:5px;"></div>
                    <button type="button" id="bsn-doc-retro-webcam-start" class="btn btn-secondary" style="margin-top:5px;">📷 Apri webcam (retro)</button>
                    <button type="button" id="bsn-doc-retro-webcam-capture" class="btn btn-primary" style="display:none; margin-top:5px;">Scatta retro</button>
                    <div id="bsn-doc-retro-webcam-area" style="display:none; margin-top:10px;">
                        <video id="bsn-doc-retro-video" width="320" height="240" autoplay style="border:1px solid #ccc;"></video>
                        <canvas id="bsn-doc-retro-canvas" style="display:none;"></canvas>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Note cliente</label>
                <textarea name="note" rows="3" style="width:100%;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Salva Cliente</button>
        </form>

                <!-- Ricerca + lista clienti -->

                <div style="margin-top:20px;">
                    <input type="text" id="bsn-clienti-search" placeholder="Cerca nei clienti..." style="width:100%; max-width:300px; padding:5px;">
                </div>

                <div id="bsn-lista-clienti" style="margin-top:10px;"></div>
            </div>

           <!-- ARTICOLI -->
            <div id="articoli" class="tab-content">
                <h3>Modulo Articoli (base)</h3>
                <form id="bsn-form-articolo">

                    <!-- RIGA 1: Nome articolo + Codice -->
                    <div style="display:flex; gap:15px; margin-bottom:15px; flex-wrap:wrap;">
                        <div style="flex:2 1 250px;">
                            <label>Nome articolo</label>
                            <input type="text" name="nome" required style="width:100%;">
                        </div>
                        <div style="flex:1 1 180px;">
                            <label>Codice (es. CDJ2000#1)</label>
                            <input type="text" name="codice" required style="width:100%;">
                        </div>
                    </div>

                    <!-- RIGA 2: Prezzo giorno + Valore del bene + Quantità -->
                    <div style="display:flex; gap:15px; margin-bottom:15px; flex-wrap:wrap;">
                        <div style="flex:1 1 180px;">
                            <label>Prezzo per giorno (€)</label>
                            <input type="number" step="0.01" name="prezzo_giorno" required style="width:100%;">
                        </div>
                        <div style="flex:1 1 180px;">
                            <label>Valore del bene (€)</label>
                            <input type="number" step="0.01" name="valore_bene" value="0.00" style="width:100%;">
                        </div>
                        <div style="flex:1 1 140px;">
                            <label>Quantità disponibile</label>
                            <input type="number" name="qty_disponibile" value="1" min="0" style="width:100%;">
                        </div>
                    </div>

                    <!-- RIGA 3: Tutti gli sconti sulla stessa riga -->
                    <div style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
                            <div style="flex:1 1 150px;">
                            <label style="font-size:0.9em;">Sconto STANDARD (%)</label>
                            <input type="number" step="0.01" name="sconto_standard" value="0" style="width:100%;">
                        </div>
                        <div style="flex:1 1 150px;">
                            <label style="font-size:0.9em;">Sconto FIDATO (%)</label>
                            <input type="number" step="0.01" name="sconto_fidato" value="0" style="width:100%;">
                        </div>
                        <div style="flex:1 1 150px;">
                            <label style="font-size:0.9em;">Sconto PREMIUM (%)</label>
                            <input type="number" step="0.01" name="sconto_premium" value="0" style="width:100%;">
                        </div>
                        <div style="flex:1 1 150px;">
                            <label style="font-size:0.9em;">Sconto SERVICE (%)</label>
                            <input type="number" step="0.01" name="sconto_service" value="0" style="width:100%;">
                        </div>
                        <div style="flex:1 1 150px;">
                            <label style="font-size:0.9em;">Sconto COLLABORATORI (%)</label>
                            <input type="number" step="0.01" name="sconto_collaboratori" value="0" style="width:100%;">
                        </div>
                    </div>

                    <!-- Flag scalare + Ubicazione + Note + Correlati come prima -->
                    <label style="display:block; margin:10px 0;">
                    <input type="checkbox" name="noleggio_scalare" value="1" style="margin-right:5px;">
                    Noleggio scalare (usa √giorni invece del moltiplicatore lineare)
                    </label>

                    <div class="form-group">
                        <label>Ubicazione magazzino (scaffale/colonna/ripiano)</label>
                        <input type="text" name="ubicazione">
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="note"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Correlati (es. cavi, accessori, hardware obbligatorio)</label>
                        <div id="bsn-correlati-wrapper">
                            <div class="bsn-correlato-row">
                                <input type="text" name="correlato_nome[]" placeholder="Nome correlato (es. Cavo alimentazione)">
                                <input type="number" name="correlato_qty[]" placeholder="Q.tà" min="1" value="1" style="max-width:80px;">
                                <button type="button" class="btn bsn-correlato-remove">X</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" id="bsn-correlato-add">+ Aggiungi correlato</button>
                        <p style="font-size:0.85em; color:#666; margin-top:5px;">
                            Esempi: CDJ → 1 cavo alimentazione, 1 RCA, 1 ethernet. Truss → 4 ovetti, 8 spine, 8 coppiglie.
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary">Salva Articolo</button>
                </form>
                <div id="bsn-lista-articoli" style="margin-top:20px;"></div>
            </div>

            <!-- MODAL QR CODE -->
<div id="bsn-modal-qr" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; display:none; justify-content:center; align-items:center;">
    <div style="background:white; padding:20px; border-radius:10px; text-align:center;">
        <h3 style="margin-top:0; margin-bottom:15px;">Codice QR Articolo</h3>

        <!-- BOX FISSO 180x180 -->
        <div id="bsn-qr-box">
            <div id="bsn-qr-container"></div>
        </div>

        <div id="bsn-qr-code-text" style="margin-top:10px; font-size:14px; font-weight:bold; color:#333;"></div>

        <div style="margin-top:15px;">
            <button type="button" id="bsn-qr-print" class="btn btn-primary" style="margin-right:10px;">🖨️ Stampa QR</button>
            <button type="button" id="bsn-qr-close" class="btn btn-secondary">Chiudi</button>
        </div>
    </div>
</div>


            <!-- NOLEGGI -->
            <div id="noleggi" class="tab-content">
                <h2>Noleggi</h2>

                            <!-- Form nuovo noleggio -->
            <form id="bsn-form-noleggio" style="margin-bottom:20px;">
                <!-- ID noleggio per modalità "Modifica" -->
                <input type="hidden" id="bsn-noleggio-id" name="noleggio_id" value="">
                
                <div class="form-group">
                    <label>Cliente</label>
                    <input type="text" id="bsn-noleggio-cliente-search" placeholder="Digita il nome del cliente..." autocomplete="off">
                    <input type="hidden" name="cliente_id" id="bsn-noleggio-cliente-id" required>
                    <div id="bsn-noleggio-clienti-risultati" class="bsn-risultati-clienti" style="border:1px solid #ccc; max-height:150px; overflow-y:auto; display:none; background:#fff; position:relative; z-index:10;"></div>
                </div>

                <!-- Date sulla stessa riga -->
                <div class="form-group" style="display:flex; flex-wrap:wrap; gap:10px;">
                    <div style="flex:1 1 150px; min-width:140px;">
                        <label>Data inizio</label>
                        <input type="date" name="data_inizio" required style="width:100%;">
                    </div>
                    <div style="flex:1 1 150px; min-width:140px;">
                        <label>Data fine</label>
                        <input type="date" name="data_fine" required style="width:100%;">
                    </div>
                </div>

                <div class="form-group">
                    <!-- STATO SEMPRE BOZZA, nascosto -->
                    <input type="hidden" name="stato" value="bozza">
                </div>

                <!-- Campi logistica/trasporto - RIGA 1 -->
                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label>Luogo di destinazione</label>
                        <input type="text" name="luogo_destinazione" placeholder="Es. Teatro Grande, Brescia" style="width:100%;">
                    </div>
                    <div style="flex: 1;">
                        <label>Trasporto a mezzo</label>
                        <input type="text" name="trasporto_mezzo" placeholder="Es. Mezzo proprio cliente, furgone Black Star, corriere..." style="width:100%;">
                    </div>
                </div>

                <!-- Campi logistica/trasporto - RIGA 2 -->
                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label>Cauzione</label>
                        <input type="text" name="cauzione" placeholder="Es. € 500 contanti, documento in originale, carta di credito..." style="width:100%;">
                    </div>
                    <div style="flex: 1;">
                        <label>Causale di trasporto</label>
                        <input type="text" name="causale_trasporto" placeholder="Es. Noleggio materiale audio per evento, prova tecnica, ecc." style="width:100%;">
                    </div>
                </div>

                <div class="form-group">
                    <label>Note</label>
                    <textarea name="note"></textarea>
                </div>

                <div class="form-group">
                    <label>Articoli nel noleggio</label>
                    <div id="bsn-noleggio-articoli-wrapper">
                        <!-- Prima riga di esempio, clonata via JS -->
                           <div class="bsn-noleggio-articolo-row">
                                <input type="text" class="bsn-noleggio-articolo-search" placeholder="Cerca articolo..." autocomplete="off" style="flex:1 1 200px; min-width:150px;">
                                <input type="hidden" class="bsn-noleggio-articolo-id" name="articoli_id[]">
                                <input type="number" class="bsn-noleggio-articolo-prezzo" name="articoli_prezzo[]" min="0" step="0.01" placeholder="Prezzo" style="max-width:90px;">
                                <input type="number" class="bsn-noleggio-articolo-qty" name="articoli_qty[]" min="1" value="1" style="max-width:60px;">
                                <button type="button" class="btn bsn-noleggio-articolo-remove">X</button>

                            <div class="bsn-noleggio-articoli-risultati" style="border:1px solid #ccc; max-height:150px; overflow-y:auto; display:none; background:#fff; position:relative; z-index:10;"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" id="bsn-noleggio-articolo-add">+ Aggiungi articolo</button>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="bsn-noleggio-consenti-overbook" name="consenti_overbook" value="1">
                        Consenti comunque il superamento della disponibilità (overbooking)
                    </label>
                </div>

                <!-- Anteprima Noleggio -->
                <div id="bsn-noleggio-preview" style="display:none; margin-top:25px; padding:20px; border:2px solid #0073aa; background:#f0f8ff; border-radius:8px;">
                    <h4 style="margin-top:0; color:#0073aa;">Anteprima Noleggio</h4>
                    
                    <p><strong>Cliente:</strong> <span id="bsn-preview-cliente-nome">-</span></p>
                    <p><strong>CF/P.IVA:</strong> <span id="bsn-preview-cliente-cf">-</span></p>
                    <p><strong>Categoria:</strong> <span id="bsn-preview-cliente-categoria">-</span></p>
                    <p><strong>Periodo:</strong> <span id="bsn-preview-data-da">-</span> / <span id="bsn-preview-data-a">-</span> (<span id="bsn-preview-giorni">-</span> giorni)</p>
                    
                    <p id="bsn-preview-note-cliente" style="display:none;">
                        <strong>Note cliente:</strong> <span id="bsn-preview-cliente-note">-</span>
                    </p>
                    
                    <div id="bsn-preview-logistica" style="display:none; margin:15px 0; padding:10px; background:#fff; border-left:3px solid #0073aa;">
                        <p><strong>Luogo destinazione:</strong> <span id="bsn-preview-luogo-dest">-</span></p>
                        <p><strong>Trasporto a mezzo:</strong> <span id="bsn-preview-trasporto">-</span></p>
                        <p><strong>Cauzione:</strong> <span id="bsn-preview-cauzione">-</span></p>
                        <p><strong>Causale trasporto:</strong> <span id="bsn-preview-causale">-</span></p>
                    </div>
                    
                    <table style="width:100%; border-collapse:collapse; margin:15px 0;">
                        <thead>
                            <tr style="background:#0073aa; color:#fff;">
                                <th style="padding:8px; text-align:left;">Articolo</th>
                                <th style="padding:8px; text-align:right;">Prezzo</th>
                                <th style="padding:8px; text-align:center;">Qty</th>
                                <th style="padding:8px; text-align:center;">Fattore</th>
                                <th style="padding:8px; text-align:center;">Sconto</th>
                                <th style="padding:8px; text-align:right;">Subtotale</th>
                                <th style="padding:8px; text-align:left;">Ubicazione</th>
                                <th style="padding:8px; text-align:left;">Correlati</th>
                            </tr>
                        </thead>
                        <tbody id="bsn-preview-articoli-tbody">
                            <!-- Popolato da JS -->
                        </tbody>
                    </table>
                    
                    <div id="bsn-preview-dettagli-articoli"></div>
                    
                    <p><strong>Note:</strong></p>
                    <p><strong>Subtotale:</strong> <span id="bsn-preview-subtotale">€ 0,00</span></p>
                    <p><strong>Regime (<span id="bsn-preview-regime-perc">0</span>%):</strong> <span id="bsn-preview-regime-importo">€ 0,00</span></p>
                    <p><strong>Totale noleggio:</strong> <span id="bsn-preview-totale">€ 0,00</span></p>
                    
                    <p id="bsn-preview-sconto-block" style="display:none;">
                        <strong>Sconto:</strong> <span id="bsn-preview-sconto">€ 0,00</span>
                    </p>
                    
                    <p style="font-size:1.3em; font-weight:bold; color:#0073aa;">
                        <strong>TOTALE FINALE:</strong> <span id="bsn-preview-totale-finale">€ 0,00</span>
                    </p>
                </div><!-- fine preview -->

                    <div class="form-group">
                        <label>Sconto globale (in €, es. -50 o +20)</label>
                        <input type="number" step="0.01" name="sconto_globale" id="bsn-noleggio-sconto-globale" placeholder="0.00" style="max-width:120px;">
                        <small style="display:block; color:#666; margin-top:3px;">Valori negativi riducono il totale, positivi lo aumentano.</small>
                    </div>

                <button type="submit" class="btn btn-primary">Salva Noleggio</button>
            </form>

                <!-- Warning sovrapposizioni -->
                <div id="bsn-noleggi-warning" style="display:none; margin-bottom:15px; padding:10px; border:1px solid #f5c6cb; background:#f8d7da; color:#721c24;">
                    <strong>Attenzione sovrapposizioni:</strong>
                    <ul id="bsn-noleggi-warning-list" style="margin:5px 0 0 20px; padding:0;"></ul>
                </div>

                <!-- Filtri noleggi -->
                <div id="bsn-noleggi-filtri" style="margin-bottom:10px;">
                    <label>
                        Stato:
                        <select id="bsn-filtro-stato">
                            <option value="">Tutti</option>
                            <option value="bozza">Bozze</option>
                            <option value="attivo">Attivi</option>
                            <option value="chiuso">Chiusi</option>
                            <option value="ritardo">In ritardo</option>
                        </select>
                    </label>
                </div>

                <!-- Lista noleggi -->
                <div id="bsn-lista-noleggi">
                    <p>Nessun noleggio caricato.</p>
                </div>
            </div>

        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'blackstar_finalizza', 'bsn_shortcode_finalizza' );
function bsn_shortcode_finalizza() {
    if ( ! bsn_check_admin() ) {
        return '<p>Accesso negato. Permesso riservato agli operatori autorizzati.</p>';
    }

    ob_start();
    bsn_render_finalizza_noleggio_page(
        [
            'back_url' => home_url( '/app-noleggi/' ),
        ]
    );
    return ob_get_clean();
}

add_shortcode( 'blackstar_ispeziona', 'bsn_shortcode_ispeziona' );
function bsn_shortcode_ispeziona() {
    if ( ! bsn_check_admin() ) {
        return '<p>Accesso negato. Permesso riservato agli operatori autorizzati.</p>';
    }

    ob_start();
    bsn_render_ispeziona_noleggio_page(
        [
            'back_url' => home_url( '/app-noleggi/' ),
        ]
    );
    return ob_get_clean();
}

add_shortcode( 'blackstar_rientro', 'bsn_shortcode_rientro' );
function bsn_shortcode_rientro() {
    if ( ! bsn_check_admin() ) {
        return '<p>Accesso negato. Permesso riservato agli operatori autorizzati.</p>';
    }

    ob_start();
    bsn_render_rientro_noleggio_page();
    return ob_get_clean();
}

/**
 * Enqueue CSS/JS solo dove serve
 */
function bsn_enqueue_assets() {
    if (!is_singular()) return;

    $post = get_post();
    if (!$post) return;

    $has_app = has_shortcode( $post->post_content, 'blackstar_noleggi' );
    $has_finalizza = has_shortcode( $post->post_content, 'blackstar_finalizza' );
    $has_ispeziona = has_shortcode( $post->post_content, 'blackstar_ispeziona' );
    $has_rientro = has_shortcode( $post->post_content, 'blackstar_rientro' );

    if ( $has_app ) {
        $version_dinamica = BSN_VERSION . '.' . time(); // Aggiunge timestamp
        wp_enqueue_style( 'bsn-style', BSN_URL . 'assets/css/style.css', [], $version_dinamica );
        wp_enqueue_script( 'bsn-script', BSN_URL . 'assets/js/script.js', ['jquery'], $version_dinamica, true );
               
        // Includi libreria qrcode.js da CDN
        wp_enqueue_script(
            'qrcode-js',
            'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
            [],
            '1.0.0',
            true
        );
        wp_localize_script('bsn-script', 'BSN_API', [
            'root'  => esc_url_raw(rest_url('bsn/v1/')),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }

    if ( $has_finalizza || $has_ispeziona || $has_rientro ) {
        wp_enqueue_style( 'bsn-style-frontend-doc', BSN_URL . 'assets/css/style.css', [], BSN_VERSION );
        wp_enqueue_script( 'jquery' );
        wp_register_script(
            'bsn-frontend-doc',
            '',
            [ 'jquery' ],
            BSN_VERSION,
            true
        );
        wp_enqueue_script( 'bsn-frontend-doc' );
        wp_localize_script(
            'bsn-frontend-doc',
            'BSN_API',
            [
                'root'  => esc_url_raw( rest_url( 'bsn/v1/' ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ]
        );
    }
}
add_action('wp_enqueue_scripts', 'bsn_enqueue_assets');

/**
 * Enqueue JS in admin per pagina Finalizza Noleggio
 */
function bsn_enqueue_admin_finalizza( $hook ) {
    // Carica solo sulla pagina admin.php?page=bsn-finalizza-noleggio
    if ( $hook !== 'toplevel_page_blackstar-noleggi'
         && $hook !== 'noleggi_page_bsn-finalizza-noleggio'
         && $hook !== 'admin_page_bsn-finalizza-noleggio'
    ) {
        return;
    }

    // CSS dell'app anche in admin, così vale @media print
    wp_enqueue_style(
        'bsn-style-admin',
        BSN_URL . 'assets/css/style.css',
        [],
        BSN_VERSION
    );

    // jQuery è già in admin, ma per sicurezza:
    wp_enqueue_script( 'jquery' );

    // Script vuoto solo per avere BSN_API
    wp_register_script(
        'bsn-admin-finalizza',
        '',
        [ 'jquery' ],
        BSN_VERSION,
        true
    );

    wp_enqueue_script( 'bsn-admin-finalizza' );

    wp_localize_script(
        'bsn-admin-finalizza',
        'BSN_API',
        [
            'root'  => esc_url_raw( rest_url( 'bsn/v1/' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ]
    );
}
add_action( 'admin_enqueue_scripts', 'bsn_enqueue_admin_finalizza' );

/**
 * Endpoint cambio stato noleggio
 */
add_action('rest_api_init', function () {
    register_rest_route('bsn/v1', '/noleggi/stato', array(
        'methods'             => 'POST',
        'callback'            => 'bsn_cambia_stato_noleggio',
                'permission_callback' => function () {
            return bsn_check_admin();
        },

        'args'                => array(
            'id_noleggio' => array(
                'required' => true,
                'type'     => 'string',
            ),
            'nuovo_stato' => array(
                'required' => true,
                'type'     => 'string',
            ),
        ),
    ));
});

// === Endpoint Rientro noleggio (attivo/ritardo -> chiuso) ===
add_action('rest_api_init', function () {
    register_rest_route(
        'bsn/v1',
        '/noleggi/rientro',
        array(
            'methods'             => 'POST',
            'callback'            => 'bsn_api_noleggi_rientro',
            'permission_callback' => 'bsn_check_admin',
        )
    );
});

/**
 * API REST base (Clienti + Articoli + Noleggi)
 */
add_action('rest_api_init', 'bsn_register_routes');
function bsn_register_routes() {
    // CLIENTI
    register_rest_route('bsn/v1', '/clienti', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_clienti_get',
        'permission_callback' => 'bsn_check_admin',
    ]);

        register_rest_route('bsn/v1', '/clienti/delete', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_clienti_delete',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id' => [
                'required' => true,
                'type'     => 'integer',
            ],
        ],
    ]);

    register_rest_route('bsn/v1', '/clienti', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_clienti_post',
        'permission_callback' => 'bsn_check_admin',
    ]);

        // CLIENTI UPDATE
    register_rest_route('bsn/v1', '/clienti/update', [
        'methods'             => 'POST', // possiamo usare anche 'PUT', ma JS ora usa POST
        'callback'            => 'bsn_api_clienti_update',
        'permission_callback' => 'bsn_check_admin',
    ]);

        register_rest_route('bsn/v1', '/articoli', [
        'methods'             => 'PUT',
        'callback'            => 'bsn_api_articoli_put',
        'permission_callback' => 'bsn_check_admin',
    ]);

    register_rest_route('bsn/v1', '/articoli/delete', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_articoli_delete',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id' => [
                'required' => true,
                'type'     => 'integer',
            ],
        ],
    ]);

    register_rest_route('bsn/v1', '/articoli/clone', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_articoli_clone',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id' => [
                'required' => true,
                'type'     => 'integer',
            ],
        ],
    ]);

    // ARTICOLI
    register_rest_route('bsn/v1', '/articoli', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_articoli_get',
        'permission_callback' => 'bsn_check_admin',
    ]);

    register_rest_route('bsn/v1', '/articoli', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_articoli_post',
        'permission_callback' => 'bsn_check_admin',
    ]);

        // NOLEGGI (GET + POST)
    register_rest_route('bsn/v1', '/noleggi', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_noleggi_get',
        'permission_callback' => 'bsn_check_admin',
    ]);

    register_rest_route('bsn/v1', '/noleggi', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_noleggi_post',
        'permission_callback' => 'bsn_check_admin',
    ]);

    // NOLEGGI – DUPLICA
        register_rest_route('bsn/v1', '/noleggi/delete', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_noleggi_delete',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id' => [
                'required' => true,
                'type'     => 'string',
            ],
        ],
    ]);
}

// === STEP A1: Controllo permessi unico per tutta l'app ===
function bsn_check_admin( $request = null ) {
    // Admin classico
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }

    // Editor (se lo usi per l'app)
    if ( current_user_can( 'edit_others_posts' ) ) {
        return true;
    }

    // Ruolo/capability dedicata all'app noleggi
    if ( current_user_can( 'bsn_manage_noleggi' ) ) {
        return true;
    }

    return false;
}

/**
 * Endpoint upload documento cliente
 * POST /wp-json/bsn/v1/upload-doc
 * Accetta un file (campo "file") e restituisce l'URL.
 */
add_action('rest_api_init', function () {
    register_rest_route('bsn/v1', '/upload-doc', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_upload_doc',
        'permission_callback' => 'bsn_check_admin',
    ]);
});

function bsn_api_upload_doc( WP_REST_Request $request ) {
    if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
        return new WP_Error(
            'bsn_no_file',
            'Nessun file ricevuto',
            array( 'status' => 400 )
        );
    }

    $file = $_FILES['file'];

    // Limita a immagini
    $allowed_types = [ 'image/jpeg', 'image/jpg', 'image/png' ];
    if ( ! in_array( $file['type'], $allowed_types, true ) ) {
        return new WP_Error( 'bsn_file_type', 'Tipo di file non valido. Usa JPG o PNG.', [ 'status' => 400 ] );
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    // Carica nel media library, senza associare a post specifico
    $overrides = [ 'test_form' => false ];

    $file_info = wp_handle_upload( $file, $overrides );

    if ( isset( $file_info['error'] ) ) {
        return new WP_Error( 'bsn_upload_error', $file_info['error'], [ 'status' => 500 ] );
    }

    // Crea attachment
    $file_type = wp_check_filetype( $file_info['file'], null );
    $attachment = [
        'post_mime_type' => $file_type['type'],
        'post_title'     => sanitize_file_name( basename( $file_info['file'] ) ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

       $attach_id = wp_insert_attachment( $attachment, $file_info['file'] );

    if ( is_wp_error( $attach_id ) || ! $attach_id ) {
        return new WP_Error(
            'bsn_attach_error',
            'Errore nel creare l\'attachment',
            array( 'status' => 500 )
        );
    }

    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_info['file'] );
    wp_update_attachment_metadata( $attach_id, $attach_data );

    $url = wp_get_attachment_url( $attach_id );

    return rest_ensure_response( array(
        'success' => true,
        'id'      => $attach_id,
        'url'     => $url,
    ) );
}

/* ==== CLIENTI ==== */

function bsn_api_clienti_get($request) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_clienti';
    $clienti = $wpdb->get_results("SELECT * FROM $table ORDER BY data_creazione DESC");
    return rest_ensure_response(['clienti' => $clienti]);
}

function bsn_api_clienti_post($request) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_clienti';

    $nome       = sanitize_text_field($request->get_param('nome'));
    $cf_piva    = sanitize_text_field($request->get_param('cf_piva'));
    $indirizzo  = sanitize_textarea_field($request->get_param('indirizzo'));
    $telefono   = sanitize_text_field($request->get_param('telefono'));
    $email      = sanitize_email($request->get_param('email'));

    // NUOVO: campi aggiuntivi
    $cap        = sanitize_text_field($request->get_param('cap'));
    $citta      = sanitize_text_field($request->get_param('citta'));

    $categoria_cliente  = sanitize_text_field($request->get_param('categoria_cliente'));
    $regime_percentuale = floatval($request->get_param('regime_percentuale')) ?: 22.00;
    $regime_note        = sanitize_text_field($request->get_param('regime_note'));

    // NUOVO: documenti
    $doc_fronte       = esc_url_raw($request->get_param('doc_fronte'));
    $doc_retro        = esc_url_raw($request->get_param('doc_retro'));
    $tipo_documento   = sanitize_text_field($request->get_param('tipo_documento'));
    $numero_documento = sanitize_text_field($request->get_param('numero_documento'));

    // Note
    $note = sanitize_textarea_field($request->get_param('note'));

    if (empty($nome)) {
        return new WP_Error('bsn_no_nome', 'Il nome è obbligatorio', ['status' => 400]);
    }

    $result = $wpdb->insert($table, [
        'nome'               => $nome,
        'cf_piva'            => $cf_piva,
        'indirizzo'          => $indirizzo,
        'cap'                => $cap,
        'citta'              => $citta,
        'telefono'           => $telefono,
        'email'              => $email,
        'categoria_cliente'  => $categoria_cliente,
        'regime_percentuale' => $regime_percentuale,
        'regime_note'        => $regime_note,
        'doc_fronte'         => $doc_fronte,
        'doc_retro'          => $doc_retro,
        'tipo_documento'     => $tipo_documento,
        'numero_documento'   => $numero_documento,
        'note'               => $note,
        'data_creazione'     => current_time('mysql'),
    ]);

    if (!$result) {
        return new WP_Error('bsn_insert_error', 'Errore nel salvataggio cliente', ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'id'      => $wpdb->insert_id,
    ]);
}

function bsn_api_clienti_update( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_clienti';

    $cliente_id = intval( $request->get_param('cliente_id') );
    if ( ! $cliente_id ) {
        return new WP_Error(
            'bsn_cliente_id_mancante',
            'ID cliente mancante per l\'aggiornamento',
            [ 'status' => 400 ]
        );
    }

    // Leggi tutti i campi come in POST
    $nome       = sanitize_text_field($request->get_param('nome'));
    $cf_piva    = sanitize_text_field($request->get_param('cf_piva'));
    $indirizzo  = sanitize_textarea_field($request->get_param('indirizzo'));
    $telefono   = sanitize_text_field($request->get_param('telefono'));
    $email      = sanitize_email($request->get_param('email'));

    $cap        = sanitize_text_field($request->get_param('cap'));
    $citta      = sanitize_text_field($request->get_param('citta'));

    $categoria_cliente  = sanitize_text_field($request->get_param('categoria_cliente'));
    $regime_percentuale = floatval($request->get_param('regime_percentuale')) ?: 22.00;
    $regime_note        = sanitize_text_field($request->get_param('regime_note'));

    $doc_fronte       = esc_url_raw($request->get_param('doc_fronte'));
    $doc_retro        = esc_url_raw($request->get_param('doc_retro'));
    $tipo_documento   = sanitize_text_field($request->get_param('tipo_documento'));
    $numero_documento = sanitize_text_field($request->get_param('numero_documento'));

    $note = sanitize_textarea_field($request->get_param('note'));

    if (empty($nome)) {
        return new WP_Error('bsn_no_nome', 'Il nome è obbligatorio', ['status' => 400]);
    }

    $data_update = [
        'nome'               => $nome,
        'cf_piva'            => $cf_piva,
        'indirizzo'          => $indirizzo,
        'cap'                => $cap,
        'citta'              => $citta,
        'telefono'           => $telefono,
        'email'              => $email,
        'categoria_cliente'  => $categoria_cliente,
        'regime_percentuale' => $regime_percentuale,
        'regime_note'        => $regime_note,
        'doc_fronte'         => $doc_fronte,
        'doc_retro'          => $doc_retro,
        'tipo_documento'     => $tipo_documento,
        'numero_documento'   => $numero_documento,
        'note'               => $note,
    ];

    $updated = $wpdb->update(
        $table,
        $data_update,
        [ 'id' => $cliente_id ],
        null,
        [ '%d' ]
    );

    if ( $updated === false ) {
        return new WP_Error(
            'bsn_update_cliente_error',
            'Errore nell\'aggiornamento cliente',
            [ 'status' => 500 ]
        );
    }

    return rest_ensure_response([
        'success' => true,
        'id'      => $cliente_id,
    ]);
}

/* ==== CLIENTI: DELETE ==== */

function bsn_api_clienti_delete( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_clienti';

    $id = intval( $request->get_param( 'id' ) );
    if ( ! $id ) {
        return new WP_Error(
            'bsn_cliente_id_mancante',
            'ID cliente mancante',
            [ 'status' => 400 ]
        );
    }

    $deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

    if ( $deleted === false ) {
        return new WP_Error(
            'bsn_delete_cliente_error',
            'Errore nella cancellazione cliente',
            [ 'status' => 500 ]
        );
    }

    return rest_ensure_response( [
        'success' => true,
        'id'      => $id,
    ] );
}

/* ==== ARTICOLI ==== */

function bsn_api_articoli_get($request) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';
    $articoli = $wpdb->get_results("SELECT * FROM $table ORDER BY data_creazione DESC");
    return rest_ensure_response(['articoli' => $articoli]);
}

function bsn_api_articoli_post($request) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';

    $nome          = sanitize_text_field($request->get_param('nome'));
    $codice        = sanitize_text_field($request->get_param('codice'));
    $prezzo_giorno = floatval($request->get_param('prezzo_giorno'));
    $valore_bene   = floatval($request->get_param('valore_bene'));

    // Campi sconto per categoria cliente
    $sconto_standard      = floatval($request->get_param('sconto_standard'));
    $sconto_fidato        = floatval($request->get_param('sconto_fidato'));
    $sconto_premium       = floatval($request->get_param('sconto_premium'));
    $sconto_service       = floatval($request->get_param('sconto_service'));
    $sconto_collaboratori = floatval($request->get_param('sconto_collaboratori'));

    $qty_disponibile = intval($request->get_param('qty_disponibile'));
    $ubicazione      = sanitize_text_field($request->get_param('ubicazione'));
    $note            = sanitize_textarea_field($request->get_param('note'));
    $noleggio_scalare = intval($request->get_param('noleggio_scalare')) ? 1 : 0;

    // Stringa JSON dei correlati
    $correlati_raw  = $request->get_param('correlati');
    $correlati_json = null;

    if (!empty($correlati_raw)) {
        $decoded = json_decode($correlati_raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $correlati_json = wp_json_encode($decoded);
        }
    }

    if ( empty( $nome ) || empty( $codice ) ) {
        return new WP_Error(
            'bsn_articolo_dati_mancanti',
            'Nome e codice sono obbligatori',
            ['status' => 400]
        );
    }

    $data = [
        'nome'               => $nome,
        'codice'             => $codice,
        'prezzo_giorno'      => $prezzo_giorno,
        'valore_bene'        => $valore_bene,
        'sconto_standard'    => $sconto_standard,
        'sconto_fidato'      => $sconto_fidato,
        'sconto_premium'     => $sconto_premium,
        'sconto_service'     => $sconto_service,
        'sconto_collaboratori' => $sconto_collaboratori,
        'noleggio_scalare'   => $noleggio_scalare,
        'qty_disponibile'    => $qty_disponibile,
        'ubicazione'         => $ubicazione,
        'note'               => $note,
        'data_creazione'     => current_time('mysql'),
    ];

    if ($correlati_json !== null) {
        $data['correlati'] = $correlati_json;
    }

    $result = $wpdb->insert($table, $data);

    if (!$result) {
        return new WP_Error(
            'bsn_insert_articolo_error',
            'Errore nel salvataggio articolo',
            ['status' => 500]
        );
    }

    return rest_ensure_response([
        'success' => true,
        'id'      => $wpdb->insert_id,
    ]);
}

/* ==== ARTICOLI: UPDATE ==== */

function bsn_api_articoli_put( $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';

    $id = intval( $request->get_param( 'id' ) );
    if ( ! $id ) {
        return new WP_Error(
            'bsn_articolo_id_mancante',
            'ID articolo mancante',
            [ 'status' => 400 ]
        );
    }

    $data = [];
    $fields = [
        'nome',
        'codice',
        'prezzo_giorno',
        'valore_bene',
        'sconto_standard',
        'sconto_fidato',
        'sconto_premium',
        'sconto_service',
        'sconto_collaboratori',
        'qty_disponibile',
        'ubicazione',
        'note',
        'noleggio_scalare',
        'correlati',
    ];

    foreach ( $fields as $field ) {
        $value = $request->get_param( $field );
        if ( $value !== null ) {
            switch ( $field ) {
                case 'prezzo_giorno':
                case 'valore_bene':
                case 'sconto_standard':
                case 'sconto_fidato':
                case 'sconto_premium':
                case 'sconto_service':
                case 'sconto_collaboratori':
                    $data[ $field ] = floatval( $value );
                    break;
                case 'qty_disponibile':
                case 'noleggio_scalare':
                    $data[ $field ] = intval( $value );
                    break;
                case 'correlati':
                    $decoded = json_decode( $value, true );
                    if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                        $data[ $field ] = wp_json_encode( $decoded );
                    }
                    break;
                default:
                    $data[ $field ] = sanitize_text_field( $value );
                    break;
            }
        }
    }

    if ( empty( $data ) ) {
        return new WP_Error(
            'bsn_articolo_nessun_campo',
            'Nessun campo da aggiornare',
            [ 'status' => 400 ]
        );
    }

    $updated = $wpdb->update(
        $table,
        $data,
        [ 'id' => $id ],
        null,
        [ '%d' ]
    );

    if ( $updated === false ) {
        return new WP_Error(
            'bsn_update_articolo_error',
            'Errore nell\'aggiornamento articolo',
            [ 'status' => 500 ]
        );
    }

    return rest_ensure_response( [
        'success' => true,
        'id'      => $id,
    ] );
}

/* ==== ARTICOLI: DELETE ==== */

function bsn_api_articoli_delete( $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';

    $id = intval( $request->get_param( 'id' ) );
    if ( ! $id ) {
        return new WP_Error(
            'bsn_articolo_id_mancante',
            'ID articolo mancante',
            [ 'status' => 400 ]
        );
    }

    $deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

    if ( $deleted === false ) {
        return new WP_Error(
            'bsn_delete_articolo_error',
            'Errore nella cancellazione articolo',
            [ 'status' => 500 ]
        );
    }

    return rest_ensure_response( [
        'success' => true,
        'id'      => $id,
    ] );
}

/* ==== ARTICOLI: CLONE ==== */

function bsn_api_articoli_clone( $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';

    $id = intval( $request->get_param( 'id' ) );
    if ( ! $id ) {
        return new WP_Error(
            'bsn_articolo_id_mancante',
            'ID articolo da clonare mancante',
            [ 'status' => 400 ]
        );
    }

    $articolo = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ),
        ARRAY_A
    );

    if ( ! $articolo ) {
        return new WP_Error(
            'bsn_articolo_non_trovato',
            'Articolo da clonare non trovato',
            [ 'status' => 404 ]
        );
    }

    // Calcolo nuovo codice es: CDJ2000#1 -> CDJ2000#2
    $codice = $articolo['codice'];
    $nuovo_codice = $codice;

    if ( preg_match( '/^(.*?#)(\d+)$/', $codice, $m ) ) {
        $prefix   = $m[1];
        $numero   = intval( $m[2] );
        $nuovo_codice = $prefix . ( $numero + 1 );
    } else {
        // Se non ha #, parte da #1
        $nuovo_codice = $codice . '#1';
    }

    unset( $articolo['id'] );
    $articolo['codice']         = $nuovo_codice;
    $articolo['data_creazione'] = current_time( 'mysql' );

    $result = $wpdb->insert( $table, $articolo );

    if ( ! $result ) {
        return new WP_Error(
            'bsn_clone_articolo_error',
            'Errore nella clonazione articolo',
            [ 'status' => 500 ]
        );
    }

    return rest_ensure_response( [
        'success' => true,
        'id'      => $wpdb->insert_id,
        'codice'  => $nuovo_codice,
    ] );
}

/* ==== NOLEGGI ==== */

function bsn_api_noleggi_get($request) {
    global $wpdb;
    $table_noleggi  = $wpdb->prefix . 'bs_noleggi';
    $table_clienti  = $wpdb->prefix . 'bs_clienti';
    $table_articoli = $wpdb->prefix . 'bs_articoli';

    // Recupera tutti i noleggi
    $sql = "SELECT n.*, c.nome AS cliente_nome
            FROM $table_noleggi n
            LEFT JOIN $table_clienti c ON n.cliente_id = c.id
            ORDER BY n.data_richiesta DESC";

    $noleggi = $wpdb->get_results($sql);

    // Raccogli tutti gli ID articolo presenti nei noleggi (per un'unica query)
    $ids_articoli = [];
    foreach ($noleggi as $n) {
        if (!empty($n->articoli)) {
            $articoli = json_decode($n->articoli, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($articoli)) {
                foreach ($articoli as $a) {
                    if (!empty($a['id'])) {
                        $ids_articoli[] = intval($a['id']);
                    }
                }
            }
        }
    }
    $ids_articoli = array_values(array_unique(array_filter($ids_articoli)));

    // Mappa id_articolo -> dati articolo (codice + nome)
    $mappa_articoli = [];
    if (!empty($ids_articoli)) {
        $placeholders = implode(',', array_fill(0, count($ids_articoli), '%d'));
        $sql_art = "SELECT id, codice, nome FROM $table_articoli WHERE id IN ($placeholders)";
        $rows_art = $wpdb->get_results($wpdb->prepare($sql_art, $ids_articoli));
        foreach ($rows_art as $r) {
            $mappa_articoli[intval($r->id)] = [
                'codice' => $r->codice,
                'nome'   => $r->nome,
            ];
        }
    }

    $output = [];
    foreach ($noleggi as $n) {
        // Prepara riassunto articoli
        $articoli_riassunto = '-';
        if (!empty($n->articoli)) {
            $articoli = json_decode($n->articoli, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($articoli) && !empty($articoli)) {
                $parts = [];
                foreach ($articoli as $a) {
                    $qty    = isset($a['qty']) ? intval($a['qty']) : 1;
                    $id_art = isset($a['id'])  ? intval($a['id'])  : 0;
                    if ($id_art > 0 && $qty > 0) {
                        $label = $qty . 'x ';
                        if (isset($mappa_articoli[$id_art])) {
                            $info = $mappa_articoli[$id_art];
                            if (!empty($info['codice'])) {
                                $label .= '[' . $info['codice'] . '] ';
                            }
                            $label .= $info['nome'];
                        } else {
                            $label .= 'ID#' . $id_art;
                        }
                        $parts[] = $label;
                    }
                }
                if (!empty($parts)) {
                    $articoli_riassunto = implode(', ', $parts);
                }
            }
        }

        // Formatta date in stile italiano (solo giorno/mese/anno)
        $data_da_it = '';
        $data_a_it  = '';
        if (!empty($n->data_inizio)) {
            $ts = strtotime($n->data_inizio);
            if ($ts) {
                $data_da_it = date('d/m/Y', $ts);
            }
        }
        if (!empty($n->data_fine)) {
            $ts = strtotime($n->data_fine);
            if ($ts) {
                $data_a_it = date('d/m/Y', $ts);
            }
        }

        $output[] = [
            'id'                 => $n->id,
            'cliente_nome'       => $n->cliente_nome,
            'data_da'            => $data_da_it,
            'data_a'             => $data_a_it,
            'stato'              => $n->stato,
            'articoli_riassunto' => $articoli_riassunto,
        ];
    }

    return rest_ensure_response(['noleggi' => $output]);
}
/**
 * Calcola il totale noleggio in base a:
 * - articoli (array con id, qty)
 * - giorni
 * - categoria cliente
 * - regime_percentuale
 *
 * Regole:
 * - sconto per categoria cliente
 * - articoli scalari (noleggio_scalare = 1): prezzo_netto * qty * sqrt(giorni)
 * - articoli NON scalari: prezzo_netto * qty (senza moltiplicatore giorni)
 * - alla fine applica regime_percentuale sul totale
 */
function bsn_calcola_totale_noleggio( $articoli_puliti, $giorni, $categoria_cliente, $regime_percentuale, $wpdb, $table_articoli ) {
    $totale_calcolato = 0.0;

    if ( empty( $articoli_puliti ) ) {
        return 0.0;
    }

    // Raccogli ID articoli
    $ids_articoli = array_map(
        function( $a ) {
            return $a['id'];
        },
        $articoli_puliti
    );
    $ids_articoli = array_values( array_unique( $ids_articoli ) );

    if ( empty( $ids_articoli ) ) {
        return 0.0;
    }

    $placeholders = implode( ',', array_fill( 0, count( $ids_articoli ), '%d' ) );

    // Leggiamo anche noleggio_scalare
    $sql_art = "
        SELECT id,
               prezzo_giorno,
               sconto_standard,
               sconto_fidato,
               sconto_premium,
               sconto_service,
               sconto_collaboratori,
               noleggio_scalare
        FROM $table_articoli
        WHERE id IN ($placeholders)
    ";
    $rows_art = $wpdb->get_results( $wpdb->prepare( $sql_art, $ids_articoli ) );

    if ( empty( $rows_art ) ) {
        return 0.0;
    }

    $mappa_prezzi = [];
    foreach ( $rows_art as $row ) {
        $mappa_prezzi[ (int) $row->id ] = [
            'prezzo_giorno'        => (float) $row->prezzo_giorno,
            'sconto_standard'      => (float) $row->sconto_standard,
            'sconto_fidato'        => (float) $row->sconto_fidato,
            'sconto_premium'       => (float) $row->sconto_premium,
            'sconto_service'       => (float) $row->sconto_service,
            'sconto_collaboratori' => (float) $row->sconto_collaboratori,
            'noleggio_scalare'     => (int)   $row->noleggio_scalare,
        ];
    }

    // Fattore giorni per articoli SCALARI
    $fattore_giorni_scalare = sqrt( max( 1, (float) $giorni ) );

    foreach ( $articoli_puliti as $a ) {
        $id_art = isset( $a['id'] )  ? (int) $a['id']  : 0;
        $qty    = isset( $a['qty'] ) ? (int) $a['qty'] : 0;

        if ( $id_art <= 0 || $qty <= 0 ) {
            continue;
        }

        if ( ! isset( $mappa_prezzi[ $id_art ] ) ) {
            continue;
        }

        $info = $mappa_prezzi[ $id_art ];

        // Se nell'array pulito c'è un prezzo custom, usalo
        if ( isset( $a['prezzo'] ) && $a['prezzo'] !== '' && $a['prezzo'] !== null ) {
            $prezzo = (float) $a['prezzo'];
        } else {
            $prezzo = (float) $info['prezzo_giorno'];
        }

        // Sconto in base alla categoria cliente
        $sconto = 0.0;
        switch ( $categoria_cliente ) {
            case 'fidato':
                $sconto = $info['sconto_fidato'];
                break;
            case 'premium':
                $sconto = $info['sconto_premium'];
                break;
            case 'service':
                $sconto = $info['sconto_service'];
                break;
            case 'collaboratori':
                $sconto = $info['sconto_collaboratori'];
                break;
            case 'standard':
            default:
                $sconto = $info['sconto_standard'];
                break;
        }

        $fattore_sconto = max( 0, min( 100, (float) $sconto ) );
        $prezzo_netto   = $prezzo * ( 1 - $fattore_sconto / 100 );

        // Articolo SCALARE → usa √giorni
        // Articolo NON scalare → no giorni
        if ( ! empty( $info['noleggio_scalare'] ) ) {
            $totale_calcolato += $prezzo_netto * $qty * $fattore_giorni_scalare;
        } else {
            $totale_calcolato += $prezzo_netto * $qty;
        }
    }

    // Applicazione regime fiscale sul totale
    $regime = (float) $regime_percentuale;
    if ( $regime > 0 ) {
        $totale_calcolato = $totale_calcolato * ( 1 + ( $regime / 100 ) );
    }

    return $totale_calcolato;
}

/**
 * Calcola le sovrapposizioni per un noleggio (nuovo o esistente).
 *
 * @param array  $articoli_puliti  Array di articoli [{id, qty, prezzo}, ...]
 * @param string $data_inizio      'YYYY-MM-DD'
 * @param string $data_fine        'YYYY-MM-DD'
 * @param string $id_noleggio_corrente ID noleggio da escludere ('' se nuovo)
 * @param wpdb   $wpdb
 * @param string $table_articoli   Nome tabella articoli
 * @param string $table_noleggi    Nome tabella noleggi
 *
 * @return array Elenco stringhe descrittive delle sovrapposizioni
 */
function bsn_calcola_sovrapposizioni_noleggio( $articoli_puliti, $data_inizio, $data_fine, $id_noleggio_corrente, $wpdb, $table_articoli, $table_noleggi ) {
    $sovrapposizioni = [];

    if ( empty( $articoli_puliti ) ) {
        return $sovrapposizioni;
    }

    $ids_articoli = array_map( function( $a ) { return $a['id']; }, $articoli_puliti );
    $ids_articoli = array_values( array_unique( $ids_articoli ) );

    if ( empty( $ids_articoli ) ) {
        return $sovrapposizioni;
    }

    // Mappa disponibilità articoli
    $placeholders = implode( ',', array_fill( 0, count( $ids_articoli ), '%d' ) );
    $sql_art      = "SELECT id, qty_disponibile, nome, codice FROM $table_articoli WHERE id IN ($placeholders)";
    $art_rows     = $wpdb->get_results( $wpdb->prepare( $sql_art, $ids_articoli ) );

    if ( empty( $art_rows ) ) {
        return $sovrapposizioni;
    }

    $mappa_disponibile = [];
    foreach ( $art_rows as $row ) {
        $mappa_disponibile[ intval( $row->id ) ] = [
            'qty_disponibile' => intval( $row->qty_disponibile ),
            'nome'            => $row->nome,
            'codice'          => $row->codice,
        ];
    }

    $data_inizio_full = $data_inizio . ' 00:00:00';
    $data_fine_full   = $data_fine   . ' 23:59:59';

    foreach ( $articoli_puliti as $a ) {
        $id_art  = $a['id'];
        $qty_new = $a['qty'];

        if ( ! isset( $mappa_disponibile[ $id_art ] ) ) {
            continue;
        }

        $qty_disp = $mappa_disponibile[ $id_art ]['qty_disponibile'];

        // Escludi il noleggio corrente se in update; se nuovo, $id_noleggio_corrente sarà vuoto
        if ( ! empty( $id_noleggio_corrente ) ) {
            $sql_nol = "SELECT id, articoli, data_inizio, data_fine
                FROM $table_noleggi
                WHERE id <> %s
                  AND data_inizio <= %s
                  AND data_fine   >= %s
                  AND stato IN ('bozza', 'attivo', 'ritardo')";
            $existing = $wpdb->get_results( $wpdb->prepare( $sql_nol, $id_noleggio_corrente, $data_fine_full, $data_inizio_full ) );
        } else {
            $sql_nol = "SELECT id, articoli, data_inizio, data_fine
                FROM $table_noleggi
                WHERE data_inizio <= %s
                  AND data_fine   >= %s
                  AND stato IN ('bozza', 'attivo', 'ritardo')";
            $existing = $wpdb->get_results( $wpdb->prepare( $sql_nol, $data_fine_full, $data_inizio_full ) );
        }

        $qty_in_uso = 0;
        if ( ! empty( $existing ) ) {
            foreach ( $existing as $ex ) {
                if ( empty( $ex->articoli ) ) {
                    continue;
                }
                $ex_art = json_decode( $ex->articoli, true );
                if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $ex_art ) ) {
                    continue;
                }
                foreach ( $ex_art as $ea ) {
                    if ( isset( $ea['id'] ) && intval( $ea['id'] ) === $id_art ) {
                        $qty_in_uso += isset( $ea['qty'] ) ? intval( $ea['qty'] ) : 0;
                    }
                }
            }
        }

        $qty_totale = $qty_in_uso + $qty_new;
        if ( $qty_totale > $qty_disp ) {
            $info        = $mappa_disponibile[ $id_art ];
            $qty_libere  = max( 0, $qty_disp - $qty_in_uso );
            $sovrapposizioni[] = sprintf(
                '%dx richiesti per [%s] %s (a stock: %d, già in uso: %d, disponibilità: %d)',
                $qty_new,
                $info['codice'],
                $info['nome'],
                $qty_disp,
                $qty_in_uso,
                $qty_libere
            );
        }
    }

    return $sovrapposizioni;
}

function bsn_api_noleggi_post($request) {
    global $wpdb;
    $table_noleggi = $wpdb->prefix . 'bs_noleggi';
    $table_clienti = $wpdb->prefix . 'bs_clienti';
    $table_articoli = $wpdb->prefix . 'bs_articoli';

    // ID noleggio (per UPDATE o per generare nuovo)
    $noleggio_id = sanitize_text_field($request->get_param('noleggio_id'));
    $is_update = !empty($noleggio_id);

    // Dati base
    $cliente_id = intval($request->get_param('cliente_id'));
    $data_inizio = sanitize_text_field($request->get_param('data_inizio'));
    $data_fine = sanitize_text_field($request->get_param('data_fine'));
    $stato = sanitize_text_field($request->get_param('stato')) ?: 'bozza';
    $note = sanitize_textarea_field($request->get_param('note'));

    // ===== NUOVI CAMPI LOGISTICA =====
    $luogo_destinazione = sanitize_text_field($request->get_param('luogo_destinazione'));
    $trasporto_mezzo = sanitize_text_field($request->get_param('trasporto_mezzo'));
    $cauzione = sanitize_text_field($request->get_param('cauzione'));
    $causale_trasporto = sanitize_text_field($request->get_param('causale_trasporto'));

    // ===== ARTICOLI CON PREZZI CUSTOM =====
$articoli_json = $request->get_param('articoli');

if (empty($cliente_id) || empty($data_inizio) || empty($data_fine)) {
    return new WP_Error('bsn_dati_mancanti', 'Dati obbligatori mancanti', ['status' => 400]);
}

// Decodifica il JSON ricevuto dal JS
$articoli = [];
if (!empty($articoli_json)) {
    $decoded = json_decode($articoli_json, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Pulisci e valida ogni articolo
        foreach ($decoded as $a) {
            $id_art = isset($a['id']) ? intval($a['id']) : 0;
            $qty = isset($a['qty']) ? intval($a['qty']) : 0;
            if ($id_art > 0 && $qty > 0) {
                $articoli[] = [
                    'id' => $id_art,
                    'qty' => $qty,
                    'prezzo' => isset($a['prezzo']) ? floatval($a['prezzo']) : null,
                ];
            }
        }
    }
}


    // ===== CALCOLO TOTALE (con prezzi custom e sconto globale) =====
    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT categoria_cliente, regime_percentuale FROM $table_clienti WHERE id = %d",
        $cliente_id
    ), ARRAY_A);

    if (!$cliente) {
        return new WP_Error('bsn_cliente_non_trovato', 'Cliente non trovato', ['status' => 404]);
    }

    $categoria = $cliente['categoria_cliente'] ?? 'standard';
    $regime = (float)($cliente['regime_percentuale'] ?? 22.00);

    // Calcolo giorni
    $ts_inizio = strtotime($data_inizio);
    $ts_fine = strtotime($data_fine);
    $giorni = max(1, (int)ceil(($ts_fine - $ts_inizio) / 86400));
    $fattore_scalare = sqrt($giorni);

    // Recupera info articoli dal DB
    $ids_articoli = array_map(function($a) { return (int)$a['id']; }, $articoli);
    $mappa_articoli = [];
    
    if (!empty($ids_articoli)) {
        $placeholders = implode(',', array_fill(0, count($ids_articoli), '%d'));
        $sql = "SELECT * FROM $table_articoli WHERE id IN ($placeholders)";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $ids_articoli), ARRAY_A);
        
        foreach ($rows as $row) {
            $mappa_articoli[(int)$row['id']] = $row;
        }
    }

    // Calcolo subtotale
    $subtotale = 0;
    foreach ($articoli as &$art) {
        $art_id = $art['id'];
        $qty = $art['qty'];
        $prezzo_custom = $art['prezzo'];
        
        if (!isset($mappa_articoli[$art_id])) continue;
        
        $art_db = $mappa_articoli[$art_id];
        $prezzo_base = (float)($art_db['prezzo_giorno'] ?? 0);
        $noleggio_scalare = (int)($art_db['noleggio_scalare'] ?? 0);
        
        // Usa prezzo custom se fornito, altrimenti prezzo base
        $prezzo = ($prezzo_custom !== null) ? $prezzo_custom : $prezzo_base;
        
        // ===== IMPORTANTE: Se prezzo_custom è null, lo impostiamo al prezzo_base =====
        // In modo che venga salvato nel JSON
        if ($art['prezzo'] === null) {
            $art['prezzo'] = $prezzo_base;
        }
        
        // Applica sconto categoria
        $sconto = 0;
        switch ($categoria) {
            case 'fidato': $sconto = (float)($art_db['sconto_fidato'] ?? 0); break;
            case 'premium': $sconto = (float)($art_db['sconto_premium'] ?? 0); break;
            case 'service': $sconto = (float)($art_db['sconto_service'] ?? 0); break;
            case 'collaboratori': $sconto = (float)($art_db['sconto_collaboratori'] ?? 0); break;
            default: $sconto = (float)($art_db['sconto_standard'] ?? 0); break;
        }
        
        $prezzo_netto = $prezzo * (1 - ($sconto / 100));
        
        // Calcolo subtotale riga
        if ($noleggio_scalare === 1) {
            $subtotale_riga = $prezzo_netto * $qty * $fattore_scalare;
        } else {
            $subtotale_riga = $prezzo_netto * $qty;
        }
        
        $subtotale += $subtotale_riga;
    }

    // Calcolo totale con regime
    $importo_regime = $subtotale * ($regime / 100);
    $totale_noleggio = $subtotale + $importo_regime;

    // ===== SCONTO GLOBALE =====
    $sconto_globale = (float)($request->get_param('sconto_globale') ?? 0);
    $totale_finale = $totale_noleggio + $sconto_globale; // + perché se negativo riduce, se positivo aumenta

    // ----- CONTROLLO SOVRAPPOSIZIONI ANCHE IN CREAZIONE (POST) -----
    $sovrapposizioni = bsn_calcola_sovrapposizioni_noleggio(
        $articoli,
        $data_inizio,
        $data_fine,
        '', // nuovo noleggio → nessun ID da escludere
        $wpdb,
        $table_articoli,
        $table_noleggi
    );

    $consenti_overbook = intval( $request->get_param( 'consenti_overbook' ) );
    if ( ! empty( $sovrapposizioni ) && ! $consenti_overbook ) {
        return new WP_Error(
            'bsn_sovrapposizioni_blocco',
            "Sovrapposizioni rilevate:\n- " . implode( "\n- ", $sovrapposizioni ),
            [ 'status' => 400, 'sovrapposizioni' => $sovrapposizioni ]
        );
    }

    // ===== GENERA O MANTIENE ID NOLEGGIO =====
    if (!$is_update) {
        $anno = date('Y');
        $ultimo = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(id, '/', -1) AS UNSIGNED)) 
             FROM $table_noleggi 
             WHERE id LIKE %s",
            $anno . '/%'
        ));
        $progressivo = str_pad((intval($ultimo) + 1), 3, '0', STR_PAD_LEFT);
        $noleggio_id = "$anno/$progressivo";
    }

    // ===== PREPARA DATI PER SALVATAGGIO =====
    $data = [
        'cliente_id' => $cliente_id,
        'data_richiesta' => current_time('mysql'),
        'data_inizio' => $data_inizio,
        'data_fine' => $data_fine,
        'stato' => $stato,
        'articoli' => wp_json_encode($articoli), // ← ORA INCLUDE I PREZZI!
        'totale_calcolato' => $totale_finale,
        'sconto_globale' => $sconto_globale,
        'note' => $note,
        'luogo_destinazione' => $luogo_destinazione,
        'trasporto_mezzo' => $trasporto_mezzo,
        'cauzione' => $cauzione,
        'causale_trasporto' => $causale_trasporto,
    ];

    // ===== SALVA (INSERT O UPDATE) =====
    if ($is_update) {
        $result = $wpdb->update($table_noleggi, $data, ['id' => $noleggio_id]);
        if ($result === false) {
            return new WP_Error('bsn_update_error', 'Errore aggiornamento noleggio', ['status' => 500]);
        }
    } else {
        $data['id'] = $noleggio_id;
        $result = $wpdb->insert($table_noleggi, $data);
        if (!$result) {
            return new WP_Error('bsn_insert_error', 'Errore salvataggio noleggio', ['status' => 500]);
        }
    }

    return rest_ensure_response([
        'success' => true,
        'id' => $noleggio_id,
        'totale' => $totale_finale,
        'sovrapposizioni'  => $sovrapposizioni,
    ]);
}

/**
 * Endpoint update noleggio esistente
 * POST /wp-json/bsn/v1/noleggi/update
 */
add_action('rest_api_init', function () {
    register_rest_route('bsn/v1', '/noleggi/update', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_noleggi_update',
        'permission_callback' => 'bsn_check_admin',
    ]);
});

/* ==== NOLEGGI: DELETE ==== */

function bsn_api_noleggi_delete( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_noleggi';

    $id = sanitize_text_field( $request->get_param( 'id' ) );
    if ( empty( $id ) ) {
        return new WP_Error(
            'bsn_noleggio_id_mancante',
            'ID noleggio mancante',
            [ 'status' => 400 ]
        );
    }

    $deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%s' ] );

    if ( $deleted === false ) {
        return new WP_Error(
            'bsn_delete_noleggio_error',
            'Errore nella cancellazione noleggio',
            [ 'status' => 500 ]
        );
    }

    return rest_ensure_response( [
        'success' => true,
        'id'      => $id,
    ] );
}


function bsn_api_noleggi_update( WP_REST_Request $request ) {
    global $wpdb;
    $table_noleggi  = $wpdb->prefix . 'bs_noleggi';
    $table_articoli = $wpdb->prefix . 'bs_articoli';
    $table_clienti  = $wpdb->prefix . 'bs_clienti';

    $id_noleggio = sanitize_text_field( $request->get_param('noleggio_id') );
    if ( empty( $id_noleggio ) ) {
        return new WP_Error('bsn_noleggio_id_mancante', 'ID noleggio mancante', ['status' => 400]);
    }

    $cliente_id   = intval( $request->get_param('cliente_id') );
    $data_inizio  = sanitize_text_field( $request->get_param('data_inizio') );
    $data_fine    = sanitize_text_field( $request->get_param('data_fine') );
    $stato        = sanitize_text_field( $request->get_param('stato') );
    $note         = sanitize_textarea_field( $request->get_param('note') );
        // Nuovi campi logistica/trasporto
    $luogo_destinazione = sanitize_text_field( $request->get_param('luogo_destinazione') );
    $trasporto_mezzo    = sanitize_text_field( $request->get_param('trasporto_mezzo') );
    $cauzione           = sanitize_text_field( $request->get_param('cauzione') );
    $causale_trasporto  = sanitize_text_field( $request->get_param('causale_trasporto') );
    $articoli_raw = $request->get_param('articoli' );

    if ( ! $cliente_id || empty( $data_inizio ) || empty( $data_fine ) ) {
        return new WP_Error('bsn_noleggio_dati_mancanti', 'Cliente, data inizio e data fine sono obbligatori', ['status' => 400]);
    }

    // Decodifica JSON articoli
$articoli_puliti = [];
if ( ! empty( $articoli_raw ) ) {
    $decoded = json_decode( $articoli_raw, true );
    if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
        foreach ( $decoded as $a ) {
            $id  = isset( $a['id'] )  ? intval( $a['id'] )  : 0;
            $qty = isset( $a['qty'] ) ? intval( $a['qty'] ) : 0;
            if ( $id > 0 && $qty > 0 ) {
                $articoli_puliti[] = [
                    'id'    => $id,
                    'qty'   => $qty,
                    'prezzo' => isset( $a['prezzo'] ) ? floatval( $a['prezzo'] ) : null,
                ];
            }
        }
    }
}

    $articoli_json = ! empty( $articoli_puliti ) ? wp_json_encode( $articoli_puliti ) : null;

        // Recupera categoria cliente + regime fiscale
    $cliente = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT categoria_cliente, regime_percentuale FROM $table_clienti WHERE id = %d",
            $cliente_id
        )
    );
    $categoria_cliente   = $cliente ? $cliente->categoria_cliente : 'standard';
    $regime_percentuale  = $cliente ? (float)$cliente->regime_percentuale : 0.0;

    // Giorni
        // Calcolo giorni (notti) - minimo 1
    $giorni = 1;
    $ts_da  = strtotime($data_inizio);
    $ts_a   = strtotime($data_fine);
    if ($ts_da && $ts_a && $ts_a > $ts_da) {
        // differenza in giornate intere, senza +1
        $giorni = max(1, (int)ceil(($ts_a - $ts_da) / 86400));
    }

        // Totale calcolato con la stessa logica della POST
    $totale_calcolato = bsn_calcola_totale_noleggio(
        $articoli_puliti,
        $giorni,
        $categoria_cliente,
        $regime_percentuale,
        $wpdb,
        $table_articoli
    );
    
    // ===== SCONTO GLOBALE =====
$sconto_globale = (float)($request->get_param('sconto_globale') ?? 0);
$totale_calcolato = $totale_calcolato + $sconto_globale;


        // ----- CONTROLLO SOVRAPPOSIZIONI (UPDATE) -----
    $sovrapposizioni = bsn_calcola_sovrapposizioni_noleggio(
        $articoli_puliti,
        $data_inizio,
        $data_fine,
        $id_noleggio,      // in update escludiamo il noleggio stesso
        $wpdb,
        $table_articoli,
        $table_noleggi
    );

    $consenti_overbook = intval( $request->get_param( 'consenti_overbook' ) );
    if ( ! empty( $sovrapposizioni ) && ! $consenti_overbook ) {
        return new WP_Error(
            'bsn_sovrapposizioni_blocco',
            "Sovrapposizioni rilevate:\n- " . implode( "\n- ", $sovrapposizioni ),
            [ 'status' => 400, 'sovrapposizioni' => $sovrapposizioni ]
        );
    }

        // ----- UPDATE NOLEGGIO -----
        $data_update = [
            'cliente_id'         => $cliente_id,
            'data_inizio'        => $data_inizio . ' 00:00:00',
            'data_fine'          => $data_fine . ' 23:59:59',
            'stato'              => $stato ?: 'bozza',
            'note'               => $note,
            'totale_calcolato'   => $totale_calcolato,
            'sconto_globale'     => $sconto_globale, // << AGGIUNTO: allinea alla POST
            // nuovi campi logistica/trasporto
            'luogo_destinazione' => $luogo_destinazione,
            'trasporto_mezzo'    => $trasporto_mezzo,
            'cauzione'           => $cauzione,
            'causale_trasporto'  => $causale_trasporto,
        ];

        if ( $articoli_json !== null ) {
            $data_update['articoli'] = $articoli_json;
        } else {
            $data_update['articoli'] = null;
        }


    $updated = $wpdb->update(
        $table_noleggi,
        $data_update,
        [ 'id' => $id_noleggio ],
        null,
        [ '%s' ]
    );

    if ( $updated === false ) {
        return new WP_Error('bsn_update_noleggio_error', 'Errore nell\'aggiornare il noleggio', ['status' => 500]);
    }

    return rest_ensure_response([
        'success'         => true,
        'id'              => $id_noleggio,
        'totale'          => $totale_calcolato,
        'giorni'          => $giorni,
        'sovrapposizioni' => $sovrapposizioni,
    ]);
}

/**
 * Endpoint dettaglio noleggio
 * GET /wp-json/bsn/v1/noleggi/dettaglio?id=2026/001
 */
add_action('rest_api_init', function () {
    register_rest_route('bsn/v1', '/noleggi/dettaglio', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_noleggi_dettaglio',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id' => [
                'required' => true,
                'type'     => 'string',
            ],
        ],
    ]);
});

/**
 * Restituisce tutti i dati di un singolo noleggio:
 * - cliente_id, cliente_nome
 * - date grezze (YYYY-MM-DD), stato, note
 * - articoli: [{id, qty, nome, codice, ubicazione, correlati}]
 */
function bsn_api_noleggi_dettaglio( WP_REST_Request $request ) {
    global $wpdb;
    $table_noleggi  = $wpdb->prefix . 'bs_noleggi';
    $table_clienti  = $wpdb->prefix . 'bs_clienti';
    $table_articoli = $wpdb->prefix . 'bs_articoli';

    $id = sanitize_text_field( $request->get_param('id') );
    if ( empty( $id ) ) {
        return new WP_Error(
            'bsn_noleggio_id_mancante',
            'ID noleggio mancante',
            [ 'status' => 400 ]
        );
    }

    // Recupera il noleggio specifico
    $sql = $wpdb->prepare(
        "SELECT n.*, c.nome AS cliente_nome
         FROM $table_noleggi n
         LEFT JOIN $table_clienti c ON n.cliente_id = c.id
         WHERE n.id = %s
         LIMIT 1",
        $id
    );
    $n = $wpdb->get_row( $sql );

    if ( ! $n ) {
        return new WP_Error(
            'bsn_noleggio_non_trovato',
            'Noleggio non trovato',
            [ 'status' => 404 ]
        );
    }

    // Date grezze per <input type="date">
    $data_inizio_iso = '';
    $data_fine_iso   = '';

    if ( ! empty( $n->data_inizio ) ) {
        $ts = strtotime( $n->data_inizio );
        if ( $ts ) {
            $data_inizio_iso = date( 'Y-m-d', $ts );
        }
    }
    if ( ! empty( $n->data_fine ) ) {
        $ts = strtotime( $n->data_fine );
        if ( $ts ) {
            $data_fine_iso = date( 'Y-m-d', $ts );
        }
    }

    // Decodifica articoli JSON
    $articoli = [];
    if ( ! empty( $n->articoli ) ) {
        $decoded = json_decode( $n->articoli, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $articoli = $decoded;
        }
    }

    // Se ci sono articoli, recupera i dati base (nome, codice, ubicazione, correlati)
    $ids_articoli = [];
    foreach ( $articoli as $a ) {
        if ( ! empty( $a['id'] ) ) {
            $ids_articoli[] = intval( $a['id'] );
        }
    }
    $ids_articoli = array_values( array_unique( array_filter( $ids_articoli ) ) );

    $mappa_articoli = [];
    if ( ! empty( $ids_articoli ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $ids_articoli ), '%d' ) );
        $sql_art = "SELECT id, codice, nome, ubicazione, correlati
                    FROM $table_articoli
                    WHERE id IN ($placeholders)";
        $rows_art = $wpdb->get_results( $wpdb->prepare( $sql_art, $ids_articoli ) );
        foreach ( $rows_art as $r ) {
            $mappa_articoli[ intval( $r->id ) ] = [
                'codice'     => $r->codice,
                'nome'       => $r->nome,
                'ubicazione' => $r->ubicazione,
                'correlati'  => $r->correlati,
            ];
        }
    }

    $articoli_out = [];
    foreach ( $articoli as $a ) {
        $id_art = isset( $a['id'] )  ? intval( $a['id'] )  : 0;
        $qty    = isset( $a['qty'] ) ? intval( $a['qty'] ) : 0;

        if ( $id_art <= 0 || $qty <= 0 ) {
            continue;
        }

        $nome       = '';
        $codice     = '';
        $ubicazione = '';
        $correlati  = [];
        $prezzo     = isset( $a['prezzo'] ) ? $a['prezzo'] : null;

        if ( isset( $mappa_articoli[ $id_art ] ) ) {
            $nome       = $mappa_articoli[ $id_art ]['nome'];
            $codice     = $mappa_articoli[ $id_art ]['codice'];
            $ubicazione = $mappa_articoli[ $id_art ]['ubicazione'];

            // Prova a decodificare correlati come JSON [{nome, qty}, ...]
            $correlati_raw = $mappa_articoli[ $id_art ]['correlati'];
            if ( ! empty( $correlati_raw ) ) {
                $decoded_corr = json_decode( $correlati_raw, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_corr ) ) {
                    $correlati = $decoded_corr;
                }
            }
        }

        $articoli_out[] = [
            'id'         => $id_art,
            'qty'        => $qty,
            'prezzo'     => $prezzo,
            'nome'       => $nome,
            'codice'     => $codice,
            'ubicazione' => $ubicazione,
            'correlati'  => $correlati,
        ];
    }

    $response = [
        'id'             => $n->id,
        'cliente_id'     => intval( $n->cliente_id ),
        'cliente_nome'   => $n->cliente_nome,
        'data_inizio'    => $data_inizio_iso,
        'data_fine'      => $data_fine_iso,
        'stato'          => $n->stato,
        'note'           => $n->note,
        'luogo_destinazione' => $n->luogo_destinazione,
        'trasporto_mezzo'    => $n->trasporto_mezzo,
        'cauzione'           => $n->cauzione,
        'causale_trasporto'  => $n->causale_trasporto,
        'sconto_globale' => isset( $n->sconto_globale ) ? (float) $n->sconto_globale : 0.0,
        'articoli'       => $articoli_out,
    ];

    return rest_ensure_response( $response );
}

/**
 * Duplica un noleggio esistente creando un nuovo record con nuovo ID.
 * Copia: cliente_id, date, stato (forzato a "bozza"), note, articoli, totale_calcolato.
 */
function bsn_api_noleggi_duplica( WP_REST_Request $request ) {
    global $wpdb;

    $table_noleggi = $wpdb->prefix . 'bs_noleggi';

    $id_originale = sanitize_text_field( $request->get_param( 'id' ) );
    if ( empty( $id_originale ) ) {
        return new WP_Error( 'bsn_noleggio_id_mancante', 'ID noleggio mancante', [ 'status' => 400 ] );
    }

    // Recupera il noleggio originale
    $noleggio = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM $table_noleggi WHERE id = %s LIMIT 1", $id_originale ),
        ARRAY_A
    );

    if ( ! $noleggio ) {
        return new WP_Error( 'bsn_noleggio_non_trovato', 'Noleggio originale non trovato.', [ 'status' => 404 ] );
    }

    // Genera un nuovo ID come in bsn_api_noleggi_post
    $anno = date( 'Y' );
    $ultimo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $table_noleggi WHERE id LIKE %s ORDER BY id DESC LIMIT 1",
            $anno . '/%'
        )
    );

    $progressivo = 1;
    if ( $ultimo ) {
        $pezzi = explode( '/', $ultimo );
        if ( ! empty( $pezzi[1] ) ) {
            $progressivo = intval( $pezzi[1], 10 ) + 1;
        }
    }

    $id_nuovo = $anno . '/' . str_pad( $progressivo, 3, '0', STR_PAD_LEFT );

    // Prepara dati per inserimento
    $current_user = wp_get_current_user();

    $data_insert = [
        'id'                 => $id_nuovo,
        'cliente_id'         => isset( $noleggio['cliente_id'] ) ? intval( $noleggio['cliente_id'] ) : 0,
        'data_richiesta'     => current_time( 'mysql' ),
        'data_inizio'        => isset( $noleggio['data_inizio'] ) ? $noleggio['data_inizio'] : null,
        'data_fine'          => isset( $noleggio['data_fine'] ) ? $noleggio['data_fine'] : null,
        'stato'              => 'bozza', // il duplicato parte sempre come bozza
        'operatore_richiesta'=> $current_user ? $current_user->user_login : '',
        'operatore_verifica' => null,
        'articoli'           => isset( $noleggio['articoli'] ) ? $noleggio['articoli'] : null,
        'totale_calcolato'   => isset( $noleggio['totale_calcolato'] ) ? $noleggio['totale_calcolato'] : null,
        'note'               => isset( $noleggio['note'] ) ? $noleggio['note'] : '',
    ];

    $formati = [
        '%s', // id
        '%d', // cliente_id
        '%s', // data_richiesta
        '%s', // data_inizio
        '%s', // data_fine
        '%s', // stato
        '%s', // operatore_richiesta
        '%s', // operatore_verifica
        '%s', // articoli
        '%f', // totale_calcolato
        '%s', // note
    ];

    $result = $wpdb->insert( $table_noleggi, $data_insert, $formati );

    if ( ! $result ) {
        return new WP_Error( 'bsn_duplica_noleggio_error', 'Errore nel duplicare il noleggio.', [ 'status' => 500 ] );
    }

    return rest_ensure_response( [
        'success' => true,
        'id'      => $id_nuovo,
    ] );
}
/**
 * Endpoint finalizza noleggio
 * POST /wp-json/bsn/v1/noleggi/finalizza
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'bsn/v1', '/noleggi/finalizza', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_noleggi_finalizza',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id_noleggio'         => [ 'required' => true,  'type' => 'string'  ],
            'firma_base64'        => [ 'required' => true,  'type' => 'string'  ],
            'firma_data'          => [ 'required' => true,  'type' => 'string'  ],
            'consenso_privacy'    => [ 'required' => true,  'type' => 'integer' ],
            'consenso_condizioni' => [ 'required' => true,  'type' => 'integer' ],
            'op_documento'        => [ 'required' => true,  'type' => 'string'  ],
            'op_materiale'        => [ 'required' => true,  'type' => 'string'  ],
            'op_consegna'         => [ 'required' => false, 'type' => 'string'  ],
            'op_rientro'          => [ 'required' => false, 'type' => 'string'  ],
        ],
    ] );
} );

// === STEP A4: Redirect dopo login per operatori ed editor verso /app-noleggi/ ===
function bsn_login_redirect( $redirect_to, $request, $user ) {
    if ( ! $user || is_wp_error( $user ) ) {
        return $redirect_to;
    }

    // Admin resta nel backend classico
    if ( user_can( $user, 'manage_options' ) ) {
        return $redirect_to;
    }

    // Utenti autorizzati all'app (editor + bsn_operatore) → /app-noleggi/
    if ( user_can( $user, 'bsn_manage_noleggi' ) || user_can( $user, 'edit_others_posts' ) ) {
        return home_url( '/app-noleggi/' );
    }

    // Altri ruoli → comportamento standard
    return $redirect_to;
}
add_filter( 'login_redirect', 'bsn_login_redirect', 10, 3 );

// === STEP A5: Blocca l'accesso a /wp-admin/ per bsn_operatore ===
function bsn_restrict_admin_for_operator() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        $user = wp_get_current_user();

        // Se è operatore noleggi ma NON è admin/editor, blocchiamo wp-admin
        if ( in_array( 'bsn_operatore', (array) $user->roles, true )
             && ! current_user_can( 'manage_options' )
             && ! current_user_can( 'edit_others_posts' ) ) {

            wp_redirect( home_url( '/app-noleggi/' ) );
            exit;
        }
    }
}
add_action( 'admin_init', 'bsn_restrict_admin_for_operator' );

// === STEP A6: Nascondi la admin-bar per bsn_operatore ===
function bsn_hide_admin_bar_for_operator() {
    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();

        if ( in_array( 'bsn_operatore', (array) $user->roles, true ) ) {
            show_admin_bar( false );
        }
    }
}
add_action( 'after_setup_theme', 'bsn_hide_admin_bar_for_operator' );

/**
 * Finalizza un noleggio:
 * - verifica stato = 'bozza'
 * - salva firma PNG in uploads/bsn-firme/firma-noleggio-ID.png
 * - salva consensi e operatori
 * - imposta stato = 'attivo'
 */
function bsn_api_noleggi_finalizza( WP_REST_Request $request ) {
    global $wpdb;

    $table_noleggi = $wpdb->prefix . 'bs_noleggi';

    $id_noleggio         = sanitize_text_field( $request->get_param( 'id_noleggio' ) );
    $firma_base64        = $request->get_param( 'firma_base64' );
    $firma_data_raw      = sanitize_text_field( $request->get_param( 'firma_data' ) );
    $consenso_privacy    = intval( $request->get_param( 'consenso_privacy' ) );
    $consenso_condizioni = intval( $request->get_param( 'consenso_condizioni' ) );
    $op_documento        = sanitize_text_field( $request->get_param( 'op_documento' ) );
    $op_materiale        = sanitize_text_field( $request->get_param( 'op_materiale' ) );
    $op_consegna         = sanitize_text_field( $request->get_param( 'op_consegna' ) );
    $op_rientro          = sanitize_text_field( $request->get_param( 'op_rientro' ) );

    if ( empty( $id_noleggio ) ) {
        return new WP_Error(
            'bsn_finalizza_id_mancante',
            'ID noleggio mancante.',
            [ 'status' => 400 ]
        );
    }

    // Recupera noleggio
    $noleggio = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_noleggi WHERE id = %s LIMIT 1",
            $id_noleggio
        ),
        ARRAY_A
    );

    if ( ! $noleggio ) {
        return new WP_Error(
            'bsn_finalizza_noleggio_non_trovato',
            'Noleggio non trovato.',
            [ 'status' => 404 ]
        );
    }

    if ( $noleggio['stato'] !== 'bozza' ) {
        return new WP_Error(
            'bsn_finalizza_stato_non_valido',
            'Solo i noleggi in stato "bozza" possono essere finalizzati.',
            [ 'status' => 400 ]
        );
    }

    // --- Salvataggio firma PNG in uploads/bsn-firme/ ---
    if ( empty( $firma_base64 ) || strpos( $firma_base64, 'data:image/png;base64,' ) !== 0 ) {
        return new WP_Error(
            'bsn_finalizza_firma_non_valida',
            'Firma non valida.',
            [ 'status' => 400 ]
        );
    }

    $upload_dir = wp_upload_dir();
    if ( ! empty( $upload_dir['error'] ) ) {
        return new WP_Error(
            'bsn_finalizza_upload_dir_error',
            'Impossibile accedere alla cartella upload.',
            [ 'status' => 500 ]
        );
    }

    $firma_data_clean = str_replace( 'data:image/png;base64,', '', $firma_base64 );
    $firma_data_clean = str_replace( ' ', '+', $firma_data_clean );
    $firma_bin        = base64_decode( $firma_data_clean );

    if ( ! $firma_bin ) {
        return new WP_Error(
            'bsn_finalizza_decode_error',
            'Errore nella decodifica della firma.',
            [ 'status' => 400 ]
        );
    }

    // Usiamo wp_upload_bits per rispettare le policy dell'hosting
    $firma_filename = 'firma-noleggio-' . preg_replace( '/[^A-Za-z0-9_\-]/', '_', $id_noleggio ) . '.png';

    $upload_bits = wp_upload_bits(
        'bsn-firme/' . $firma_filename,
        null,
        $firma_bin
    );

    if ( ! empty( $upload_bits['error'] ) ) {
        return new WP_Error(
            'bsn_finalizza_scrittura_firma',
            'Errore nel salvataggio del file firma.',
            [ 'status' => 500, 'detail' => $upload_bits['error'] ]
        );
    }

    $firma_url = $upload_bits['url'];

    // --- Conversione data firma in Y-m-d H:i:s ---
    if ( ! empty( $firma_data_raw ) ) {
        $dt = DateTime::createFromFormat( 'd/m/Y H:i', $firma_data_raw );
        if ( $dt ) {
            $firma_data_mysql = $dt->format( 'Y-m-d H:i:s' );
        } else {
            $firma_data_mysql = current_time( 'mysql' );
        }
    } else {
        $firma_data_mysql = current_time( 'mysql' );
    }

    // --- Aggiorna record noleggio ---
    $data_update = [
        'stato'                     => 'attivo',
        'consenso_privacy'          => $consenso_privacy ? 1 : 0,
        'consenso_condizioni'       => $consenso_condizioni ? 1 : 0,
        'firma_url'                 => $firma_url,
        'firma_data'                => $firma_data_mysql,
        'op_preparazione_documento' => $op_documento,
        'op_preparazione_materiale' => $op_materiale,
        'op_consegna_materiale'     => $op_consegna,
        'op_rientro_materiale'      => $op_rientro,
    ];

    $updated = $wpdb->update(
        $table_noleggi,
        $data_update,
        [ 'id' => $id_noleggio ],
        null,
        [ '%s' ]
    );

    if ( $updated === false ) {
        return new WP_Error(
            'bsn_finalizza_update_error',
            'Errore nell\'aggiornare il noleggio.',
            [ 'status' => 500 ]
        );
    }

    return rest_ensure_response( [
        'success'   => true,
        'id'        => $id_noleggio,
        'stato'     => 'attivo',
        'firma_url' => $firma_url,
    ] );
}

/**
 * Endpoint cambio stato noleggio - callback
 */
function bsn_cambia_stato_noleggio( $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_noleggi';

    $id_noleggio = sanitize_text_field( $request->get_param( 'id_noleggio' ) );
    $nuovo_stato = sanitize_text_field( $request->get_param( 'nuovo_stato' ) );

    $stati_validi = array( 'bozza', 'attivo', 'chiuso', 'ritardo' );
    if ( ! in_array( $nuovo_stato, $stati_validi, true ) ) {
        return new WP_Error( 'stato_non_valido', 'Stato non valido', array( 'status' => 400 ) );
    }

    $updated = $wpdb->update(
        $table,
        array( 'stato' => $nuovo_stato ),
        array( 'id'    => $id_noleggio ),
        array( '%s' ),
        array( '%s' )
    );

    if ( $updated === false ) {
        return new WP_Error( 'db_error', 'Errore nel salvataggio', array( 'status' => 500 ) );
    }

    return array(
        'success'     => true,
        'id_noleggio' => $id_noleggio,
        'stato'       => $nuovo_stato,
    );
}

/**
 * Endpoint Rientro noleggio - callback
 */
function bsn_api_noleggi_rientro( $request ) {
    global $wpdb;
    $table_noleggi = $wpdb->prefix . 'bs_noleggi';

    $params      = $request->get_json_params();
    $id_noleggio = isset( $params['id_noleggio'] ) ? sanitize_text_field( $params['id_noleggio'] ) : '';
    $op_rientro  = isset( $params['op_rientro'] ) ? sanitize_text_field( $params['op_rientro'] ) : '';
    $articoli    = isset( $params['articoli'] ) && is_array( $params['articoli'] ) ? $params['articoli'] : array();

    if ( ! $id_noleggio ) {
        return new WP_Error( 'bsn_no_id', 'ID noleggio mancante.', array( 'status' => 400 ) );
    }

    // Recupera noleggio
    $noleggio = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_noleggi WHERE id = %s",
            $id_noleggio
        )
    );

    if ( ! $noleggio ) {
        return new WP_Error( 'bsn_not_found', 'Noleggio non trovato.', array( 'status' => 404 ) );
    }

    if ( $noleggio->stato !== 'attivo' && $noleggio->stato !== 'ritardo' ) {
        return new WP_Error( 'bsn_bad_state', 'Il noleggio non è in stato attivo o ritardo.', array( 'status' => 400 ) );
    }

    if ( ! $op_rientro ) {
        return new WP_Error( 'bsn_no_op', 'Operatore rientro materiali mancante.', array( 'status' => 400 ) );
    }

    // STEP B2: solo chiusura noleggio + operatore rientro
    $updated = $wpdb->update(
        $table_noleggi,
        array(
            'op_rientro_materiale' => $op_rientro,
            'stato'                => 'chiuso',
        ),
        array(
            'id' => $id_noleggio,
        ),
        array( '%s', '%s' ),
        array( '%s' )
    );

    if ( $updated === false ) {
        return new WP_Error( 'bsn_db_error', 'Errore durante l\'aggiornamento del noleggio.', array( 'status' => 500 ) );
    }

    return array(
        'success' => true,
        'message' => 'Rientro salvato e noleggio chiuso.',
        'stato'   => 'chiuso',
        'id'      => $id_noleggio,
    );
}
