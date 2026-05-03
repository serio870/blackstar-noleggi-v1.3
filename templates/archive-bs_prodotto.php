<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
$customer_category = function_exists( 'bsn_get_current_public_customer_category' ) ? bsn_get_current_public_customer_category() : 'standard';
$customer_category_label = function_exists( 'bsn_get_public_customer_category_label' ) ? bsn_get_public_customer_category_label( $customer_category ) : 'Guest / standard';
?>
<main class="bsn-public-page bsn-archive-page">
    <div class="bsn-public-shell">
        <header class="bsn-archive-header">
            <p class="bsn-eyebrow">Catalogo noleggio</p>
            <h1><?php post_type_archive_title(); ?></h1>
            <p>Esplora i prodotti pubblici collegati al gestionale Black Star Noleggi. La disponibilit&agrave; visualizzata deriva dagli articoli interni reali.</p>
        </header>

        <?php if ( have_posts() ) : ?>
            <div class="bsn-card-grid">
                <?php
                while ( have_posts() ) :
                    the_post();
                    $product_id = get_the_ID();
                    $meta = bsn_get_public_product_meta( $product_id );
                    $gallery_urls = bsn_get_public_product_gallery_urls( $product_id );
                    $price_standard = bsn_get_public_product_price_from( $product_id, 'standard' );
                    $price_reserved = $customer_category !== 'standard' ? bsn_get_public_product_price_from( $product_id, $customer_category ) : null;
                    ?>
                    <article <?php post_class( 'bsn-public-product-card' ); ?>>
                        <a class="bsn-card-media" href="<?php the_permalink(); ?>">
                            <?php if ( ! empty( $gallery_urls ) ) : ?>
                                <img src="<?php echo esc_url( $gallery_urls[0] ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
                            <?php else : ?>
                                <span>Nessuna immagine</span>
                            <?php endif; ?>
                        </a>
                        <div class="bsn-card-body">
                            <h2 class="bsn-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                            <?php if ( ! empty( $meta['sottotitolo_catalogo'] ) ) : ?>
                                <p class="bsn-card-subtitle"><?php echo esc_html( $meta['sottotitolo_catalogo'] ); ?></p>
                            <?php else : ?>
                                <p class="bsn-card-subtitle bsn-card-subtitle-empty">&nbsp;</p>
                            <?php endif; ?>
                            <div class="bsn-card-availability">
                                <?php echo bsn_render_public_product_availability_html( $product_id, '', '', [ 'title' => '', 'compact' => true ] ); ?>
                            </div>
                            <div class="bsn-card-content-spacer"></div>
                            <div class="bsn-card-footer">
                                <div class="bsn-card-price-stack">
                                    <?php if ( null !== $price_standard ) : ?>
                                        <?php if ( null !== $price_reserved && $price_reserved < $price_standard ) : ?>
                                            <div class="bsn-card-price-standard">Tariffa standard: <?php echo esc_html( number_format_i18n( $price_standard, 2 ) ); ?> EUR</div>
                                            <div class="bsn-card-price-reserved">Prezzo riservato <?php echo esc_html( $customer_category_label ); ?>: <?php echo esc_html( number_format_i18n( $price_reserved, 2 ) ); ?> EUR</div>
                                        <?php else : ?>
                                            <div class="bsn-card-price">Tariffa 1 giorno: <?php echo esc_html( number_format_i18n( $price_standard, 2 ) ); ?> EUR</div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <div class="bsn-card-price">Prezzo su richiesta</div>
                                    <?php endif; ?>
                                </div>
                                <a class="bsn-public-btn" href="<?php the_permalink(); ?>">Dettagli</a>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <div class="bsn-pagination">
                <?php the_posts_pagination(); ?>
            </div>
        <?php else : ?>
            <div class="bsn-public-card">
                <h2>Nessun prodotto disponibile</h2>
                <p>Il catalogo pubblico e in aggiornamento.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
