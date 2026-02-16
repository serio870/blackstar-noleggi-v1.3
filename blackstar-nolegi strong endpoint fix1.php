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
 * Attivazione: crea / aggiorna tabelle BS Noleggi
 */
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
        regime_note varchar(150),                       -- es. \"esente art. 10\"

        -- Documenti identità
        doc_fronte varchar(255),
        doc_retro varchar(255),
        tipo_documento varchar(50),
        numero_documento varchar(50),

        -- Note interne cliente
        note text,

        data_creazione datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    // Articoli
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
        -- Flag scalare
        noleggio_scalare tinyint(1) NOT NULL DEFAULT 0,
        ubicazione varchar(100),
        external_product_url varchar(255),
        external_product_slug varchar(200),
        external_product_name varchar(255),
        external_image_url varchar(255),
        external_last_sync datetime,
        qty_disponibile int DEFAULT 1,
        correlati longtext,
        note text,
        data_creazione datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    // Noleggi
    $table_noleggi = $wpdb->prefix . 'bs_noleggi';
    $sql_noleggi = "CREATE TABLE $table_noleggi (
        id varchar(20) NOT NULL,
        cliente_id mediumint(9),
        data_richiesta datetime,
        data_inizio datetime,
        data_fine datetime,
        stato enum('preventivo','bozza','attivo','chiuso','ritardo') DEFAULT 'bozza',
        metodo_pagamento varchar(255),
        operatore_richiesta varchar(50),
        operatore_verifica varchar(50),
        articoli longtext,
        rientro_report longtext,
        rientro_data datetime,
        totale_calcolato decimal(10,2),
        sconto_globale decimal(10,2) DEFAULT 0,
        note text,
        luogo_destinazione varchar(255),
        trasporto_mezzo varchar(255),
        cauzione varchar(255),
        causale_trasporto varchar(255),
        consenso_privacy tinyint(1) DEFAULT 0,
        consenso_condizioni tinyint(1) DEFAULT 0,
        firma_url varchar(255),
        firma_base64 longtext,
        firma_data datetime,
        op_preparazione_documento varchar(50),
        op_preparazione_materiale varchar(50),
        op_consegna_materiale varchar(50),
        op_rientro_materiale varchar(50),
        PRIMARY KEY (id)
    ) $charset;";

    // Ticket
    $table_ticket = $wpdb->prefix . 'bs_ticket';
    $sql_ticket = "CREATE TABLE $table_ticket (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        noleggio_id varchar(20) DEFAULT NULL,
        articolo_id mediumint(9) NOT NULL,
        qty int NOT NULL DEFAULT 1,
        tipo enum('mancante','non_rientrato','quantita_inferiore','danneggiato','problematico_utilizzabile') NOT NULL,
        note text,
        foto longtext,
        stato enum('aperto','chiuso') DEFAULT 'aperto',
        operatore varchar(50),
        origine varchar(20) DEFAULT 'manuale',
        creato_il datetime DEFAULT CURRENT_TIMESTAMP,
        chiuso_il datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY noleggio_id (noleggio_id),
        KEY articolo_id (articolo_id),
        KEY stato (stato)
    ) $charset;";

    // Profili sconti
    $table_profili_sconti = $wpdb->prefix . 'bs_profili_sconti';
    $sql_profili_sconti = "CREATE TABLE $table_profili_sconti (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nome varchar(100) NOT NULL UNIQUE,
        sconto_standard decimal(5,2) DEFAULT 0,
        sconto_fidato decimal(5,2) DEFAULT 0,
        sconto_premium decimal(5,2) DEFAULT 0,
        sconto_service decimal(5,2) DEFAULT 0,
        sconto_collaboratori decimal(5,2) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

// KIT (bundle)
    $table_kits = $wpdb->prefix . 'bsn_kits';
    $sql_kits = "CREATE TABLE $table_kits (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nome_kit varchar(150) NOT NULL,
        articolo_kit_id mediumint(9) NOT NULL,
        note text,
        data_creazione datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY articolo_kit_id (articolo_kit_id)
    ) $charset;";

    $table_kit_componenti = $wpdb->prefix . 'bsn_kit_componenti';
    $sql_kit_componenti = "CREATE TABLE $table_kit_componenti (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        kit_id mediumint(9) NOT NULL,
        articolo_id mediumint(9) NOT NULL,
        ruolo varchar(50) NOT NULL,
        qty int NOT NULL DEFAULT 1,
        is_default tinyint(1) NOT NULL DEFAULT 1,
        is_selectable tinyint(1) NOT NULL DEFAULT 1,
        gruppo_equivalenza varchar(80) NOT NULL,
        PRIMARY KEY (id),
        KEY kit_id (kit_id),
        KEY articolo_id (articolo_id),
        KEY gruppo_equivalenza (gruppo_equivalenza)
    ) $charset;";

    $table_noleggio_kits = $wpdb->prefix . 'bsn_noleggio_kits';
    $sql_noleggio_kits = "CREATE TABLE $table_noleggio_kits (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        noleggio_id varchar(20) NOT NULL,
        kit_id mediumint(9) NOT NULL,
        kit_label varchar(150),
        PRIMARY KEY (id),
        KEY noleggio_id (noleggio_id),
        KEY kit_id (kit_id)
    ) $charset;";

    $table_noleggio_kit_componenti = $wpdb->prefix . 'bsn_noleggio_kit_componenti';
    $sql_noleggio_kit_componenti = "CREATE TABLE $table_noleggio_kit_componenti (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        noleggio_kit_id bigint(20) unsigned NOT NULL,
        ruolo varchar(50) NOT NULL,
        gruppo_equivalenza varchar(80) NOT NULL,
        articolo_id_scelto mediumint(9) NOT NULL,
        qty int NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY noleggio_kit_id (noleggio_kit_id),
        KEY articolo_id_scelto (articolo_id_scelto),
        KEY gruppo_equivalenza (gruppo_equivalenza)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_clienti);
    dbDelta($sql_articoli);
    dbDelta($sql_noleggi);
    dbDelta($sql_ticket);
    dbDelta($sql_profili_sconti);
    dbDelta($sql_kits);
    dbDelta($sql_kit_componenti);
    dbDelta($sql_noleggio_kits);
    dbDelta($sql_noleggio_kit_componenti);
}

add_action('init', function() {
    global $wpdb;
    $table_profili_sconti = $wpdb->prefix . 'bs_profili_sconti';
    $exists = $wpdb->get_var( $wpdb->prepare('SHOW TABLES LIKE %s', $table_profili_sconti) );
    if ( $exists !== $table_profili_sconti ) {
        bsn_install_tables();
    }
    bsn_ensure_noleggi_columns();
    bsn_ensure_articoli_columns();
    bsn_ensure_ticket_table();
    bsn_ensure_preventivo_page();
});

// Attiva creazione tabelle all'attivazione plugin
register_activation_hook(__FILE__, 'bsn_install_tables');
register_activation_hook(__FILE__, 'bsn_ensure_preventivo_page');


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

function bsn_ensure_ticket_table() {
    global $wpdb;
    $table_ticket = $wpdb->prefix . 'bs_ticket';

    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_ticket ) );
    if ( $exists === $table_ticket ) {
        $col_origine = $wpdb->get_var(
            $wpdb->prepare( "SHOW COLUMNS FROM $table_ticket LIKE %s", 'origine' )
        );
        if ( ! $col_origine ) {
            $wpdb->query( "ALTER TABLE $table_ticket ADD origine varchar(20) DEFAULT 'manuale'" );
        }
        $col_foto = $wpdb->get_var(
            $wpdb->prepare( "SHOW COLUMNS FROM $table_ticket LIKE %s", 'foto' )
        );
        if ( ! $col_foto ) {
            $wpdb->query( "ALTER TABLE $table_ticket ADD foto longtext" );
        }
        return;
    }

    $charset = $wpdb->get_charset_collate();
    $sql_ticket = "CREATE TABLE $table_ticket (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        noleggio_id varchar(20) DEFAULT NULL,
        articolo_id mediumint(9) NOT NULL,
        qty int NOT NULL DEFAULT 1,
        tipo enum('mancante','non_rientrato','quantita_inferiore','danneggiato','problematico_utilizzabile') NOT NULL,
        note text,
        foto longtext,
        stato enum('aperto','chiuso') DEFAULT 'aperto',
        operatore varchar(50),
        origine varchar(20) DEFAULT 'manuale',
        creato_il datetime DEFAULT CURRENT_TIMESTAMP,
        chiuso_il datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY noleggio_id (noleggio_id),
        KEY articolo_id (articolo_id),
        KEY stato (stato)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_ticket );
}

function bsn_ensure_noleggi_columns() {
    global $wpdb;
    $table_noleggi = $wpdb->prefix . 'bs_noleggi';

    $col_stato = $wpdb->get_row( "SHOW COLUMNS FROM $table_noleggi LIKE 'stato'" );
    if ( $col_stato && isset( $col_stato->Type ) && strpos( $col_stato->Type, 'preventivo' ) === false ) {
        $wpdb->query( "ALTER TABLE $table_noleggi MODIFY stato enum('preventivo','bozza','attivo','chiuso','ritardo') DEFAULT 'bozza'" );
    }

    $col_report = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'rientro_report' )
    );
    if ( ! $col_report ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD rientro_report longtext" );
    }

    $col_data = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'rientro_data' )
    );
    if ( ! $col_data ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD rientro_data datetime" );
    }

    $col_consenso_privacy = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'consenso_privacy' )
    );
    if ( ! $col_consenso_privacy ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD consenso_privacy tinyint(1) DEFAULT 0" );
    }

    $col_consenso_condizioni = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'consenso_condizioni' )
    );
    if ( ! $col_consenso_condizioni ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD consenso_condizioni tinyint(1) DEFAULT 0" );
    }

    $col_firma_url = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'firma_url' )
    );
    if ( ! $col_firma_url ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD firma_url varchar(255)" );
    }

    $col_firma_base64 = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'firma_base64' )
    );
    if ( ! $col_firma_base64 ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD firma_base64 longtext" );
    }

    $col_firma_data = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'firma_data' )
    );
    if ( ! $col_firma_data ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD firma_data datetime" );
    }

    $col_op_doc = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'op_preparazione_documento' )
    );
    if ( ! $col_op_doc ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD op_preparazione_documento varchar(50)" );
    }

    $col_op_mat = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'op_preparazione_materiale' )
    );
    if ( ! $col_op_mat ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD op_preparazione_materiale varchar(50)" );
    }

    $col_op_consegna = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'op_consegna_materiale' )
    );
    if ( ! $col_op_consegna ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD op_consegna_materiale varchar(50)" );
    }

    $col_op_rientro = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'op_rientro_materiale' )
    );
    if ( ! $col_op_rientro ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD op_rientro_materiale varchar(50)" );
    }

    $col_metodo_pagamento = $wpdb->get_var(
        $wpdb->prepare( "SHOW COLUMNS FROM $table_noleggi LIKE %s", 'metodo_pagamento' )
    );
    if ( ! $col_metodo_pagamento ) {
        $wpdb->query( "ALTER TABLE $table_noleggi ADD metodo_pagamento varchar(255)" );
    }
}

function bsn_ensure_articoli_columns() {
    global $wpdb;
    $table_articoli = $wpdb->prefix . 'bs_articoli';

    $columns = [
        'external_product_url'  => "ALTER TABLE $table_articoli ADD external_product_url varchar(255)",
        'external_product_slug' => "ALTER TABLE $table_articoli ADD external_product_slug varchar(200)",
        'external_product_name' => "ALTER TABLE $table_articoli ADD external_product_name varchar(255)",
        'external_image_url'    => "ALTER TABLE $table_articoli ADD external_image_url varchar(255)",
        'external_last_sync'    => "ALTER TABLE $table_articoli ADD external_last_sync datetime",
        'qr_stampato'           => "ALTER TABLE $table_articoli ADD qr_stampato tinyint(1) NOT NULL DEFAULT 0",
        'qr_stampato_at'        => "ALTER TABLE $table_articoli ADD qr_stampato_at datetime NULL",
        'qr_stampato_by'        => "ALTER TABLE $table_articoli ADD qr_stampato_by bigint(20) unsigned NULL",
        'data_modifica'         => "ALTER TABLE $table_articoli ADD data_modifica datetime NULL",
    ];

    foreach ( $columns as $column => $sql ) {
        $exists = $wpdb->get_var(
            $wpdb->prepare( "SHOW COLUMNS FROM $table_articoli LIKE %s", $column )
        );
        if ( ! $exists ) {
            $wpdb->query( $sql );
        }
    }
}

function bsn_ensure_preventivo_page() {
    $page_id = get_option( 'bsn_preventivo_page_id' );
    if ( $page_id && get_post( $page_id ) ) {
        return;
    }

    $page = get_page_by_path( 'preventivo' );
    if ( $page ) {
        update_option( 'bsn_preventivo_page_id', $page->ID );
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $page_id = wp_insert_post(
        [
            'post_title'   => 'Preventivo',
            'post_name'    => 'preventivo',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[blackstar_preventivo]',
        ]
    );

    if ( $page_id && ! is_wp_error( $page_id ) ) {
        update_option( 'bsn_preventivo_page_id', $page_id );
    }
}

function bsn_get_preventivo_page_url() {
    $page_id = get_option( 'bsn_preventivo_page_id' );
    if ( $page_id ) {
        $url = get_permalink( $page_id );
        if ( $url ) {
            return $url;
        }
    }

    $page = get_page_by_path( 'preventivo' );
    if ( $page ) {
        update_option( 'bsn_preventivo_page_id', $page->ID );
        $url = get_permalink( $page->ID );
        if ( $url ) {
            return $url;
        }
    }

    return home_url( '/preventivo/' );
}


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
            'render_document_only' => false,
            'noleggio_id' => '',
        ]
    );

    // ===== RECUPERA ID NOLEGGIO =====
    $render_document_only = ! empty( $args['render_document_only'] );
    $noleggio_id = ! empty( $args['noleggio_id'] )
        ? sanitize_text_field( $args['noleggio_id'] )
        : ( isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '' );
    
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
    if ($noleggio['stato'] !== 'bozza' && ! $render_document_only) {
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
    
    $consenso_privacy = ! empty( $noleggio['consenso_privacy'] );
    $consenso_condizioni = ! empty( $noleggio['consenso_condizioni'] );
    $firma_base64_db = $noleggio['firma_base64'] ?? '';
    $firma_url_db = $noleggio['firma_url'] ?? '';
    $firma_src = '';

    if ( ! empty( $firma_base64_db ) ) {
        if ( strpos( $firma_base64_db, 'data:image' ) === 0 ) {
            $firma_src = $firma_base64_db;
        } else {
            $firma_src = 'data:image/png;base64,' . $firma_base64_db;
        }
    } elseif ( ! empty( $firma_url_db ) ) {
        $firma_src = $firma_url_db;
    }

    $firma_data_value = 'N/D';
    if ( ! empty( $noleggio['firma_data'] ) ) {
        $firma_timestamp = strtotime( $noleggio['firma_data'] );
        if ( $firma_timestamp ) {
            $firma_data_value = date_i18n( 'd/m/Y H:i', $firma_timestamp );
        } else {
            $firma_data_value = $noleggio['firma_data'];
        }
    }

    $op_documento = $noleggio['op_preparazione_documento'] ?? '';
    $op_materiale = $noleggio['op_preparazione_materiale'] ?? '';
    $op_consegna = $noleggio['op_consegna_materiale'] ?? '';
    $op_rientro = $noleggio['op_rientro_materiale'] ?? '';

    ?>

<?php if ( $render_document_only ) : ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page { margin: 14mm 12mm; }
                body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; }
            </style>
        </head>
        <body>
    <?php endif; ?>

    <?php if ( ! $render_document_only ) : ?>
    <div class="wrap bsn-finalizza-wrap">
        
        <!-- ===== HEADER AZIONI ===== -->
        <div class="bsn-finalizza-header" style="background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccc;">
            <a href="<?php echo esc_url( $args['back_url'] ); ?>" class="button">← Torna ai noleggi</a>
            <button id="bsn-salva-finalizza" class="button button-primary" style="float: right; margin-left: 10px;">💾 Salva e Attiva Noleggio</button>
            <button id="bsn-stampa-documento" class="button" style="float: right;">🖨️ Stampa/Scarica PDF</button>
            <div style="clear: both;"></div>
        </div>
    <?php endif; ?>

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
                            <td style="padding: 2px 0;"><strong>Metodo pagamento:</strong></td>
                            <td style="padding: 2px 0;"><?php echo esc_html($noleggio['metodo_pagamento'] ?? 'N/D'); ?></td>
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
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 8%;">Img</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 8%;">Codice</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 22%;">Prodotto</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 15%;">Correlati</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: right; width: 7%;">Prezzo</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 5%;">Q.tà</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 8%;">Fattore</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 7%;">Sconto</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: right; width: 8%;">Subtot.</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: right; width: 8%;">Val. Bene</th>
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
                            $external_name = $articolo_db['external_product_name'] ?? '';
                            $external_url = $articolo_db['external_product_url'] ?? '';
                            $external_image = $articolo_db['external_image_url'] ?? '';
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
                                <td style="border: 1px solid #ccc; padding: 3px; text-align:center;">
                                    <?php if ( ! empty( $external_image ) ) : ?>
                                        <?php if ( ! empty( $external_url ) ) : ?>
                                            <a href="<?php echo esc_url( $external_url ); ?>" target="_blank" rel="noopener noreferrer">
                                                <img src="<?php echo esc_url( $external_image ); ?>" alt="" style="width:50px; height:auto;">
                                            </a>
                                        <?php else : ?>
                                            <img src="<?php echo esc_url( $external_image ); ?>" alt="" style="width:50px; height:auto;">
                                        <?php endif; ?>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="border: 1px solid #ccc; padding: 3px;"><?php echo esc_html($codice); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px;">
                                    <?php
                                    $nome_prodotto = ! empty( $external_name ) ? $external_name : $nome;
                                    if ( ! empty( $external_url ) ) :
                                    ?>
                                        <a href="<?php echo esc_url( $external_url ); ?>" target="_blank" rel="noopener noreferrer" style="color:#000; text-decoration:underline;">
                                            <?php echo esc_html( $nome_prodotto ); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html( $nome_prodotto ); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="border: 1px solid #ccc; padding: 3px; font-size: 8px;"><?php echo esc_html($correlati_testo); ?></td>
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
            
            <?php echo bsn_render_noleggio_kits_block( $noleggio_id ); ?>

            <!-- ===== CONSENSI E RESPONSABILITÀ ===== -->
            <div class="bsn-doc-section" style="margin-bottom: 15px; border: 2px solid #000; padding: 12px; background: #fffbf0;">
                <h3 style="margin-top: 0; font-size: 12px; margin-bottom: 8px;">CONSENSI E RESPONSABILITÀ</h3>
                
                <?php if ( $render_document_only ) : ?>
                    <div style="margin-bottom: 10px; font-size: 11px;">
                        <?php echo $consenso_privacy ? '[X]' : '[ ]'; ?>
                        Dichiaro di aver letto e accettato l'
                        <strong>Informativa Privacy</strong>
                    </div>
                    <div style="margin-bottom: 10px; font-size: 11px;">
                        <?php echo $consenso_condizioni ? '[X]' : '[ ]'; ?>
                        Dichiaro di aver preso visione delle
                        <strong>Modalità di Noleggio e Responsabilità</strong>
                    </div>
                <?php else : ?>
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
                <?php endif; ?>
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
                    <?php if ( $render_document_only ) : ?>
                        <div style="font-size: 10px; padding: 4px 0;">
                            <?php echo esc_html( $firma_data_value ); ?>
                        </div>
                    <?php else : ?>
                        <input type="text" id="bsn-data-firma"
                               value="<?php echo date_i18n( 'd/m/Y H:i', $firma_ts ); ?>"
                               readonly 
                               style="width: 180px; padding: 4px; background: #f9f9f9; border: 1px solid #ccc; font-size: 10px;">
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 10px;">
                    <label style="font-size: 10px; display: block; margin-bottom: 5px;">
                        <strong>Firma (utilizzare touchscreen o mouse):</strong>
                    </label>
                    <?php if ( $render_document_only ) : ?>
                        <div style="border: 2px solid #000; background: #fff; display: inline-block; width: 500px; height: 150px; text-align: center;">
                            <?php if ( ! empty( $firma_src ) ) : ?>
                                <img src="<?php echo esc_attr( $firma_src ); ?>" alt="Firma cliente" style="max-width: 500px; max-height: 150px;">
                            <?php else : ?>
                                <div style="font-size: 10px; color: #666; line-height: 150px;">Firma non disponibile</div>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <div style="border: 2px solid #000; background: #fff; display: inline-block;">
                            <canvas id="bsn-canvas-firma" width="500" height="150" style="display: block; cursor: crosshair;"></canvas>
                        </div>
                        <br>
                        <button type="button" id="bsn-cancella-firma" class="button" style="margin-top: 5px; font-size: 11px;">🗑️ Cancella Firma</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== OPERATORI ===== -->
            <div class="bsn-doc-section" style="margin-bottom: 15px; border: 1px solid #000; padding: 12px; background: #f9f9f9;">
                <h3 style="margin-top: 0; font-size: 12px; margin-bottom: 8px;">OPERATORI RESPONSABILI</h3>
                
                <?php if ( $render_document_only ) : ?>
                    <table style="width: 100%; font-size: 10px;">
                        <tr>
                            <td style="width: 38%; padding: 3px 0;"><strong>Op. Prep. Documento:</strong></td>
                            <td style="padding: 3px 0;"><?php echo esc_html( $op_documento !== '' ? $op_documento : '-' ); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 0;"><strong>Op. Prep. Materiale:</strong></td>
                            <td style="padding: 3px 0;"><?php echo esc_html( $op_materiale !== '' ? $op_materiale : '-' ); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 0;"><strong>Op. Consegna Materiale:</strong></td>
                            <td style="padding: 3px 0;"><?php echo esc_html( $op_consegna !== '' ? $op_consegna : '-' ); ?></td>
                        </tr>
                        <?php if ( $op_rientro !== '' ) : ?>
                            <tr>
                                <td style="padding: 3px 0;"><strong>Op. Rientro Materiale:</strong></td>
                                <td style="padding: 3px 0;"><?php echo esc_html( $op_rientro ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                <?php else : ?>
                    <table style="width: 100%; font-size: 10px;">
                        <tr>
                            <td style="width: 38%; padding: 3px 0;"><strong>Op. Prep. Documento:</strong></td>
                            <td style="padding: 3px 0;">
                                <input type="text" id="bsn-op-documento"
                                       value="<?php echo esc_attr( $op_documento ); ?>"
                                       style="width: 100%; padding: 4px; border: 1px solid #ccc; font-size: 10px;"
                                       placeholder="Nome operatore">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 0;"><strong>Op. Prep. Materiale:</strong></td>
                            <td style="padding: 3px 0;">
                                <input type="text" id="bsn-op-materiale"
                                       value="<?php echo esc_attr( $op_materiale ); ?>"
                                       style="width: 100%; padding: 4px; border: 1px solid #ccc; font-size: 10px;"
                                       placeholder="Nome operatore">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 0;"><strong>Op. Consegna Materiale:</strong></td>
                            <td style="padding: 3px 0;">
                                <input type="text" id="bsn-op-consegna"
                                       value="<?php echo esc_attr( $op_consegna ); ?>"
                                       style="width: 100%; padding: 4px; border: 1px solid #ccc; font-size: 10px;"
                                       placeholder="Nome operatore">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 0;"><strong>Op. Rientro Materiale:</strong></td>
                            <td style="padding: 3px 0;">
                                <input type="text" id="bsn-op-rientro"
                                       value="<?php echo esc_attr( $op_rientro ); ?>"
                                       style="width: 100%; padding: 4px; border: 1px solid #ccc; font-size: 10px;"
                                       placeholder="Compilabile dopo">
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>
            
        </div><!-- fine documento -->

    <?php if ( ! $render_document_only ) : ?>
        <!-- ===== FOOTER AZIONI RIPETUTO ===== -->
        <div class="bsn-finalizza-footer" style="background: #fff; padding: 15px; margin-top: 20px; border: 1px solid #ccc; text-align: center;">
            <button id="bsn-salva-finalizza-2" class="button button-primary button-hero">💾 Salva e Attiva Noleggio</button>
            <button id="bsn-stampa-documento-2" class="button button-hero" style="margin-left: 10px;">🖨️ Stampa/Scarica PDF</button>
        </div>
        
    </div><!-- fine wrap -->
    <?php endif; ?>

    <?php if ( $render_document_only ) : ?>
        </body>
        </html>
    <?php endif; ?>
    
    <?php if ( ! $render_document_only ) : ?>
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
    <?php endif; ?>

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

            <?php echo bsn_render_noleggio_kits_block( $noleggio_id ); ?>
            
            <div class="bsn-doc-section" style="margin-bottom: 15px;">
                <h3>Operatori</h3>
                <table style="width: 100%; font-size: 10px; line-height: 1.3;">
                    <tr>
                        <td style="width: 35%; padding: 2px 0;"><strong>Preparazione documento:</strong></td>
                        <td style="padding: 2px 0;"><?php echo esc_html( ( $noleggio['op_preparazione_documento'] ?? '' ) !== '' ? $noleggio['op_preparazione_documento'] : '-' ); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;"><strong>Preparazione materiale:</strong></td>
                        <td style="padding: 2px 0;"><?php echo esc_html( ( $noleggio['op_preparazione_materiale'] ?? '' ) !== '' ? $noleggio['op_preparazione_materiale'] : '-' ); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;"><strong>Consegna materiale:</strong></td>
                        <td style="padding: 2px 0;"><?php echo esc_html( ( $noleggio['op_consegna_materiale'] ?? '' ) !== '' ? $noleggio['op_consegna_materiale'] : '-' ); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;"><strong>Rientro materiale:</strong></td>
                        <td style="padding: 2px 0;"><?php echo esc_html( ( $noleggio['op_rientro_materiale'] ?? '' ) !== '' ? $noleggio['op_rientro_materiale'] : '-' ); ?></td>
                    </tr>
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

function bsn_render_preventivo_noleggio_page( $args = [] ) {
    global $wpdb;

    $args = wp_parse_args(
        $args,
        [
            'back_url'            => home_url( '/app-noleggi/' ),
            'noleggio_id'         => '',
            'render_document_only' => false,
        ]
    );

    $render_document_only = ! empty( $args['render_document_only'] );
    $noleggio_id = ! empty( $args['noleggio_id'] )
        ? sanitize_text_field( $args['noleggio_id'] )
        : ( isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '' );

    if ( empty( $noleggio_id ) ) {
        echo '<div class="wrap"><h1>Errore</h1><p>ID preventivo mancante.</p></div>';
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
        echo '<div class="wrap"><h1>Errore</h1><p>Preventivo non trovato.</p></div>';
        return;
    }

    if ( ! $render_document_only && $noleggio['stato'] !== 'preventivo' ) {
        echo '<div class="wrap"><h1>Attenzione</h1><p>Questo documento non è in stato preventivo.</p>';
        echo '<p><a href="' . esc_url( $args['back_url'] ) . '" class="button">← Torna ai noleggi</a></p></div>';
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

    $giorni = 1;
    if ( $data_inizio_raw && $data_fine_raw ) {
        $ts_da = strtotime( $data_inizio_raw );
        $ts_a = strtotime( $data_fine_raw );
        if ( $ts_da && $ts_a && $ts_a > $ts_da ) {
            $giorni = max( 1, (int) ceil( ( $ts_a - $ts_da ) / 86400 ) );
        }
    }
    $fattore_giorni_scalare = sqrt( max( 1, (float) $giorni ) );

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

    $categoria = $cliente['categoria_cliente'] ?? 'standard';
    $regime = (float) ( $cliente['regime_percentuale'] ?? 22.00 );
    $subtotale = 0.0;

    if ( $render_document_only ) :
    ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page { margin: 14mm 12mm; }
                body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; }
            </style>
        </head>
        <body>
    <?php endif; ?>

    <?php if ( ! $render_document_only ) : ?>
    <div class="wrap bsn-preventivo-wrap">
        <div class="bsn-preventivo-header" style="background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccc;">
            <a href="<?php echo esc_url( $args['back_url'] ); ?>" class="button">← Torna ai noleggi</a>
            <div style="clear: both;"></div>
        </div>
    <?php endif; ?>

        <div id="bsn-documento-preventivo" style="background: #fff; padding: 30px; max-width: 21cm; margin: 0 auto; border: 1px solid #ddd;">
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
                Documento di preventivo - solo proposta commerciale
            </div>

            <div style="border-bottom: 2px solid #000; margin-bottom: 8px;"></div>

            <div style="text-align: right; margin-bottom: 15px;">
                <strong style="font-size: 15px;">PREVENTIVO N° <?php echo esc_html( $noleggio_id ); ?></strong><br>
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
                    <h3 style="margin: 0 0 8px 0; font-size: 12px; background: #000; color: #fff; padding: 3px 8px; margin: -10px -10px 8px -10px;">DATI PREVENTIVO</h3>
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
                <h3 style="font-size: 12px; background: #000; color: #fff; padding: 3px 8px; margin-bottom: 8px;">ARTICOLI PREVENTIVO</h3>

                <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
                    <thead>
                        <tr style="background: #f0f0f0;">
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 8%;">Img</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 8%;">Codice</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 22%;">Prodotto</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: left; width: 15%;">Correlati</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: right; width: 7%;">Prezzo</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 5%;">Q.tà</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 8%;">Fattore</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 7%;">Sconto</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: right; width: 8%;">Subtot.</th>
                            <th style="border: 1px solid #000; padding: 3px; text-align: right; width: 8%;">Val. Bene</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ( $articoli as $art ) {
                            $qty = isset( $art['qty'] ) ? (int) $art['qty'] : 1;
                            $articolo_id = isset( $art['id'] ) ? (int) $art['id'] : 0;
                            $prezzo_custom = isset( $art['prezzo'] ) ? (float) $art['prezzo'] : null;

                            if ( ! isset( $mappa_articoli[ $articolo_id ] ) ) {
                                continue;
                            }

                            $articolo_db = $mappa_articoli[ $articolo_id ];

                            $codice = $articolo_db['codice'] ?? 'N/D';
                            $nome = $articolo_db['nome'] ?? 'Articolo #' . $articolo_id;
                            $prezzo_base = (float) ( $articolo_db['prezzo_giorno'] ?? 0 );
                            $valore_bene = (float) ( $articolo_db['valore_bene'] ?? 0 );
                            $external_name = $articolo_db['external_product_name'] ?? '';
                            $external_url = $articolo_db['external_product_url'] ?? '';
                            $external_image = $articolo_db['external_image_url'] ?? '';
                            $noleggio_scalare = (int) ( $articolo_db['noleggio_scalare'] ?? 0 );

                            $prezzo = $prezzo_custom !== null ? $prezzo_custom : $prezzo_base;

                            $sconto = 0;
                            switch ( $categoria ) {
                                case 'fidato':
                                    $sconto = (float) ( $articolo_db['sconto_fidato'] ?? 0 );
                                    break;
                                case 'premium':
                                    $sconto = (float) ( $articolo_db['sconto_premium'] ?? 0 );
                                    break;
                                case 'service':
                                    $sconto = (float) ( $articolo_db['sconto_service'] ?? 0 );
                                    break;
                                case 'collaboratori':
                                    $sconto = (float) ( $articolo_db['sconto_collaboratori'] ?? 0 );
                                    break;
                                default:
                                    $sconto = (float) ( $articolo_db['sconto_standard'] ?? 0 );
                                    break;
                            }

                            $prezzo_netto = $prezzo * ( 1 - ( $sconto / 100 ) );

                            if ( $noleggio_scalare === 1 ) {
                                $fattore_display = '√' . $giorni;
                                $subtotale_riga = $prezzo_netto * $qty * $fattore_giorni_scalare;
                            } else {
                                $fattore_display = 'Lineare';
                                $subtotale_riga = $prezzo_netto * $qty;
                            }

                            $subtotale += $subtotale_riga;

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
                                <td style="border: 1px solid #ccc; padding: 3px; text-align:center;">
                                    <?php if ( ! empty( $external_image ) ) : ?>
                                        <?php if ( ! empty( $external_url ) ) : ?>
                                            <a href="<?php echo esc_url( $external_url ); ?>" target="_blank" rel="noopener noreferrer">
                                                <img src="<?php echo esc_url( $external_image ); ?>" alt="" style="width:50px; height:auto;">
                                            </a>
                                        <?php else : ?>
                                            <img src="<?php echo esc_url( $external_image ); ?>" alt="" style="width:50px; height:auto;">
                                        <?php endif; ?>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="border: 1px solid #ccc; padding: 3px;"><?php echo esc_html( $codice ); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px;">
                                    <?php
                                    $nome_prodotto = ! empty( $external_name ) ? $external_name : $nome;
                                    if ( ! empty( $external_url ) ) :
                                    ?>
                                        <a href="<?php echo esc_url( $external_url ); ?>" target="_blank" rel="noopener noreferrer" style="color:#000; text-decoration:underline;">
                                            <?php echo esc_html( $nome_prodotto ); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html( $nome_prodotto ); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="border: 1px solid #ccc; padding: 3px; font-size: 8px;"><?php echo esc_html( $correlati_testo ); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: right;">€ <?php echo number_format( $prezzo, 2, ',', '.' ); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: center;"><?php echo $qty; ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: center; font-size: 8px;"><?php echo esc_html( $fattore_display ); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: center;">-<?php echo number_format( $sconto, 0 ); ?>%</td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: right;">€ <?php echo number_format( $subtotale_riga, 2, ',', '.' ); ?></td>
                                <td style="border: 1px solid #ccc; padding: 3px; text-align: right; color: #666;">€ <?php echo number_format( $valore_bene, 2, ',', '.' ); ?></td>
                            </tr>
                            <?php
                        }

                        $importo_regime = $subtotale * ( $regime / 100 );
                        $totale_noleggio = $subtotale + $importo_regime;
                        $totale_finale = (float) ( $noleggio['totale_calcolato'] ?? 0 );
                        $sconto_globale = $totale_finale - $totale_noleggio;
                        ?>
                    </tbody>
                </table>

                <div style="margin-top: 10px; text-align: right; font-size: 11px; line-height: 1.6;">
                    <div><strong>Subtotale:</strong> € <?php echo number_format( $subtotale, 2, ',', '.' ); ?></div>
                    <div><strong>Regime (<?php echo number_format( $regime, 2, ',', '.' ); ?>%):</strong> € <?php echo number_format( $importo_regime, 2, ',', '.' ); ?></div>
                    <div><strong>Totale noleggio:</strong> € <?php echo number_format( $totale_noleggio, 2, ',', '.' ); ?></div>
                    <?php if ( abs( $sconto_globale ) > 0.01 ) : ?>
                    <div><strong>Sconto globale:</strong> € <?php echo number_format( $sconto_globale, 2, ',', '.' ); ?></div>
                    <?php endif; ?>
                    <div style="font-size: 14px; background: #f9f9f9; padding: 8px; display: inline-block; border: 2px solid #000; margin-top: 5px;">
                        <strong>TOTALE FINALE:</strong> € <?php echo number_format( $totale_finale, 2, ',', '.' ); ?>
                    </div>
                </div>
            </div>

            <?php echo bsn_render_noleggio_kits_block( $noleggio_id ); ?>
        </div>

        <?php if ( ! $render_document_only ) : ?>
        <div class="bsn-preventivo-footer" style="background: #fff; padding: 15px; margin-top: 20px; border: 1px solid #ccc; text-align: center;">
            <a href="<?php echo esc_url( rest_url( 'bsn/v1/noleggi/preventivo/pdf' ) . '?id=' . rawurlencode( $noleggio_id ) . '&_wpnonce=' . rawurlencode( wp_create_nonce( 'wp_rest' ) ) ); ?>" class="button" target="_blank" rel="noopener noreferrer">⬇️ Scarica PDF</a>
            <button id="bsn-invia-preventivo" class="button button-primary">✉️ Invia PDF al cliente</button>
        </div>
        <?php endif; ?>

    <?php if ( $render_document_only ) : ?>
        </body>
        </html>
    <?php else : ?>
        <script>
            if (typeof BSN_API === 'undefined') {
            var BSN_API = {
                root: '<?php echo esc_js( rest_url( 'bsn/v1/' ) ); ?>',
                nonce: '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
            };
        }
        jQuery(document).ready(function($) {
            $('#bsn-invia-preventivo').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Invio in corso...');
                $.ajax({
                    url: BSN_API.root + 'noleggi/preventivo/invia',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', BSN_API.nonce);
                    },
                    data: { id: '<?php echo esc_js( $noleggio_id ); ?>' },
                    success: function() {
                        alert('✅ Preventivo inviato correttamente.');
                    },
                    error: function(err) {
                        console.error(err);
                        var msg = 'Errore durante l\'invio del preventivo.';
                        if (err && err.responseJSON && err.responseJSON.message) {
                            msg += '\n' + err.responseJSON.message;
                        }
                        alert(msg);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('✉️ Invia PDF al cliente');
                    }
                });
            });
        });
        </script>
        </div>
    <?php endif; ?>
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

    // Aggiorna eventuale stato "ritardo" in base alla data fine
    $oggi = current_time( 'Y-m-d' );
    if ( ! empty( $n->data_fine ) ) {
        $data_fine_check = date( 'Y-m-d', strtotime( $n->data_fine ) );
        if ( $data_fine_check < $oggi && $n->stato === 'attivo' ) {
            $wpdb->update(
                $table_noleggi,
                [ 'stato' => 'ritardo' ],
                [ 'id' => $id_noleggio ],
                [ '%s' ],
                [ '%s' ]
            );
            $n->stato = 'ritardo';
        }
    }

    if ( $n->stato !== 'attivo' && $n->stato !== 'ritardo' ) {
        echo '<h1>Rientro noleggio #' . esc_html( $id_noleggio ) . '</h1>';
        echo '<p>Questo noleggio non è in stato attivo o ritardo.</p>';
        echo '<p><a href="' . esc_url( home_url( '/app-noleggi/' ) ) . '" class="button">← Torna ai noleggi</a></p>';
        echo '</div>';
        return;
    }

    // Date grezze per visualizzazione
    $data_inizio_iso = '';
    $data_fine_iso   = '';

    if ( ! empty( $n->data_inizio ) ) {
        $ts = strtotime( $n->data_inizio );
        if ( $ts ) {
            $data_inizio_iso = date( 'd/m/Y', $ts );
        }
    }
    if ( ! empty( $n->data_fine ) ) {
        $ts = strtotime( $n->data_fine );
        if ( $ts ) {
            $data_fine_iso = date( 'd/m/Y', $ts );
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

    // Recupera dati base articoli (nome, codice, ubicazione, correlati)
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

    $current_user  = wp_get_current_user();
    $op_default    = $current_user ? $current_user->user_login : '';

    echo '<h1>Rientro noleggio #' . esc_html( $id_noleggio ) . '</h1>';

    // Blocco info noleggio
    echo '<h2>Dati noleggio</h2>';
    echo '<p><strong>Cliente:</strong> ' . esc_html( $n->cliente_nome ) . '</p>';
    echo '<p><strong>Periodo:</strong> ' . esc_html( $data_inizio_iso ) . ' → ' . esc_html( $data_fine_iso ) . '</p>';
    if ( $n->stato === 'ritardo' ) {
        echo '<p style="color:#b00;"><strong>Stato:</strong> In ritardo (rientro tardivo)</p>';
    } else {
        echo '<p><strong>Stato:</strong> ' . esc_html( $n->stato ) . '</p>';
    }
    echo '<p><strong>Note:</strong> ' . nl2br( esc_html( $n->note ) ) . '</p>';

    // Campo operatore rientro
    echo '<h2>Rientro materiali</h2>';
    echo '<p>';
    echo '<label for="bsn-op-rientro">Operatore rientro materiali:</label> ';
    echo '<input type="text" id="bsn-op-rientro" value="' . esc_attr( $op_default ) . '" style="width: 250px;">';
    echo '</p>';

    // Tabella articoli
    echo '<table class="widefat striped bsn-tabella" id="bsn-tabella-rientro-articoli">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Articolo</th>';
    echo '<th>Qty noleggiata</th>';
    echo '<th>Qty rientrata</th>';
    echo '<th>Esito rientro</th>';
    echo '<th>Note</th>';
    echo '<th>Correlati</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    if ( empty( $articoli ) ) {
        echo '<tr><td colspan="6">Nessun articolo associato a questo noleggio.</td></tr>';
    } else {
        foreach ( $articoli as $riga ) {
            $art_id = isset( $riga['id'] ) ? intval( $riga['id'] ) : 0;
            $qty    = isset( $riga['qty'] ) ? intval( $riga['qty'] ) : 0;

            $nome      = '';
            $codice    = '';
            $correlati = '';
            if ( isset( $mappa_articoli[ $art_id ] ) ) {
                $nome   = $mappa_articoli[ $art_id ]['nome'];
                $codice = $mappa_articoli[ $art_id ]['codice'];
            }
            $correlati_raw = '';
            if ( isset( $mappa_articoli[ $art_id ]['correlati'] ) ) {
                $correlati_raw = $mappa_articoli[ $art_id ]['correlati'];
            } elseif ( isset( $riga['correlati'] ) && is_array( $riga['correlati'] ) ) {
                $correlati_raw = wp_json_encode( $riga['correlati'] );
            }

            if ( $correlati_raw ) {
                $decoded_corr = json_decode( $correlati_raw, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_corr ) && ! empty( $decoded_corr ) ) {
                    $corr_labels = array_map(
                        function( $c ) use ( $qty ) {
                            $nome_corr = isset( $c['nome'] ) ? $c['nome'] : '';
                            $qty_corr  = isset( $c['qty'] ) ? intval( $c['qty'] ) : 1;
                            return ( $qty_corr * $qty ) . 'x ' . $nome_corr;
                        },
                        $decoded_corr
                    );
                    $correlati = implode( ', ', $corr_labels );
                }
            }

            echo '<tr data-articolo-id="' . esc_attr( $art_id ) . '">';
            echo '<td>';
            echo esc_html( $nome );
            if ( $codice ) {
                echo '<br><small>Codice: ' . esc_html( $codice ) . '</small>';
            }
            echo '</td>';
            echo '<td>' . esc_html( $qty ) . '</td>';

            // Qty rientrata
            echo '<td style="text-align:center;">';
            echo '<input type="number" class="bsn-qty-rientrata" min="0" max="' . esc_attr( $qty ) . '" value="' . esc_attr( $qty ) . '" style="width:70px;">';
            echo '</td>';

            // Select esito rientro
            echo '<td>';
            echo '<select class="bsn-stato-rientro">';
            echo '<option value="ok">OK</option>';
            echo '<option value="ticket">Ticket (anomalia)</option>';
            echo '</select>';
            echo '</td>';

            // Note
            echo '<td>';
            echo '<input type="text" class="bsn-note-rientro" style="width: 100%;">';
            echo '</td>';

            echo '<td>' . esc_html( $correlati ? $correlati : '-' ) . '</td>';

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
            let validRows = 0;
            let hasError = false;

            rows.forEach(function(row){
                const articoloId = row.getAttribute('data-articolo-id');
                const qtyCell = row.querySelector('td:nth-child(2)');
                const qty = qtyCell ? parseInt(qtyCell.textContent.trim(), 10) || 0 : 0;
                const qtyRientroInput = row.querySelector('.bsn-qty-rientrata');
                const selStato = row.querySelector('.bsn-stato-rientro');
                const inpNote = row.querySelector('.bsn-note-rientro');

                const qtyRientrata = qtyRientroInput ? parseInt(qtyRientroInput.value, 10) || 0 : 0;
                const statoRientro = selStato ? selStato.value : 'ok';
                const note = inpNote ? inpNote.value.trim() : '';

                if (qtyRientrata < 0 || qtyRientrata > qty) {
                    alert('La quantità rientrata deve essere tra 0 e ' + qty + '.');
                    hasError = true;
                    return;
                }

                if (qtyRientrata < qty && statoRientro === 'ok') {
                    alert('Se la quantità rientrata è inferiore, seleziona "Ticket (anomalia)".');
                    hasError = true;
                    return;
                }

                if (statoRientro === 'ticket' && !note) {
                    alert('Inserisci una nota per ogni articolo con Ticket.');
                    hasError = true;
                    return;
                }

                articoli.push({
                    articolo_id: articoloId,
                    qty: qty,
                    qty_rientrata: qtyRientrata,
                    stato_rientro: statoRientro,
                    note: note
                });

                validRows++;
            });

            if (hasError) {
                return;
            }
            if (!validRows) {
                alert('Nessun articolo valido da salvare.');
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
                if (data && data.ticket_count && parseInt(data.ticket_count, 10) > 0) {
                    alert('Rientro salvato e ticket creati.');
                } else {
                    alert('Rientro salvato e noleggio chiuso.');
                }
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
        return "\n\nAccesso negato. Permesso riservato agli operatori autorizzati.\n\n";
    }

$current_user = wp_get_current_user();
    $user_label = ( $current_user && $current_user->exists() ) ? $current_user->display_name : '';

    ob_start();
    ?>
    <div id="bsn-app" style="max-width:1200px; margin:20px auto;">
        <div class="bsn-app-header">
            <h2>Black Star Rental Management</h2>
            <?php if ( ! empty( $user_label ) ) : ?>
                <span class="bsn-user-badge">👤 <?php echo esc_html( $user_label ); ?></span>
            <?php endif; ?>
        </div>

        <div id="bsn-tabs">
            <button class="tab-btn active" data-tab="clienti">Clienti</button>
            <button class="tab-btn" data-tab="articoli">Articoli</button>
            <button class="tab-btn" data-tab="noleggi">Noleggi</button>
            <button class="tab-btn" data-tab="calendario">Calendario</button>
            <button class="tab-btn" data-tab="ticket">Ticket</button>
            <button class="tab-btn" data-tab="etichette">Etichette</button>
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

                <div class="bsn-list-toolbar" style="margin-top:20px;">
                    <input type="text" id="bsn-clienti-search" placeholder="Cerca nei clienti..." style="width:100%; max-width:360px; padding:5px;">
                    <label for="bsn-clienti-limit">Mostra:</label>
                    <select id="bsn-clienti-limit">
                        <option value="12" selected>12</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
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

                     <!-- Flag scalare + Profili Sconti sulla stessa riga -->
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; margin:10px 0;">
                        <!-- Checkbox noleggio scalare -->
                        <label style="margin:0; flex:0 0 auto;">
                            <input type="checkbox" name="noleggio_scalare" value="1" style="margin-right:5px;">
                            Noleggio scalare (usa √giorni invece del moltiplicatore lineare)
                        </label>

                        <!-- Profili Sconti -->
                        <div id="bsn-profili-sconti-box" style="display:flex; align-items:center; gap:8px; flex:1 1 auto; justify-content:flex-end; flex-wrap:wrap;">
                            <label for="bsn-profili-sconti-dropdown" style="margin:0; white-space:nowrap; font-weight:bold;">Profili Sconti:</label>
                            <select id="bsn-profili-sconti-dropdown" style="max-width:180px; padding:4px;"></select>
                            <button type="button" id="bsn-salva-profilo-btn" class="btn btn-primary" style="padding:4px 10px; font-size:13px; white-space:nowrap;">💾 Salva Profilo</button>
                            <button type="button" id="bsn-elimina-profilo-btn" class="btn" style="padding:4px 10px; font-size:13px; background:#dc3545; color:white; white-space:nowrap;">🗑 Elimina</button>
                            <span id="bsn-profili-sconti-messaggio" style="display:none; font-size:12px; color:green; margin-left:8px;"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Ubicazione magazzino (scaffale/colonna/ripiano)</label>
                        <input type="text" name="ubicazione">
                    </div>
                    <div class="form-group">
                        <label>Link prodotto pubblico (.com)</label>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <input type="url" name="external_product_url" id="bsn-articolo-external-url" placeholder="https://blackstarservice.com/prodotto/nome-prodotto/" style="flex:1 1 320px;">
                            <button type="button" class="btn btn-secondary" id="bsn-articolo-sync">Recupera dati prodotto</button>
                        </div>
                        <small style="display:block; color:#666; margin-top:4px;">
                            Incolla l'URL del prodotto pubblico su blackstarservice.com e recupera nome + immagine.
                        </small>
                        <div id="bsn-articolo-external-preview" style="margin-top:6px; font-size:12px; color:#333;"></div>
                        <input type="hidden" name="external_product_slug" id="bsn-articolo-external-slug">
                        <input type="hidden" name="external_product_name" id="bsn-articolo-external-name">
                        <input type="hidden" name="external_image_url" id="bsn-articolo-external-image">
                        <input type="hidden" name="external_last_sync" id="bsn-articolo-external-sync">
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

                    <!-- Pulsante salvataggio articolo -->
                    <button type="submit" class="btn btn-primary">Salva Articolo</button>

                    <!-- Campo ricerca articoli (sotto Salva, prima della lista) -->
                     <div class="bsn-list-toolbar" style="margin-top:10px;">
                        <input type="text"
                               id="bsn-articoli-search"
                               placeholder="Cerca negli articoli (ID, nome, codice, note, ubicazione)..."
                               style="width:100%; max-width:380px; padding:5px;">
                        <label for="bsn-articoli-limit">Mostra:</label>
                        <select id="bsn-articoli-limit">
                            <option value="12" selected>12</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                </form>

                <!-- Lista articoli -->
                <div id="bsn-lista-articoli" style="margin-top:20px;"></div>

                <!-- KIT -->
                <div id="bsn-kit-section" style="margin-top:30px; padding:15px; border:1px solid #e0e0e0; background:#f9fafb; border-radius:8px;">
                    <h3 style="margin-top:0;">Crea Kit</h3>
                    <form id="bsn-form-kit">
                        <input type="hidden" name="kit_id" id="bsn-kit-id" value="">
                        <p style="margin-top:0; font-size:12px; color:#555;">
                            Il Kit è un articolo “contenitore” con prezzo fisso. L’articolo KIT è l’articolo base
                            che cerchi nel noleggio, mentre i componenti sono gli articoli reali che lo compongono.
                        </p>
                        <div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:10px;">
                            <div style="flex:2 1 240px;">
                                <label>Nome kit</label>
                                <input type="text" name="nome_kit" required style="width:100%;">
                                <small style="display:block; color:#666; margin-top:4px;">
                                    Etichetta del kit (es. “Kit Top Pro”) visualizzata nelle schermate di noleggio.
                                </small>
                            </div>
                            <div style="flex:2 1 240px;">
                                <label>Articolo KIT (articolo base con prezzo)</label>
                                <select id="bsn-kit-articolo-id" name="articolo_kit_id" required style="width:100%;"></select>
                                <small style="display:block; color:#666; margin-top:4px;">
                                    Questo è l’articolo “contenitore” con prezzo fisso. Le sue note restano le note
                                    dell’articolo, mentre le note del kit sotto sono note specifiche del kit.
                                </small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Note</label>
                            <textarea name="note" rows="2" style="width:100%;"></textarea>
                            <small style="display:block; color:#666; margin-top:4px;">
                                Note interne del kit (diverse dalle note dell’articolo base).
                            </small>
                        </div>

                        <div>
                            <label>Componenti del kit</label>
                            <small style="display:block; color:#666; margin:4px 0 8px;">
                                <strong>Ruolo</strong> = funzione del componente (es. TOP, SUB, CDJ).<br>
                                <strong>Gruppo equivalenza</strong> = famiglia/modello per limitare le sostituzioni
                                (es. TOP_PRO, SUB_PRO). Solo articoli con lo stesso gruppo saranno selezionabili
                                in sostituzione.<br>
                                <strong>Default</strong> = componente precompilato quando aggiungi il kit.<br>
                                <strong>Selezionabile</strong> = permette la sostituzione tramite dropdown; se disattivo
                                rimane fisso.
                            </small>
                            <div id="bsn-kit-componenti-wrapper"></div>
                            <button type="button" class="btn btn-secondary" id="bsn-kit-componente-add">+ Aggiungi componente</button>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top:10px;">Salva Kit</button>
                    </form>

                    <div id="bsn-lista-kits" style="margin-top:20px;"></div>
                </div>
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



                <!-- ETICHETTE -->
            <div id="etichette" class="tab-content">
                <h2>Etichette</h2>
                <div class="bsn-list-toolbar" style="margin-bottom:10px;">
                    <input type="text" id="bsn-etichette-search" placeholder="Cerca articolo per ID, nome o codice..." style="width:100%; max-width:360px; padding:5px;">
                    <label for="bsn-etichette-limit">Mostra:</label>
                    <select id="bsn-etichette-limit">
                        <option value="12" selected>12</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <label style="display:flex; align-items:center; gap:4px;">
                        <input type="checkbox" id="bsn-etichette-only-unprinted" value="1"> Solo NON stampati
                    </label>
                    <button type="button" class="btn btn-secondary" id="bsn-etichette-export">Scarica CSV (selezionati)</button>
                    <button type="button" class="btn btn-secondary" id="bsn-etichette-mark-printed">Segna come stampati</button>
                    <button type="button" class="btn btn-secondary" id="bsn-etichette-mark-unprinted">Segna come NON stampati</button>
                    <button type="button" class="btn btn-secondary" id="bsn-etichette-clear-selection">Svuota selezione</button>
                </div>
                <div id="bsn-lista-etichette"><p>Nessun articolo caricato.</p></div>
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
                    <!-- Stato noleggio/preventivo, gestito dai pulsanti -->
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
                    <label>Metodo di pagamento</label>
                    <input type="text" name="metodo_pagamento" placeholder="Es. Bonifico, carta, contanti" style="width:100%;">
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
                    
                    <table class="bsn-preview-table" style="width:100%; border-collapse:collapse; margin:15px 0;">
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
                                <th class="bsn-preview-stato-col" style="padding:8px; text-align:center;">!</th>
                            </tr>
                        </thead>
                        <tbody id="bsn-preview-articoli-tbody">
                            <!-- Popolato da JS -->
                        </tbody>
                    </table>
                    
                    <div id="bsn-preview-dettagli-articoli"></div>
                    <div id="bsn-preview-kit-componenti" style="margin-top:15px;"></div>
                    
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

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
                    <button type="button" class="btn btn-warning bsn-save-preventivo" data-stato="preventivo">Salva Preventivo</button>
                    <button type="button" class="btn btn-primary bsn-save-noleggio" data-stato="bozza">Salva Noleggio</button>
                </div>
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
                            <option value="preventivo">Preventivi</option>
                            <option value="bozza">Bozze</option>
                            <option value="attivo">Attivi</option>
                            <option value="chiuso">Chiusi</option>
                            <option value="ritardo">In ritardo</option>
                        </select>
                    </label>
                    <div class="bsn-list-toolbar" style="margin-top:8px;">
                        <input type="text" id="bsn-noleggi-search" placeholder="Cerca nei noleggi..." style="width:100%; max-width:360px; padding:5px;">
                        <label for="bsn-noleggi-limit">Mostra:</label>
                        <select id="bsn-noleggi-limit">
                            <option value="12" selected>12</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <!-- Lista noleggi -->
                 <div id="bsn-lista-noleggi">
                    <p>Nessun noleggio caricato.</p>
                </div>
            </div>

            <!-- CALENDARIO -->
            <div id="calendario" class="tab-content">
                <h2>Calendario Noleggi</h2>

                <div class="bsn-calendar-toolbar">
                    <div class="bsn-calendar-nav">
                        <button type="button" class="btn btn-secondary" id="bsn-calendar-prev">◀</button>
                        <div id="bsn-calendar-title">-</div>
                        <button type="button" class="btn btn-secondary" id="bsn-calendar-next">▶</button>
                    </div>
                    <div class="bsn-calendar-views">
                        <button type="button" class="btn btn-secondary bsn-calendar-view active" data-view="month">Mese</button>
                        <button type="button" class="btn btn-secondary bsn-calendar-view" data-view="week">Settimana</button>
                        <button type="button" class="btn btn-secondary bsn-calendar-view" data-view="day">Giorno</button>
                    </div>
                </div>

                <div class="bsn-calendar-filters">
                    <input type="text" id="bsn-calendar-search" placeholder="Cerca per cliente, materiale o ID..." />
                </div>

                <div id="bsn-calendar" class="bsn-calendar">
                    <div class="bsn-calendar-weekdays">
                        <div>Lun</div>
                        <div>Mar</div>
                        <div>Mer</div>
                        <div>Gio</div>
                        <div>Ven</div>
                        <div>Sab</div>
                        <div>Dom</div>
                    </div>
                    <div id="bsn-calendar-grid" class="bsn-calendar-grid"></div>
                    <div id="bsn-calendar-day" class="bsn-calendar-day" hidden></div>
                </div>
            </div>

            <!-- TICKET -->
            <div id="ticket" class="tab-content">
                <h2>Ticket (anomalia materiale)</h2>

                <form id="bsn-form-ticket" style="margin-bottom:20px;">
                    <div style="display:flex; gap:15px; flex-wrap:wrap;">
                        <div style="flex:1 1 260px; position:relative;">
                            <label>Articolo *</label>
                            <input type="text" id="bsn-ticket-articolo-search" placeholder="Cerca per nome o ID..." autocomplete="off" style="width:100%;">
                            <input type="hidden" id="bsn-ticket-articolo-id">
                            <input type="hidden" id="bsn-ticket-id">
                            <div id="bsn-ticket-articoli-risultati"
                                 style="border:1px solid #ccc; max-height:150px; overflow-y:auto; display:none; background:#fff; position:absolute; z-index:10; width:100%;"></div>
                        </div>
                        <div style="flex:0 0 140px;">
                            <label>Quantità *</label>
                            <input type="number" id="bsn-ticket-qty" min="1" value="1" style="width:100%;">
                        </div>
                        <div style="flex:1 1 200px;">
                            <label>Tipo problema *</label>
                            <select id="bsn-ticket-tipo" style="width:100%;">
                                <option value="mancante">Mancante</option>
                                <option value="non_rientrato">Non rientrato</option>
                                <option value="quantita_inferiore">Quantità inferiore</option>
                                <option value="danneggiato">Danneggiato</option>
                                <option value="problematico_utilizzabile">Problematico ma utilizzabile</option>
                            </select>
                        </div>
                        <div style="flex:0 0 140px;">
                            <label>Stato</label>
                            <select id="bsn-ticket-stato" style="width:100%;">
                                <option value="aperto">Aperto</option>
                                <option value="chiuso">Chiuso</option>
                            </select>
                        </div>
                        <div style="flex:1 1 200px;">
                            <label>Riferimento noleggio</label>
                            <input type="text" id="bsn-ticket-noleggio" placeholder="Es. 2026/001" style="width:100%;">
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <label>Note operative</label>
                        <textarea id="bsn-ticket-note" rows="3" style="width:100%;"></textarea>
                    </div>
                    <div style="margin-top:10px;">
                        <label>Foto (opzionale)</label>
                        <input type="file" id="bsn-ticket-foto" accept="image/*" capture="environment" multiple style="width:100%;">
                        <input type="hidden" id="bsn-ticket-foto-urls" value="">
                        <div id="bsn-ticket-foto-preview" style="margin-top:6px;"></div>
                        <!-- BSN: Ticket webcam -->
                        <button type="button" id="bsn-ticket-webcam-start" class="btn btn-secondary" style="margin-top:6px;">📷 Apri webcam</button>
                        <button type="button" id="bsn-ticket-webcam-capture" class="btn btn-primary" style="display:none; margin-top:6px;">Scatta foto</button>
                        <div id="bsn-ticket-webcam-area" style="display:none; margin-top:10px;">
                            <video id="bsn-ticket-webcam-video" width="320" height="240" autoplay style="border:1px solid #ccc;"></video>
                            <canvas id="bsn-ticket-webcam-canvas" style="display:none;"></canvas>
                        </div>
                        <!-- BSN: Ticket webcam end -->
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:10px;">Apri ticket</button>
                </form>

                <div id="bsn-ticket-filtri" style="margin-bottom:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <input type="text" id="bsn-ticket-search" placeholder="Cerca nei ticket..." style="width:100%; max-width:300px; padding:5px;">
                    <label style="display:flex; align-items:center; gap:6px; margin:0;">
                        Stato:
                        <select id="bsn-ticket-filtro-stato">
                            <option value="">Tutti</option>
                            <option value="aperto" selected>Aperti</option>
                            <option value="chiuso">Chiusi</option>
                        </select>
                    </label>
                </div>

                <div id="bsn-lista-ticket">
                    <p>Nessun ticket caricato.</p>
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

add_shortcode( 'blackstar_preventivo', 'bsn_shortcode_preventivo' );
add_shortcode( 'blackstar_preventivi', 'bsn_shortcode_preventivo' );
function bsn_shortcode_preventivo() {
    if ( ! bsn_check_admin() ) {
        return '<p>Accesso negato. Permesso riservato agli operatori autorizzati.</p>';
    }

    ob_start();
    bsn_render_preventivo_noleggio_page(
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
    $has_preventivo = has_shortcode( $post->post_content, 'blackstar_preventivo' );
    $has_preventivi = has_shortcode( $post->post_content, 'blackstar_preventivi' );

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
            'nonce' => wp_create_nonce('wp_rest'),
            'preventivo_url' => esc_url_raw( bsn_get_preventivo_page_url() ),
        ]);
    }

     if ( $has_finalizza || $has_ispeziona || $has_rientro || $has_preventivo || $has_preventivi ) {
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

     register_rest_route('bsn/v1', '/clienti/search', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_clienti_search',
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

     register_rest_route('bsn/v1', '/articoli/search', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_articoli_search',
        'permission_callback' => 'bsn_check_admin',
    ]);

    register_rest_route('bsn/v1', '/articoli', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_articoli_post',
        'permission_callback' => 'bsn_check_admin',
    ]);

    register_rest_route('bsn/v1', '/articoli/woocommerce', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_articoli_woocommerce',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'external_product_url' => [ 'required' => true, 'type' => 'string' ],
        ],
    ]);


     register_rest_route('bsn/v1', '/articoli/export', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_articoli_export',
        'permission_callback' => 'bsn_check_admin',
    ]);

    register_rest_route('bsn/v1', '/articoli/etichette', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_articoli_etichette_get',
        'permission_callback' => 'bsn_check_admin',
    ]);

    register_rest_route('bsn/v1', '/articoli/etichette-export', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_articoli_etichette_export',
        'permission_callback' => 'bsn_check_admin',
    ]);

    register_rest_route('bsn/v1', '/articoli/etichette-stato', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_articoli_etichette_stato',
        'permission_callback' => 'bsn_check_admin',
    ]);

        // PROFILI SCONTI (precompilazione articoli)
    register_rest_route('bsn/v1', '/profili-sconti', array(
        'methods'  => 'GET',
        'callback' => 'bsn_api_profili_sconti_get',
        'permission_callback' => 'bsn_check_admin',
    ));

    register_rest_route('bsn/v1', '/profili-sconti', array(
        'methods'  => 'POST',
        'callback' => 'bsn_api_profili_sconti_create',
        'permission_callback' => 'bsn_check_admin',
    ));

    register_rest_route('bsn/v1', '/profili-sconti/delete', array(
        'methods'  => 'POST',
        'callback' => 'bsn_api_profili_sconti_delete',
        'permission_callback' => 'bsn_check_admin',
    ));

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

    // PROFILI SCONTI (precompilazione articoli)
    register_rest_route('bsn/v1', '/profili-sconti', array(
        'methods' => 'GET',
        'callback' => 'bsn_api_profili_sconti_get',
        'permission_callback' => 'bsn_check_admin',
    ));
    register_rest_route('bsn/v1', '/profili-sconti', array(
        'methods' => 'POST',
        'callback' => 'bsn_api_profili_sconti_create',
        'permission_callback' => 'bsn_check_admin',
    ));
    register_rest_route('bsn/v1', '/profili-sconti/delete', array(
        'methods' => 'POST',
        'callback' => 'bsn_api_profili_sconti_delete',
        'permission_callback' => 'bsn_check_admin',
    ));

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
    
    $search = trim( (string) $request->get_param( 'search' ) );
    $limit  = absint( $request->get_param( 'limit' ) );
    if ( $limit < 1 ) {
        $limit = 12;
    }
    if ( $limit > 100 ) {
        $limit = 100;
    }

    if ( $search !== '' ) {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $sql  = $wpdb->prepare(
            "SELECT * FROM $table
             WHERE
                CAST(id AS CHAR) LIKE %s
                OR nome LIKE %s
                OR cf_piva LIKE %s
                OR telefono LIKE %s
                OR email LIKE %s
                OR citta LIKE %s
             ORDER BY data_creazione DESC
             LIMIT %d",
            $like, $like, $like, $like, $like, $like, 100
        );
    } else {
        $sql = $wpdb->prepare(
            "SELECT * FROM $table ORDER BY data_creazione DESC LIMIT %d",
            $limit
        );
    }

    $clienti = $wpdb->get_results($sql, ARRAY_A);
    return rest_ensure_response(['clienti' => $clienti]);
}

function bsn_api_clienti_search( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_clienti';

    $search = trim( (string) $request->get_param( 'search' ) );
    $limit  = absint( $request->get_param( 'limit' ) );

    if ( $limit < 1 ) {
        $limit = 15;
    }
    if ( $limit > 25 ) {
        $limit = 25;
    }

    if ( $search === '' || strlen( $search ) < 2 ) {
        return rest_ensure_response( array( 'clienti' => array() ) );
    }

    $like = '%' . $wpdb->esc_like( $search ) . '%';
    $sql  = $wpdb->prepare(
         "SELECT id, nome, cf_piva, categoria_cliente, regime_percentuale, note
         FROM $table
         WHERE CAST(id AS CHAR) LIKE %s
            OR nome LIKE %s
            OR cf_piva LIKE %s
         ORDER BY data_creazione DESC
         LIMIT %d",
        $like,
        $like,
        $like,
        $limit
    );

    $clienti = $wpdb->get_results( $sql, ARRAY_A );

    return rest_ensure_response( array( 'clienti' => $clienti ) );
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

function bsn_get_wc_api_credentials() {
    $base_url = defined( 'BSN_WC_COM_URL' ) ? rtrim( constant( 'BSN_WC_COM_URL' ), '/' ) : '';
    $consumer_key = defined( 'BSN_WC_COM_CK' ) ? constant( 'BSN_WC_COM_CK' ) : '';
    $consumer_secret = defined( 'BSN_WC_COM_CS' ) ? constant( 'BSN_WC_COM_CS' ) : '';

    if ( $base_url && $consumer_key && $consumer_secret ) {
        return [
            'base_url'       => $base_url,
            'consumer_key'    => $consumer_key,
            'consumer_secret' => $consumer_secret,
        ];
    }

    return new WP_Error(
        'bsn_wc_credentials_missing',
        'Credenziali WooCommerce non disponibili.',
        [ 'status' => 500 ]
    );
}

function bsn_extract_wc_product_slug( $url ) {
    $parsed = wp_parse_url( $url );
    if ( empty( $parsed['path'] ) ) {
        return '';
    }

    $path = trim( $parsed['path'], '/' );
    if ( $path === '' ) {
        return '';
    }

    $segments = explode( '/', $path );
    return sanitize_title( end( $segments ) );
}

function bsn_log_wc_error( $endpoint, $status, $body, $error_message = '' ) {
    $snippet = substr( wp_strip_all_tags( (string) $body ), 0, 200 );
    $message = 'BSN WC: endpoint=' . $endpoint . ' status=' . $status;
    if ( $error_message ) {
        $message .= ' error=' . $error_message;
    }
    if ( $snippet !== '' ) {
        $message .= ' body=' . $snippet;
    }
    error_log( $message );
}

function bsn_fetch_wc_product_data( $slug ) {
    $slug = sanitize_title( $slug );
    if ( $slug === '' ) {
        return new WP_Error( 'bsn_wc_slug_missing', 'Slug prodotto mancante.', [ 'status' => 400 ] );
    }

    $cache_key = 'bsn_wc_product_' . md5( $slug );
    $cached = get_transient( $cache_key );
    if ( ! empty( $cached ) ) {
        return $cached;
    }

    $creds = bsn_get_wc_api_credentials();
    if ( is_wp_error( $creds ) ) {
        return $creds;
    }

    $endpoint = trailingslashit( $creds['base_url'] ) . 'wp-json/wc/v3/products';
    $query_args = [
        'slug' => $slug,
    ];

    $auth_header = 'Basic ' . base64_encode( $creds['consumer_key'] . ':' . $creds['consumer_secret'] );
    $endpoint_url = add_query_arg( $query_args, $endpoint );
    $response = wp_remote_get(
        $endpoint_url,
        [
            'timeout' => 12,
            'headers' => [
                'Authorization' => $auth_header,
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        bsn_log_wc_error( $endpoint_url, 'wp_error', '', $response->get_error_message() );
        return new WP_Error( 'bsn_wc_api_error', $response->get_error_message(), [ 'status' => 500 ] );
    }

    $status = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    if ( $status === 401 || $status === 403 ) {
        $fallback_args = [
            'slug'            => $slug,
            'consumer_key'    => $creds['consumer_key'],
            'consumer_secret' => $creds['consumer_secret'],
        ];
        $fallback_url = add_query_arg( $fallback_args, $endpoint );
        $response = wp_remote_get(
            $fallback_url,
            [
                'timeout' => 12,
            ]
        );
        if ( is_wp_error( $response ) ) {
            bsn_log_wc_error( $fallback_url, 'wp_error', '', $response->get_error_message() );
            return new WP_Error( 'bsn_wc_api_error', $response->get_error_message(), [ 'status' => 500 ] );
        }
        $status = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
    }

    if ( $status < 200 || $status >= 300 ) {
        $logged_endpoint = isset( $fallback_url ) ? $fallback_url : $endpoint_url;
        bsn_log_wc_error( $logged_endpoint, $status, $body );
        $message = 'Errore WooCommerce REST API.';
        if ( $status === 401 || $status === 403 ) {
            $message = $status . ' Unauthorized: chiavi errate o permessi insufficienti.';
        } elseif ( $status === 404 ) {
            $message = '404: prodotto non trovato per slug.';
        } elseif ( $status === 408 ) {
            $message = 'Timeout durante la chiamata WooCommerce.';
        }
        return new WP_Error( 'bsn_wc_api_error', $message, [ 'status' => $status ] );
    }

    $decoded = json_decode( $body, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) || empty( $decoded ) ) {
        bsn_log_wc_error( $endpoint_url, $status, $body, 'empty_response' );
        return new WP_Error( 'bsn_wc_empty', '404: prodotto non trovato per slug.', [ 'status' => 404 ] );
    }

    $product = $decoded[0];
    $data = [
        'external_product_slug' => $slug,
        'external_product_name' => $product['name'] ?? '',
        'external_product_url'  => $product['permalink'] ?? '',
        'external_image_url'    => $product['images'][0]['src'] ?? '',
    ];

    set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

    return $data;
}

function bsn_api_articoli_woocommerce( WP_REST_Request $request ) {
    $url = trim( (string) $request->get_param( 'external_product_url' ) );
    if ( $url === '' ) {
        return new WP_Error( 'bsn_wc_url_missing', 'URL prodotto mancante.', [ 'status' => 400 ] );
    }

    $slug = bsn_extract_wc_product_slug( $url );
    if ( $slug === '' ) {
        return new WP_Error( 'bsn_wc_slug_missing', 'Slug prodotto non valido.', [ 'status' => 400 ] );
    }

    $creds = bsn_get_wc_api_credentials();
    if ( is_wp_error( $creds ) ) {
        return $creds;
    }
    $endpoint = trailingslashit( $creds['base_url'] ) . 'wp-json/wc/v3/products?slug=' . rawurlencode( $slug );

    $data = bsn_fetch_wc_product_data( $slug );
    if ( is_wp_error( $data ) ) {
        $error_data = $data->get_error_data();
        if ( ! is_array( $error_data ) ) {
            $error_data = [];
        }
        $error_data['slug'] = $slug;
        $error_data['endpoint'] = $endpoint;
        return new WP_Error( $data->get_error_code(), $data->get_error_message(), $error_data );
    }

    $data['external_last_sync'] = current_time( 'mysql' );
    $data['external_product_slug'] = $slug;
    $data['external_product_url'] = $data['external_product_url'] ?: $url;
    $data['success'] = true;
    $data['endpoint'] = $endpoint;

    return rest_ensure_response( $data );
}

function bsn_api_articoli_get( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';

    $search = trim( (string) $request->get_param( 'search' ) );
    $limit  = absint( $request->get_param( 'limit' ) );
    if ( $limit < 1 ) {
        $limit = 12;
    }
    if ( $limit > 100 ) {
        $limit = 100;
    }

    if ( $search !== '' ) {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $sql  = $wpdb->prepare(
            "SELECT * FROM $table
             WHERE
                CAST(id AS CHAR) LIKE %s
                OR nome LIKE %s
                OR codice LIKE %s
                OR ubicazione LIKE %s
                 OR note LIKE %s
             ORDER BY id DESC
             LIMIT %d",
            $like, $like, $like, $like, $like, 100
        );
    } else {
        $sql = $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", $limit );
    }

    $rows = $wpdb->get_results( $sql, ARRAY_A );

    return rest_ensure_response( array(
        'articoli' => $rows,
    ) );
}

function bsn_api_articoli_search( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';

    $search = trim( (string) $request->get_param( 'search' ) );
    $limit  = absint( $request->get_param( 'limit' ) );

    if ( $limit < 1 ) {
        $limit = 15;
    }
    if ( $limit > 25 ) {
        $limit = 25;
    }

    if ( $search === '' || strlen( $search ) < 2 ) {
        return rest_ensure_response( array( 'articoli' => array() ) );
    }

    $like = '%' . $wpdb->esc_like( $search ) . '%';
    $sql  = $wpdb->prepare(
        "SELECT id, nome, codice, prezzo_giorno,
                sconto_standard, sconto_fidato, sconto_premium, sconto_service, sconto_collaboratori,
                noleggio_scalare, ubicazione, correlati
         FROM $table
         WHERE CAST(id AS CHAR) LIKE %s
            OR nome LIKE %s
            OR codice LIKE %s
         ORDER BY id DESC
         LIMIT %d",
        $like,
        $like,
        $like,
        $limit
    );

    $articoli = $wpdb->get_results( $sql, ARRAY_A );

    return rest_ensure_response( array( 'articoli' => $articoli ) );
}

function bsn_api_articoli_export( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';

    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        $params = array();
    }

    $ids = isset( $params['ids'] ) && is_array( $params['ids'] ) ? array_map( 'absint', $params['ids'] ) : array();
    $ids = array_values( array_filter( array_unique( $ids ) ) );

    $search = isset( $params['search'] ) ? sanitize_text_field( (string) $params['search'] ) : '';
    $max_export = 1000;

    if ( empty( $ids ) && $search === '' ) {
        return new WP_Error( 'bsn_export_empty', 'Nessun articolo selezionato per export.', array( 'status' => 400 ) );
    }

    if ( ! empty( $ids ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql = "SELECT id, nome, codice, external_product_slug, ubicazione, note FROM $table WHERE id IN ($placeholders) ORDER BY id DESC";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $ids ), ARRAY_A );
    } else {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $sql = $wpdb->prepare(
            "SELECT id, nome, codice, external_product_slug, ubicazione, note
             FROM $table
             WHERE CAST(id AS CHAR) LIKE %s OR nome LIKE %s OR codice LIKE %s OR ubicazione LIKE %s OR note LIKE %s
             ORDER BY id DESC
             LIMIT %d",
            $like, $like, $like, $like, $like, $max_export
        );
        $rows = $wpdb->get_results( $sql, ARRAY_A );
    }

    if ( empty( $rows ) ) {
        return new WP_Error( 'bsn_export_no_rows', 'Nessun articolo trovato per export.', array( 'status' => 404 ) );
    }

    $upload_dir = wp_upload_dir();
    $dir = trailingslashit( $upload_dir['basedir'] ) . 'bsn-exports';
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
    }

    $filename = 'bsn-articoli-' . current_time( 'Ymd-His' ) . '.csv';
    $filepath = trailingslashit( $dir ) . $filename;
    $fileurl  = trailingslashit( $upload_dir['baseurl'] ) . 'bsn-exports/' . $filename;

    $fp = fopen( $filepath, 'w' );
    if ( ! $fp ) {
        return new WP_Error( 'bsn_export_file_error', 'Impossibile creare il file CSV.', array( 'status' => 500 ) );
    }

    fputcsv( $fp, array( 'id_articolo', 'nome_articolo', 'codice', 'valore_qr', 'categoria_ubicazione', 'note' ) );

    foreach ( $rows as $row ) {
        $id = absint( $row['id'] );
        $nome = sanitize_text_field( $row['nome'] );
        $codice = sanitize_text_field( $row['codice'] );
        $slug = sanitize_text_field( $row['external_product_slug'] );
        $valore_qr = $codice !== '' ? $codice : ( $slug !== '' ? $slug : (string) $id );

        fputcsv( $fp, array(
            $id,
            $nome,
            $codice,
            $valore_qr,
            sanitize_text_field( $row['ubicazione'] ),
            sanitize_textarea_field( $row['note'] ),
        ) );
    }

    fclose( $fp );

    return rest_ensure_response( array(
        'success'  => true,
        'filename' => $filename,
        'url'      => esc_url_raw( $fileurl ),
        'count'    => count( $rows ),
    ) );
}


function bsn_api_articoli_etichette_get( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';

    $search = trim( (string) $request->get_param( 'search' ) );
    $limit = absint( $request->get_param( 'limit' ) );
    $only_unprinted = absint( $request->get_param( 'only_unprinted' ) ) ? 1 : 0;

    if ( $limit < 1 ) {
        $limit = 12;
    }
    if ( $limit > 100 ) {
        $limit = 100;
    }

    $where = array();
    $params = array();

    if ( $search !== '' ) {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $where[] = '(CAST(id AS CHAR) LIKE %s OR nome LIKE %s OR codice LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ( $only_unprinted ) {
        $where[] = 'COALESCE(qr_stampato, 0) = 0';
    }

    $sql = "SELECT id, nome, codice, data_creazione, data_modifica, qr_stampato, qr_stampato_at, qr_stampato_by FROM $table";
    if ( ! empty( $where ) ) {
        $sql .= ' WHERE ' . implode( ' AND ', $where );
    }

    $sql .= ' ORDER BY id DESC LIMIT %d';
    $params[] = ( $search !== '' ) ? 200 : $limit;

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

    return rest_ensure_response( array(
        'articoli' => $rows,
    ) );
}

function bsn_api_articoli_etichette_stato( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';

    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        $params = array();
    }

    $ids = isset( $params['ids'] ) && is_array( $params['ids'] )
        ? array_values( array_filter( array_unique( array_map( 'absint', $params['ids'] ) ) ) )
        : array();

    if ( empty( $ids ) || ! isset( $params['printed'] ) ) {
        return new WP_Error( 'bsn_etichette_stato_invalid', 'Parametri mancanti per aggiornare lo stato.', array( 'status' => 400 ) );
    }

    $printed = (bool) $params['printed'];
    $now = current_time( 'mysql' );
    $user_id = get_current_user_id();
    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

    if ( $printed ) {
        $sql = "UPDATE $table SET qr_stampato = 1, qr_stampato_at = %s, qr_stampato_by = %d, data_modifica = %s WHERE id IN ($placeholders)";
        $args = array_merge( array( $now, $user_id, $now ), $ids );
    } else {
        $sql = "UPDATE $table SET qr_stampato = 0, qr_stampato_at = NULL, qr_stampato_by = NULL, data_modifica = %s WHERE id IN ($placeholders)";
        $args = array_merge( array( $now ), $ids );
    }

    $updated = $wpdb->query( $wpdb->prepare( $sql, $args ) );
    if ( $updated === false ) {
        return new WP_Error( 'bsn_etichette_stato_error', 'Errore durante aggiornamento stato.', array( 'status' => 500 ) );
    }

    return rest_ensure_response( array( 'success' => true, 'updated' => intval( $updated ) ) );
}

function bsn_api_articoli_etichette_export( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_articoli';

    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        $params = array();
    }

    $raw_items = isset( $params['items'] ) && is_array( $params['items'] ) ? $params['items'] : array();
    $items = array();

    foreach ( $raw_items as $item ) {
        if ( ! is_array( $item ) ) {
            continue;
        }

        $id = absint( $item['id'] ?? 0 );
        $copies = absint( $item['copies'] ?? 1 );

        if ( $id < 1 ) {
            continue;
        }

        if ( $copies < 1 ) {
            $copies = 1;
        }
        if ( $copies > 100 ) {
            $copies = 100;
        }

        $items[] = array(
            'id' => $id,
            'copies' => $copies,
        );
    }

    if ( empty( $items ) ) {
        return new WP_Error( 'bsn_etichette_export_empty', 'Seleziona almeno un articolo.', array( 'status' => 400 ) );
    }

    $ids = array_values( array_unique( array_map( function( $item ) {
        return $item['id'];
    }, $items ) ) );

    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $sql = "SELECT id, codice FROM $table WHERE id IN ($placeholders)";
    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $ids ), ARRAY_A );

    if ( empty( $rows ) ) {
        return new WP_Error( 'bsn_etichette_export_not_found', 'Nessun articolo trovato.', array( 'status' => 404 ) );
    }

    $map = array();
    foreach ( $rows as $row ) {
        $map[ absint( $row['id'] ) ] = sanitize_text_field( (string) $row['codice'] );
    }

     $max_rows = 10000;
    $rows_written = 0;

     $fp = fopen( 'php://temp', 'w+' );
    if ( ! $fp ) {
        return new WP_Error( 'bsn_etichette_export_file', 'Impossibile creare CSV temporaneo.', array( 'status' => 500 ) );
    }

    fputcsv( $fp, array( 'CODICE' ) );

    foreach ( $items as $item ) {
        $id = $item['id'];
        $code = $map[ $id ] ?? '';
        if ( $code === '' ) {
            continue;
        }

        for ( $i = 0; $i < $item['copies']; $i++ ) {
             $rows_written++;
            if ( $rows_written > $max_rows ) {
                fclose( $fp );
                return new WP_Error( 'bsn_etichette_export_limit', 'Troppe etichette richieste in un solo export (max 10.000 righe).', array( 'status' => 400 ) );
            }
            fputcsv( $fp, array( $code ) );
        }
    }

    rewind( $fp );
    $csv_content = stream_get_contents( $fp );
    fclose( $fp );

    if ( ! is_string( $csv_content ) || $csv_content === '' ) {
        return new WP_Error( 'bsn_etichette_export_empty_file', 'CSV vuoto non valido.', array( 'status' => 500 ) );
    }

    $now = current_time( 'mysql' );
    $user_id = get_current_user_id();
    $sql_update = "UPDATE $table SET qr_stampato = 1, qr_stampato_at = %s, qr_stampato_by = %d, data_modifica = %s WHERE id IN ($placeholders)";
    $args_update = array_merge( array( $now, $user_id, $now ), $ids );
    $wpdb->query( $wpdb->prepare( $sql_update, $args_update ) );

    return rest_ensure_response( array(
        'success' => true,
        'filename' => 'bsn-etichette-' . current_time( 'Ymd-Hi' ) . '.csv',
        'mime' => 'text/csv;charset=utf-8',
        'csv_base64' => base64_encode( $csv_content ),
        'count' => $rows_written,
    ) );
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
    $external_product_url  = esc_url_raw( $request->get_param( 'external_product_url' ) );
    $external_product_slug = sanitize_text_field( $request->get_param( 'external_product_slug' ) );
    $external_product_name = sanitize_text_field( $request->get_param( 'external_product_name' ) );
    $external_image_url    = esc_url_raw( $request->get_param( 'external_image_url' ) );
    $external_last_sync    = sanitize_text_field( $request->get_param( 'external_last_sync' ) );
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
        'external_product_url'  => $external_product_url,
        'external_product_slug' => $external_product_slug,
        'external_product_name' => $external_product_name,
        'external_image_url'    => $external_image_url,
        'external_last_sync'    => $external_last_sync,
        'qr_stampato'           => 0,
        'qr_stampato_at'        => null,
        'qr_stampato_by'        => null,
        'data_creazione'        => current_time('mysql'),
        'data_modifica'         => current_time('mysql'),
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
        'external_product_url',
        'external_product_slug',
        'external_product_name',
        'external_image_url',
        'external_last_sync',
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
                    case 'external_product_url':
                case 'external_image_url':
                    $data[ $field ] = esc_url_raw( $value );
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

     $data['data_modifica'] = current_time( 'mysql' );
     
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

/* ==== KIT (bundle articoli) ==== */

function bsn_get_kits_with_components( $kit_ids = [] ) {
    global $wpdb;

    $table_kits          = $wpdb->prefix . 'bsn_kits';
    $table_kit_componenti = $wpdb->prefix . 'bsn_kit_componenti';
    $table_articoli      = $wpdb->prefix . 'bs_articoli';

    $kits = [];
    if ( ! empty( $kit_ids ) ) {
        $kit_ids = array_values( array_unique( array_filter( array_map( 'intval', $kit_ids ) ) ) );
        if ( empty( $kit_ids ) ) {
            return [];
        }
        $placeholders = implode( ',', array_fill( 0, count( $kit_ids ), '%d' ) );
        $sql = "SELECT k.*, a.nome AS articolo_kit_nome, a.codice AS articolo_kit_codice
                FROM $table_kits k
                LEFT JOIN $table_articoli a ON k.articolo_kit_id = a.id
                WHERE k.id IN ($placeholders)
                ORDER BY k.id DESC";
        $kits = $wpdb->get_results( $wpdb->prepare( $sql, $kit_ids ), ARRAY_A );
    } else {
        $kits = $wpdb->get_results(
            "SELECT k.*, a.nome AS articolo_kit_nome, a.codice AS articolo_kit_codice
             FROM $table_kits k
             LEFT JOIN $table_articoli a ON k.articolo_kit_id = a.id
             ORDER BY k.id DESC",
            ARRAY_A
        );
    }

    if ( empty( $kits ) ) {
        return [];
    }

    $kits_map = [];
    foreach ( $kits as $kit ) {
        $kit_id = intval( $kit['id'] );
        $kits_map[ $kit_id ] = [
            'id'                => $kit_id,
            'nome_kit'          => $kit['nome_kit'],
            'articolo_kit_id'   => intval( $kit['articolo_kit_id'] ),
            'articolo_kit_nome' => $kit['articolo_kit_nome'] ?? '',
            'articolo_kit_codice' => $kit['articolo_kit_codice'] ?? '',
            'note'              => $kit['note'] ?? '',
            'components'        => [],
        ];
    }

    $kit_ids = array_keys( $kits_map );
    $placeholders = implode( ',', array_fill( 0, count( $kit_ids ), '%d' ) );
    $sql_components = "SELECT kc.*, a.nome AS articolo_nome, a.codice AS articolo_codice
                       FROM $table_kit_componenti kc
                       LEFT JOIN $table_articoli a ON kc.articolo_id = a.id
                       WHERE kc.kit_id IN ($placeholders)
                       ORDER BY kc.id ASC";
    $components = $wpdb->get_results( $wpdb->prepare( $sql_components, $kit_ids ), ARRAY_A );

    foreach ( $components as $component ) {
        $kit_id = intval( $component['kit_id'] );
        if ( ! isset( $kits_map[ $kit_id ] ) ) {
            continue;
        }
        $kits_map[ $kit_id ]['components'][] = [
            'id'                => intval( $component['id'] ),
            'articolo_id'       => intval( $component['articolo_id'] ),
            'articolo_nome'     => $component['articolo_nome'] ?? '',
            'articolo_codice'   => $component['articolo_codice'] ?? '',
            'ruolo'             => $component['ruolo'],
            'qty'               => intval( $component['qty'] ),
            'is_default'        => intval( $component['is_default'] ),
            'is_selectable'     => intval( $component['is_selectable'] ),
            'gruppo_equivalenza'=> $component['gruppo_equivalenza'],
        ];
    }

    return array_values( $kits_map );
}

function bsn_api_kits_get( WP_REST_Request $request ) {
    $kits = bsn_get_kits_with_components();
    return rest_ensure_response( [ 'kits' => $kits ] );
}

function bsn_api_kits_post( WP_REST_Request $request ) {
    global $wpdb;

    $table_kits           = $wpdb->prefix . 'bsn_kits';
    $table_kit_componenti = $wpdb->prefix . 'bsn_kit_componenti';
    $table_articoli       = $wpdb->prefix . 'bs_articoli';

    $nome_kit = sanitize_text_field( $request->get_param( 'nome_kit' ) );
    $articolo_kit_id = intval( $request->get_param( 'articolo_kit_id' ) );
    $note = sanitize_textarea_field( $request->get_param( 'note' ) );

    if ( empty( $nome_kit ) || $articolo_kit_id <= 0 ) {
        return new WP_Error( 'bsn_kit_dati_mancanti', 'Nome kit e articolo kit sono obbligatori.', [ 'status' => 400 ] );
    }

    $articolo_esiste = $wpdb->get_var(
        $wpdb->prepare( "SELECT id FROM $table_articoli WHERE id = %d", $articolo_kit_id )
    );
    if ( ! $articolo_esiste ) {
        return new WP_Error( 'bsn_kit_articolo_non_trovato', 'Articolo kit non trovato.', [ 'status' => 404 ] );
    }

    $componenti_raw = $request->get_param( 'componenti' );
    $componenti = [];
    if ( ! empty( $componenti_raw ) ) {
        $decoded = json_decode( $componenti_raw, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            foreach ( $decoded as $comp ) {
                $articolo_id = isset( $comp['articolo_id'] ) ? intval( $comp['articolo_id'] ) : 0;
                $ruolo = isset( $comp['ruolo'] ) ? sanitize_text_field( $comp['ruolo'] ) : '';
                $qty = isset( $comp['qty'] ) ? intval( $comp['qty'] ) : 0;
                $gruppo = isset( $comp['gruppo_equivalenza'] ) ? sanitize_text_field( $comp['gruppo_equivalenza'] ) : '';
                $is_default = ! empty( $comp['is_default'] ) ? 1 : 0;
                $is_selectable = ! empty( $comp['is_selectable'] ) ? 1 : 0;

                if ( $articolo_id > 0 && $ruolo !== '' && $qty > 0 && $gruppo !== '' ) {
                    $componenti[] = [
                        'articolo_id'       => $articolo_id,
                        'ruolo'             => $ruolo,
                        'qty'               => $qty,
                        'is_default'        => $is_default,
                        'is_selectable'     => $is_selectable,
                        'gruppo_equivalenza'=> $gruppo,
                    ];
                }
            }
        }
    }

    if ( empty( $componenti ) ) {
        return new WP_Error( 'bsn_kit_componenti_mancanti', 'Inserisci almeno un componente valido.', [ 'status' => 400 ] );
    }

    $inserted = $wpdb->insert(
        $table_kits,
        [
            'nome_kit'        => $nome_kit,
            'articolo_kit_id' => $articolo_kit_id,
            'note'            => $note,
            'data_creazione'  => current_time( 'mysql' ),
        ],
        [ '%s', '%d', '%s', '%s' ]
    );

    if ( ! $inserted ) {
        return new WP_Error( 'bsn_kit_insert_error', 'Errore nel salvataggio del kit.', [ 'status' => 500 ] );
    }

    $kit_id = (int) $wpdb->insert_id;

    foreach ( $componenti as $comp ) {
        $wpdb->insert(
            $table_kit_componenti,
            [
                'kit_id'             => $kit_id,
                'articolo_id'        => $comp['articolo_id'],
                'ruolo'              => $comp['ruolo'],
                'qty'                => $comp['qty'],
                'is_default'         => $comp['is_default'],
                'is_selectable'      => $comp['is_selectable'],
                'gruppo_equivalenza' => $comp['gruppo_equivalenza'],
            ],
            [ '%d', '%d', '%s', '%d', '%d', '%d', '%s' ]
        );
    }

    return rest_ensure_response( [
        'success' => true,
        'id'      => $kit_id,
    ] );
}

function bsn_api_kits_update( WP_REST_Request $request ) {
    global $wpdb;

    $table_kits           = $wpdb->prefix . 'bsn_kits';
    $table_kit_componenti = $wpdb->prefix . 'bsn_kit_componenti';
    $table_articoli       = $wpdb->prefix . 'bs_articoli';

    $kit_id = intval( $request->get_param( 'id' ) );
    if ( ! $kit_id ) {
        return new WP_Error( 'bsn_kit_id_mancante', 'ID kit mancante.', [ 'status' => 400 ] );
    }

    $nome_kit = sanitize_text_field( $request->get_param( 'nome_kit' ) );
    $articolo_kit_id = intval( $request->get_param( 'articolo_kit_id' ) );
    $note = sanitize_textarea_field( $request->get_param( 'note' ) );

    if ( empty( $nome_kit ) || $articolo_kit_id <= 0 ) {
        return new WP_Error( 'bsn_kit_dati_mancanti', 'Nome kit e articolo kit sono obbligatori.', [ 'status' => 400 ] );
    }

    $articolo_esiste = $wpdb->get_var(
        $wpdb->prepare( "SELECT id FROM $table_articoli WHERE id = %d", $articolo_kit_id )
    );
    if ( ! $articolo_esiste ) {
        return new WP_Error( 'bsn_kit_articolo_non_trovato', 'Articolo kit non trovato.', [ 'status' => 404 ] );
    }

    $componenti_raw = $request->get_param( 'componenti' );
    $componenti = [];
    if ( ! empty( $componenti_raw ) ) {
        $decoded = json_decode( $componenti_raw, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            foreach ( $decoded as $comp ) {
                $articolo_id = isset( $comp['articolo_id'] ) ? intval( $comp['articolo_id'] ) : 0;
                $ruolo = isset( $comp['ruolo'] ) ? sanitize_text_field( $comp['ruolo'] ) : '';
                $qty = isset( $comp['qty'] ) ? intval( $comp['qty'] ) : 0;
                $gruppo = isset( $comp['gruppo_equivalenza'] ) ? sanitize_text_field( $comp['gruppo_equivalenza'] ) : '';
                $is_default = ! empty( $comp['is_default'] ) ? 1 : 0;
                $is_selectable = ! empty( $comp['is_selectable'] ) ? 1 : 0;

                if ( $articolo_id > 0 && $ruolo !== '' && $qty > 0 && $gruppo !== '' ) {
                    $componenti[] = [
                        'articolo_id'       => $articolo_id,
                        'ruolo'             => $ruolo,
                        'qty'               => $qty,
                        'is_default'        => $is_default,
                        'is_selectable'     => $is_selectable,
                        'gruppo_equivalenza'=> $gruppo,
                    ];
                }
            }
        }
    }

    if ( empty( $componenti ) ) {
        return new WP_Error( 'bsn_kit_componenti_mancanti', 'Inserisci almeno un componente valido.', [ 'status' => 400 ] );
    }

    $updated = $wpdb->update(
        $table_kits,
        [
            'nome_kit'        => $nome_kit,
            'articolo_kit_id' => $articolo_kit_id,
            'note'            => $note,
        ],
        [ 'id' => $kit_id ],
        [ '%s', '%d', '%s' ],
        [ '%d' ]
    );

    if ( $updated === false ) {
        return new WP_Error( 'bsn_kit_update_error', 'Errore nell\'aggiornamento del kit.', [ 'status' => 500 ] );
    }

    $wpdb->delete( $table_kit_componenti, [ 'kit_id' => $kit_id ], [ '%d' ] );
    foreach ( $componenti as $comp ) {
        $wpdb->insert(
            $table_kit_componenti,
            [
                'kit_id'             => $kit_id,
                'articolo_id'        => $comp['articolo_id'],
                'ruolo'              => $comp['ruolo'],
                'qty'                => $comp['qty'],
                'is_default'         => $comp['is_default'],
                'is_selectable'      => $comp['is_selectable'],
                'gruppo_equivalenza' => $comp['gruppo_equivalenza'],
            ],
            [ '%d', '%d', '%s', '%d', '%d', '%d', '%s' ]
        );
    }

    return rest_ensure_response( [
        'success' => true,
        'id'      => $kit_id,
    ] );
}

function bsn_api_kits_delete( WP_REST_Request $request ) {
    global $wpdb;

    $table_kits           = $wpdb->prefix . 'bsn_kits';
    $table_kit_componenti = $wpdb->prefix . 'bsn_kit_componenti';

    $kit_id = intval( $request->get_param( 'id' ) );
    if ( ! $kit_id ) {
        return new WP_Error( 'bsn_kit_id_mancante', 'ID kit mancante.', [ 'status' => 400 ] );
    }

    $wpdb->delete( $table_kit_componenti, [ 'kit_id' => $kit_id ], [ '%d' ] );
    $deleted = $wpdb->delete( $table_kits, [ 'id' => $kit_id ], [ '%d' ] );

    if ( $deleted === false ) {
        return new WP_Error( 'bsn_kit_delete_error', 'Errore nell\'eliminazione del kit.', [ 'status' => 500 ] );
    }

    return rest_ensure_response( [
        'success' => true,
        'id'      => $kit_id,
    ] );
}

function bsn_get_noleggio_kits( $noleggio_id ) {
    global $wpdb;

    $table_noleggio_kits = $wpdb->prefix . 'bsn_noleggio_kits';
    $table_noleggio_kit_componenti = $wpdb->prefix . 'bsn_noleggio_kit_componenti';
    $table_kits = $wpdb->prefix . 'bsn_kits';
    $table_articoli = $wpdb->prefix . 'bs_articoli';

    $kits_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT nk.id, nk.kit_id, nk.kit_label, k.nome_kit, k.articolo_kit_id,
                    a.nome AS articolo_kit_nome, a.codice AS articolo_kit_codice
             FROM $table_noleggio_kits nk
             LEFT JOIN $table_kits k ON nk.kit_id = k.id
             LEFT JOIN $table_articoli a ON k.articolo_kit_id = a.id
             WHERE nk.noleggio_id = %s
             ORDER BY nk.id ASC",
            $noleggio_id
        ),
        ARRAY_A
    );

    if ( empty( $kits_rows ) ) {
        return [];
    }

    $kits_map = [];
    $noleggio_kit_ids = [];
    foreach ( $kits_rows as $row ) {
        $nk_id = intval( $row['id'] );
        $kits_map[ $nk_id ] = [
            'noleggio_kit_id'    => $nk_id,
            'kit_id'             => intval( $row['kit_id'] ),
            'kit_label'          => $row['kit_label'] ?: $row['nome_kit'],
            'nome_kit'           => $row['nome_kit'],
            'articolo_kit_id'    => intval( $row['articolo_kit_id'] ),
            'articolo_kit_nome'  => $row['articolo_kit_nome'] ?? '',
            'articolo_kit_codice'=> $row['articolo_kit_codice'] ?? '',
            'components'         => [],
        ];
        $noleggio_kit_ids[] = $nk_id;
    }

    $placeholders = implode( ',', array_fill( 0, count( $noleggio_kit_ids ), '%d' ) );
    $components = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT nkc.*, a.nome AS articolo_nome, a.codice AS articolo_codice
             FROM $table_noleggio_kit_componenti nkc
             LEFT JOIN $table_articoli a ON nkc.articolo_id_scelto = a.id
             WHERE nkc.noleggio_kit_id IN ($placeholders)
             ORDER BY nkc.id ASC",
            $noleggio_kit_ids
        ),
        ARRAY_A
    );

    foreach ( $components as $component ) {
        $nk_id = intval( $component['noleggio_kit_id'] );
        if ( ! isset( $kits_map[ $nk_id ] ) ) {
            continue;
        }
        $kits_map[ $nk_id ]['components'][] = [
            'ruolo'             => $component['ruolo'],
            'gruppo_equivalenza'=> $component['gruppo_equivalenza'],
            'articolo_id'       => intval( $component['articolo_id_scelto'] ),
            'articolo_nome'     => $component['articolo_nome'] ?? '',
            'articolo_codice'   => $component['articolo_codice'] ?? '',
            'qty'               => intval( $component['qty'] ),
        ];
    }

    return array_values( $kits_map );
}

function bsn_render_noleggio_kits_block( $noleggio_id ) {
    $kits = bsn_get_noleggio_kits( $noleggio_id );
    if ( empty( $kits ) ) {
        return '';
    }

    ob_start();
    ?>
    <div class="bsn-doc-section" style="margin-bottom: 15px;">
        <h3 style="font-size: 12px; background: #000; color: #fff; padding: 3px 8px; margin-bottom: 8px;">KIT PRESENTI NEL NOLEGGIO</h3>
        <?php foreach ( $kits as $kit ) : ?>
            <div style="margin-bottom: 8px;">
                <strong><?php echo esc_html( $kit['kit_label'] ?: $kit['nome_kit'] ); ?></strong>
                <ul style="margin: 5px 0 0 15px; padding: 0; font-size: 10px;">
                    <?php foreach ( $kit['components'] as $comp ) : ?>
                        <li>
                            <?php
                            $label = esc_html( $comp['ruolo'] );
                            $codice = $comp['articolo_codice'] ? '[' . $comp['articolo_codice'] . '] ' : '';
                            $nome_articolo = $comp['articolo_nome'] ?: ( 'Articolo #' . $comp['articolo_id'] );
                            echo $label . ' – ' . esc_html( $comp['qty'] ) . 'x ' . esc_html( $codice . $nome_articolo );
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

function bsn_parse_noleggio_kits_payload( $kits_json, $articoli_puliti ) {
    global $wpdb;

    if ( empty( $kits_json ) ) {
        return [];
    }

    $decoded = json_decode( $kits_json, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
        return new WP_Error( 'bsn_kit_json_invalid', 'Dati kit non validi.', [ 'status' => 400 ] );
    }

    $kit_ids = [];
    foreach ( $decoded as $kit ) {
        $kit_id = isset( $kit['kit_id'] ) ? intval( $kit['kit_id'] ) : 0;
        if ( $kit_id > 0 ) {
            $kit_ids[] = $kit_id;
        }
    }

    if ( empty( $kit_ids ) ) {
        return [];
    }

    $kit_defs = bsn_get_kits_with_components( $kit_ids );
    $kit_defs_map = [];
    $allowed_map = [];

    foreach ( $kit_defs as $kit_def ) {
        $kit_id = intval( $kit_def['id'] );
        $kit_defs_map[ $kit_id ] = $kit_def;
        foreach ( $kit_def['components'] as $comp ) {
            $ruolo = $comp['ruolo'];
            $gruppo = $comp['gruppo_equivalenza'];
            if ( ! isset( $allowed_map[ $kit_id ] ) ) {
                $allowed_map[ $kit_id ] = [];
            }
            if ( ! isset( $allowed_map[ $kit_id ][ $ruolo ] ) ) {
                $allowed_map[ $kit_id ][ $ruolo ] = [];
            }
            if ( ! isset( $allowed_map[ $kit_id ][ $ruolo ][ $gruppo ] ) ) {
                $allowed_map[ $kit_id ][ $ruolo ][ $gruppo ] = [];
            }
            $allowed_map[ $kit_id ][ $ruolo ][ $gruppo ][] = intval( $comp['articolo_id'] );
        }
    }

    $articoli_ids = array_map( function( $a ) { return intval( $a['id'] ); }, $articoli_puliti );
    $articoli_ids = array_values( array_unique( array_filter( $articoli_ids ) ) );

    $kits_payload = [];
    $kit_component_ids = [];

    foreach ( $decoded as $kit ) {
        $kit_id = isset( $kit['kit_id'] ) ? intval( $kit['kit_id'] ) : 0;
        if ( ! $kit_id || ! isset( $kit_defs_map[ $kit_id ] ) ) {
            return new WP_Error( 'bsn_kit_non_trovato', 'Kit non valido o mancante.', [ 'status' => 404 ] );
        }

        $kit_label = isset( $kit['kit_label'] ) ? sanitize_text_field( $kit['kit_label'] ) : '';
        $components = isset( $kit['components'] ) && is_array( $kit['components'] ) ? $kit['components'] : [];

        if ( empty( $components ) ) {
            return new WP_Error( 'bsn_kit_componenti_mancanti', 'Componenti kit mancanti.', [ 'status' => 400 ] );
        }

        $components_payload = [];
        foreach ( $components as $comp ) {
            $ruolo = isset( $comp['ruolo'] ) ? sanitize_text_field( $comp['ruolo'] ) : '';
            $gruppo = isset( $comp['gruppo_equivalenza'] ) ? sanitize_text_field( $comp['gruppo_equivalenza'] ) : '';
            $articolo_id = isset( $comp['articolo_id'] ) ? intval( $comp['articolo_id'] ) : 0;
            $qty = isset( $comp['qty'] ) ? intval( $comp['qty'] ) : 0;

            if ( $ruolo === '' || $gruppo === '' || $articolo_id <= 0 || $qty <= 0 ) {
                return new WP_Error( 'bsn_kit_componenti_invalidi', 'Componenti kit non validi.', [ 'status' => 400 ] );
            }

            $allowed = $allowed_map[ $kit_id ][ $ruolo ][ $gruppo ] ?? [];
            if ( empty( $allowed ) || ! in_array( $articolo_id, $allowed, true ) ) {
                return new WP_Error( 'bsn_kit_componenti_non_equivalenti', 'Componente kit non appartiene al gruppo selezionato.', [ 'status' => 400 ] );
            }

            $components_payload[] = [
                'ruolo'             => $ruolo,
                'gruppo_equivalenza'=> $gruppo,
                'articolo_id'       => $articolo_id,
                'qty'               => $qty,
            ];

            $kit_component_ids[] = $articolo_id;
        }

        $kits_payload[] = [
            'kit_id'           => $kit_id,
            'kit_label'        => $kit_label ?: ( $kit_defs_map[ $kit_id ]['nome_kit'] ?? '' ),
            'components'       => $components_payload,
            'articolo_kit_id'  => intval( $kit_defs_map[ $kit_id ]['articolo_kit_id'] ),
        ];
    }

    $duplicati = array_diff_assoc( $kit_component_ids, array_unique( $kit_component_ids ) );
    if ( ! empty( $duplicati ) ) {
        return new WP_Error( 'bsn_kit_componenti_duplicati', 'Componente kit duplicato nel noleggio.', [ 'status' => 400 ] );
    }

    $intersezione = array_intersect( $kit_component_ids, $articoli_ids );
    if ( ! empty( $intersezione ) ) {
        return new WP_Error( 'bsn_kit_componenti_in_noleggio', 'Un componente kit è già presente tra gli articoli del noleggio.', [ 'status' => 400 ] );
    }

    return $kits_payload;
}

function bsn_merge_articoli_con_kit_componenti( $articoli_puliti, $kits_payload ) {
    $merged = [];

    foreach ( $articoli_puliti as $art ) {
        $id = intval( $art['id'] );
        $qty = intval( $art['qty'] );
        if ( $id > 0 && $qty > 0 ) {
            if ( ! isset( $merged[ $id ] ) ) {
                $merged[ $id ] = [ 'id' => $id, 'qty' => 0 ];
            }
            $merged[ $id ]['qty'] += $qty;
        }
    }

    foreach ( $kits_payload as $kit ) {
        foreach ( $kit['components'] as $comp ) {
            $id = intval( $comp['articolo_id'] );
            $qty = intval( $comp['qty'] );
            if ( $id > 0 && $qty > 0 ) {
                if ( ! isset( $merged[ $id ] ) ) {
                    $merged[ $id ] = [ 'id' => $id, 'qty' => 0 ];
                }
                $merged[ $id ]['qty'] += $qty;
            }
        }
    }

    return array_values( $merged );
}

function bsn_save_noleggio_kits( $noleggio_id, $kits_payload ) {
    global $wpdb;

    $table_noleggio_kits = $wpdb->prefix . 'bsn_noleggio_kits';
    $table_noleggio_kit_componenti = $wpdb->prefix . 'bsn_noleggio_kit_componenti';

    $existing = $wpdb->get_results(
        $wpdb->prepare( "SELECT id FROM $table_noleggio_kits WHERE noleggio_id = %s", $noleggio_id ),
        ARRAY_A
    );
    if ( ! empty( $existing ) ) {
        $existing_ids = array_map( function( $row ) { return intval( $row['id'] ); }, $existing );
        $placeholders = implode( ',', array_fill( 0, count( $existing_ids ), '%d' ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM $table_noleggio_kit_componenti WHERE noleggio_kit_id IN ($placeholders)", $existing_ids ) );
        $wpdb->delete( $table_noleggio_kits, [ 'noleggio_id' => $noleggio_id ], [ '%s' ] );
    }

    if ( empty( $kits_payload ) ) {
        return;
    }

    foreach ( $kits_payload as $kit ) {
        $wpdb->insert(
            $table_noleggio_kits,
            [
                'noleggio_id' => $noleggio_id,
                'kit_id'      => $kit['kit_id'],
                'kit_label'   => $kit['kit_label'],
            ],
            [ '%s', '%d', '%s' ]
        );
        $noleggio_kit_id = (int) $wpdb->insert_id;

        foreach ( $kit['components'] as $component ) {
            $wpdb->insert(
                $table_noleggio_kit_componenti,
                [
                    'noleggio_kit_id'   => $noleggio_kit_id,
                    'ruolo'             => $component['ruolo'],
                    'gruppo_equivalenza'=> $component['gruppo_equivalenza'],
                    'articolo_id_scelto'=> $component['articolo_id'],
                    'qty'               => $component['qty'],
                ],
                [ '%d', '%s', '%s', '%d', '%d' ]
            );
        }
    }
}

function bsn_get_kit_component_usage_map( $noleggio_ids ) {
    global $wpdb;

    $noleggio_ids = array_values( array_unique( array_filter( $noleggio_ids ) ) );
    if ( empty( $noleggio_ids ) ) {
        return [];
    }

    $table_noleggio_kits = $wpdb->prefix . 'bsn_noleggio_kits';
    $table_noleggio_kit_componenti = $wpdb->prefix . 'bsn_noleggio_kit_componenti';

    $placeholders = implode( ',', array_fill( 0, count( $noleggio_ids ), '%s' ) );
    $sql = "SELECT nkc.articolo_id_scelto AS articolo_id, SUM(nkc.qty) AS qty_totale
            FROM $table_noleggio_kit_componenti nkc
            INNER JOIN $table_noleggio_kits nk ON nkc.noleggio_kit_id = nk.id
            WHERE nk.noleggio_id IN ($placeholders)
            GROUP BY nkc.articolo_id_scelto";

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $noleggio_ids ) );
    $map = [];
    foreach ( $rows as $row ) {
        $map[ intval( $row->articolo_id ) ] = intval( $row->qty_totale );
    }

    return $map;
}

add_action('rest_api_init', function () {
    register_rest_route('bsn/v1', '/kits', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_kits_get',
        'permission_callback' => 'bsn_check_admin',
    ]);

    register_rest_route('bsn/v1', '/kits', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_kits_post',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'nome_kit'        => [ 'required' => true, 'type' => 'string' ],
            'articolo_kit_id' => [ 'required' => true, 'type' => 'integer' ],
        ],
    ]);

    register_rest_route('bsn/v1', '/kits/update', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_kits_update',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id'              => [ 'required' => true, 'type' => 'integer' ],
            'nome_kit'        => [ 'required' => true, 'type' => 'string' ],
            'articolo_kit_id' => [ 'required' => true, 'type' => 'integer' ],
        ],
    ]);

    register_rest_route('bsn/v1', '/kits/delete', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_kits_delete',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id' => [ 'required' => true, 'type' => 'integer' ],
        ],
    ]);
});

/* ==== PROFILI SCONTI (precompilazione) ==== */

/**
 * GET /profili-sconti
 */
function bsn_api_profili_sconti_get( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_profili_sconti';

    $rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY nome ASC", ARRAY_A );
    return rest_ensure_response( array( 'profili' => $rows ) );
}

/**
 * POST /profili-sconti
 * param: nome, sconto_standard, sconto_fidato, sconto_premium, sconto_service, sconto_collaboratori
 */
function bsn_api_profili_sconti_create( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_profili_sconti';

    $nome = trim( (string) $request->get_param( 'nome' ) );
    if ( $nome === '' ) {
        return new WP_Error( 'missing_nome', 'Nome profilo obbligatorio.', array( 'status' => 400 ) );
    }

    // esiste già?
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM $table WHERE nome = %s LIMIT 1",
        $nome
    ) );
    if ( $exists ) {
        return new WP_Error( 'duplicato', 'Un profilo con questo nome esiste già. Usa un nome diverso.', array( 'status' => 409 ) );
    }

    $data = array(
        'nome'                 => $nome,
        'sconto_standard'      => floatval( $request->get_param( 'sconto_standard' ) ),
        'sconto_fidato'        => floatval( $request->get_param( 'sconto_fidato' ) ),
        'sconto_premium'       => floatval( $request->get_param( 'sconto_premium' ) ),
        'sconto_service'       => floatval( $request->get_param( 'sconto_service' ) ),
        'sconto_collaboratori' => floatval( $request->get_param( 'sconto_collaboratori' ) ),
    );

    $wpdb->insert(
        $table,
        $data,
        array( '%s', '%f', '%f', '%f', '%f', '%f' )
    );

    if ( ! $wpdb->insert_id ) {
        return new WP_Error( 'insert_failed', 'Impossibile salvare il profilo.', array( 'status' => 500 ) );
    }

    return rest_ensure_response( array(
        'success' => true,
        'id'      => $wpdb->insert_id,
        'nome'    => $nome,
        'profile' => $data,
    ) );
}

/**
 * POST /profili-sconti/delete
 * param: id
 */
function bsn_api_profili_sconti_delete( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_profili_sconti';

    $id = intval( $request->get_param( 'id' ) );
    if ( ! $id ) {
        return new WP_Error( 'missing_id', 'ID profilo obbligatorio.', array( 'status' => 400 ) );
    }

    $profilo = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ),
        ARRAY_A
    );
    if ( ! $profilo ) {
        return new WP_Error( 'not_found', 'Profilo non trovato.', array( 'status' => 404 ) );
    }

    $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

    return rest_ensure_response( array(
        'success' => true,
        'id'      => $id,
        'nome'    => $profilo['nome'],
    ) );
}

/* ==== NOLEGGI ==== */

function bsn_sync_noleggi_ritardo() {
    global $wpdb;
    $table_noleggi = $wpdb->prefix . 'bs_noleggi';

    $oggi = current_time( 'Y-m-d' );
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table_noleggi
             SET stato = 'ritardo'
             WHERE stato = 'attivo'
               AND data_fine IS NOT NULL
               AND DATE(data_fine) < %s",
            $oggi
        )
    );
}

function bsn_api_noleggi_get($request) {
    global $wpdb;
    $table_noleggi  = $wpdb->prefix . 'bs_noleggi';
    $table_clienti  = $wpdb->prefix . 'bs_clienti';
    $table_articoli = $wpdb->prefix . 'bs_articoli';

    bsn_sync_noleggi_ritardo();

    $search = trim( (string) $request->get_param( 'search' ) );
    $stato  = sanitize_text_field( (string) $request->get_param( 'stato' ) );
    $limit  = absint( $request->get_param( 'limit' ) );
    if ( $limit < 1 ) {
        $limit = 12;
    }
    if ( $limit > 100 ) {
        $limit = 100;
    }

    $where  = [];
    $params = [];

    if ( $stato !== '' ) {
        $where[]  = 'n.stato = %s';
        $params[] = $stato;
    }

    if ( $search !== '' ) {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $where[] = '(CAST(n.id AS CHAR) LIKE %s OR c.nome LIKE %s OR n.note LIKE %s OR n.articoli LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT n.*, c.nome AS cliente_nome
            FROM $table_noleggi n
             LEFT JOIN $table_clienti c ON n.cliente_id = c.id";

    if ( ! empty( $where ) ) {
        $sql .= ' WHERE ' . implode( ' AND ', $where );
    }

    $sql .= ' ORDER BY n.data_richiesta DESC';

    if ( $search === '' ) {
        $sql .= ' LIMIT %d';
        $params[] = $limit;
    } else {
        $sql .= ' LIMIT %d';
        $params[] = 100;
    }

    if ( ! empty( $params ) ) {
        $sql = $wpdb->prepare( $sql, $params );
    }

    $noleggi = $wpdb->get_results($sql);

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

    $table_ticket   = $wpdb->prefix . 'bs_ticket';
    $ticket_map = [];
    $ticket_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_ticket ) );
    if ( $ticket_table_exists === $table_ticket && ! empty( $ids_articoli ) ) {
        $placeholders = implode(',', array_fill(0, count($ids_articoli), '%d'));
        $sql_ticket = "SELECT articolo_id, COUNT(*) AS totale FROM $table_ticket WHERE stato = 'aperto' AND articolo_id IN ($placeholders) GROUP BY articolo_id";
        $rows_ticket = $wpdb->get_results( $wpdb->prepare( $sql_ticket, $ids_articoli ) );
        foreach ( $rows_ticket as $row ) {
            $ticket_map[ intval( $row->articolo_id ) ] = intval( $row->totale );
        }
    }

    $output = [];
    foreach ($noleggi as $n) {
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

        $data_da_it = '';
        $data_a_it  = '';
        $data_da_raw = '';
        $data_a_raw  = '';
        if (!empty($n->data_inizio)) {
            $ts = strtotime($n->data_inizio);
            if ($ts) {
                $data_da_it = date('d/m/Y', $ts);
                $data_da_raw = date('Y-m-d', $ts);
            }
        }
        if (!empty($n->data_fine)) {
            $ts = strtotime($n->data_fine);
            if ($ts) {
                $data_a_it = date('d/m/Y', $ts);
                $data_a_raw = date('Y-m-d', $ts);
            }
        }

        $has_ticket = false;
        if (!empty($n->articoli)) {
            $articoli_ticket = json_decode($n->articoli, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($articoli_ticket)) {
                foreach ($articoli_ticket as $a) {
                    $id_art = isset($a['id']) ? intval($a['id']) : 0;
                    if ($id_art > 0 && ! empty($ticket_map[$id_art])) {
                        $has_ticket = true;
                        break;
                    }
                }
            }
        }

        $output[] = [
            'id'                 => $n->id,
            'cliente_nome'       => $n->cliente_nome,
            'data_da'            => $data_da_it,
            'data_a'             => $data_a_it,
            'data_inizio_raw'    => $data_da_raw,
            'data_fine_raw'      => $data_a_raw,
            'stato'              => $n->stato,
            'articoli_riassunto' => $articoli_riassunto,
            'ticket_alert'       => $has_ticket ? 1 : 0,
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
    $ticket_qty_map  = [];

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

    // Ticket aperti (se tabella esiste)
    $table_ticket = $wpdb->prefix . 'bs_ticket';
    $ticket_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_ticket ) );
    if ( $ticket_table_exists === $table_ticket ) {
        $placeholders_ticket = implode( ',', array_fill( 0, count( $ids_articoli ), '%d' ) );
        $sql_ticket = "SELECT articolo_id, SUM(qty) AS qty_ticket
                       FROM $table_ticket
                       WHERE stato = 'aperto'
                       AND articolo_id IN ($placeholders_ticket)
                       GROUP BY articolo_id";
        $rows_ticket = $wpdb->get_results( $wpdb->prepare( $sql_ticket, $ids_articoli ) );
        foreach ( $rows_ticket as $row ) {
            $ticket_qty_map[ intval( $row->articolo_id ) ] = intval( $row->qty_ticket );
        }
    }

    $ticket_map = [];
    $ticket_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_ticket ) );
    if ( $ticket_table_exists === $table_ticket && ! empty( $ids_articoli ) ) {
        $placeholders = implode(',', array_fill(0, count($ids_articoli), '%d'));
        $sql_ticket = "SELECT articolo_id, COUNT(*) AS totale
                       FROM $table_ticket
                       WHERE stato = 'aperto'
                       AND articolo_id IN ($placeholders)
                       GROUP BY articolo_id";
        $rows_ticket = $wpdb->get_results( $wpdb->prepare( $sql_ticket, $ids_articoli ) );
        foreach ( $rows_ticket as $row ) {
            $ticket_map[ intval( $row->articolo_id ) ] = intval( $row->totale );
        }
    }
    
    $data_inizio_full = $data_inizio . ' 00:00:00';
    $data_fine_full   = $data_fine   . ' 23:59:59';

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

    $existing_ids = [];
    if ( ! empty( $existing ) ) {
        foreach ( $existing as $ex ) {
            $existing_ids[] = $ex->id;
        }
    }
    $kit_usage_map = bsn_get_kit_component_usage_map( $existing_ids );

    foreach ( $articoli_puliti as $a ) {
        $id_art  = $a['id'];
        $qty_new = $a['qty'];

        if ( ! isset( $mappa_disponibile[ $id_art ] ) ) {
            continue;
        }

        $qty_disp = $mappa_disponibile[ $id_art ]['qty_disponibile'];

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

        if ( isset( $kit_usage_map[ $id_art ] ) ) {
            $qty_in_uso += $kit_usage_map[ $id_art ];
        }

        $qty_totale = $qty_in_uso + $qty_new;
        if ( $qty_totale > $qty_disp ) {
            $info        = $mappa_disponibile[ $id_art ];
            $qty_libere  = max( 0, $qty_disp - $qty_in_uso );
            $qty_ticket  = isset( $ticket_qty_map[ $id_art ] ) ? $ticket_qty_map[ $id_art ] : 0;
            $sovrapposizioni[] = sprintf(
                '%dx richiesti per [%s] %s (a stock: %d, già in uso: %d, disponibilità: %d, in tiket: %d)',
                $qty_new,
                $info['codice'],
                $info['nome'],
                $qty_disp,
                $qty_in_uso,
                $qty_libere,
                $qty_ticket
            );
        }
    }

    return $sovrapposizioni;
}

/**
 * Endpoint batch per stati alert in anteprima noleggio.
 * Restituisce disponibilità + ticket aperti per ciascun articolo.
 */
function bsn_api_noleggi_alert_stato( WP_REST_Request $request ) {
    global $wpdb;

    $table_noleggi  = $wpdb->prefix . 'bs_noleggi';
    $table_articoli = $wpdb->prefix . 'bs_articoli';
    $table_ticket   = $wpdb->prefix . 'bs_ticket';

    $data_inizio = sanitize_text_field( $request->get_param( 'data_inizio' ) );
    $data_fine   = sanitize_text_field( $request->get_param( 'data_fine' ) );
    $items       = $request->get_param( 'items' );
    $exclude_id  = sanitize_text_field( $request->get_param( 'exclude_noleggio_id' ) );

    if ( empty( $data_inizio ) || empty( $data_fine ) || empty( $items ) || ! is_array( $items ) ) {
        return rest_ensure_response( [] );
    }

    $requested_map = [];
    foreach ( $items as $item ) {
        $art_id = isset( $item['articolo_id'] ) ? intval( $item['articolo_id'] ) : 0;
        $qty    = isset( $item['qty'] ) ? intval( $item['qty'] ) : 0;
        if ( $art_id > 0 && $qty > 0 ) {
            if ( ! isset( $requested_map[ $art_id ] ) ) {
                $requested_map[ $art_id ] = 0;
            }
            $requested_map[ $art_id ] += $qty;
        }
    }

    if ( empty( $requested_map ) ) {
        return rest_ensure_response( [] );
    }

    $ids_articoli = array_keys( $requested_map );
    $placeholders = implode( ',', array_fill( 0, count( $ids_articoli ), '%d' ) );

    $art_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, qty_disponibile FROM $table_articoli WHERE id IN ($placeholders)",
            $ids_articoli
        )
    );

    $disponibili = [];
    foreach ( $art_rows as $row ) {
        $disponibili[ intval( $row->id ) ] = intval( $row->qty_disponibile );
    }

    $ticket_map = [];
    $ticket_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_ticket ) );
    if ( $ticket_table_exists === $table_ticket ) {
        $rows_ticket = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT articolo_id, COUNT(*) AS totale
                 FROM $table_ticket
                 WHERE stato = 'aperto'
                   AND articolo_id IN ($placeholders)
                 GROUP BY articolo_id",
                $ids_articoli
            )
        );
        foreach ( $rows_ticket as $row ) {
            $ticket_map[ intval( $row->articolo_id ) ] = intval( $row->totale ) > 0;
        }
    }

    $data_inizio_full = $data_inizio . ' 00:00:00';
    $data_fine_full   = $data_fine   . ' 23:59:59';

    if ( ! empty( $exclude_id ) ) {
        $sql_nol = "SELECT id, articoli, data_inizio
            FROM $table_noleggi
            WHERE id <> %s
              AND data_inizio <= %s
              AND data_fine   >= %s
              AND stato IN ('bozza', 'attivo', 'ritardo')
            ORDER BY data_inizio ASC";
        $existing = $wpdb->get_results( $wpdb->prepare( $sql_nol, $exclude_id, $data_fine_full, $data_inizio_full ) );
    } else {
        $sql_nol = "SELECT id, articoli, data_inizio
            FROM $table_noleggi
            WHERE data_inizio <= %s
              AND data_fine   >= %s
              AND stato IN ('bozza', 'attivo', 'ritardo')
            ORDER BY data_inizio ASC";
        $existing = $wpdb->get_results( $wpdb->prepare( $sql_nol, $data_fine_full, $data_inizio_full ) );
    }

    $qty_in_uso = array_fill_keys( $ids_articoli, 0 );
    $first_conflict = array_fill_keys( $ids_articoli, '' );

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
                $id_art = isset( $ea['id'] ) ? intval( $ea['id'] ) : 0;
                $qty    = isset( $ea['qty'] ) ? intval( $ea['qty'] ) : 0;
                if ( $id_art > 0 && $qty > 0 && isset( $qty_in_uso[ $id_art ] ) ) {
                    $qty_in_uso[ $id_art ] += $qty;
                    if ( empty( $first_conflict[ $id_art ] ) ) {
                        $first_conflict[ $id_art ] = $ex->id;
                    }
                }
            }
        }
    }

    $existing_ids = [];
    if ( ! empty( $existing ) ) {
        foreach ( $existing as $ex ) {
            $existing_ids[] = $ex->id;
        }
    }
    $kit_usage_map = bsn_get_kit_component_usage_map( $existing_ids );
    foreach ( $kit_usage_map as $art_id => $qty ) {
        if ( isset( $qty_in_uso[ $art_id ] ) ) {
            $qty_in_uso[ $art_id ] += $qty;
        }
    }

    $response = [];
    foreach ( $requested_map as $art_id => $qty_requested ) {
        $qty_disp = isset( $disponibili[ $art_id ] ) ? $disponibili[ $art_id ] : 0;
        $qty_used = isset( $qty_in_uso[ $art_id ] ) ? $qty_in_uso[ $art_id ] : 0;
        $qty_available = max( 0, $qty_disp - $qty_used );
        $is_available = ( $qty_requested <= $qty_available );

        $response[ $art_id ] = [
            'is_available' => $is_available,
            'first_conflict_noleggio_id' => $is_available ? '' : ( $first_conflict[ $art_id ] ?? '' ),
            'has_open_ticket' => ! empty( $ticket_map[ $art_id ] ),
            'qty_requested' => $qty_requested,
            'qty_available' => $qty_available,
        ];
    }

    return rest_ensure_response( $response );
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
    $metodo_pagamento = sanitize_text_field($request->get_param('metodo_pagamento'));

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

 $kits_payload = bsn_parse_noleggio_kits_payload( $request->get_param( 'kits' ), $articoli );
    if ( is_wp_error( $kits_payload ) ) {
        return $kits_payload;
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
    $sovrapposizioni = [];
    if ( $stato !== 'preventivo' ) {
        $articoli_disponibilita = bsn_merge_articoli_con_kit_componenti( $articoli, $kits_payload );
        $sovrapposizioni = bsn_calcola_sovrapposizioni_noleggio(
            $articoli_disponibilita,
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
        'metodo_pagamento' => $metodo_pagamento,
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

    bsn_save_noleggio_kits( $noleggio_id, $kits_payload );

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

    $table_noleggio_kits = $wpdb->prefix . 'bsn_noleggio_kits';
    $table_noleggio_kit_componenti = $wpdb->prefix . 'bsn_noleggio_kit_componenti';
    $kit_rows = $wpdb->get_results(
        $wpdb->prepare( "SELECT id FROM $table_noleggio_kits WHERE noleggio_id = %s", $id ),
        ARRAY_A
    );
    if ( ! empty( $kit_rows ) ) {
        $kit_ids = array_map( function( $row ) { return intval( $row['id'] ); }, $kit_rows );
        $placeholders = implode( ',', array_fill( 0, count( $kit_ids ), '%d' ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM $table_noleggio_kit_componenti WHERE noleggio_kit_id IN ($placeholders)", $kit_ids ) );
        $wpdb->delete( $table_noleggio_kits, [ 'noleggio_id' => $id ], [ '%s' ] );
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
    $metodo_pagamento = sanitize_text_field( $request->get_param('metodo_pagamento') );
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

    $kits_payload = bsn_parse_noleggio_kits_payload( $request->get_param( 'kits' ), $articoli_puliti );
    if ( is_wp_error( $kits_payload ) ) {
        return $kits_payload;
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
    $sovrapposizioni = [];
    $stato_effettivo = $stato ?: 'bozza';
    if ( $stato_effettivo !== 'preventivo' ) {
        $articoli_disponibilita = bsn_merge_articoli_con_kit_componenti( $articoli_puliti, $kits_payload );
        $sovrapposizioni = bsn_calcola_sovrapposizioni_noleggio(
            $articoli_disponibilita,
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
    }

        // ----- UPDATE NOLEGGIO -----
        $data_update = [
            'cliente_id'         => $cliente_id,
            'data_inizio'        => $data_inizio . ' 00:00:00',
            'data_fine'          => $data_fine . ' 23:59:59',
            'stato'              => $stato_effettivo,
            'note'               => $note,
            'totale_calcolato'   => $totale_calcolato,
            'sconto_globale'     => $sconto_globale, // << AGGIUNTO: allinea alla POST
            'metodo_pagamento'   => $metodo_pagamento,
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

    bsn_save_noleggio_kits( $id_noleggio, $kits_payload );

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

    $kits_out = bsn_get_noleggio_kits( $n->id );

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
        'metodo_pagamento'   => $n->metodo_pagamento,
        'causale_trasporto'  => $n->causale_trasporto,
        'sconto_globale' => isset( $n->sconto_globale ) ? (float) $n->sconto_globale : 0.0,
        'articoli'       => $articoli_out,
        'kits'           => $kits_out,
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
    bsn_ensure_noleggi_columns();

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
        'luogo_destinazione' => isset( $noleggio['luogo_destinazione'] ) ? $noleggio['luogo_destinazione'] : '',
        'trasporto_mezzo'    => isset( $noleggio['trasporto_mezzo'] ) ? $noleggio['trasporto_mezzo'] : '',
        'cauzione'           => isset( $noleggio['cauzione'] ) ? $noleggio['cauzione'] : '',
        'causale_trasporto'  => isset( $noleggio['causale_trasporto'] ) ? $noleggio['causale_trasporto'] : '',
        'consenso_privacy'   => 0,
        'consenso_condizioni' => 0,
        'firma_url'          => null,
        'firma_base64'       => '',
        'firma_data'         => null,
        'op_preparazione_documento' => '',
        'op_preparazione_materiale' => '',
        'op_consegna_materiale'     => '',
        'op_rientro_materiale'      => '',
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
        '%s', // luogo_destinazione
        '%s', // trasporto_mezzo
        '%s', // cauzione
        '%s', // causale_trasporto
        '%d', // consenso_privacy
        '%d', // consenso_condizioni
        '%s', // firma_url
        '%s', // firma_base64
        '%s', // firma_data
        '%s', // op_preparazione_documento
        '%s', // op_preparazione_materiale
        '%s', // op_consegna_materiale
        '%s', // op_rientro_materiale
    ];

    $result = $wpdb->insert( $table_noleggi, $data_insert, $formati );

    if ( ! $result ) {
        return new WP_Error( 'bsn_duplica_noleggio_error', 'Errore nel duplicare il noleggio.', [ 'status' => 500 ] );
    }

    $kits_originali = bsn_get_noleggio_kits( $id_originale );
    if ( ! empty( $kits_originali ) ) {
        $kits_payload = [];
        foreach ( $kits_originali as $kit ) {
            $components_payload = [];
            foreach ( $kit['components'] as $comp ) {
                $components_payload[] = [
                    'ruolo'             => $comp['ruolo'],
                    'gruppo_equivalenza'=> $comp['gruppo_equivalenza'],
                    'articolo_id'       => $comp['articolo_id'],
                    'qty'               => $comp['qty'],
                ];
            }
            $kits_payload[] = [
                'kit_id'          => $kit['kit_id'],
                'kit_label'       => $kit['kit_label'],
                'components'      => $components_payload,
                'articolo_kit_id' => $kit['articolo_kit_id'],
            ];
        }
        bsn_save_noleggio_kits( $id_nuovo, $kits_payload );
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

// === Ticket API ===
add_action( 'rest_api_init', function () {
    register_rest_route( 'bsn/v1', '/noleggi/duplica', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_noleggi_duplica',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id' => [ 'required' => true, 'type' => 'string' ],
        ],
    ] );

    register_rest_route( 'bsn/v1', '/tickets', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_tickets_get',
        'permission_callback' => 'bsn_check_admin',
    ] );

    register_rest_route( 'bsn/v1', '/tickets', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_tickets_post',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'noleggio_id' => [ 'required' => false, 'type' => 'string' ],
            'articolo_id' => [ 'required' => true,  'type' => 'integer' ],
            'qty'         => [ 'required' => true,  'type' => 'integer' ],
            'tipo'        => [ 'required' => true,  'type' => 'string'  ],
            'note'        => [ 'required' => false, 'type' => 'string'  ],
            'foto'        => [ 'required' => false, 'type' => 'string'  ],
        ],
    ] );

    register_rest_route( 'bsn/v1', '/tickets/chiudi', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_tickets_chiudi',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'ticket_id' => [ 'required' => true, 'type' => 'integer' ],
        ],
    ] );

    register_rest_route( 'bsn/v1', '/tickets/update', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_tickets_update',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'ticket_id'  => [ 'required' => true,  'type' => 'integer' ],
            'articolo_id'=> [ 'required' => true,  'type' => 'integer' ],
            'qty'        => [ 'required' => true,  'type' => 'integer' ],
            'tipo'       => [ 'required' => true,  'type' => 'string'  ],
            'note'       => [ 'required' => false, 'type' => 'string'  ],
            'stato'      => [ 'required' => false, 'type' => 'string'  ],
            'foto'       => [ 'required' => false, 'type' => 'string'  ],
        ],
    ] );

    register_rest_route( 'bsn/v1', '/noleggi/alert-stato', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_noleggi_alert_stato',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'data_inizio' => [ 'required' => true, 'type' => 'string' ],
            'data_fine'   => [ 'required' => true, 'type' => 'string' ],
            'items'       => [ 'required' => true, 'type' => 'array' ],
            'exclude_noleggio_id' => [ 'required' => false, 'type' => 'string' ],
        ],
    ] );

    register_rest_route( 'bsn/v1', '/noleggi/pdf', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_noleggi_pdf_get',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id' => [ 'required' => true, 'type' => 'string' ],
        ],
    ] );

    register_rest_route( 'bsn/v1', '/noleggi/preventivo/pdf', [
        'methods'             => 'GET',
        'callback'            => 'bsn_api_noleggi_preventivo_pdf_get',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id' => [ 'required' => true, 'type' => 'string' ],
        ],
    ] );

    register_rest_route( 'bsn/v1', '/noleggi/preventivo/invia', [
        'methods'             => 'POST',
        'callback'            => 'bsn_api_noleggi_preventivo_invia',
        'permission_callback' => 'bsn_check_admin',
        'args'                => [
            'id' => [ 'required' => true, 'type' => 'string' ],
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
    bsn_ensure_noleggi_columns();

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

    if ( ! $consenso_privacy || ! $consenso_condizioni ) {
        return new WP_Error(
            'bsn_finalizza_consenso_mancante',
            'Per finalizzare il noleggio servono entrambi i consensi.',
            [ 'status' => 400 ]
        );
    }

    if ( empty( $op_documento ) || empty( $op_materiale ) ) {
        return new WP_Error(
            'bsn_finalizza_operatori_mancanti',
            'Inserisci gli operatori di preparazione documento e materiale.',
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
        'firma_base64'              => $firma_base64,
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

    $pdf_info = bsn_generate_noleggio_pdf( $id_noleggio, true );
    $table_clienti = $wpdb->prefix . 'bs_clienti';
    $cliente = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_clienti WHERE id = %d",
            $noleggio['cliente_id']
        ),
        ARRAY_A
    );

    if ( $cliente ) {
        bsn_invia_email_noleggio_finalizzato( $noleggio, $cliente, $pdf_info );
    } else {
        bsn_invia_email_noleggio_finalizzato( $noleggio, null, $pdf_info );
    }

    return rest_ensure_response( [
        'success'   => true,
        'id'        => $id_noleggio,
        'stato'     => 'attivo',
        'firma_url' => $firma_url,
    ] );
}

// [BSN_PDF_EMAIL_START]
/**
 * Genera l'HTML del documento di finalizzazione per PDF.
 */
function bsn_get_finalizza_document_html( $noleggio_id ) {
    ob_start();
    bsn_render_finalizza_noleggio_page(
        [
            'render_document_only' => true,
            'noleggio_id' => $noleggio_id,
        ]
    );
    return ob_get_clean();
}

/**
 * Restituisce path e url della cartella PDF.
 */
function bsn_get_pdf_directory() {
    $upload_dir = wp_upload_dir();
    if ( ! empty( $upload_dir['error'] ) ) {
        return false;
    }

    $dir = trailingslashit( $upload_dir['basedir'] ) . 'bsn-pdf';
    $url = trailingslashit( $upload_dir['baseurl'] ) . 'bsn-pdf';

    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
    }

    return [
        'dir' => $dir,
        'url' => $url,
    ];
}

/**
 * Genera il PDF del noleggio e lo salva su filesystem.
 */
function bsn_generate_noleggio_pdf( $noleggio_id, $force = false ) {
    $folder = bsn_get_pdf_directory();
    if ( ! $folder ) {
        return false;
    }

    $safe_id = preg_replace( '/[^A-Za-z0-9_\-]/', '_', $noleggio_id );
    $filename = 'noleggio-' . $safe_id . '.pdf';
    $path = trailingslashit( $folder['dir'] ) . $filename;
    $url = trailingslashit( $folder['url'] ) . $filename;

    if ( ! $force && file_exists( $path ) ) {
        return [ 'path' => $path, 'url' => $url ];
    }

    $html = bsn_get_finalizza_document_html( $noleggio_id );
    if ( empty( $html ) ) {
        error_log( 'BSN PDF: HTML vuoto per noleggio ' . $noleggio_id );
        return false;
    }

    if ( ! class_exists( 'Dompdf\\Dompdf' ) ) {
        $autoload_path = bsn_get_dompdf_autoload_path();
        if ( empty( $autoload_path ) ) {
            $autoload_path = bsn_download_dompdf_library();
        }
        if ( is_wp_error( $autoload_path ) || empty( $autoload_path ) ) {
            return is_wp_error( $autoload_path )
                ? $autoload_path
                : new WP_Error(
                    'bsn_pdf_missing_library',
                    'Libreria PDF mancante. Dompdf non è stato trovato.',
                    [ 'status' => 500 ]
                );
        }
        require_once $autoload_path;
    }

    $options = new Dompdf\Options();
    $options->set( 'isRemoteEnabled', true );
    $options->set( 'isHtml5ParserEnabled', true );
    try {
        $dompdf = new Dompdf\Dompdf( $options );
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        $output = $dompdf->output();
        file_put_contents( $path, $output );
    } catch ( Exception $e ) {
        error_log( 'BSN PDF: errore generazione noleggio ' . $noleggio_id . ' - ' . $e->getMessage() );
        return false;
    }

    return [ 'path' => $path, 'url' => $url ];
}

/**
 * Genera l'HTML del preventivo per PDF.
 */
function bsn_get_preventivo_document_html( $noleggio_id ) {
    ob_start();
    bsn_render_preventivo_noleggio_page(
        [
            'render_document_only' => true,
            'noleggio_id'          => $noleggio_id,
        ]
    );
    return ob_get_clean();
}

/**
 * Genera il PDF del preventivo e lo salva su filesystem.
 */
function bsn_generate_preventivo_pdf( $noleggio_id, $force = false ) {
    $folder = bsn_get_pdf_directory();
    if ( ! $folder ) {
        return false;
    }

    $safe_id = preg_replace( '/[^A-Za-z0-9_\-]/', '_', $noleggio_id );
    $filename = 'preventivo-' . $safe_id . '.pdf';
    $path = trailingslashit( $folder['dir'] ) . $filename;
    $url = trailingslashit( $folder['url'] ) . $filename;

    if ( ! $force && file_exists( $path ) ) {
        return [ 'path' => $path, 'url' => $url ];
    }

    $html = bsn_get_preventivo_document_html( $noleggio_id );
    if ( empty( $html ) ) {
        error_log( 'BSN PDF: HTML vuoto per preventivo ' . $noleggio_id );
        return false;
    }

    if ( ! class_exists( 'Dompdf\\Dompdf' ) ) {
        $autoload_path = bsn_get_dompdf_autoload_path();
        if ( empty( $autoload_path ) ) {
            $autoload_path = bsn_download_dompdf_library();
        }
        if ( is_wp_error( $autoload_path ) || empty( $autoload_path ) ) {
            return is_wp_error( $autoload_path )
                ? $autoload_path
                : new WP_Error(
                    'bsn_pdf_missing_library',
                    'Libreria PDF mancante. Dompdf non è stato trovato.',
                    [ 'status' => 500 ]
                );
        }
        require_once $autoload_path;
    }

    $options = new Dompdf\Options();
    $options->set( 'isRemoteEnabled', true );
    $options->set( 'isHtml5ParserEnabled', true );
    try {
        $dompdf = new Dompdf\Dompdf( $options );
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        $output = $dompdf->output();
        file_put_contents( $path, $output );
    } catch ( Exception $e ) {
        error_log( 'BSN PDF: errore generazione preventivo ' . $noleggio_id . ' - ' . $e->getMessage() );
        return false;
    }

    return [ 'path' => $path, 'url' => $url ];
}

/**
 * Invia email di conferma noleggio con PDF allegato.
 */
function bsn_invia_email_noleggio_finalizzato( $noleggio, $cliente, $pdf_info ) {
    $pdf_path = is_array( $pdf_info ) && ! empty( $pdf_info['path'] ) ? $pdf_info['path'] : '';

    $cliente_nome = $cliente && ! empty( $cliente['nome'] ) ? $cliente['nome'] : 'Cliente';
    $cliente_email = $cliente && ! empty( $cliente['email'] ) ? $cliente['email'] : '';

    $data_inizio = ! empty( $noleggio['data_inizio'] ) ? date( 'd/m/Y', strtotime( $noleggio['data_inizio'] ) ) : '-';
    $data_fine = ! empty( $noleggio['data_fine'] ) ? date( 'd/m/Y', strtotime( $noleggio['data_fine'] ) ) : '-';

    $subject = 'Conferma noleggio #' . $noleggio['id'] . ' – Riepilogo e documento in PDF - Black Star Service Srl';

    $body = '
    <div style="font-family: Arial, sans-serif; font-size:14px; color:#222;">
        <p><img src="http://www.blackstarservice.it/wp-content/uploads/2021/04/logo-1500px-1.jpg" alt="Black Star Service" style="max-width:260px; height:auto;"></p>
        <p>Ciao ' . esc_html( $cliente_nome ) . ',</p>
        <p>grazie per aver scelto Black Star Service srl.</p>
        <p>In allegato trovi il riepilogo del tuo noleggio in formato PDF (documento di conferma).</p>
        <p><strong>Promemoria importante</strong></p>
        <p>Ritiro / inizio noleggio: <strong>' . esc_html( $data_inizio ) . '</strong><br>
        Termine noleggio / riconsegna entro: <strong>' . esc_html( $data_fine ) . '</strong><br>
        Riferimento ordine: <strong>#' . esc_html( $noleggio['id'] ) . '</strong></p>
        <hr>
        <p><strong>BLACK STAR SERVICE SRL</strong><br>
        Sede Operativa: Via Cerca 28 - cap : 25135 - Brescia<br>
        Nota: Tutti i ritiri e le consegne devono essere fatti nel sopra citato indirizzo ⬆️ </p>
        ⏰ <strong>Orari di ritiro e riconsegna:</strong><br>
        Dal lunedì al venerdì: <strong>09:30 - 12:30</strong> e <strong>14:30 - 17:30</strong><br>
        Sabato: <strong>09:30 - 12:30</strong><br>
        Domenica: <strong>chiuso</strong>
        <p>Sede Legale: Via Repubblica Argentina 54 - cap 25124 - Brescia<br>
        Nota: L\'indirizzo di fatturazione deve essere quello della sede legale.</p>
        <p>📞 Tel amministrazione: 3921135447<br>
        📱 Mobile tecnico: 3201169791<br>
        📧 E-mail: info@blackstarservice.it<br>
        📧 E-mail: noleggi@blackstarservice.it<br>
        <p>🖥️ Sito web: https://www.blackstarservice.com/</p>
        <p>📍 Info e posizione: https://goo.gl/maps/mvj6Tc9VVCEgjDUw5</p>
        <p>PI/CF: 04130270988<br>
        CODICE UNIV: M5UXCR1</p>
        <p>AZIENDA ABILITATA MEPA</p>
        <p>Cordiali saluti, Black Star Service SRL</p>
    </div>';

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Black Star Service SRL <noleggi@blackstarservice.it>',
    ];

    $attachments = $pdf_path ? [ $pdf_path ] : [];

    if ( $cliente_email ) {
       $sent_cliente = wp_mail( $cliente_email, $subject, $body, $headers, $attachments );
        if ( ! $sent_cliente ) {
            error_log( 'BSN EMAIL: invio fallito a cliente per noleggio ' . $noleggio['id'] );
        }
    }

    $sent_staff = wp_mail( 'noleggi@blackstarservice.it', $subject, $body, $headers, $attachments );
    if ( ! $sent_staff ) {
        error_log( 'BSN EMAIL: invio fallito a staff per noleggio ' . $noleggio['id'] );
    }
}

/**
 * Invia email preventivo con PDF allegato.
 */
function bsn_invia_email_preventivo( $noleggio, $cliente, $pdf_info ) {
    $pdf_path = is_array( $pdf_info ) && ! empty( $pdf_info['path'] ) ? $pdf_info['path'] : '';

    $cliente_nome = $cliente && ! empty( $cliente['nome'] ) ? $cliente['nome'] : 'Cliente';
    $cliente_email = $cliente && ! empty( $cliente['email'] ) ? $cliente['email'] : '';

    $subject = 'PREVENTIVO BLACK STAR SERVICE';

    $body = '
    <div style="font-family: Arial, sans-serif; font-size:14px; color:#222;">
        <p><img src="http://www.blackstarservice.it/wp-content/uploads/2021/04/logo-1500px-1.jpg" alt="Black Star Service" style="max-width:260px; height:auto;"></p>
        <p>Ciao ' . esc_html( $cliente_nome ) . ',</p>
        <p>grazie per aver scelto Black Star Service Srl.</p>
        <p>In allegato trovi il riepilogo del tuo <strong>PREVENTIVO</strong> in formato PDF.</p>
        <p><strong>PROMEMORIA IMPORTANTE</strong><br>
        <strong>IL PREVENTIVO NON IMPLICA LA CONFERMA DEL MATERIALE</strong><br>
        È necessario confermare telefonicamente al numero:<br>
        392 113 5447 (anche WhatsApp)<br>
        includendo il riferimento ordine: <strong>' . esc_html( $noleggio['id'] ) . '</strong></p>
        <hr>
        <p><strong>BLACK STAR SERVICE SRL</strong><br>
        Sede Operativa: Via Cerca 28 – 25135 Brescia<br>
        Nota: Tutti i ritiri e le consegne devono avvenire presso questo indirizzo.</p>
        <p>⏰ Orari:<br>
        L–V 09:30–12:30 / 14:30–17:30<br>
        Sabato 09:30–12:30<br>
        Domenica chiuso</p>
        <p>📞 Amministrazione: 392 113 5447<br>
        📱 Tecnico: 320 116 9791<br>
        📧 info@blackstarservice.it<br>
        📧 noleggi@blackstarservice.it</p>
        <p>🖥️ https://www.blackstarservice.com<br>
        📍 https://goo.gl/maps/mvj6Tc9VVCEgjDUw5</p>
        <p>PI/CF: 04130270988<br>
        CODICE UNIV: M5UXCR1</p>
        <p>AZIENDA ABILITATA MEPA</p>
        <p>Cordiali saluti<br>
        Black Star Service Srl</p>
    </div>';

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Black Star Service SRL <noleggi@blackstarservice.it>',
    ];

    $attachments = $pdf_path ? [ $pdf_path ] : [];

    if ( $cliente_email ) {
        $sent_cliente = wp_mail( $cliente_email, $subject, $body, $headers, $attachments );
        if ( ! $sent_cliente ) {
            error_log( 'BSN EMAIL: invio fallito a cliente per preventivo ' . $noleggio['id'] );
        }
    }

    $sent_staff = wp_mail( 'noleggi@blackstarservice.it', $subject, $body, $headers, $attachments );
    if ( ! $sent_staff ) {
        error_log( 'BSN EMAIL: invio fallito a staff per preventivo ' . $noleggio['id'] );
    }
}
// [BSN_PDF_EMAIL_END]

/**
 * Endpoint PDF noleggio: restituisce il file generato.
 */
function bsn_api_noleggi_pdf_get( WP_REST_Request $request ) {
    ob_start();
    $noleggio_id = sanitize_text_field( $request->get_param( 'id' ) );
    if ( empty( $noleggio_id ) ) {
        ob_end_clean();
        return new WP_Error( 'bsn_pdf_id', 'ID noleggio mancante.', [ 'status' => 400 ] );
    }

    $pdf_info = bsn_generate_noleggio_pdf( $noleggio_id, false );
    if ( is_wp_error( $pdf_info ) ) {
        ob_end_clean();
        return $pdf_info;
    }
    if ( ! $pdf_info || empty( $pdf_info['path'] ) || ! file_exists( $pdf_info['path'] ) ) {
        ob_end_clean();
        return new WP_Error( 'bsn_pdf_missing', 'PDF non trovato.', [ 'status' => 404 ] );
    }

    if ( ob_get_length() ) {
        ob_clean();
    }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: inline; filename="' . basename( $pdf_info['path'] ) . '"' );
    header( 'Content-Length: ' . filesize( $pdf_info['path'] ) );
    readfile( $pdf_info['path'] );
    exit;
}

/**
 * Endpoint PDF preventivo: restituisce il file generato.
 */
function bsn_api_noleggi_preventivo_pdf_get( WP_REST_Request $request ) {
    ob_start();
    $noleggio_id = sanitize_text_field( $request->get_param( 'id' ) );
    if ( empty( $noleggio_id ) ) {
        ob_end_clean();
        return new WP_Error( 'bsn_pdf_id', 'ID preventivo mancante.', [ 'status' => 400 ] );
    }

    global $wpdb;
    $table_noleggi = $wpdb->prefix . 'bs_noleggi';
    $stato = $wpdb->get_var(
        $wpdb->prepare( "SELECT stato FROM $table_noleggi WHERE id = %s", $noleggio_id )
    );
    if ( $stato !== 'preventivo' ) {
        ob_end_clean();
        return new WP_Error( 'bsn_pdf_stato', 'Documento non in stato preventivo.', [ 'status' => 400 ] );
    }

    $pdf_info = bsn_generate_preventivo_pdf( $noleggio_id, false );
    if ( is_wp_error( $pdf_info ) ) {
        ob_end_clean();
        return $pdf_info;
    }

    if ( ! $pdf_info || empty( $pdf_info['path'] ) || ! file_exists( $pdf_info['path'] ) ) {
        ob_end_clean();
        return new WP_Error( 'bsn_pdf_missing', 'PDF preventivo non trovato.', [ 'status' => 404 ] );
    }

    ob_end_clean();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: inline; filename="' . basename( $pdf_info['path'] ) . '"' );
    header( 'Content-Length: ' . filesize( $pdf_info['path'] ) );
    readfile( $pdf_info['path'] );
    exit;
}

/**
 * Endpoint invio preventivo con PDF via email.
 */
function bsn_api_noleggi_preventivo_invia( WP_REST_Request $request ) {
    global $wpdb;

    $id_noleggio = sanitize_text_field( $request->get_param( 'id' ) );
    if ( empty( $id_noleggio ) ) {
        return new WP_Error( 'bsn_preventivo_id', 'ID preventivo mancante.', [ 'status' => 400 ] );
    }

    $table_noleggi = $wpdb->prefix . 'bs_noleggi';
    $noleggio = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM $table_noleggi WHERE id = %s", $id_noleggio ),
        ARRAY_A
    );
    if ( ! $noleggio ) {
        return new WP_Error( 'bsn_preventivo_non_trovato', 'Preventivo non trovato.', [ 'status' => 404 ] );
    }
    if ( $noleggio['stato'] !== 'preventivo' ) {
        return new WP_Error( 'bsn_preventivo_stato', 'Il documento non è in stato preventivo.', [ 'status' => 400 ] );
    }

    $pdf_info = bsn_generate_preventivo_pdf( $id_noleggio, true );
    if ( is_wp_error( $pdf_info ) ) {
        return $pdf_info;
    }

    $table_clienti = $wpdb->prefix . 'bs_clienti';
    $cliente = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM $table_clienti WHERE id = %d", $noleggio['cliente_id'] ),
        ARRAY_A
    );

    if ( $cliente ) {
        bsn_invia_email_preventivo( $noleggio, $cliente, $pdf_info );
    } else {
        bsn_invia_email_preventivo( $noleggio, null, $pdf_info );
    }

    return rest_ensure_response( [
        'success' => true,
        'id'      => $id_noleggio,
    ] );
}

/**
 * Restituisce il path di autoload dompdf se presente.
 */
function bsn_get_dompdf_autoload_path() {
    $candidates = [
        BSN_PATH . 'lib/dompdf/autoload.inc.php',
        WP_CONTENT_DIR . '/uploads/bsn-dompdf/dompdf/autoload.inc.php',
        WP_CONTENT_DIR . '/uploads/bsn-dompdf/dompdf-2.0.4/autoload.inc.php',
    ];

    foreach ( $candidates as $path ) {
        if ( file_exists( $path ) ) {
            return $path;
        }
    }

    return '';
}

/**
 * Scarica dompdf dal repository e lo salva in uploads/bsn-dompdf.
 */
function bsn_download_dompdf_library() {
    $upload_dir = wp_upload_dir();
    if ( ! empty( $upload_dir['error'] ) ) {
        return new WP_Error(
            'bsn_pdf_upload_dir',
            'Impossibile accedere alla cartella upload per dompdf.',
            [ 'status' => 500 ]
        );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $target_dir = trailingslashit( $upload_dir['basedir'] ) . 'bsn-dompdf';
    if ( ! file_exists( $target_dir ) ) {
        wp_mkdir_p( $target_dir );
    }

    $zip_url = 'https://github.com/dompdf/dompdf/archive/refs/tags/v2.0.4.zip';
    $tmp_file = download_url( $zip_url );
    if ( is_wp_error( $tmp_file ) ) {
        return new WP_Error(
            'bsn_pdf_download',
            'Errore nel download di dompdf.',
            [ 'status' => 500 ]
        );
    }

    $unzipped = unzip_file( $tmp_file, $target_dir );
    @unlink( $tmp_file );

    if ( is_wp_error( $unzipped ) ) {
        return new WP_Error(
            'bsn_pdf_unzip',
            'Errore nell\'estrazione di dompdf.',
            [ 'status' => 500 ]
        );
    }

    $autoload_path = $target_dir . '/dompdf-2.0.4/autoload.inc.php';
    if ( ! file_exists( $autoload_path ) ) {
        return new WP_Error(
            'bsn_pdf_missing_library',
            'Libreria PDF mancante dopo il download.',
            [ 'status' => 500 ]
        );
    }

    return $autoload_path;
}

/**
 * Endpoint cambio stato noleggio - callback
 */
function bsn_cambia_stato_noleggio( $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'bs_noleggi';

    $id_noleggio = sanitize_text_field( $request->get_param( 'id_noleggio' ) );
    $nuovo_stato = sanitize_text_field( $request->get_param( 'nuovo_stato' ) );

    $stati_validi = array( 'preventivo', 'bozza', 'attivo', 'chiuso', 'ritardo' );
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

function bsn_ticket_tipo_impatta_disponibilita( $tipo ) {
    $tipi_bloccanti = [
        'mancante',
        'non_rientrato',
        'quantita_inferiore',
        'danneggiato',
    ];

    return in_array( $tipo, $tipi_bloccanti, true );
}

function bsn_ticket_aggiorna_disponibilita( $articolo_id, $qty_delta ) {
    global $wpdb;
    $table_articoli = $wpdb->prefix . 'bs_articoli';

    $articolo_id = intval( $articolo_id );
    $qty_delta   = intval( $qty_delta );

    if ( $articolo_id <= 0 || $qty_delta === 0 ) {
        return;
    }

    $current_qty = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT qty_disponibile FROM $table_articoli WHERE id = %d",
            $articolo_id
        )
    );

    if ( $current_qty === null ) {
        return;
    }

    $nuova_qty = max( 0, intval( $current_qty ) + $qty_delta );
    $wpdb->update(
        $table_articoli,
        [ 'qty_disponibile' => $nuova_qty ],
        [ 'id' => $articolo_id ],
        [ '%d' ],
        [ '%d' ]
    );
}

/**
 * Normalizza le foto ticket in JSON (array di URL).
 */
function bsn_ticket_normalizza_foto( $foto_param ) {
    if ( empty( $foto_param ) ) {
        return '';
    }

    if ( is_array( $foto_param ) ) {
        $urls = array_filter( array_map( 'esc_url_raw', $foto_param ) );
        return $urls ? wp_json_encode( array_values( $urls ) ) : '';
    }

    if ( is_string( $foto_param ) ) {
        $decoded = json_decode( $foto_param, true );
        if ( is_array( $decoded ) ) {
            $urls = array_filter( array_map( 'esc_url_raw', $decoded ) );
            return $urls ? wp_json_encode( array_values( $urls ) ) : '';
        }

        $single = esc_url_raw( $foto_param );
        return $single ? wp_json_encode( [ $single ] ) : '';
    }

    return '';
}

function bsn_crea_ticket( $data ) {
    global $wpdb;
    $table_ticket = $wpdb->prefix . 'bs_ticket';

    bsn_ensure_ticket_table();

    $insert = [
        'noleggio_id' => ! empty( $data['noleggio_id'] ) ? $data['noleggio_id'] : null,
        'articolo_id' => intval( $data['articolo_id'] ),
        'qty'         => max( 1, intval( $data['qty'] ) ),
        'tipo'        => $data['tipo'],
        'note'        => ! empty( $data['note'] ) ? $data['note'] : '',
        'foto'        => ! empty( $data['foto'] ) ? $data['foto'] : '',
        'operatore'   => ! empty( $data['operatore'] ) ? $data['operatore'] : '',
        'origine'     => ! empty( $data['origine'] ) ? $data['origine'] : 'manuale',
        'stato'       => 'aperto',
        'creato_il'   => current_time( 'mysql' ),
    ];

    $result = $wpdb->insert(
        $table_ticket,
        $insert,
        [ '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
    );

    if ( ! $result ) {
        return false;
    }

    if ( bsn_ticket_tipo_impatta_disponibilita( $insert['tipo'] ) ) {
        bsn_ticket_aggiorna_disponibilita( $insert['articolo_id'], -1 * $insert['qty'] );
    }

    return $wpdb->insert_id;
}

function bsn_api_tickets_get() {
    global $wpdb;
    $table_ticket   = $wpdb->prefix . 'bs_ticket';
    $table_articoli = $wpdb->prefix . 'bs_articoli';

    bsn_ensure_ticket_table();

    $sql = "SELECT t.*, a.nome AS articolo_nome, a.codice AS articolo_codice
            FROM $table_ticket t
            LEFT JOIN $table_articoli a ON t.articolo_id = a.id
            ORDER BY t.creato_il DESC";

    $tickets = $wpdb->get_results( $sql, ARRAY_A );

    return rest_ensure_response( [ 'tickets' => $tickets ] );
}

function bsn_api_tickets_post( WP_REST_Request $request ) {
    bsn_ensure_ticket_table();
    
    $noleggio_id = sanitize_text_field( $request->get_param( 'noleggio_id' ) );
    $articolo_id = intval( $request->get_param( 'articolo_id' ) );
    $qty         = intval( $request->get_param( 'qty' ) );
    $tipo        = sanitize_text_field( $request->get_param( 'tipo' ) );
    $note        = sanitize_text_field( $request->get_param( 'note' ) );
    $stato       = sanitize_text_field( $request->get_param( 'stato' ) );
    $foto_raw    = $request->get_param( 'foto' );
    $foto_json   = bsn_ticket_normalizza_foto( $foto_raw );

    if ( $articolo_id <= 0 ) {
        return new WP_Error( 'bsn_ticket_articolo', 'Articolo non valido.', [ 'status' => 400 ] );
    }

    if ( $qty <= 0 ) {
        return new WP_Error( 'bsn_ticket_qty', 'Quantità non valida.', [ 'status' => 400 ] );
    }

    $tipi_validi = [ 'mancante', 'non_rientrato', 'quantita_inferiore', 'danneggiato', 'problematico_utilizzabile' ];
    if ( ! in_array( $tipo, $tipi_validi, true ) ) {
        return new WP_Error( 'bsn_ticket_tipo', 'Tipo ticket non valido.', [ 'status' => 400 ] );
    }

    $current_user = wp_get_current_user();
    $ticket_id = bsn_crea_ticket( [
        'noleggio_id' => $noleggio_id,
        'articolo_id' => $articolo_id,
        'qty'         => $qty,
        'tipo'        => $tipo,
        'note'        => $note,
        'foto'        => $foto_json,
        'operatore'   => $current_user ? $current_user->user_login : '',
        'origine'     => 'manuale',
    ] );

    if ( ! $ticket_id ) {
       global $wpdb;
        $detail = $wpdb->last_error ? $wpdb->last_error : 'Errore sconosciuto DB.';
        return new WP_Error( 'bsn_ticket_error', 'Errore nella creazione del ticket.', [ 'status' => 500, 'detail' => $detail ] );
    }

    return rest_ensure_response( [
        'success'   => true,
        'ticket_id' => $ticket_id,
    ] );
}

function bsn_api_tickets_update( WP_REST_Request $request ) {
    global $wpdb;
    $table_ticket = $wpdb->prefix . 'bs_ticket';

    bsn_ensure_ticket_table();

    $ticket_id  = intval( $request->get_param( 'ticket_id' ) );
    $articolo_id = intval( $request->get_param( 'articolo_id' ) );
    $qty         = intval( $request->get_param( 'qty' ) );
    $tipo        = sanitize_text_field( $request->get_param( 'tipo' ) );
    $note        = sanitize_text_field( $request->get_param( 'note' ) );
    $stato       = sanitize_text_field( $request->get_param( 'stato' ) );
    $foto_raw    = $request->get_param( 'foto' );
    $foto_json   = bsn_ticket_normalizza_foto( $foto_raw );

    if ( $ticket_id <= 0 ) {
        return new WP_Error( 'bsn_ticket_id', 'ID ticket non valido.', [ 'status' => 400 ] );
    }
    if ( $articolo_id <= 0 || $qty <= 0 ) {
        return new WP_Error( 'bsn_ticket_data', 'Dati ticket non validi.', [ 'status' => 400 ] );
    }

    $tipi_validi = [ 'mancante', 'non_rientrato', 'quantita_inferiore', 'danneggiato', 'problematico_utilizzabile' ];
    if ( ! in_array( $tipo, $tipi_validi, true ) ) {
        return new WP_Error( 'bsn_ticket_tipo', 'Tipo ticket non valido.', [ 'status' => 400 ] );
    }

    $stati_validi = [ 'aperto', 'chiuso' ];
    if ( ! in_array( $stato, $stati_validi, true ) ) {
        $stato = 'aperto';
    }

    $ticket = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM $table_ticket WHERE id = %d", $ticket_id ),
        ARRAY_A
    );

    if ( ! $ticket ) {
        return new WP_Error( 'bsn_ticket_not_found', 'Ticket non trovato.', [ 'status' => 404 ] );
    }

    $old_impacts = ( $ticket['stato'] === 'aperto' ) && bsn_ticket_tipo_impatta_disponibilita( $ticket['tipo'] );
    $new_impacts = ( $stato === 'aperto' ) && bsn_ticket_tipo_impatta_disponibilita( $tipo );

    if ( $old_impacts ) {
        bsn_ticket_aggiorna_disponibilita( intval( $ticket['articolo_id'] ), intval( $ticket['qty'] ) );
    }

    if ( $new_impacts ) {
        bsn_ticket_aggiorna_disponibilita( $articolo_id, -1 * $qty );
    }

    $updated = $wpdb->update(
        $table_ticket,
        [
            'articolo_id' => $articolo_id,
            'qty'         => $qty,
            'tipo'        => $tipo,
            'note'        => $note,
            'stato'       => $stato,
            'foto'        => $foto_json,
        ],
        [ 'id' => $ticket_id ],
        [ '%d', '%d', '%s', '%s', '%s', '%s' ],
        [ '%d' ]
    );

    if ( $updated === false ) {
        return new WP_Error( 'bsn_ticket_update', 'Errore nel salvataggio ticket.', [ 'status' => 500 ] );
    }

    return rest_ensure_response( [ 'success' => true, 'ticket_id' => $ticket_id ] );
}

function bsn_api_tickets_chiudi( WP_REST_Request $request ) {
    global $wpdb;
    $table_ticket = $wpdb->prefix . 'bs_ticket';

    bsn_ensure_ticket_table();

    $ticket_id = intval( $request->get_param( 'ticket_id' ) );
    if ( $ticket_id <= 0 ) {
        return new WP_Error( 'bsn_ticket_id', 'ID ticket non valido.', [ 'status' => 400 ] );
    }

    $ticket = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM $table_ticket WHERE id = %d", $ticket_id ),
        ARRAY_A
    );

    if ( ! $ticket ) {
        return new WP_Error( 'bsn_ticket_not_found', 'Ticket non trovato.', [ 'status' => 404 ] );
    }

    if ( $ticket['stato'] === 'chiuso' ) {
        return rest_ensure_response( [ 'success' => true, 'ticket_id' => $ticket_id ] );
    }

    $updated = $wpdb->update(
        $table_ticket,
        [
            'stato'     => 'chiuso',
            'chiuso_il' => current_time( 'mysql' ),
        ],
        [ 'id' => $ticket_id ],
        [ '%s', '%s' ],
        [ '%d' ]
    );

    if ( $updated === false ) {
        return new WP_Error( 'bsn_ticket_update', 'Errore nella chiusura ticket.', [ 'status' => 500 ] );
    }

    if ( bsn_ticket_tipo_impatta_disponibilita( $ticket['tipo'] ) ) {
        bsn_ticket_aggiorna_disponibilita( intval( $ticket['articolo_id'] ), intval( $ticket['qty'] ) );
    }

    return rest_ensure_response( [ 'success' => true, 'ticket_id' => $ticket_id ] );
}

/**
 * Endpoint Rientro noleggio - callback
 */
function bsn_api_noleggi_rientro( $request ) {
    global $wpdb;
    $table_noleggi = $wpdb->prefix . 'bs_noleggi';

    bsn_ensure_ticket_table();
    bsn_ensure_noleggi_columns();
    
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

    if ( empty( $articoli ) ) {
        return new WP_Error( 'bsn_no_articoli', 'Nessun articolo fornito per il rientro.', array( 'status' => 400 ) );
    }

    $report     = [];
    $current_user = wp_get_current_user();

    $ticket_items = [];
    $tipi_validi = [ 'ok', 'ticket' ];

    foreach ( $articoli as $riga ) {
        $articolo_id   = isset( $riga['articolo_id'] ) ? intval( $riga['articolo_id'] ) : 0;
        $qty           = isset( $riga['qty'] ) ? intval( $riga['qty'] ) : 0;
        $qty_rientrata = isset( $riga['qty_rientrata'] ) ? intval( $riga['qty_rientrata'] ) : 0;
        $stato_rientro = isset( $riga['stato_rientro'] ) ? sanitize_text_field( $riga['stato_rientro'] ) : 'ok';
        $note          = isset( $riga['note'] ) ? sanitize_text_field( $riga['note'] ) : '';

        if ( $articolo_id <= 0 || $qty <= 0 ) {
            continue;
        }

        if ( $qty_rientrata < 0 || $qty_rientrata > $qty ) {
            return new WP_Error( 'bsn_qty_rientro', 'Quantità rientrata non valida per un articolo.', array( 'status' => 400 ) );
        }

        if ( ! in_array( $stato_rientro, $tipi_validi, true ) ) {
            return new WP_Error( 'bsn_stato_rientro', 'Stato rientro non valido.', array( 'status' => 400 ) );
        }

        $report[] = [
            'articolo_id'   => $articolo_id,
            'qty'           => $qty,
            'qty_rientrata' => $qty_rientrata,
            'stato_rientro' => $stato_rientro,
            'note'          => $note,
        ];

        $richiede_ticket = ( $stato_rientro !== 'ok' ) || ( $qty_rientrata < $qty );
        if ( ! $richiede_ticket ) {
            continue;
        }

        $tipo_ticket = $stato_rientro;
        if ( $stato_rientro === 'ok' && $qty_rientrata < $qty ) {
            $tipo_ticket = 'quantita_inferiore';
        }

        $qty_ticket = 0;
        if ( in_array( $tipo_ticket, [ 'mancante', 'non_rientrato', 'quantita_inferiore' ], true ) ) {
            $qty_ticket = max( 1, $qty - $qty_rientrata );
        } elseif ( in_array( $tipo_ticket, [ 'danneggiato', 'problematico_utilizzabile' ], true ) ) {
            $qty_ticket = max( 1, $qty_rientrata );
        } else {
            $qty_ticket = max( 1, $qty );
        }

        $ticket_id = bsn_crea_ticket( [
            'noleggio_id' => $id_noleggio,
            'articolo_id' => $articolo_id,
            'qty'         => $qty_ticket,
            'tipo'        => $tipo_ticket,
            'note'        => $note,
            'operatore'   => $current_user ? $current_user->user_login : $op_rientro,
        ] );

        if ( $ticket_id ) {
            $ticket_ids[] = $ticket_id;
        }
    }

    // STEP B2: chiusura noleggio + operatore rientro + report
    $updated = $wpdb->update(
        $table_noleggi,
        array(
            'op_rientro_materiale' => $op_rientro,
            'stato'                => 'chiuso',
            'rientro_report'       => wp_json_encode( $report ),
            'rientro_data'         => current_time( 'mysql' ),
        ),
        array(
            'id' => $id_noleggio,
        ),
        array( '%s', '%s', '%s', '%s' ),
        array( '%s' )
    );

    if ( $updated === false ) {
        return new WP_Error( 'bsn_db_error', 'Errore durante l\'aggiornamento del noleggio.', array( 'status' => 500 ) );
    }

    return array(
        'success'    => true,
        'message'    => 'Rientro salvato e noleggio chiuso.',
        'stato'      => 'chiuso',
        'id'         => $id_noleggio,
        'ticket_ids' => $ticket_ids,
    );
}