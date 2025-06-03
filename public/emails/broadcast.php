<!-- Subject: <?= esc_html( $title ); ?> -->
<h1><?= esc_html( $title ); ?></h1>
<p><?= wp_kses_post( $message ); ?></p>
