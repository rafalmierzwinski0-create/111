<?php
/**
 * Conditional formatting rules, printed inside the free plugin's source form.
 *
 * Each rule is laid out as one sentence rather than as five cells of a table.
 * A table made you read across five headings and assemble the sentence in your
 * head — and the headings could not say it either: "Show it" meant the colour,
 * "Applies to" meant whether the colour lands on one cell or the whole row.
 * Both were only guessable from having tried it.
 *
 * @package LiveSheetsTablePro
 *
 * @var array<int,array<string,mixed>> $rules   Stored rules.
 * @var array<int,string>              $headers Sheet headings, empty before the first sync.
 * @var bool                           $is_edit Whether an existing source is being edited.
 */

defined( 'ABSPATH' ) || exit;

$lstabp_palette   = LSTABP_Rules::palette();
$lstabp_effects   = LSTABP_Rules::effects();
$lstabp_operators = LSTABP_Rules::operators();

/*
 * Two spare lines, so adding a rule needs no button and no JavaScript, and two
 * can be added in one go. They are drawn as outlines rather than as filled-in
 * rules, so an empty card does not look like a form somebody half-completed.
 */
$lstabp_waiting = ! $headers;
$lstabp_saved   = count( $rules );

/*
 * Rules whose column is no longer in the sheet. Renaming a heading in Google
 * is enough: the rule stays stored, stops colouring anything, and — because
 * its column had no matching option — the select fell back to the empty one,
 * so the next save deleted it without a word. Now the missing column is kept
 * as an option of its own, so a save preserves the rule, and the card says
 * what happened.
 */
$lstabp_orphans = array();
foreach ( $rules as $lstabp_i => $lstabp_stored ) {
	if ( '' !== $lstabp_stored['column'] && ! in_array( $lstabp_stored['column'], $headers, true ) ) {
		$lstabp_orphans[ $lstabp_i ] = $lstabp_stored['column'];
	}
}
$lstabp_blank   = array( 'column' => '', 'operator' => '=', 'value' => '', 'style' => LSTABP_Rules::DEFAULT_STYLE, 'scope' => 'cell' );

/*
 * One blank line waiting rather than two. Two was a guess at how many more
 * rules somebody wanted, and being wrong was the whole problem: a fourth rule
 * meant filling both, saving, and coming back for two more. The button under
 * the list adds them now, and the blank line is only there so a table with no
 * rules yet has an obvious first one.
 */
$lstabp_rows    = array_merge( $rules, array( $lstabp_blank ) );
?>
<div class="lstab-card lstabp-rules-card<?php echo $lstabp_waiting ? ' is-waiting' : ''; ?>">
	<h2 class="lstab-card-title"><?php esc_html_e( 'Colour rules', 'live-sheets-table-pro' ); ?></h2>
	<p class="lstab-help">
		<?php esc_html_e( 'Colour a cell, or its whole row, according to the cell\'s value. Colours are worked out on the server, so they are already in the page a visitor receives.', 'live-sheets-table-pro' ); ?>
	</p>

	<?php
	/*
	 * The two questions this card used to answer only by experiment: what
	 * happens when two rules match the same cell, and what "the whole row"
	 * does to a cell that has a colour of its own.
	 */
	?>
	<ul class="lstabp-rules-how">
		<li>
			<?php echo LSTAB_Icons::icon( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( 'Rules are read from the top down. If two of them colour the same place, the lower one wins, so put the general rule first.', 'live-sheets-table-pro' ); ?>
		</li>
		<li>
			<?php echo LSTAB_Icons::icon( 'brush' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( 'A colour on a cell sits on top of a colour on its row, so “grey row, one red cell” is two rules.', 'live-sheets-table-pro' ); ?>
		</li>
		<li>
			<?php echo LSTAB_Icons::icon( 'sliders' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
			<?php esc_html_e( '“is” and “is not” compare text, ignoring case and spacing. The number comparisons read a price as a number, so 1 215,50 and 1215.5 are the same figure.', 'live-sheets-table-pro' ); ?>
		</li>
	</ul>

	<?php if ( $lstabp_orphans && ! $lstabp_waiting ) : ?>
		<div class="lstabp-rules-orphans">
			<p>
				<strong>
					<?php
					printf(
						/* translators: %s: number of rules. */
						esc_html( _n( 'One rule names a column your sheet no longer has.', '%s rules name columns your sheet no longer has.', count( $lstabp_orphans ), 'live-sheets-table-pro' ) ),
						esc_html( number_format_i18n( count( $lstabp_orphans ) ) )
					);
					?>
				</strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: the headings a rule names, comma separated. */
					esc_html__( 'Nothing is coloured by %s until the rule points at a heading that exists. The rule is kept until you change it.', 'live-sheets-table-pro' ),
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
					: __( 'Load the preview first. Once the columns are known you can set rules on them.', 'live-sheets-table-pro' )
			);
			?>
		</p>
	<?php endif; ?>

	<?php
	/*
	 * Only when the card can actually be filled in. Its controls are disabled
	 * while there are no columns to choose from, so they submit nothing — and
	 * a marker without them would read as "the user cleared every rule" and
	 * wipe a rule set the screen never showed.
	 */
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
		/*
		 * The line the button adds, kept out of the form until it is asked for.
		 * Cloned from here rather than from the last line on screen, so a new
		 * rule cannot arrive carrying somebody else's colour and value. The
		 * placeholder in its field names is replaced with the next number.
		 */
		$lstabp_index  = LSTABP_Rules::INDEX_PLACEHOLDER;
		$lstabp_rule   = $lstabp_blank;
		$lstabp_is_new = true;
		?>
		<template id="lstabp-rule-template">
			<?php require __DIR__ . '/rule-line.php'; ?>
		</template>
	<?php endif; ?>

	<p class="lstab-help">
		<?php esc_html_e( 'To remove a rule, set its column back to “remove this rule”.', 'live-sheets-table-pro' ); ?>
	</p>
</div>
