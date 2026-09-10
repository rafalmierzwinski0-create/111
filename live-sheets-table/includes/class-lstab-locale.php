<?php
/**
 * The language the plugin speaks in.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which language this plugin's own text is shown in.
 *
 * WordPress gives every plugin the site's language and no way for the reader to
 * disagree, which is the right default and wrong often enough to matter: a
 * Polish site run by an English-speaking developer, an agency working in one
 * language on sites published in another, a shop whose admin team does not
 * share the language of its customers. The choice here covers this plugin and
 * its add-on only — the rest of the dashboard is WordPress's own business.
 *
 * It is done by answering the translation filters rather than by loading a
 * catalogue under a different locale. Since WordPress 6.7 a plugin's catalogue
 * is loaded just in time, under one locale for the whole request, and a plugin
 * that loads its own under another one moves the line every other plugin's
 * translations are looked up on. Answering `gettext` touches nothing outside
 * these two text domains.
 */
class LSTAB_Locale {

	/** Both domains this setting governs. */
	const DOMAINS = array( 'live-sheets-table', 'live-sheets-table-pro' );

	/*
	 * What may be stored, kept apart from choices() on purpose: that method
	 * translates its first label, and a translation asks which language was
	 * chosen, which would ask choices() again and never come back.
	 */
	const CODES = array( '', 'en_US', 'pl_PL' );

	/**
	 * Catalogues already read, by locale then domain. False where there is none.
	 *
	 * @var array<string,array<string,MO|false>>
	 */
	private static $catalogues = array();

	/**
	 * The choice, read once per request. Null until it has been.
	 *
	 * @var string|null
	 */
	private static $chosen = null;

	/**
	 * The languages on offer.
	 *
	 * Keyed by what is stored: an empty key means "whatever the site is set
	 * to", which is what a site that has never opened this screen gets. The
	 * language names are deliberately untranslated — a language is easiest to
	 * find in a list when it is written in itself.
	 *
	 * @return array<string,string>
	 */
	public static function choices() {
		return array(
			''      => __( 'Same as the site', 'live-sheets-table' ),
			'en_US' => 'English',
			'pl_PL' => 'Polski',
		);
	}

	/**
	 * The stored choice, or an empty string for "same as the site".
	 *
	 * Read straight from the option rather than through LSTAB_Settings, because
	 * this is asked on every translated string and the defaults array behind
	 * that call reaches into half the plugin.
	 *
	 * @return string
	 */
	public static function chosen() {
		if ( null !== self::$chosen ) {
			return self::$chosen;
		}

		$settings     = get_option( LSTAB_Settings::OPTION, array() );
		$chosen       = is_array( $settings ) && isset( $settings['locale'] ) ? (string) $settings['locale'] : '';
		self::$chosen = in_array( $chosen, self::CODES, true ) ? $chosen : '';

		return self::$chosen;
	}

	/**
	 * Forget the choice, so the next string asks the option again.
	 *
	 * Saving the settings and then printing "Settings saved." happens in one
	 * request, and it would be a poor advertisement for the new language if
	 * that line came back in the old one.
	 *
	 * @return void
	 */
	public static function forget() {
		self::$chosen = null;
	}

	/**
	 * Keep only a language that is actually on the list.
	 *
	 * @param string $value Submitted value.
	 * @return string
	 */
	public static function sanitize( $value ) {
		$value = (string) $value;

		return in_array( $value, self::CODES, true ) ? $value : '';
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		/*
		 * Both, because WordPress fires one or the other and never both: the
		 * very first save on a new site *creates* the option, and only a
		 * later one updates it. Listening for the update alone meant a site
		 * that had never opened the settings screen chose a language, saved,
		 * and was answered in the old one — the single occasion this cache
		 * exists to get right.
		 */
		add_action( 'add_option_' . LSTAB_Settings::OPTION, array( __CLASS__, 'forget' ) );
		add_action( 'update_option_' . LSTAB_Settings::OPTION, array( __CLASS__, 'forget' ) );

		add_filter( 'gettext', array( __CLASS__, 'single' ), 10, 3 );
		add_filter( 'gettext_with_context', array( __CLASS__, 'single_with_context' ), 10, 4 );
		add_filter( 'ngettext', array( __CLASS__, 'plural' ), 10, 5 );
		add_filter( 'ngettext_with_context', array( __CLASS__, 'plural_with_context' ), 10, 6 );

		// The block's own panel reads a JSON catalogue, which takes its locale
		// from the site alone; this points it at the chosen one instead.
		add_filter( 'load_script_translation_file', array( __CLASS__, 'pick_for_script' ), 10, 3 );
	}

	/**
	 * One string.
	 *
	 * @param string $translation What WordPress found.
	 * @param string $text        The English source.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function single( $translation, $text, $domain ) {
		return self::say( $translation, $text, '', $domain );
	}

	/**
	 * One string with a context.
	 *
	 * @param string $translation What WordPress found.
	 * @param string $text        The English source.
	 * @param string $context     Disambiguating context.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function single_with_context( $translation, $text, $context, $domain ) {
		return self::say( $translation, $text, $context, $domain );
	}

	/**
	 * A counted string.
	 *
	 * @param string $translation What WordPress found.
	 * @param string $single      Singular source.
	 * @param string $plural      Plural source.
	 * @param int    $number      How many.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function plural( $translation, $single, $plural, $number, $domain ) {
		return self::count( $translation, $single, $plural, $number, '', $domain );
	}

	/**
	 * A counted string with a context.
	 *
	 * @param string $translation What WordPress found.
	 * @param string $single      Singular source.
	 * @param string $plural      Plural source.
	 * @param int    $number      How many.
	 * @param string $context     Disambiguating context.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function plural_with_context( $translation, $single, $plural, $number, $context, $domain ) {
		return self::count( $translation, $single, $plural, $number, $context, $domain );
	}

	/**
	 * The chosen language's answer for one string.
	 *
	 * @param string $translation What WordPress found.
	 * @param string $text        The English source.
	 * @param string $context     Disambiguating context.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	private static function say( $translation, $text, $context, $domain ) {
		$catalogue = self::catalogue( $domain );

		if ( null === $catalogue ) {
			return $translation;
		}

		// English is the source language, so there is no catalogue to read:
		// the string as written is the answer.
		if ( false === $catalogue ) {
			return $text;
		}

		// POMO reads an empty context as a real one and looks for a key that
		// nothing was ever filed under; only null means "no context".
		$found = $catalogue->translate( $text, '' === $context ? null : $context );

		return is_string( $found ) ? $found : $text;
	}

	/**
	 * The chosen language's answer for a counted string.
	 *
	 * @param string $translation What WordPress found.
	 * @param string $single      Singular source.
	 * @param string $plural      Plural source.
	 * @param int    $number      How many.
	 * @param string $context     Disambiguating context.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	private static function count( $translation, $single, $plural, $number, $context, $domain ) {
		$catalogue = self::catalogue( $domain );

		if ( null === $catalogue ) {
			return $translation;
		}

		if ( false === $catalogue ) {
			return 1 === (int) $number ? $single : $plural;
		}

		$found = $catalogue->translate_plural(
			$single,
			$plural,
			(int) $number,
			'' === $context ? null : $context
		);

		return is_string( $found ) ? $found : ( 1 === (int) $number ? $single : $plural );
	}

	/**
	 * The catalogue to answer from.
	 *
	 * @param string $domain Text domain.
	 * @return MO|false|null MO to read, false for English, null to stand aside.
	 */
	private static function catalogue( $domain ) {
		if ( ! in_array( $domain, self::DOMAINS, true ) ) {
			return null;
		}

		$locale = self::chosen();

		if ( '' === $locale ) {
			return null;
		}

		if ( isset( self::$catalogues[ $locale ][ $domain ] ) ) {
			return self::$catalogues[ $locale ][ $domain ];
		}

		$catalogue = false;

		if ( 'en_US' !== $locale ) {
			$file = self::mofile( $domain, $locale );

			if ( $file ) {
				// POMO is loaded on demand in modern WordPress, so it may not
				// be here yet when the first string is translated.
				require_once ABSPATH . WPINC . '/pomo/mo.php';

				$mo = new MO();

				if ( $mo->import_from_file( $file ) ) {
					$catalogue = $mo;
				}
			}
		}

		self::$catalogues[ $locale ][ $domain ] = $catalogue;

		return $catalogue;
	}

	/**
	 * Where a domain's compiled catalogue lives.
	 *
	 * A translation dropped into wp-content/languages/plugins wins, the way it
	 * does for every other plugin, so a site can correct our wording without
	 * editing the plugin.
	 *
	 * @param string $domain Text domain.
	 * @param string $locale Locale code.
	 * @return string|false
	 */
	private static function mofile( $domain, $locale ) {
		$candidates = array(
			WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo',
			WP_PLUGIN_DIR . '/' . $domain . '/languages/' . $domain . '-' . $locale . '.mo',
		);

		foreach ( $candidates as $candidate ) {
			if ( is_readable( $candidate ) ) {
				return $candidate;
			}
		}

		return false;
	}

	/**
	 * The same choice, for the catalogue the block's panel reads.
	 *
	 * Scripts take their language from determine_locale() alone, so the file
	 * WordPress is about to open is renamed instead. Core builds that name as
	 * domain-locale-<md5 of the script path>.json, so only the locale in the
	 * middle has to change.
	 *
	 * @param string|false $file   Catalogue WordPress would have read.
	 * @param string       $handle Script handle.
	 * @param string       $domain Text domain.
	 * @return string|false
	 */
	public static function pick_for_script( $file, $handle, $domain ) {
		if ( ! is_string( $file ) || ! in_array( $domain, self::DOMAINS, true ) ) {
			return $file;
		}

		$chosen = self::chosen();

		if ( '' === $chosen ) {
			return $file;
		}

		$prefix = $domain . '-';
		$name   = basename( $file );

		if ( 0 !== strpos( $name, $prefix ) ) {
			return $file;
		}

		// What is left after "domain-" is "locale-rest.json"; a locale never
		// holds a hyphen, so the first one is where the locale ends.
		$rest = substr( $name, strlen( $prefix ) );
		$cut  = strpos( $rest, '-' );

		if ( false === $cut ) {
			return $file;
		}

		return dirname( $file ) . '/' . $prefix . $chosen . substr( $rest, $cut );
	}

	/**
	 * A length of time, in the language the plugin was told to use.
	 *
	 * WordPress's own human_time_diff() answers in the site's language, which
	 * would leave "1 week" sitting in the middle of a Polish sentence on a
	 * screen this setting has otherwise translated. Only this plugin's own
	 * durations come through here; core's own function is left alone, so
	 * nobody else's dates change.
	 *
	 * The thresholds are core's, so the two never disagree about whether
	 * something is a day old or a week old.
	 *
	 * @param int $from Timestamp.
	 * @param int $to   Timestamp. Defaults to now.
	 * @return string
	 */
	public static function span( $from, $to = 0 ) {
		if ( '' === self::chosen() ) {
			return human_time_diff( $from, $to );
		}

		$to   = $to ? (int) $to : time();
		$diff = (int) abs( $to - (int) $from );

		if ( $diff < MINUTE_IN_SECONDS ) {
			$count = max( 1, $diff );
			/* translators: %s: number of seconds. */
			$said = _n( '%s second', '%s seconds', $count, 'live-sheets-table' );
		} elseif ( $diff < HOUR_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / MINUTE_IN_SECONDS ) );
			/* translators: %s: number of minutes. */
			$said = _n( '%s minute', '%s minutes', $count, 'live-sheets-table' );
		} elseif ( $diff < DAY_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / HOUR_IN_SECONDS ) );
			/* translators: %s: number of hours. */
			$said = _n( '%s hour', '%s hours', $count, 'live-sheets-table' );
		} elseif ( $diff < WEEK_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / DAY_IN_SECONDS ) );
			/* translators: %s: number of days. */
			$said = _n( '%s day', '%s days', $count, 'live-sheets-table' );
		} elseif ( $diff < MONTH_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / WEEK_IN_SECONDS ) );
			/* translators: %s: number of weeks. */
			$said = _n( '%s week', '%s weeks', $count, 'live-sheets-table' );
		} elseif ( $diff < YEAR_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / MONTH_IN_SECONDS ) );
			/* translators: %s: number of months. */
			$said = _n( '%s month', '%s months', $count, 'live-sheets-table' );
		} else {
			$count = max( 1, (int) round( $diff / YEAR_IN_SECONDS ) );
			/* translators: %s: number of years. */
			$said = _n( '%s year', '%s years', $count, 'live-sheets-table' );
		}

		return sprintf( $said, number_format_i18n( $count ) );
	}
}
