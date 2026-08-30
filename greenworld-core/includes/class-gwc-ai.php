<?php
/**
 * Green World Assistant orchestrator.
 *
 * Ties the AI layer together:
 *   User -> Assistant -> Safety router -> GREEN/YELLOW/RED -> approved knowledge
 *   base -> Gemini/Groq (provider abstraction) -> response or case creation.
 *
 * - Grounds every answer ONLY on GWC_Compliance approved data (ai_usable items).
 * - Runs the deterministic safety router first; RED/YELLOW never reach a
 *   generative "recommendation"; the model can only RAISE severity.
 * - Creates cases through the existing gw_consultation + GWC_Cases workflow.
 * - Exposes a nonce + rate-limited AJAX endpoint and a [green_world_assistant]
 *   chat widget. API keys stay server-side and never touch the browser.
 * - Logs metadata only (see GWC_AI_Log).
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_AI {

	private static $instance = null;

	const OPTION   = 'gwc_ai';
	const KB_CACHE = 'gwc_ai_kb_index';

	private $rendered = false;

	public static function instance(): GWC_AI {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'wp_ajax_gwc_ai_message', array( $this, 'ajax_message' ) );
		add_action( 'wp_ajax_nopriv_gwc_ai_message', array( $this, 'ajax_message' ) );
		add_shortcode( 'green_world_assistant', array( $this, 'shortcode_widget' ) );
		add_action( 'wp_footer', array( $this, 'maybe_render_sitewide' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 40 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
	}

	/* --------------------------------------------------------------- settings */

	public function defaults(): array {
		return array(
			'enabled'      => 1,
			'sitewide'     => 1,
			'order'        => 'gemini,groq,openai',
			'gemini_model' => GWC_AI_Provider_Gemini::DEFAULT_MODEL,
			'groq_model'   => GWC_AI_Provider_Groq::DEFAULT_MODEL,
			'openai_model' => GWC_AI_Provider_OpenAI::DEFAULT_MODEL,
			'temperature'  => 0.2,
			'max_tokens'   => 600,
			'kb_limit'     => 16,
			'rate_max'     => 20,
			'rate_window'  => 600,
			'budget_usd'   => 0,
			'widget_title' => 'Green World Assistant',
			'intro'        => 'Hello! I can help with product information, prices, availability, orders and general wellness questions. For anything about your personal health, I will connect you with an advisor.',
		);
	}

	public function settings(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $this->defaults(), $saved );
	}

	public function register_settings(): void {
		register_setting( 'gwc_ai_group', self::OPTION, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ): array {
		$d   = $this->defaults();
		$out = array();

		$out['enabled'] = empty( $input['enabled'] ) ? 0 : 1;
		$out['sitewide'] = empty( $input['sitewide'] ) ? 0 : 1;

		$order = isset( $input['order'] ) ? (string) $input['order'] : $d['order'];
		$known = array( 'gemini', 'groq', 'openai' );
		$clean = array();
		foreach ( explode( ',', $order ) as $id ) {
			$id = sanitize_key( trim( $id ) );
			if ( in_array( $id, $known, true ) && ! in_array( $id, $clean, true ) ) {
				$clean[] = $id;
			}
		}
		if ( empty( $clean ) ) {
			$clean = array( 'gemini', 'groq' );
		}
		$out['order'] = implode( ',', $clean );

		foreach ( array( 'gemini_model', 'groq_model', 'openai_model' ) as $k ) {
			$v        = isset( $input[ $k ] ) ? sanitize_text_field( (string) $input[ $k ] ) : '';
			$out[ $k ] = '' !== $v ? $v : $d[ $k ];
		}

		$temp               = isset( $input['temperature'] ) ? (float) $input['temperature'] : $d['temperature'];
		$out['temperature'] = max( 0.0, min( 1.5, $temp ) );
		$out['max_tokens']  = max( 64, min( 4000, isset( $input['max_tokens'] ) ? (int) $input['max_tokens'] : $d['max_tokens'] ) );
		$out['kb_limit']    = max( 1, min( 50, isset( $input['kb_limit'] ) ? (int) $input['kb_limit'] : $d['kb_limit'] ) );
		$out['rate_max']    = max( 0, min( 1000, isset( $input['rate_max'] ) ? (int) $input['rate_max'] : $d['rate_max'] ) );
		$out['rate_window'] = max( 30, min( 86400, isset( $input['rate_window'] ) ? (int) $input['rate_window'] : $d['rate_window'] ) );
		$out['budget_usd']  = max( 0, min( 100000, isset( $input['budget_usd'] ) ? (int) $input['budget_usd'] : $d['budget_usd'] ) );

		$out['widget_title'] = isset( $input['widget_title'] ) ? sanitize_text_field( (string) $input['widget_title'] ) : $d['widget_title'];
		if ( '' === $out['widget_title'] ) {
			$out['widget_title'] = $d['widget_title'];
		}
		$out['intro'] = isset( $input['intro'] ) ? sanitize_textarea_field( (string) $input['intro'] ) : $d['intro'];
		if ( '' === $out['intro'] ) {
			$out['intro'] = $d['intro'];
		}

		delete_transient( self::KB_CACHE );
		return $out;
	}

	/** Monthly budget in USD - env/constant override, then setting. 0 = free tier only. */
	public function monthly_budget(): int {
		$env = getenv( 'GWC_AI_MONTHLY_BUDGET' );
		if ( is_string( $env ) && is_numeric( trim( $env ) ) ) {
			return (int) trim( $env );
		}
		if ( defined( 'GWC_AI_MONTHLY_BUDGET' ) && is_numeric( (string) constant( 'GWC_AI_MONTHLY_BUDGET' ) ) ) {
			return (int) constant( 'GWC_AI_MONTHLY_BUDGET' );
		}
		$s = $this->settings();
		return (int) $s['budget_usd'];
	}

	/* -------------------------------------------------------------- providers */

	private function provider_for( string $id, array $s ) {
		switch ( $id ) {
			case 'gemini':
				return new GWC_AI_Provider_Gemini( (string) $s['gemini_model'] );
			case 'groq':
				return new GWC_AI_Provider_Groq( (string) $s['groq_model'] );
			case 'openai':
				return new GWC_AI_Provider_OpenAI( (string) $s['openai_model'] );
		}
		return null;
	}

	/**
	 * Ordered provider instances (primary first). Optionally only those whose
	 * key is present server-side.
	 *
	 * @return array<int,GWC_AI_Provider>
	 */
	public function providers_ordered( bool $only_available = true ): array {
		$s   = $this->settings();
		$ids = array_filter( array_map( 'trim', explode( ',', (string) $s['order'] ) ) );
		if ( empty( $ids ) ) {
			$ids = array( 'gemini', 'groq' );
		}
		$out  = array();
		$seen = array();
		foreach ( $ids as $id ) {
			$id = sanitize_key( $id );
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = 1;
			$p           = $this->provider_for( $id, $s );
			if ( null === $p ) {
				continue;
			}
			if ( $only_available && ! $p->is_available() ) {
				continue;
			}
			$out[] = $p;
		}
		return $out;
	}

	/* ------------------------------------------------------------ knowledge base */

	/**
	 * Build (and cache) a searchable index of approved products. Only ai_usable
	 * items - compliance-ready and not risk-flagged - are ever included.
	 *
	 * @return array<int,array{name:string,hay:string,line:string}>
	 */
	public function build_kb_index(): array {
		$cached = get_transient( self::KB_CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$index = array();
		if ( class_exists( 'GWC_Compliance' ) && is_callable( array( 'GWC_Compliance', 'catalogue_payload' ) ) ) {
			$items = GWC_Compliance::catalogue_payload( 500 );
			foreach ( (array) $items as $p ) {
				if ( ! is_array( $p ) || empty( $p['ai_usable'] ) ) {
					continue;
				}
				$cats = isset( $p['categories'] ) ? (array) $p['categories'] : array();
				$hay  = strtolower(
					trim(
						( isset( $p['name'] ) ? $p['name'] : '' ) . ' ' .
						implode( ' ', array_map( 'strval', $cats ) ) . ' ' .
						( isset( $p['intended_use'] ) ? $p['intended_use'] : '' ) . ' ' .
						( isset( $p['benefits'] ) ? $p['benefits'] : '' ) . ' ' .
						( isset( $p['ingredients'] ) ? $p['ingredients'] : '' )
					)
				);
				$index[] = array(
					'name' => (string) ( isset( $p['name'] ) ? $p['name'] : '' ),
					'hay'  => $hay,
					'line' => $this->kb_line( $p ),
				);
			}
		}
		set_transient( self::KB_CACHE, $index, 15 * MINUTE_IN_SECONDS );
		return $index;
	}

	private function kb_line( array $p ): string {
		$name  = (string) ( isset( $p['name'] ) ? $p['name'] : '' );
		$price = (string) ( isset( $p['price'] ) ? $p['price'] : '' );
		$stock = empty( $p['in_stock'] ) ? 'out of stock' : 'in stock';
		$head  = '- ' . $name . ( '' !== $price ? ' (' . $price . ', ' . $stock . ')' : ' (' . $stock . ')' );

		$bits = array( $head );

		$cats = isset( $p['categories'] ) ? (array) $p['categories'] : array();
		if ( ! empty( $cats ) ) {
			$bits[] = '  Category: ' . implode( ', ', array_map( 'strval', $cats ) );
		}

		$map = array(
			'Use'               => 'intended_use',
			'Benefits'          => 'benefits',
			'Ingredients'       => 'ingredients',
			'Directions'        => 'directions',
			'Warnings'          => 'warnings',
			'Contraindications' => 'contraindications',
			'Pregnancy'         => 'pregnancy',
			'Interactions'      => 'interactions',
		);
		foreach ( $map as $label => $key ) {
			$v = isset( $p[ $key ] ) ? trim( (string) $p[ $key ] ) : '';
			if ( '' === $v ) {
				continue;
			}
			if ( strlen( $v ) > 220 ) {
				$v = substr( $v, 0, 217 ) . '...';
			}
			$bits[] = '  ' . $label . ': ' . $v;
		}

		$link = (string) ( isset( $p['permalink'] ) ? $p['permalink'] : '' );
		if ( '' !== $link ) {
			$bits[] = '  Link: ' . $link;
		}
		return implode( "\n", $bits );
	}

	/** Compact, query-relevant slice of the approved catalogue. */
	public function kb_context( string $query, int $limit ): string {
		$index = $this->build_kb_index();
		if ( empty( $index ) ) {
			return '';
		}
		$parts = preg_split( '/[^a-z0-9]+/', strtolower( $query ) );
		$words = array();
		foreach ( (array) $parts as $w ) {
			if ( strlen( $w ) > 2 ) {
				$words[ $w ] = 1;
			}
		}
		$words = array_keys( $words );

		$scored = array();
		foreach ( $index as $i => $row ) {
			$score = 0;
			foreach ( $words as $w ) {
				if ( false !== strpos( $row['hay'], $w ) ) {
					$score++;
				}
			}
			$scored[] = array(
				'score' => $score,
				'i'     => $i,
			);
		}
		usort(
			$scored,
			static function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return $a['i'] - $b['i'];
				}
				return $b['score'] - $a['score'];
			}
		);

		$lines = array();
		$count = 0;
		$len   = 0;
		foreach ( $scored as $row ) {
			if ( $count >= $limit ) {
				break;
			}
			$line = $index[ $row['i'] ]['line'];
			$len += strlen( $line );
			if ( $len > 7000 ) {
				break;
			}
			$lines[] = $line;
			$count++;
		}
		return implode( "\n", $lines );
	}

	private function system_prompt( string $kb ): string {
		$kb_block = '' !== $kb ? $kb : '(no approved products matched this question; do not guess - offer to connect the customer with an advisor)';

		return "You are the Green World Health Assistant for greenworldhealth.co.ke, a natural wellness retailer serving Kenya and beyond.\n\n"
			. "STRICT RULES (follow every one):\n"
			. "1. Answer ONLY using the APPROVED PRODUCT DATA below. If the data does not contain the answer, say you are not certain and offer to connect the customer with a Green World Health advisor. Never invent products, ingredients, prices, claims, or facts.\n"
			. "2. You give GENERAL product and wellness information only. You do NOT diagnose, prescribe, recommend treatment for a medical condition, or make medical claims. Never say or imply a product treats, cures, or prevents any disease.\n"
			. "3. Do not give personalised medical advice, per-person dosing, or advice about drug interactions, pregnancy or breastfeeding, or existing medical conditions. If asked, politely decline and defer to a Green World Health advisor.\n"
			. "4. Be concise, warm and practical. Mirror the customer's language. When relevant, include the product price, whether it is in stock, and the product link.\n"
			. "5. Respond with STRICT JSON only (no markdown, no code fences) in exactly this shape:\n"
			. "{\"answer\": \"<reply to the customer>\", \"safety\": \"green|yellow|red\", \"product\": \"<single most relevant product name or empty>\", \"needs_human\": true|false}\n"
			. "Set safety to yellow or red and needs_human true whenever the question actually involves symptoms, personal suitability, dosing judgement, interactions, pregnancy, existing conditions, an emergency, or anything you cannot answer safely from the approved data.\n\n"
			. "APPROVED PRODUCT DATA (the only facts you may use):\n"
			. $kb_block;
	}

	/* ---------------------------------------------------------------- pipeline */

	/**
	 * Core pipeline. Returns reply + safety + optional case number (+ debug for admins).
	 *
	 * @param array<int,array<string,string>> $history
	 * @param array<string,string>            $contact
	 * @return array<string,mixed>
	 */
	public function handle_message( string $message, array $history, array $contact, string $channel, bool $with_debug = false ): array {
		$message = trim( wp_strip_all_tags( $message ) );
		$channel = '' !== $channel ? sanitize_key( $channel ) : 'web';

		$result = array(
			'ok'          => true,
			'reply'       => '',
			'safety'      => GWC_AI_Safety::GREEN,
			'case_number' => '',
		);
		$debug = array();

		if ( '' === $message ) {
			$result['ok']    = false;
			$result['reply'] = __( 'Please type a question and I will help.', 'greenworld-core' );
			return $with_debug ? array_merge( $result, array( 'debug' => $debug ) ) : $result;
		}

		// 1) Deterministic safety classification (the floor).
		$cls    = GWC_AI_Safety::classify( $message );
		$level  = $cls['level'];
		$reason = $cls['reason'];

		// 2) RED - escalate immediately, never generate a health answer.
		if ( GWC_AI_Safety::RED === $level ) {
			$case = $this->create_case(
				$level,
				array(
					'question' => $message,
					'summary'  => $this->short_summary( $message ),
					'reason'   => $reason,
					'product'  => '',
					'channel'  => $channel,
					'contact'  => $contact,
				)
			);
			GWC_AI_Log::record(
				array(
					'provider'   => 'none',
					'model'      => '',
					'latency_ms' => 0,
					'safety'     => 'red',
					'outcome'    => 'escalated_red',
					'tokens'     => array(),
					'case_id'    => $case['id'],
					'ok'         => true,
				)
			);
			$result['safety']      = 'red';
			$result['reply']       = GWC_AI_Safety::red_reply();
			$result['case_number'] = $case['number'];
			if ( $with_debug ) {
				$debug = array(
					'safety'   => 'red',
					'reason'   => $reason,
					'provider' => 'none',
					'case'     => $case['number'],
				);
			}
			return $with_debug ? array_merge( $result, array( 'debug' => $debug ) ) : $result;
		}

		// 3) YELLOW - safe holding reply + advisor case; no definitive answer.
		if ( GWC_AI_Safety::YELLOW === $level ) {
			$case = $this->create_case(
				$level,
				array(
					'question' => $message,
					'summary'  => $this->short_summary( $message ),
					'reason'   => $reason,
					'product'  => '',
					'channel'  => $channel,
					'contact'  => $contact,
				)
			);
			GWC_AI_Log::record(
				array(
					'provider'   => 'none',
					'model'      => '',
					'latency_ms' => 0,
					'safety'     => 'yellow',
					'outcome'    => 'escalated_yellow',
					'tokens'     => array(),
					'case_id'    => $case['id'],
					'ok'         => true,
				)
			);
			$result['safety']      = 'yellow';
			$result['reply']       = GWC_AI_Safety::yellow_reply();
			$result['case_number'] = $case['number'];
			if ( $with_debug ) {
				$debug = array(
					'safety'   => 'yellow',
					'reason'   => $reason,
					'provider' => 'none',
					'case'     => $case['number'],
				);
			}
			return $with_debug ? array_merge( $result, array( 'debug' => $debug ) ) : $result;
		}

		// 4) GREEN - answer from the approved knowledge base.
		$s         = $this->settings();
		$providers = $this->providers_ordered( true );

		if ( empty( $providers ) ) {
			$case = $this->create_case(
				GWC_AI_Safety::GREEN,
				array(
					'question' => $message,
					'summary'  => $this->short_summary( $message ),
					'reason'   => 'AI provider not configured; captured for human follow-up.',
					'product'  => '',
					'channel'  => $channel,
					'contact'  => $contact,
				)
			);
			GWC_AI_Log::record(
				array(
					'provider'   => 'none',
					'model'      => '',
					'latency_ms' => 0,
					'safety'     => 'green',
					'outcome'    => 'no_provider',
					'tokens'     => array(),
					'case_id'    => $case['id'],
					'ok'         => false,
				)
			);
			$result['reply']       = __( 'Thanks for your message. Our assistant is not available right now, but I have passed your question to the Green World Health team and someone will get back to you.', 'greenworld-core' );
			$result['case_number'] = $case['number'];
			if ( $with_debug ) {
				$debug = array(
					'safety'   => 'green',
					'provider' => 'none',
					'note'     => 'no provider available',
				);
			}
			return $with_debug ? array_merge( $result, array( 'debug' => $debug ) ) : $result;
		}

		$kb       = $this->kb_context( $message, (int) $s['kb_limit'] );
		$system   = $this->system_prompt( $kb );
		$messages = $this->build_messages( $history, $message );

		$ai = $this->call_ai(
			$system,
			$messages,
			$providers,
			array(
				'temperature' => (float) $s['temperature'],
				'max_tokens'  => (int) $s['max_tokens'],
			)
		);

		if ( ! $ai['ok'] ) {
			GWC_AI_Log::record(
				array(
					'provider'   => $ai['provider'],
					'model'      => $ai['model'],
					'latency_ms' => $ai['latency_ms'],
					'safety'     => 'green',
					'outcome'    => 'provider_error',
					'tokens'     => $ai['usage'],
					'case_id'    => 0,
					'ok'         => false,
				)
			);
			$result['ok']    = false;
			$result['reply'] = __( 'Sorry, I am having trouble answering right now. Please try again shortly, or use the consultation form and an advisor will help.', 'greenworld-core' );
			if ( $with_debug ) {
				$debug = array(
					'safety'   => 'green',
					'provider' => $ai['provider'],
					'error'    => $ai['error'],
				);
			}
			return $with_debug ? array_merge( $result, array( 'debug' => $debug ) ) : $result;
		}

		$parsed = $this->parse_model_json( $ai['text'] );

		// Reconcile: rules said green here; the model may only RAISE severity.
		$final_level = GWC_AI_Safety::escalate( GWC_AI_Safety::GREEN, GWC_AI_Safety::normalise( $parsed['safety'] ) );
		if ( ! empty( $parsed['needs_human'] ) && GWC_AI_Safety::GREEN === $final_level ) {
			$final_level = GWC_AI_Safety::YELLOW;
		}

		if ( GWC_AI_Safety::GREEN !== $final_level ) {
			// Model caught a risk the rules missed - escalate; never return its free-form answer as advice.
			$case = $this->create_case(
				$final_level,
				array(
					'question' => $message,
					'summary'  => $this->short_summary( $message ),
					'reason'   => 'Model self-assessed as ' . $final_level . '.',
					'product'  => (string) $parsed['product'],
					'channel'  => $channel,
					'contact'  => $contact,
				)
			);
			GWC_AI_Log::record(
				array(
					'provider'   => $ai['provider'],
					'model'      => $ai['model'],
					'latency_ms' => $ai['latency_ms'],
					'safety'     => $final_level,
					'outcome'    => 'escalated_' . $final_level,
					'tokens'     => $ai['usage'],
					'case_id'    => $case['id'],
					'ok'         => true,
				)
			);
			$result['safety']      = $final_level;
			$result['reply']       = ( GWC_AI_Safety::RED === $final_level ) ? GWC_AI_Safety::red_reply() : GWC_AI_Safety::yellow_reply();
			$result['case_number'] = $case['number'];
			if ( $with_debug ) {
				$debug = array(
					'safety'       => $final_level,
					'provider'     => $ai['provider'],
					'model'        => $ai['model'],
					'latency_ms'   => $ai['latency_ms'],
					'tokens'       => $ai['usage'],
					'escalated_by' => 'model',
					'case'         => $case['number'],
				);
			}
			return $with_debug ? array_merge( $result, array( 'debug' => $debug ) ) : $result;
		}

		// GREEN answered.
		GWC_AI_Log::record(
			array(
				'provider'   => $ai['provider'],
				'model'      => $ai['model'],
				'latency_ms' => $ai['latency_ms'],
				'safety'     => 'green',
				'outcome'    => 'answered',
				'tokens'     => $ai['usage'],
				'case_id'    => 0,
				'ok'         => true,
			)
		);
		$result['reply'] = '' !== trim( (string) $parsed['answer'] ) ? (string) $parsed['answer'] : $ai['text'];
		if ( $with_debug ) {
			$debug = array(
				'safety'     => 'green',
				'provider'   => $ai['provider'],
				'model'      => $ai['model'],
				'latency_ms' => $ai['latency_ms'],
				'tokens'     => $ai['usage'],
				'product'    => $parsed['product'],
			);
		}
		return $with_debug ? array_merge( $result, array( 'debug' => $debug ) ) : $result;
	}

	/**
	 * @param array<int,array<string,string>> $history
	 * @return array<int,array{role:string,content:string}>
	 */
	private function build_messages( array $history, string $message ): array {
		$out     = array();
		$history = array_slice( $history, -6 );
		foreach ( $history as $h ) {
			if ( ! is_array( $h ) ) {
				continue;
			}
			$role    = ( isset( $h['role'] ) && 'assistant' === $h['role'] ) ? 'assistant' : 'user';
			$content = isset( $h['content'] ) ? trim( wp_strip_all_tags( (string) $h['content'] ) ) : '';
			if ( '' === $content ) {
				continue;
			}
			if ( strlen( $content ) > 1000 ) {
				$content = substr( $content, 0, 1000 );
			}
			$out[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}
		$out[] = array(
			'role'    => 'user',
			'content' => $message,
		);
		return $out;
	}

	/**
	 * Try each provider in order; first success wins (fallback chain).
	 *
	 * @param array<int,GWC_AI_Provider> $providers
	 * @return array<string,mixed>
	 */
	private function call_ai( string $system, array $messages, array $providers, array $opts ): array {
		$last = array(
			'ok'         => false,
			'text'       => '',
			'model'      => '',
			'provider'   => '',
			'usage'      => array(
				'prompt'     => 0,
				'completion' => 0,
				'total'      => 0,
			),
			'latency_ms' => 0,
			'error'      => 'No provider available.',
		);
		foreach ( $providers as $p ) {
			$start = microtime( true );
			$r     = $p->chat( $system, $messages, $opts );
			$lat   = (int) round( ( microtime( true ) - $start ) * 1000 );
			if ( ! empty( $r['ok'] ) ) {
				return array(
					'ok'         => true,
					'text'       => $r['text'],
					'model'      => $r['model'],
					'provider'   => $p->id(),
					'usage'      => $r['usage'],
					'latency_ms' => $lat,
					'error'      => '',
				);
			}
			$last = array(
				'ok'         => false,
				'text'       => '',
				'model'      => $r['model'],
				'provider'   => $p->id(),
				'usage'      => $r['usage'],
				'latency_ms' => $lat,
				'error'      => $r['error'],
			);
		}
		return $last;
	}

	/** @return array{answer:string,safety:string,product:string,needs_human:bool} */
	private function parse_model_json( string $text ): array {
		$out = array(
			'answer'      => '',
			'safety'      => 'green',
			'product'     => '',
			'needs_human' => false,
		);
		$t = trim( $text );
		$t = preg_replace( '/^```(json)?/i', '', $t );
		$t = preg_replace( '/```$/', '', (string) $t );
		$t = trim( (string) $t );

		$data = json_decode( $t, true );
		if ( ! is_array( $data ) ) {
			$start = strpos( $t, '{' );
			$end   = strrpos( $t, '}' );
			if ( false !== $start && false !== $end && $end > $start ) {
				$data = json_decode( substr( $t, $start, $end - $start + 1 ), true );
			}
		}

		if ( is_array( $data ) ) {
			$out['answer']      = isset( $data['answer'] ) ? (string) $data['answer'] : '';
			$out['safety']      = isset( $data['safety'] ) ? (string) $data['safety'] : 'green';
			$out['product']     = isset( $data['product'] ) ? (string) $data['product'] : '';
			$out['needs_human'] = ! empty( $data['needs_human'] );
		} else {
			$out['answer'] = $t;
		}
		return $out;
	}

	private function short_summary( string $message ): string {
		$m = trim( wp_strip_all_tags( $message ) );
		if ( strlen( $m ) > 300 ) {
			$m = substr( $m, 0, 297 ) . '...';
		}
		return $m;
	}

	/* ------------------------------------------------------------ case creation */

	/**
	 * Open a case through the existing gw_consultation + GWC_Cases workflow.
	 *
	 * @param array<string,mixed> $ctx question, summary, reason, product, channel, contact.
	 * @return array{id:int,number:string}
	 */
	private function create_case( string $level, array $ctx ): array {
		$contact  = ( isset( $ctx['contact'] ) && is_array( $ctx['contact'] ) ) ? $ctx['contact'] : array();
		$name     = isset( $contact['name'] ) ? sanitize_text_field( (string) $contact['name'] ) : '';
		$phone    = isset( $contact['phone'] ) ? sanitize_text_field( (string) $contact['phone'] ) : '';
		$email    = isset( $contact['email'] ) ? sanitize_email( (string) $contact['email'] ) : '';
		$question = (string) ( isset( $ctx['question'] ) ? $ctx['question'] : '' );
		$summary  = (string) ( isset( $ctx['summary'] ) ? $ctx['summary'] : '' );
		$reason   = (string) ( isset( $ctx['reason'] ) ? $ctx['reason'] : '' );
		$product  = (string) ( isset( $ctx['product'] ) ? $ctx['product'] : '' );
		$channel  = (string) ( isset( $ctx['channel'] ) ? $ctx['channel'] : 'web' );

		$who   = '' !== $name ? $name : __( 'Website visitor', 'greenworld-core' );
		$title = sprintf( __( 'AI assistant - %1$s (%2$s)', 'greenworld-core' ), $who, strtoupper( $level ) );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'gw_consultation',
				'post_status'  => 'private',
				'post_title'   => $title,
				'post_content' => '',
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return array(
				'id'     => 0,
				'number' => '',
			);
		}
		$post_id = (int) $post_id;

		// Existing consultation intake meta (reuse the theme's keys).
		update_post_meta( $post_id, '_gw_c_name', $name );
		update_post_meta( $post_id, '_gw_c_phone', $phone );
		update_post_meta( $post_id, '_gw_c_email', $email );
		update_post_meta( $post_id, '_gw_c_concern', $question );
		update_post_meta( $post_id, '_gw_c_prefer', '' !== $phone ? 'whatsapp' : 'email' );

		// AI intake meta (additive, this module's own).
		update_post_meta( $post_id, '_gw_ai_safety', $level );
		update_post_meta( $post_id, '_gw_ai_summary', $summary );
		update_post_meta( $post_id, '_gw_ai_question', $question );
		update_post_meta( $post_id, '_gw_ai_reason', $reason );
		update_post_meta( $post_id, '_gw_ai_product', $product );
		update_post_meta( $post_id, '_gw_ai_channel', $channel );
		update_post_meta( $post_id, '_gw_ai_created', time() );

		// Case pipeline (reuse GWC_Cases): start at "new"; priority by severity.
		$priority = ( GWC_AI_Safety::RED === $level ) ? 'urgent' : ( ( GWC_AI_Safety::YELLOW === $level ) ? 'high' : 'normal' );
		update_post_meta( $post_id, '_gw_case_status', 'new' );
		update_post_meta( $post_id, '_gw_case_priority', $priority );
		update_post_meta(
			$post_id,
			'_gw_case_history',
			array(
				array(
					't'    => time(),
					'by'   => 0,
					'from' => '',
					'to'   => 'new',
				),
			)
		);

		$number = ( class_exists( 'GWC_Cases' ) && is_callable( array( 'GWC_Cases', 'number' ) ) )
			? GWC_Cases::number( $post_id )
			: ( 'GW-' . str_pad( (string) $post_id, 5, '0', STR_PAD_LEFT ) );

		// Reuse the existing staff-notification workflow (WhatsApp bridge, etc.).
		do_action(
			'greenworld/consultation_submitted',
			array(
				'name'    => $name,
				'phone'   => $phone,
				'email'   => $email,
				'concern' => $question,
				'prefer'  => '' !== $phone ? 'whatsapp' : 'email',
			)
		);

		return array(
			'id'     => $post_id,
			'number' => $number,
		);
	}

	/* ----------------------------------------------------------------- AJAX */

	private function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip;
	}

	private function rate_limit_ok(): bool {
		$s   = $this->settings();
		$max = (int) $s['rate_max'];
		$win = (int) $s['rate_window'];
		if ( $max <= 0 ) {
			return true;
		}
		$ip = $this->client_ip();
		if ( '' === $ip ) {
			return true;
		}
		$key = 'gwc_ai_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return false;
		}
		set_transient( $key, $n + 1, $win );
		return true;
	}

	public function ajax_message(): void {
		if ( ! check_ajax_referer( 'gwc_ai', 'nonce', false ) ) {
			wp_send_json_error( array( 'reply' => __( 'Your session expired. Please refresh the page and try again.', 'greenworld-core' ) ), 403 );
		}
		$s = $this->settings();
		if ( empty( $s['enabled'] ) ) {
			wp_send_json_error( array( 'reply' => __( 'The assistant is currently turned off.', 'greenworld-core' ) ), 200 );
		}
		// Honeypot: silently accept bots.
		if ( ! empty( $_POST['gw_hp'] ) ) {
			wp_send_json_success(
				array(
					'reply'       => __( 'Thank you.', 'greenworld-core' ),
					'safety'      => 'green',
					'case_number' => '',
				)
			);
		}
		if ( ! $this->rate_limit_ok() ) {
			wp_send_json_error( array( 'reply' => __( 'You have sent a lot of messages in a short time. Please wait a moment and try again.', 'greenworld-core' ) ), 429 );
		}

		$message = isset( $_POST['message'] ) ? wp_unslash( (string) $_POST['message'] ) : '';
		$name    = isset( $_POST['name'] ) ? wp_unslash( (string) $_POST['name'] ) : '';
		$phone   = isset( $_POST['phone'] ) ? wp_unslash( (string) $_POST['phone'] ) : '';
		$email   = isset( $_POST['email'] ) ? wp_unslash( (string) $_POST['email'] ) : '';
		$channel = isset( $_POST['channel'] ) ? sanitize_key( (string) $_POST['channel'] ) : 'web';

		$history = array();
		if ( isset( $_POST['history'] ) ) {
			$raw = json_decode( wp_unslash( (string) $_POST['history'] ), true );
			if ( is_array( $raw ) ) {
				$history = $raw;
			}
		}

		$with_debug = current_user_can( 'manage_options' ) && ! empty( $_POST['debug'] );

		$out = $this->handle_message(
			$message,
			$history,
			array(
				'name'  => $name,
				'phone' => $phone,
				'email' => $email,
			),
			$channel,
			$with_debug
		);

		wp_send_json_success( $out );
	}

	/* --------------------------------------------------------------- widget */

	public function shortcode_widget( $atts ): string {
		$s = $this->settings();
		if ( empty( $s['enabled'] ) ) {
			return '';
		}
		$atts = shortcode_atts( array( 'title' => $s['widget_title'] ), (array) $atts, 'green_world_assistant' );
		return $this->render_widget( (string) $atts['title'] );
	}

	/**
	 * Output the chat widget in the site footer on every front-end page when the
	 * site-wide option is on. Deduped so it never doubles the shortcode.
	 */
	public function maybe_render_sitewide(): void {
		$s = $this->settings();
		if ( empty( $s['enabled'] ) || empty( $s['sitewide'] ) ) {
			return;
		}
		echo $this->render_widget( (string) $s['widget_title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build the chat widget markup, at most once per page request. Shared by the
	 * [green_world_assistant] shortcode and the site-wide footer output.
	 *
	 * @param string $title Panel heading.
	 */
	public function render_widget( string $title = '' ): string {
		if ( $this->rendered ) {
			return '';
		}
		$this->rendered = true;
		$s = $this->settings();
		if ( '' === trim( $title ) ) {
			$title = (string) $s['widget_title'];
		}
		$uid   = 'gwcai_' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$cfg   = array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'gwc_ai' ),
			'intro' => (string) $s['intro'],
		);
		ob_start();
		?>
<div class="gwc-ai" id="<?php echo esc_attr( $uid ); ?>">
	<button type="button" class="gwc-ai__launch"><?php echo esc_html__( 'Chat with us', 'greenworld-core' ); ?></button>
	<div class="gwc-ai__panel" hidden>
		<div class="gwc-ai__head">
			<span class="gwc-ai__title"><?php echo esc_html( $title ); ?></span>
			<button type="button" class="gwc-ai__close" aria-label="<?php echo esc_attr__( 'Close', 'greenworld-core' ); ?>">&times;</button>
		</div>
		<div class="gwc-ai__log" role="log" aria-live="polite"></div>
		<div class="gwc-ai__contact">
			<input type="text" class="gwc-ai__name" placeholder="<?php echo esc_attr__( 'Your name (optional)', 'greenworld-core' ); ?>" autocomplete="name" />
			<input type="text" class="gwc-ai__phone" placeholder="<?php echo esc_attr__( 'Phone / WhatsApp (optional)', 'greenworld-core' ); ?>" autocomplete="tel" />
			<input type="email" class="gwc-ai__email" placeholder="<?php echo esc_attr__( 'Email (optional)', 'greenworld-core' ); ?>" autocomplete="email" />
		</div>
		<form class="gwc-ai__form" autocomplete="off">
			<input type="text" class="gwc-ai__hp" name="gw_hp" tabindex="-1" autocomplete="off" aria-hidden="true" />
			<textarea class="gwc-ai__input" rows="2" placeholder="<?php echo esc_attr__( 'Type your question...', 'greenworld-core' ); ?>"></textarea>
			<button type="submit" class="gwc-ai__send"><?php echo esc_html__( 'Send', 'greenworld-core' ); ?></button>
		</form>
		<p class="gwc-ai__disc"><?php echo esc_html__( 'General wellness and product information only - not medical advice.', 'greenworld-core' ); ?></p>
	</div>
</div>
<style>
.gwc-ai{position:fixed;right:18px;bottom:18px;z-index:99999;font-family:inherit}
/* Make the hidden attribute win over the panel's display:flex so it can actually minimize. */
.gwc-ai [hidden]{display:none !important}
.gwc-ai__launch{background:#1f7a3d;color:#fff;border:0;border-radius:24px;padding:12px 18px;font-size:15px;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.2)}
.gwc-ai__panel{width:340px;max-width:92vw;height:520px;max-height:80vh;background:#fff;border:1px solid #d8e0d8;border-radius:12px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.25)}
.gwc-ai__head{background:#1f7a3d;color:#fff;padding:10px 12px;display:flex;justify-content:space-between;align-items:center}
.gwc-ai__title{font-weight:600}
.gwc-ai__close{background:none;border:0;color:#fff;font-size:22px;line-height:1;cursor:pointer}
.gwc-ai__log{flex:1;overflow-y:auto;padding:12px;background:#f6f9f6}
.gwc-ai__msg{margin:0 0 10px;padding:8px 11px;border-radius:10px;font-size:14px;line-height:1.4;white-space:pre-wrap;word-wrap:break-word}
.gwc-ai__msg--user{background:#1f7a3d;color:#fff;margin-left:auto;max-width:85%}
.gwc-ai__msg--bot{background:#fff;border:1px solid #e0e6e0;color:#1a1a1a;max-width:90%}
.gwc-ai__msg--note{background:#fff7e6;border:1px solid #f0d9a8;color:#7a5a12;font-size:13px;max-width:90%}
.gwc-ai__contact{display:flex;flex-wrap:wrap;gap:6px;padding:8px 10px 0}
.gwc-ai__contact input{flex:1 1 45%;min-width:0;padding:6px 8px;border:1px solid #d8e0d8;border-radius:8px;font-size:13px}
.gwc-ai__form{display:flex;gap:6px;padding:10px}
.gwc-ai__input{flex:1;resize:none;padding:8px;border:1px solid #d8e0d8;border-radius:8px;font-size:14px}
.gwc-ai__hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.gwc-ai__send{background:#1f7a3d;color:#fff;border:0;border-radius:8px;padding:0 16px;cursor:pointer;font-size:14px}
.gwc-ai__disc{margin:0;padding:0 12px 10px;font-size:11px;color:#7a857a;text-align:center}
.gwc-ai__typing{display:inline-flex;gap:5px;align-items:center;padding:11px 12px}
.gwc-ai__dot{width:7px;height:7px;border-radius:50%;background:#8aa58f;display:inline-block;animation:gwcai-blink 1.2s infinite ease-in-out}
.gwc-ai__dot:nth-child(2){animation-delay:.15s}
.gwc-ai__dot:nth-child(3){animation-delay:.3s}
@keyframes gwcai-blink{0%,80%,100%{opacity:.3;transform:translateY(0)}40%{opacity:1;transform:translateY(-3px)}}
</style>
<script>
(function(){
	var cfg = <?php echo wp_json_encode( $cfg ); ?>;
	var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
	if(!root){return;}
	var launch = root.querySelector('.gwc-ai__launch');
	var panel = root.querySelector('.gwc-ai__panel');
	var closeBtn = root.querySelector('.gwc-ai__close');
	var logEl = root.querySelector('.gwc-ai__log');
	var form = root.querySelector('.gwc-ai__form');
	var input = root.querySelector('.gwc-ai__input');
	var nameEl = root.querySelector('.gwc-ai__name');
	var phoneEl = root.querySelector('.gwc-ai__phone');
	var emailEl = root.querySelector('.gwc-ai__email');
	var hpEl = root.querySelector('.gwc-ai__hp');
	var sendBtn = root.querySelector('.gwc-ai__send');
	var history = [];
	var opened = false;

	function bubble(text, kind){
		var d = document.createElement('div');
		d.className = 'gwc-ai__msg gwc-ai__msg--' + kind;
		d.textContent = text;
		logEl.appendChild(d);
		logEl.scrollTop = logEl.scrollHeight;
		return d;
	}
	function openPanel(){
		panel.hidden = false;
		launch.hidden = true;
		if(!opened){ opened = true; if(cfg.intro){ bubble(cfg.intro, 'bot'); } }
		input.focus();
	}
	function closePanel(){ panel.hidden = true; launch.hidden = false; }
	launch.addEventListener('click', openPanel);
	closeBtn.addEventListener('click', closePanel);

	form.addEventListener('submit', function(e){
		e.preventDefault();
		var msg = (input.value || '').trim();
		if(!msg){ return; }
		bubble(msg, 'user');
		history.push({role:'user', content:msg});
		input.value = '';
		sendBtn.disabled = true;
		var thinking = document.createElement('div');
		thinking.className = 'gwc-ai__msg gwc-ai__msg--bot gwc-ai__typing';
		thinking.setAttribute('aria-label','Assistant is typing');
		thinking.innerHTML = '<span class="gwc-ai__dot"></span><span class="gwc-ai__dot"></span><span class="gwc-ai__dot"></span>';
		logEl.appendChild(thinking);
		logEl.scrollTop = logEl.scrollHeight;

		var body = new URLSearchParams();
		body.append('action','gwc_ai_message');
		body.append('nonce', cfg.nonce);
		body.append('message', msg);
		body.append('name', nameEl.value || '');
		body.append('phone', phoneEl.value || '');
		body.append('email', emailEl.value || '');
		body.append('channel','web');
		body.append('gw_hp', hpEl.value || '');
		body.append('history', JSON.stringify(history.slice(-6)));

		fetch(cfg.ajax, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body: body.toString()})
			.then(function(r){ return r.json(); })
			.then(function(res){
				var data = (res && res.data) ? res.data : {};
				var reply = data.reply || 'Sorry, something went wrong. Please try again.';
				thinking.classList.remove('gwc-ai__typing');
				thinking.textContent = reply;
				if(res && res.success){ history.push({role:'assistant', content:reply}); }
				if(data.case_number){ bubble('Reference: ' + data.case_number + '. A Green World Health advisor will follow up.', 'note'); }
			})
			.catch(function(){ thinking.classList.remove('gwc-ai__typing'); thinking.textContent = 'Network error. Please try again.'; })
			.then(function(){ sendBtn.disabled = false; });
	});
})();
</script>
		<?php
		return (string) ob_get_clean();
	}

	/* --------------------------------------------------------------- metabox */

	public function register_metabox(): void {
		add_meta_box( 'gwc_ai_intake', __( 'AI assistant intake', 'greenworld-core' ), array( $this, 'render_metabox' ), 'gw_consultation', 'side', 'high' );
	}

	public function render_metabox( $post ): void {
		$safety = (string) get_post_meta( $post->ID, '_gw_ai_safety', true );
		if ( '' === $safety ) {
			echo '<p>' . esc_html__( 'This case did not originate from the AI assistant.', 'greenworld-core' ) . '</p>';
			return;
		}
		$colors  = array(
			'green'  => '#1f7a3d',
			'yellow' => '#b8860b',
			'red'    => '#b00020',
		);
		$color   = isset( $colors[ $safety ] ) ? $colors[ $safety ] : '#555';
		$channel = (string) get_post_meta( $post->ID, '_gw_ai_channel', true );
		$product = (string) get_post_meta( $post->ID, '_gw_ai_product', true );
		$reason  = (string) get_post_meta( $post->ID, '_gw_ai_reason', true );
		$summary = (string) get_post_meta( $post->ID, '_gw_ai_summary', true );
		$created = (int) get_post_meta( $post->ID, '_gw_ai_created', true );

		echo '<p><span style="display:inline-block;padding:2px 8px;border-radius:10px;color:#fff;font-weight:600;background:' . esc_attr( $color ) . '">' . esc_html( strtoupper( $safety ) ) . '</span></p>';
		echo '<p><strong>' . esc_html__( 'Channel', 'greenworld-core' ) . ':</strong> ' . esc_html( '' !== $channel ? $channel : '-' ) . '</p>';
		if ( '' !== $product ) {
			echo '<p><strong>' . esc_html__( 'Relevant product', 'greenworld-core' ) . ':</strong> ' . esc_html( $product ) . '</p>';
		}
		if ( '' !== $reason ) {
			echo '<p><strong>' . esc_html__( 'Escalation reason', 'greenworld-core' ) . ':</strong> ' . esc_html( $reason ) . '</p>';
		}
		if ( '' !== $summary ) {
			echo '<p><strong>' . esc_html__( 'Question summary', 'greenworld-core' ) . ':</strong><br>' . esc_html( $summary ) . '</p>';
		}
		if ( $created > 0 ) {
			echo '<p><strong>' . esc_html__( 'Captured', 'greenworld-core' ) . ':</strong> ' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created ) ) . '</p>';
		}
	}

	/* ------------------------------------------------------------- admin page */

	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=gw_consultation',
			__( 'Green World Assistant (AI)', 'greenworld-core' ),
			__( 'AI Assistant', 'greenworld-core' ),
			'manage_options',
			'gwc-ai',
			array( $this, 'render' )
		);
	}

	private function key_row( GWC_AI_Provider $p, string $env ): string {
		$ok = $p->is_available();
		$badge = $ok
			? '<span style="color:#1f7a3d;font-weight:600">' . esc_html__( 'detected', 'greenworld-core' ) . '</span>'
			: '<span style="color:#b00020;font-weight:600">' . esc_html__( 'not set', 'greenworld-core' ) . '</span>';
		return '<tr><td><strong>' . esc_html( $p->label() ) . '</strong></td><td><code>' . esc_html( $env ) . '</code></td><td>' . $badge . '</td><td>' . esc_html( $p->model() ) . '</td></tr>';
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = $this->settings();
		$gemini = new GWC_AI_Provider_Gemini( (string) $s['gemini_model'] );
		$groq   = new GWC_AI_Provider_Groq( (string) $s['groq_model'] );
		$openai = new GWC_AI_Provider_OpenAI( (string) $s['openai_model'] );

		$agg    = GWC_AI_Log::aggregates();
		$month  = GWC_AI_Log::month();
		$recent = GWC_AI_Log::recent( 30 );
		$fmt    = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$calls  = isset( $agg['calls'] ) ? (int) $agg['calls'] : 0;
		$avg    = ( $calls > 0 && isset( $agg['latency_ms'] ) ) ? (int) round( (int) $agg['latency_ms'] / $calls ) : 0;
		$nonce  = wp_create_nonce( 'gwc_ai' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Green World Assistant (AI)', 'greenworld-core' ); ?></h1>
			<p class="description"><?php esc_html_e( 'A safety-gated assistant grounded only on your approved product data. General questions are answered from the approved catalogue; anything touching personal health is never answered as advice - it opens a case for an advisor.', 'greenworld-core' ); ?></p>

			<h2><?php esc_html_e( 'API keys (server-side)', 'greenworld-core' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Keys are read from environment variables or wp-config.php constants only. They are never stored in the database, shown here, or sent to the browser. This page only shows whether each key is detected.', 'greenworld-core' ); ?></p>
			<table class="widefat striped" style="max-width:760px">
				<thead><tr>
					<th><?php esc_html_e( 'Provider', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Key name', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Status', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Model', 'greenworld-core' ); ?></th>
				</tr></thead>
				<tbody>
					<?php
					echo wp_kses_post( $this->key_row( $gemini, GWC_AI_Provider_Gemini::ENV_KEY ) );
					echo wp_kses_post( $this->key_row( $groq, GWC_AI_Provider_Groq::ENV_KEY ) );
					echo wp_kses_post( $this->key_row( $openai, GWC_AI_Provider_OpenAI::ENV_KEY ) );
					?>
				</tbody>
			</table>
			<p class="description" style="margin-top:8px">
				<?php esc_html_e( 'To add a key, edit wp-config.php and add, above "That\'s all, stop editing":', 'greenworld-core' ); ?><br>
				<code>define( 'GEMINI_API_KEY', 'your-gemini-key' );</code><br>
				<code>define( 'GROQ_API_KEY', 'your-groq-key' );</code>
			</p>

			<h2><?php esc_html_e( 'Settings', 'greenworld-core' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'gwc_ai_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Assistant', 'greenworld-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <?php esc_html_e( 'Enabled', 'greenworld-core' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show on every page', 'greenworld-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[sitewide]" value="1" <?php checked( ! empty( $s['sitewide'] ) ); ?> /> <?php esc_html_e( 'Float the chat button on every page automatically (no shortcode needed)', 'greenworld-core' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Provider order', 'greenworld-core' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[order]" value="<?php echo esc_attr( (string) $s['order'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Comma-separated, primary first. Known: gemini, groq, openai. The first available provider answers; the next is the fallback.', 'greenworld-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Gemini model', 'greenworld-core' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[gemini_model]" value="<?php echo esc_attr( (string) $s['gemini_model'] ); ?>" class="regular-text" /> <span class="description"><?php esc_html_e( 'e.g. gemini-2.5-flash (free tier) or gemini-2.5-flash-lite', 'greenworld-core' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Groq model', 'greenworld-core' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[groq_model]" value="<?php echo esc_attr( (string) $s['groq_model'] ); ?>" class="regular-text" /> <span class="description"><?php esc_html_e( 'e.g. llama-3.3-70b-versatile or llama-3.1-8b-instant', 'greenworld-core' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'OpenAI model (optional)', 'greenworld-core' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[openai_model]" value="<?php echo esc_attr( (string) $s['openai_model'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Creativity (temperature)', 'greenworld-core' ); ?></th>
						<td><input type="number" step="0.1" min="0" max="1.5" name="<?php echo esc_attr( self::OPTION ); ?>[temperature]" value="<?php echo esc_attr( (string) $s['temperature'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Max answer tokens', 'greenworld-core' ); ?></th>
						<td><input type="number" min="64" max="4000" name="<?php echo esc_attr( self::OPTION ); ?>[max_tokens]" value="<?php echo esc_attr( (string) $s['max_tokens'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Products per answer (KB)', 'greenworld-core' ); ?></th>
						<td><input type="number" min="1" max="50" name="<?php echo esc_attr( self::OPTION ); ?>[kb_limit]" value="<?php echo esc_attr( (string) $s['kb_limit'] ); ?>" class="small-text" /> <span class="description"><?php esc_html_e( 'Most relevant approved products included as context.', 'greenworld-core' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rate limit', 'greenworld-core' ); ?></th>
						<td>
							<input type="number" min="0" max="1000" name="<?php echo esc_attr( self::OPTION ); ?>[rate_max]" value="<?php echo esc_attr( (string) $s['rate_max'] ); ?>" class="small-text" />
							<?php esc_html_e( 'messages per', 'greenworld-core' ); ?>
							<input type="number" min="30" max="86400" name="<?php echo esc_attr( self::OPTION ); ?>[rate_window]" value="<?php echo esc_attr( (string) $s['rate_window'] ); ?>" class="small-text" />
							<?php esc_html_e( 'seconds, per visitor (protects the free tier). 0 = no limit.', 'greenworld-core' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Monthly budget (USD)', 'greenworld-core' ); ?></th>
						<td>
							<input type="number" min="0" max="100000" name="<?php echo esc_attr( self::OPTION ); ?>[budget_usd]" value="<?php echo esc_attr( (string) $s['budget_usd'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'For paid providers later. 0 = free tiers only. Can also be set with the GWC_AI_MONTHLY_BUDGET environment variable / constant, which overrides this.', 'greenworld-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Widget title', 'greenworld-core' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[widget_title]" value="<?php echo esc_attr( (string) $s['widget_title'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Greeting', 'greenworld-core' ); ?></th>
						<td><textarea name="<?php echo esc_attr( self::OPTION ); ?>[intro]" rows="2" class="large-text"><?php echo esc_textarea( (string) $s['intro'] ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<p class="description"><?php esc_html_e( 'With "Show on every page" enabled the chat button appears everywhere automatically. To place it on a specific page or post instead, use the shortcode:', 'greenworld-core' ); ?> <code>[green_world_assistant]</code></p>

			<h2><?php esc_html_e( 'Test the assistant', 'greenworld-core' ); ?></h2>
			<p>
				<input type="text" id="gwc-ai-test-input" class="regular-text" placeholder="<?php echo esc_attr__( 'e.g. What is the price of Spirulina and is it in stock?', 'greenworld-core' ); ?>" style="width:60%" />
				<button type="button" class="button button-primary" id="gwc-ai-test-btn"><?php esc_html_e( 'Send test', 'greenworld-core' ); ?></button>
			</p>
			<pre id="gwc-ai-test-out" style="background:#fff;border:1px solid #ddd;padding:12px;max-width:900px;white-space:pre-wrap;min-height:40px"></pre>
			<script>
			(function(){
				var btn = document.getElementById('gwc-ai-test-btn');
				var inp = document.getElementById('gwc-ai-test-input');
				var out = document.getElementById('gwc-ai-test-out');
				var cfg = { ajax: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, nonce: <?php echo wp_json_encode( $nonce ); ?> };
				if(!btn){return;}
				btn.addEventListener('click', function(){
					var msg = (inp.value||'').trim();
					if(!msg){ return; }
					out.textContent = 'Working...';
					var body = new URLSearchParams();
					body.append('action','gwc_ai_message');
					body.append('nonce', cfg.nonce);
					body.append('message', msg);
					body.append('channel','admin_test');
					body.append('debug','1');
					fetch(cfg.ajax,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString()})
						.then(function(r){return r.json();})
						.then(function(res){
							var d=(res&&res.data)?res.data:{};
							var lines=[];
							lines.push('SAFETY: '+(d.safety||'?'));
							if(d.case_number){ lines.push('CASE: '+d.case_number); }
							if(d.debug){
								if(d.debug.provider){ lines.push('PROVIDER: '+d.debug.provider); }
								if(d.debug.model){ lines.push('MODEL: '+d.debug.model); }
								if(typeof d.debug.latency_ms!=='undefined'){ lines.push('LATENCY: '+d.debug.latency_ms+' ms'); }
								if(d.debug.tokens){ lines.push('TOKENS: prompt '+(d.debug.tokens.prompt||0)+', completion '+(d.debug.tokens.completion||0)); }
								if(d.debug.error){ lines.push('ERROR: '+d.debug.error); }
							}
							lines.push('');
							lines.push('REPLY:');
							lines.push(d.reply||'(no reply)');
							out.textContent = lines.join('\n');
						})
						.catch(function(){ out.textContent = 'Request failed.'; });
				});
			})();
			</script>

			<h2><?php esc_html_e( 'Monitoring', 'greenworld-core' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: total calls, 2: success, 3: avg latency */
					esc_html__( 'Total calls: %1$d | successful: %2$d | average latency: %3$d ms', 'greenworld-core' ),
					$calls,
					isset( $agg['ok'] ) ? (int) $agg['ok'] : 0,
					$avg
				);
				?>
				<br>
				<?php
				printf(
					/* translators: 1: green, 2: yellow, 3: red */
					esc_html__( 'Safety split - green: %1$d, yellow: %2$d, red: %3$d', 'greenworld-core' ),
					isset( $agg['safety_green'] ) ? (int) $agg['safety_green'] : 0,
					isset( $agg['safety_yellow'] ) ? (int) $agg['safety_yellow'] : 0,
					isset( $agg['safety_red'] ) ? (int) $agg['safety_red'] : 0
				);
				?>
				<br>
				<?php
				printf(
					/* translators: 1: month, 2: calls, 3: prompt tokens, 4: completion tokens, 5: budget */
					esc_html__( 'This month (%1$s): %2$d calls, %3$d prompt + %4$d completion tokens. Budget: $%5$d.', 'greenworld-core' ),
					esc_html( isset( $month['month'] ) ? (string) $month['month'] : gmdate( 'Y-m' ) ),
					isset( $month['calls'] ) ? (int) $month['calls'] : 0,
					isset( $month['p_tok'] ) ? (int) $month['p_tok'] : 0,
					isset( $month['c_tok'] ) ? (int) $month['c_tok'] : 0,
					$this->monthly_budget()
				);
				?>
			</p>
			<table class="widefat striped" style="max-width:1000px">
				<thead><tr>
					<th><?php esc_html_e( 'When', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Provider', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Model', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Latency', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Tokens (p/c)', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Safety', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Outcome', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Case', 'greenworld-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $recent ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No AI calls recorded yet.', 'greenworld-core' ); ?></td></tr>
				<?php else : ?>
					<?php
					foreach ( $recent as $r ) :
						$t = isset( $r['t'] ) ? (int) $r['t'] : 0;
						?>
						<tr>
							<td><?php echo esc_html( $t > 0 ? wp_date( $fmt, $t ) : '-' ); ?></td>
							<td><?php echo esc_html( isset( $r['provider'] ) && '' !== $r['provider'] ? $r['provider'] : '-' ); ?></td>
							<td><?php echo esc_html( isset( $r['model'] ) && '' !== $r['model'] ? $r['model'] : '-' ); ?></td>
							<td><?php echo esc_html( ( isset( $r['latency_ms'] ) ? (int) $r['latency_ms'] : 0 ) . ' ms' ); ?></td>
							<td><?php echo esc_html( ( isset( $r['p_tok'] ) ? (int) $r['p_tok'] : 0 ) . ' / ' . ( isset( $r['c_tok'] ) ? (int) $r['c_tok'] : 0 ) ); ?></td>
							<td><?php echo esc_html( isset( $r['safety'] ) ? strtoupper( (string) $r['safety'] ) : '-' ); ?></td>
							<td><?php echo esc_html( isset( $r['outcome'] ) ? (string) $r['outcome'] : '-' ); ?></td>
							<td><?php echo esc_html( ! empty( $r['case_id'] ) ? ( 'GW-' . str_pad( (string) ( (int) $r['case_id'] ), 5, '0', STR_PAD_LEFT ) ) : '-' ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
