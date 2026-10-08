<?php

defined( 'ABSPATH' ) || exit;

$lstabp_face_setting = array(
	'tint' => $lstabp_face_tint,
	'ink'  => $lstabp_face_ink,
);
$lstabp_face_css     = LSTABP_Column_Looks::css_for( $lstabp_face_look, $lstabp_face_setting );

$lstabp_face_number = '1 240';
$lstabp_face_word   = '' !== trim( $lstabp_face_says ) ? $lstabp_face_says : __( 'Open', 'live-sheets-table-pro' );
?>
<?php if ( 'bar' === $lstabp_face_look ) : ?>
	<span class="lstabp-look-face lstabp-bar" style="--lstabp-bar:64%;<?php echo esc_attr( $lstabp_face_css ); ?>">
		<span class="lstab-cell-value"><?php echo esc_html( $lstabp_face_number ); ?></span>
	</span>
<?php elseif ( 'pill' === $lstabp_face_look ) : ?>
	<span class="lstabp-look-face">
		<span class="lstabp-pill-face" style="<?php echo esc_attr( $lstabp_face_css ); ?>"><?php esc_html_e( 'Open', 'live-sheets-table-pro' ); ?></span>
	</span>
<?php elseif ( 'tint' === $lstabp_face_look ) : ?>
	<span class="lstabp-look-face is-tinted" style="<?php echo esc_attr( $lstabp_face_css ); ?>">
		<span class="lstab-cell-value"><?php echo esc_html( $lstabp_face_number ); ?></span>
	</span>
<?php elseif ( 'button' === $lstabp_face_look ) : ?>
	<span class="lstabp-look-face">
		<span class="lstab-cell-value"><a class="lstabp-cta-link" style="<?php echo esc_attr( $lstabp_face_css ); ?>"><?php echo esc_html( $lstabp_face_word ); ?></a></span>
	</span>
<?php else : ?>
	<span class="lstabp-look-face">
		<span class="lstab-cell-value"><?php echo esc_html( $lstabp_face_number ); ?></span>
	</span>
<?php endif; ?>
