<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="lstab-card lstab-css-card">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Your own CSS', 'tabliko-pro' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'For people who know CSS. Rules written here change only this table, nowhere else on the site. Use & to mean the whole table, for example &.lstab-paged.', 'tabliko-pro' ); ?>
	</p>

	<input type="hidden" name="_lstabp_custom_css_present" value="1">
	<textarea id="lstabp-custom-css"
		name="lstabp_custom_css"
		class="lstab-css-field"
		rows="8"
		spellcheck="false"
		autocapitalize="off"
		autocorrect="off"
		placeholder="<?php echo esc_attr( "th { letter-spacing: 0.04em; }\ntd:first-child { font-weight: 600; }" ); ?>"><?php echo esc_textarea( $lstabp_css_value ); ?></textarea>

	<p class="lstab-help lstab-css-foot">
		<?php
		if ( $lstabp_css_id ) {
			printf(
				/* translators: %s: the CSS selector this table's rules are given, e.g. [data-lstab-id="7"]. */
				esc_html__( 'Each rule is saved with %s in front, so it only ever affects this table.', 'tabliko-pro' ),
				'<code>' . esc_html( LSTABP_Custom_Css::selector( $lstabp_css_id ) ) . '</code>'
			);
		} else {
			esc_html_e( 'Each rule is saved with this table’s own selector in front, so it only ever affects this table. You will see the selector here once the table is saved.', 'tabliko-pro' );
		}
		?>
	</p>
</div>
