<?php
/**
 * AI provider abstraction for Green World Core.
 *
 * The rest of the plugin talks to GWC_AI_Provider, never to a vendor SDK, so
 * moving from the free Gemini / Groq tiers to a paid model later is a provider
 * swap, not a rewrite. API keys are read ONLY from server-side environment
 * variables or wp-config.php constants - never stored in the database, shown in
 * the admin UI, sent to the browser, or committed to the repository.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contract every provider implements.
 */
interface GWC_AI_Provider {
	/** Machine id, e.g. "gemini". */
	public function id(): string;

	/** Human label, e.g. "Google Gemini". */
	public function label(): string;

	/** True when an API key is present server-side. */
	public function is_available(): bool;

	/** Model id this provider will call. */
	public function model(): string;

	/**
	 * Run a chat completion.
	 *
	 * @param string                                        $system   Grounding / system instructions.
	 * @param array<int,array{role:string,content:string}>  $messages Conversation turns.
	 * @param array<string,mixed>                           $opts     temperature, max_tokens, timeout.
	 * @return array{ok:bool,text:string,model:string,usage:array<string,int>,error:string}
	 */
	public function chat( string $system, array $messages, array $opts = array() ): array;
}

/**
 * Shared plumbing: secret resolution (env then wp-config constant) and HTTP
 * result shaping. No provider ever reads a key from the database.
 */
abstract class GWC_AI_Provider_Base implements GWC_AI_Provider {

	/**
	 * Resolve a secret from an environment variable first, then a same-named
	 * PHP constant (defined in wp-config.php). Never the options table.
	 */
	protected static function secret( string $name ): string {
		$env = getenv( $name );
		if ( is_string( $env ) && '' !== trim( $env ) ) {
			return trim( $env );
		}
		if ( defined( $name ) ) {
			$val = constant( $name );
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				return trim( $val );
			}
		}
		return '';
	}

	protected function default_opts(): array {
		return array(
			'temperature' => 0.2,
			'max_tokens'  => 600,
			'timeout'     => 25,
		);
	}

	protected function fail( string $msg ): array {
		return array(
			'ok'    => false,
			'text'  => '',
			'model' => $this->model(),
			'usage' => array(
				'prompt'     => 0,
				'completion' => 0,
				'total'      => 0,
			),
			'error' => $msg,
		);
	}
}

/**
 * Google Gemini (generativelanguage v1beta generateContent).
 * Free-tier friendly. Default model configurable; gemini-2.5-flash has a free
 * tier at time of writing.
 */
final class GWC_AI_Provider_Gemini extends GWC_AI_Provider_Base {

	const ENV_KEY       = 'GEMINI_API_KEY';
	const DEFAULT_MODEL = 'gemini-2.5-flash';

	private $model_id;

	public function __construct( string $model = '' ) {
		$this->model_id = '' !== $model ? $model : self::DEFAULT_MODEL;
	}

	public function id(): string {
		return 'gemini';
	}

	public function label(): string {
		return 'Google Gemini';
	}

	public function model(): string {
		return $this->model_id;
	}

	public function is_available(): bool {
		return '' !== self::secret( self::ENV_KEY );
	}

	public function chat( string $system, array $messages, array $opts = array() ): array {
		$key = self::secret( self::ENV_KEY );
		if ( '' === $key ) {
			return $this->fail( 'Gemini API key not configured.' );
		}
		$o = array_merge( $this->default_opts(), $opts );

		$contents = array();
		foreach ( $messages as $m ) {
			$role       = ( isset( $m['role'] ) && 'assistant' === $m['role'] ) ? 'model' : 'user';
			$contents[] = array(
				'role'  => $role,
				'parts' => array( array( 'text' => (string) ( isset( $m['content'] ) ? $m['content'] : '' ) ) ),
			);
		}

		$body = array(
			'systemInstruction' => array(
				'parts' => array( array( 'text' => $system ) ),
			),
			'contents'          => $contents,
			'generationConfig'  => array(
				'temperature'     => (float) $o['temperature'],
				'maxOutputTokens' => (int) $o['max_tokens'],
			),
		);

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $this->model_id ) . ':generateContent';

		$res = wp_remote_post(
			$url,
			array(
				'timeout' => (int) $o['timeout'],
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $this->fail( 'Gemini request failed: ' . $res->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $json ) ) {
			$emsg = ( is_array( $json ) && isset( $json['error']['message'] ) ) ? (string) $json['error']['message'] : ( 'HTTP ' . $code );
			return $this->fail( 'Gemini error: ' . $emsg );
		}

		$text = '';
		if ( isset( $json['candidates'][0]['content']['parts'] ) && is_array( $json['candidates'][0]['content']['parts'] ) ) {
			foreach ( $json['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$text .= (string) $part['text'];
				}
			}
		}

		if ( '' === trim( $text ) ) {
			return $this->fail( 'Gemini returned an empty response.' );
		}

		return array(
			'ok'    => true,
			'text'  => trim( $text ),
			'model' => $this->model_id,
			'usage' => array(
				'prompt'     => isset( $json['usageMetadata']['promptTokenCount'] ) ? (int) $json['usageMetadata']['promptTokenCount'] : 0,
				'completion' => isset( $json['usageMetadata']['candidatesTokenCount'] ) ? (int) $json['usageMetadata']['candidatesTokenCount'] : 0,
				'total'      => isset( $json['usageMetadata']['totalTokenCount'] ) ? (int) $json['usageMetadata']['totalTokenCount'] : 0,
			),
			'error' => '',
		);
	}
}

/**
 * Base for OpenAI-compatible chat-completions APIs (Groq today, OpenAI later).
 * Subclasses only supply an endpoint, an env key name, an id/label and a
 * default model - the request/response handling is shared.
 */
abstract class GWC_AI_Provider_OpenAI_Compatible extends GWC_AI_Provider_Base {

	protected $model_id;

	abstract protected function endpoint(): string;

	abstract protected function env_key(): string;

	public function model(): string {
		return $this->model_id;
	}

	public function is_available(): bool {
		return '' !== self::secret( $this->env_key() );
	}

	public function chat( string $system, array $messages, array $opts = array() ): array {
		$key = self::secret( $this->env_key() );
		if ( '' === $key ) {
			return $this->fail( $this->label() . ' API key not configured.' );
		}
		$o = array_merge( $this->default_opts(), $opts );

		$msgs = array();
		if ( '' !== trim( $system ) ) {
			$msgs[] = array(
				'role'    => 'system',
				'content' => $system,
			);
		}
		foreach ( $messages as $m ) {
			$role   = ( isset( $m['role'] ) && 'assistant' === $m['role'] ) ? 'assistant' : 'user';
			$msgs[] = array(
				'role'    => $role,
				'content' => (string) ( isset( $m['content'] ) ? $m['content'] : '' ),
			);
		}

		$body = array(
			'model'       => $this->model_id,
			'messages'    => $msgs,
			'temperature' => (float) $o['temperature'],
			'max_tokens'  => (int) $o['max_tokens'],
		);

		$res = wp_remote_post(
			$this->endpoint(),
			array(
				'timeout' => (int) $o['timeout'],
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $this->fail( $this->label() . ' request failed: ' . $res->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $json ) ) {
			$emsg = ( is_array( $json ) && isset( $json['error']['message'] ) ) ? (string) $json['error']['message'] : ( 'HTTP ' . $code );
			return $this->fail( $this->label() . ' error: ' . $emsg );
		}

		$text = isset( $json['choices'][0]['message']['content'] ) ? (string) $json['choices'][0]['message']['content'] : '';
		if ( '' === trim( $text ) ) {
			return $this->fail( $this->label() . ' returned an empty response.' );
		}

		return array(
			'ok'    => true,
			'text'  => trim( $text ),
			'model' => $this->model_id,
			'usage' => array(
				'prompt'     => isset( $json['usage']['prompt_tokens'] ) ? (int) $json['usage']['prompt_tokens'] : 0,
				'completion' => isset( $json['usage']['completion_tokens'] ) ? (int) $json['usage']['completion_tokens'] : 0,
				'total'      => isset( $json['usage']['total_tokens'] ) ? (int) $json['usage']['total_tokens'] : 0,
			),
			'error' => '',
		);
	}
}

/**
 * Groq - fast, free-tier OpenAI-compatible inference. Default fallback model
 * llama-3.3-70b-versatile (llama-3.1-8b-instant is faster with a higher daily
 * cap; change it in settings).
 */
final class GWC_AI_Provider_Groq extends GWC_AI_Provider_OpenAI_Compatible {

	const ENV_KEY       = 'GROQ_API_KEY';
	const DEFAULT_MODEL = 'llama-3.3-70b-versatile';

	public function __construct( string $model = '' ) {
		$this->model_id = '' !== $model ? $model : self::DEFAULT_MODEL;
	}

	public function id(): string {
		return 'groq';
	}

	public function label(): string {
		return 'Groq';
	}

	protected function endpoint(): string {
		return 'https://api.groq.com/openai/v1/chat/completions';
	}

	protected function env_key(): string {
		return self::ENV_KEY;
	}
}

/**
 * OpenAI - not used while we validate on the free tiers, but present so that
 * adding a paid provider later is a configuration change, not a rewrite. It
 * activates automatically only if OPENAI_API_KEY is defined server-side.
 */
final class GWC_AI_Provider_OpenAI extends GWC_AI_Provider_OpenAI_Compatible {

	const ENV_KEY       = 'OPENAI_API_KEY';
	const DEFAULT_MODEL = 'gpt-4o-mini';

	public function __construct( string $model = '' ) {
		$this->model_id = '' !== $model ? $model : self::DEFAULT_MODEL;
	}

	public function id(): string {
		return 'openai';
	}

	public function label(): string {
		return 'OpenAI';
	}

	protected function endpoint(): string {
		return 'https://api.openai.com/v1/chat/completions';
	}

	protected function env_key(): string {
		return self::ENV_KEY;
	}
}
