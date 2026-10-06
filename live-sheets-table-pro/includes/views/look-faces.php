<?php
/**
 * The miniature that shows what a column look actually does.
 *
 * A dropdown could only ever name the looks, and the names are the part nobody
 * can picture: "a bar behind the number" is four words for something that takes
 * a tenth of a second to recognise once it is drawn. So the chooser draws them,
 * in the very classes the table uses — the badge here is the badge a visitor
 * gets, from the same stylesheet, so a chip cannot show one thing and the page
 * another.
 *
 * Its own file because the card draws it and the script redraws it as a colour
 * is picked; one shape, so the two cannot drift.
 *
 * @package LiveSheetsTablePro
 *
 * @var string $lstabp_face_look The look being drawn.
 * @var string $lstabp_face_tint Colour chosen for it.
 * @var string $lstabp_face_ink  Text colour chosen for it, where it has one.
 * @var string $lstabp_face_says What a button says.
 */

defined( 'ABSPATH' ) || exit;

$lstabp_face_setting = array(
	'tint' => $lstabp_face_tint,
	'ink'  => $lstabp_face_ink,
);
$lstabp_face_css     = LSTABP_Column_Looks::css_for( $lstabp_face_look, $lstabp_face_setting );

/*
 * A number that is plainly a number and plainly not the whole column: the
 * chip has to read as an example, not as a value somebody could edit.
 */
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
		<?php
		/*
		 * An <a> with no href, which is not a link: it cannot be focused and
		 * cannot steal the click meant for the radio, and it is still what the
		 * front end's own selector asks for — that rule is written against the
		 * tag on purpose, so the table's accent colour cannot overrule a button
		 * whose colours were chosen by hand.
		 */
		?>
		<span class="lstab-cell-value"><a class="lstabp-cta-link" style="<?php echo esc_attr( $lstabp_face_css ); ?>"><?php echo esc_html( $lstabp_face_word ); ?></a></span>
	</span>
<?php else : ?>
	<span class="lstabp-look-face">
		<span class="lstab-cell-value"><?php echo esc_html( $lstabp_face_number ); ?></span>
	</span>
<?php endif; ?>
