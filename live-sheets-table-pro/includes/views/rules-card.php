<?php

defined( 'ABSPATH' ) || exit;

$lstabp_palette   = LSTABP_Rules::palette();
$lstabp_effects   = LSTABP_Rules::effects();
$lstabp_operators = LSTABP_Rules::operators();

$lstabp_waiting = ! $headers;
$lstabp_saved   = count( $rules );

$lstabp_orphans = array();
foreach ( $rules as $lstabp_i => $lstabp_stored ) {
	if ( '' !== $lstabp_stored['column'] && ! in_array( $lstabp_stored['column'], $headers, true ) ) {
		$lstabp_orphans[ $lstabp_i ] = $lstabp_stored['column'];
	}
}
$lstabp_blank   = array( 'column' => '', 'operator' => '=', 'value' => '', 'style' => LSTABP_Rules::DEFAULT_STYLE, 'scope' => 'cell' );

$lstabp_rows    = array_merge( $rules, array( $lstabp_blank ) );
?>
<div class="lstab-card lstabp-rules-card<?php echo $lstabp_waiting ? ' is-waiting' : ''; ?>">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Colour rules', 'live-sheets-table-pro' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'Colour a cell, its whole row or just its text depending on what the cell holds, or show the value as a pill or with a coloured dot. For example: when Availability is “Out of stock”, colour the whole row red.', 'live-sheets-table-pro' ); ?>
	</p>

	<ul class="lstabp-rules-how">
		<li>
			<?php echo LSTAB_Icons::icon( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( 'Rules are applied from the top down. If two rules colour the same place, the lower one wins, so put general rules first and exceptions below them.', 'live-sheets-table-pro' ); ?>
		</li>
		<li>
			<?php echo LSTAB_Icons::icon( 'brush' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( 'When a row and one of its cells both get a colour, that cell keeps its own. For example: one rule makes the row grey, another makes its Price cell red, and you see a grey row with a red price.', 'live-sheets-table-pro' ); ?>
		</li>
		<li>
			<?php echo LSTAB_Icons::icon( 'sliders' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( '“is” and “is not” compare text; capital letters and extra spaces make no difference. The other comparisons read the cell as a number, so “1 215,50” and “1215.5” are the same number, and a cell without a number never matches them.', 'live-sheets-table-pro' ); ?>
		</li>
	</ul>

	<?php if ( $lstabp_orphans && ! $lstabp_waiting ) : ?>
		<div class="lstabp-rules-orphans">
			<p>
				<strong>
					<?php
					printf(
						/* translators: %s: number of rules. */
						esc_html( _n( '%s rule names a column your sheet no longer has.', '%s rules name columns your sheet no longer has.', count( $lstabp_orphans ), 'live-sheets-table-pro' ) ),
						esc_html( number_format_i18n( count( $lstabp_orphans ) ) )
					);
					?>
				</strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: the headings a rule names, comma separated. */
					esc_html__( 'Rules for %s colour nothing until you choose a column that exists. They are kept until you change them.', 'live-sheets-table-pro' ),
					esc_html( implode( ', ', array_map( static function ( $lstabp_name ) { return '“' . $lstabp_name . '”'; }, $lstabp_orphans ) ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $lstabp_waiting ) : ?>
		<p class="lstab-columns-waiting">
			<?php
			echo esc_html(
				$is_edit
					? __( 'The sheet has not been read yet, so there are no columns to choose from.', 'live-sheets-table-pro' )
					: __( 'Paste the sheet’s link and choose “Load preview” first. The columns then appear here.', 'live-sheets-table-pro' )
			);
			?>
		</p>
	<?php endif; ?>

	<?php
	if ( ! $lstabp_waiting ) :
		?>
		<input type="hidden" name="_lstabp_rules_present" value="1">
	<?php endif; ?>

	<ol class="lstabp-rules">
		<?php foreach ( $lstabp_rows as $lstabp_index => $lstabp_rule ) : ?>
			<?php
			$lstabp_is_new = $lstabp_index >= $lstabp_saved;
			require __DIR__ . '/rule-line.php';
			?>
		<?php endforeach; ?>
	</ol>

	<?php if ( ! $lstabp_waiting ) : ?>
		<p class="lstabp-rules-add">
			<button type="button" class="lstab-mini" id="lstabp-add-rule">
				<?php echo LSTAB_Icons::icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
				<?php esc_html_e( 'Add a rule', 'live-sheets-table-pro' ); ?>
			</button>
		</p>

		<?php
		$lstabp_index  = LSTABP_Rules::INDEX_PLACEHOLDER;
		$lstabp_rule   = $lstabp_blank;
		$lstabp_is_new = true;
		?>
		<template id="lstabp-rule-template">
			<?php require __DIR__ . '/rule-line.php'; ?>
		</template>
	<?php endif; ?>

	<p class="lstab-help">
		<?php esc_html_e( 'To delete a rule, click the bin beside it, then save.', 'live-sheets-table-pro' ); ?>
	</p>
</div>
