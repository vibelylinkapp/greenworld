<?php
/**
 * Lightweight, privacy-preserving monitoring for the Green World Assistant.
 *
 * Records ONLY operational metadata per AI call - provider, model, latency,
 * token usage, safety classification, outcome, and (if one was opened) the
 * case id. It never stores the customer's message, the model's reply, contact
 * details, or any medical content. Held as a capped ring buffer in an option
 * plus lifetime and per-month aggregate counters, so it needs no database
 * schema.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_AI_Log {

	const OPTION_LOG   = 'gwc_ai_log';
	const OPTION_AGG   = 'gwc_ai_agg';
	const OPTION_MONTH = 'gwc_ai_month';
	const MAX_ROWS     = 500;

	/**
	 * Record one call. Metadata only - never message or reply text.
	 *
	 * @param array<string,mixed> $row provider, model, latency_ms, safety,
	 *                                  outcome, tokens[prompt,completion], case_id, ok.
	 */
	public static function record( array $row ): void {
		$p_tok = isset( $row['tokens']['prompt'] ) ? (int) $row['tokens']['prompt'] : 0;
		$c_tok = isset( $row['tokens']['completion'] ) ? (int) $row['tokens']['completion'] : 0;

		$entry = array(
			't'          => time(),
			'provider'   => isset( $row['provider'] ) ? sanitize_key( (string) $row['provider'] ) : '',
			'model'      => isset( $row['model'] ) ? sanitize_text_field( (string) $row['model'] ) : '',
			'latency_ms' => isset( $row['latency_ms'] ) ? (int) $row['latency_ms'] : 0,
			'safety'     => isset( $row['safety'] ) ? sanitize_key( (string) $row['safety'] ) : '',
			'outcome'    => isset( $row['outcome'] ) ? sanitize_key( (string) $row['outcome'] ) : '',
			'p_tok'      => $p_tok,
			'c_tok'      => $c_tok,
			'case_id'    => isset( $row['case_id'] ) ? (int) $row['case_id'] : 0,
			'ok'         => empty( $row['ok'] ) ? 0 : 1,
		);

		$log = get_option( self::OPTION_LOG, array() );
		$log = is_array( $log ) ? $log : array();
		$log[] = $entry;
		if ( count( $log ) > self::MAX_ROWS ) {
			$log = array_slice( $log, -self::MAX_ROWS );
		}
		update_option( self::OPTION_LOG, $log, false );

		// Lifetime aggregates.
		$agg               = get_option( self::OPTION_AGG, array() );
		$agg               = is_array( $agg ) ? $agg : array();
		$agg['calls']      = ( isset( $agg['calls'] ) ? (int) $agg['calls'] : 0 ) + 1;
		$agg['p_tok']      = ( isset( $agg['p_tok'] ) ? (int) $agg['p_tok'] : 0 ) + $p_tok;
		$agg['c_tok']      = ( isset( $agg['c_tok'] ) ? (int) $agg['c_tok'] : 0 ) + $c_tok;
		$agg['latency_ms'] = ( isset( $agg['latency_ms'] ) ? (int) $agg['latency_ms'] : 0 ) + $entry['latency_ms'];
		if ( 1 === $entry['ok'] ) {
			$agg['ok'] = ( isset( $agg['ok'] ) ? (int) $agg['ok'] : 0 ) + 1;
		}

		foreach ( array( 'green', 'yellow', 'red' ) as $lvl ) {
			if ( $lvl === $entry['safety'] ) {
				$k         = 'safety_' . $lvl;
				$agg[ $k ] = ( isset( $agg[ $k ] ) ? (int) $agg[ $k ] : 0 ) + 1;
			}
		}
		$pk         = 'prov_' . ( '' !== $entry['provider'] ? $entry['provider'] : 'none' );
		$agg[ $pk ] = ( isset( $agg[ $pk ] ) ? (int) $agg[ $pk ] : 0 ) + 1;
		update_option( self::OPTION_AGG, $agg, false );

		// Rolling monthly counter (budget visibility).
		$month = gmdate( 'Y-m' );
		$mrec  = get_option( self::OPTION_MONTH, array() );
		$mrec  = is_array( $mrec ) ? $mrec : array();
		if ( ! isset( $mrec['month'] ) || $mrec['month'] !== $month ) {
			$mrec = array(
				'month' => $month,
				'calls' => 0,
				'p_tok' => 0,
				'c_tok' => 0,
			);
		}
		$mrec['calls'] = (int) $mrec['calls'] + 1;
		$mrec['p_tok'] = (int) $mrec['p_tok'] + $p_tok;
		$mrec['c_tok'] = (int) $mrec['c_tok'] + $c_tok;
		update_option( self::OPTION_MONTH, $mrec, false );
	}

	/** Most recent entries, newest first. */
	public static function recent( int $limit = 50 ): array {
		$log = get_option( self::OPTION_LOG, array() );
		$log = is_array( $log ) ? $log : array();
		$log = array_reverse( $log );
		return array_slice( $log, 0, max( 1, $limit ) );
	}

	public static function aggregates(): array {
		$agg = get_option( self::OPTION_AGG, array() );
		return is_array( $agg ) ? $agg : array();
	}

	public static function month(): array {
		$m = get_option( self::OPTION_MONTH, array() );
		return is_array( $m ) ? $m : array();
	}
}
