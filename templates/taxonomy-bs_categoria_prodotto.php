<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
$term = get_queried_object();
$customer_category = function_exists( 'bsn_get_current_public_customer_category' ) ? bsn_get_current_public_customer_category() : 'standard';
$customer_category_label = function_exists( 'bsn_get_public_customer_category_label' ) ? bsn_get_public_customer_category_label( $customer_category ) : 'Guest / standard';
$child_terms = [];
if ( $term && ! empty( $term->term_id ) ) {
    $child_terms = get_terms([
        'taxonomy'   => 'bs_categoria_prodotto',
        'hide_empty' => false,
        'parent'     => (int) $term->term_id,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);
    if ( is_wp_error( $child_terms ) ) {
        $child_terms = [];
    }
}

$bsn_products_query = null;
$bsn_products_have_posts = have_posts();
if ( ! $bsn_products_have_posts && $term && ! empty( $term->term_id ) ) {
    $paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
    $per_page = (int) apply_filters( 'bsn_public_catalog_posts_per_page', 30 );
    $bsn_products_query = new WP_Query([
        'post_type'           => 'bs_prodotto',
        'post_status'         => 'publish',
        'posts_per_page'      => max( 1, $per_page ),
        'paged'               => $paged,
        'ignore_sticky_posts' => true,
        'tax_query'           => [
            [
                'taxonomy'         => 'bs_categoria_prodotto',
                'field'            => 'term_id',
                'terms'            => [ (int) $term->term_id ],
                'include_children' => true,
            ],
        ],
    ]);
    $bsn_products_have_posts = $bsn_products_query->have_posts();
}
?>
<main class="bsn-public-page bsn-taxonomy-page">
    <div class="bsn-public-shell">
        <header class="bsn-archive-header">
            <p class="bsn-eyebrow">Categoria noleggio</p>
            <h1><?php echo esc_html( single_term_title( '', false ) ); ?></h1>
            <?php if ( $term && ! empty( $term->description ) ) : ?>
                <div class="bsn-tax-description"><?php echo wp_kses_post( wpautop( $term->description ) ); ?></div>
            <?php else : ?>
                <p>Prodotti pubblici disponibili in questa categoria.</p>
            <?php endif; ?>
        </header>

        <?php if ( ! empty( $child_terms ) ) : ?>
            <section class="bsn-public-card bsn-term-section">
                <h2>Sottocategorie</h2>
                <div class="bsn-card-grid bsn-term-grid">
                    <?php foreach ( $child_terms as $child_term ) : ?>
                        <a class="bsn-term-card" href="<?php echo esc_url( get_term_link( $child_term ) ); ?>">
                            <span class="bsn-term-card-title"><?php echo esc_html( $child_term->name ); ?></span>
                            <?php if ( ! empty( $child_term->description ) ) : ?>
                                <span class="bsn-term-card-desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $child_term->description ), 14 ) ); ?></span>
                            <?php else : ?>
                                <span class="bsn-term-card-desc">Apri categoria</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ( $bsn_products_have_posts ) : ?>
            <section class="bsn-term-section">
                <h2>Prodotti</h2>
                <div class="bsn-card-grid">
                    <?php
                    while ( $bsn_products_query ? $bsn_products_query->have_posts() : have_posts() ) :
                        if ( $bsn_products_query ) {
                            $bsn_products_query->the_post();
                        } else {
                            the_post();
                        }
                        $product_id = get_the_ID();
                        $meta = bsn_get_public_product_meta( $product_id );
                        $gallery_urls = bsn_get_public_product_gallery_urls( $product_id );
                        $price_standard = bsn_get_public_product_price_from( $product_id, 'standard' );
                        $price_reserved = $customer_category !== 'standard' ? bsn_get_public_product_price_from( $product_id, $customer_category ) : null;
                        ?>
                        <article <?php post_class( 'bsn-public-product-card' ); ?>>
                            <div class="bsn-card-media">
                                <?php if ( ! empty( $gallery_urls ) ) : ?>
                                    <img src="<?php echo esc_url( $gallery_urls[0] ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
                                <?php else : ?>
                                    <span>Nessuna immagine</span>
                                <?php endif; ?>
                            </div>
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
                                                <div class="bsn-card-price-standard"><?php echo esc_html( number_format_i18n( $price_standard, 2 ) ); ?> &euro;</div>
                                                <div class="bsn-card-price-reserved"><?php echo esc_html( number_format_i18n( $price_reserved, 2 ) ); ?> &euro;</div>
                                            <?php else : ?>
                                                <div class="bsn-card-price"><?php echo esc_html( number_format_i18n( $price_standard, 2 ) ); ?> &euro;</div>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <div class="bsn-card-price bsn-card-price-on-request">Prezzo su richiesta</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                    <?php
                    if ( $bsn_products_query ) {
                        wp_reset_postdata();
                    }
                    ?>
                </div>
            </section>

            <div class="bsn-pagination">
                <?php
                if ( $bsn_products_query ) {
                    echo paginate_links([
                        'total'   => max( 1, (int) $bsn_products_query->max_num_pages ),
                        'current' => max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ),
                    ]);
                } else {
                    the_posts_pagination();
                }
                ?>
            </div>
        <?php else : ?>
            <div class="bsn-public-card">
                <h2>Nessun prodotto in questa categoria</h2>
                <p>Popoleremo questa sezione man mano che colleghiamo gli articoli al catalogo pubblico.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
