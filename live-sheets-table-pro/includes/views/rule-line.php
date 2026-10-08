<?php

defined( 'ABSPATH' ) || exit;
?>
			<li class="lstabp-rule<?php echo $lstabp_is_new ? ' is-new' : ''; ?><?php echo isset( $lstabp_orphans[ $lstabp_index ] ) ? ' is-orphan' : ''; ?>">
				<span class="lstabp-rule-line">
					<span class="lstabp-rule-word"><?php esc_html_e( 'When', 'live-sheets-table-pro' ); ?></span>

					<select class="lstabp-rule-column" name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][column]" <?php disabled( $lstabp_waiting ); ?>>
						<option value="">
							<?php
							echo esc_html(
								$lstabp_is_new
									? __( '— pick a column —', 'live-sheets-table-pro' )
									: __( '— remove this rule —', 'live-sheets-table-pro' )
							);
							?>
						</option>
						<?php if ( isset( $lstabp_orphans[ $lstabp_index ] ) ) : ?>
							<option value="<?php echo esc_attr( $lstabp_rule['column'] ); ?>" selected>
								<?php
								printf(
									/* translators: %s: the heading a rule names. */
									esc_html__( '%s — not in the sheet any more', 'live-sheets-table-pro' ),
									esc_html( $lstabp_rule['column'] )
								);
								?>
							</option>
						<?php endif; ?>
						<?php foreach ( $headers as $lstabp_heading ) : ?>
							<option value="<?php echo esc_attr( $lstabp_heading ); ?>" <?php selected( $lstabp_rule['column'], $lstabp_heading ); ?>>
								<?php echo esc_html( $lstabp_heading ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<select name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][operator]" <?php disabled( $lstabp_waiting ); ?>>
						<?php foreach ( $lstabp_operators as $lstabp_symbol => $lstabp_label ) : ?>
							<option value="<?php echo esc_attr( $lstabp_symbol ); ?>" <?php selected( $lstabp_rule['operator'], $lstabp_symbol ); ?>>
								<?php echo esc_html( $lstabp_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<input type="text" class="lstabp-rule-value"
						name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][value]"
						value="<?php echo esc_attr( $lstabp_rule['value'] ); ?>"
						placeholder="<?php esc_attr_e( 'the value to match', 'live-sheets-table-pro' ); ?>"
						<?php disabled( $lstabp_waiting ); ?>>

					<span class="lstabp-rule-word lstabp-rule-then"><?php esc_html_e( 'paint', 'live-sheets-table-pro' ); ?></span>

					<select name="lstabp_rules[<?php echo esc_attr( (string) $lstabp_index ); ?>][scope]" <?php disabled( $lstabp_waiting ); ?>>
						<option value="cell" <?php selected( $lstabp_rule['scope'], 'cell' ); ?>><?php esc_html_e( 'that cell', 'live-sheets-table-pro' ); ?></option>
						<option value="row" <?php selected( $lstabp_rule['scope'], 'row' ); ?>><?php esc_html_e( 'the whole row', 'live-sheets-table-pro' ); ?></option>
						<option value="text" <?php selected( $lstabp_rule['scope'], 'text' ); ?>><?php esc_html_e( 'just the words in that cell', 'live-sheets-table-pro' ); ?></option>
						<option value="pill" <?php selected( $lstabp_rule['scope'], 'pill' ); ?>><?php esc_html_e( 'a pill around that value', 'live-sheets-table-pro' ); ?></option>
						<option value="dot" <?php selected( $lstabp_rule['scope'], 'dot' ); ?>><?php esc_html_e( 'a dot before that value', 'live-sheets-table-pro' ); ?></option>
					</select>

					<?php
					$lstabp_style  = $lstabp_rule['style'];
					$lstabp_owncol = ( ! isset( $lstabp_palette[ $lstabp_style ] ) && ! isset( $lstabp_effects[ $lstabp_style ] ) )
						? $lstabp_style
						: '';
					$lstabp_field  = 'lstabp_rules[' . $lstabp_index . ']';
					?>
					<span class="lstabp-paint">
						<?php foreach ( $lstabp_palette as $lstabp_hex => $lstabp_name ) : ?>
							<label class="lstabp-paint-chip" title="<?php echo esc_attr( $lstabp_name ); ?>"
								style="background-color: <?php echo esc_attr( $lstabp_hex ); ?>;">
								<input type="radio"
									class="lstabp-style-input"
									name="<?php echo esc_attr( $lstabp_field ); ?>[style]"
									value="<?php echo esc_attr( $lstabp_hex ); ?>"
									<?php checked( $lstabp_style, $lstabp_hex ); ?>
									<?php disabled( $lstabp_waiting ); ?>>
								<span class="screen-reader-text"><?php echo esc_html( $lstabp_name ); ?></span>
							</label>
						<?php endforeach; ?>

						<?php foreach ( $lstabp_effects as $lstabp_key => $lstabp_effect ) : ?>
							<label class="lstabp-paint-chip lstabp-paint-effect-<?php echo esc_attr( $lstabp_key ); ?>"
								title="<?php echo esc_attr( $lstabp_effect['label'] ); ?>">
								<input type="radio"
									class="lstabp-style-input"
									name="<?php echo esc_attr( $lstabp_field ); ?>[style]"
									value="<?php echo esc_attr( $lstabp_key ); ?>"
									<?php checked( $lstabp_style, $lstabp_key ); ?>
									<?php disabled( $lstabp_waiting ); ?>>
								<span aria-hidden="true"><?php echo esc_html( $lstabp_effect['chip'] ); ?></span>
								<span class="screen-reader-text"><?php echo esc_html( $lstabp_effect['label'] ); ?></span>
							</label>
						<?php endforeach; ?>

						<?php
						$lstabp_own_face = '' !== $lstabp_owncol ? $lstabp_owncol : '';
						?>
					<span class="lstabp-paint-own-wrap<?php echo '' !== $lstabp_own_face ? ' has-colour' : ''; ?>">
						<label class="lstabp-paint-chip lstabp-paint-own"
							title="<?php esc_attr_e( 'A colour of your own', 'live-sheets-table-pro' ); ?>"
							<?php echo '' !== $lstabp_own_face ? 'style="background-image: none; background-color: ' . esc_attr( $lstabp_own_face ) . ';"' : ''; ?>>
							<input type="radio"
								class="lstabp-style-input"
								name="<?php echo esc_attr( $lstabp_field ); ?>[style]"
								value="custom"
								<?php checked( '' !== $lstabp_owncol ); ?>
								<?php disabled( $lstabp_waiting ); ?>>
							<span class="screen-reader-text"><?php esc_html_e( 'A colour of your own', 'live-sheets-table-pro' ); ?></span>
						</label>

						<input type="color"
							class="lstabp-own-colour"
							name="<?php echo esc_attr( $lstabp_field ); ?>[custom]"
							value="<?php echo esc_attr( '' !== $lstabp_owncol ? $lstabp_owncol : '#c7e0f4' ); ?>"
							aria-label="<?php esc_attr_e( 'Pick a colour of your own', 'live-sheets-table-pro' ); ?>"
							<?php disabled( $lstabp_waiting ); ?>>
					</span>
					</span>

					<span class="lstabp-swatch<?php echo in_array( $lstabp_rule['scope'], array( 'pill', 'dot' ), true ) ? ' lstabp-' . esc_attr( $lstabp_rule['scope'] ) . '-face' : ''; ?>" style="<?php echo esc_attr( LSTABP_Rules::css_for( $lstabp_style, $lstabp_rule['scope'] ) ); ?>">
						<?php esc_html_e( 'Abc', 'live-sheets-table-pro' ); ?>
					</span>

					<button type="button" class="lstabp-rule-drop"
						title="<?php esc_attr_e( 'Remove this rule', 'live-sheets-table-pro' ); ?>"
						<?php disabled( $lstabp_waiting ); ?>>
						<?php echo LSTAB_Icons::icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG. ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Remove this rule', 'live-sheets-table-pro' ); ?></span>
					</button>
					</span>
			</li>
