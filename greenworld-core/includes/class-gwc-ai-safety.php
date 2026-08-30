<?php
/**
 * Safety / compliance router for the Green World Assistant.
 *
 * Classifies every incoming message as GREEN, YELLOW or RED using deterministic
 * rules BEFORE any model is asked to answer. The rules are a safety floor: a
 * model's own self-assessment can only RAISE the level, never lower it - so a
 * YELLOW or RED can never be silently answered as a normal product
 * recommendation.
 *
 *   GREEN  - general product / order / wellness info -> the AI may answer from
 *            the approved knowledge base.
 *   YELLOW - symptoms, product suitability, interactions, dosage judgement,
 *            pregnancy / breastfeeding, existing conditions, personalised
 *            advice, or where approved data is insufficient -> no definitive
 *            medical answer; return a safe holding reply and open a case for a
 *            Green World Health advisor.
 *   RED    - emergencies, diagnosis requests, requests to replace a doctor,
 *            dangerous combinations, serious adverse reactions, self-harm ->
 *            never diagnose or treat; return an urgent-care safe reply and open
 *            a priority case immediately.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_AI_Safety {

	const GREEN  = 'green';
	const YELLOW = 'yellow';
	const RED    = 'red';

	/** RED: urgent, dangerous, or diagnosis/prescription replacement. */
	private static function red_patterns(): array {
		return array(
			// Emergencies / severe symptoms.
			'chest pain',
			'can not breathe',
			'cannot breathe',
			'can\'t breathe',
			'difficulty breathing',
			'trouble breathing',
			'struggling to breathe',
			'unconscious',
			'passed out',
			'fainted',
			'seizure',
			'convulsion',
			'stroke',
			'heart attack',
			'severe bleeding',
			'bleeding heavily',
			'coughing blood',
			'vomiting blood',
			'blood in stool',
			'anaphyla',
			'swollen throat',
			'swelling in throat',
			'severe pain',
			'unbearable pain',
			'emergency',
			// Self-harm / danger to life.
			'suicid',
			'kill myself',
			'end my life',
			'want to die',
			'self harm',
			'self-harm',
			'harm myself',
			'overdose',
			'overdosed',
			'poisoned',
			// Diagnosis / prescription replacement.
			'diagnose me',
			'what disease do i have',
			'do i have cancer',
			'is this cancer',
			'replace my doctor',
			'instead of my doctor',
			'instead of seeing a doctor',
			'stop taking my prescribed',
			'stop my medication',
			'stop my prescription',
		);
	}

	/** YELLOW: needs professional judgement, or approved data is insufficient. */
	private static function yellow_patterns(): array {
		return array(
			// Symptoms / how the person feels.
			'symptom',
			'i feel',
			'i am feeling',
			'i have been feeling',
			'i have a',
			'i have an',
			'ache',
			'aching',
			'dizzy',
			'dizziness',
			'nausea',
			'vomit',
			'diarrhea',
			'diarrhoea',
			'fever',
			'rash',
			'infection',
			// Diagnosis / treatment / prescription language.
			'diagnos',
			'treat',
			'cure',
			'prescri',
			'heal my',
			// Dosing that needs judgement.
			'dose',
			'dosage',
			'how much should i take',
			'how many should i take',
			'how often should i take',
			// Interactions / suitability.
			'interact',
			'with my medication',
			'with my meds',
			'side effect',
			'contraindicat',
			'is it safe for me',
			'safe to take',
			'can i take',
			'should i take',
			'suitable for me',
			'recommend for my',
			'good for my condition',
			'what should i use for',
			'what can i take for',
			'cure for',
			'treatment for',
			'remedy for',
			// Pregnancy / children.
			'pregnan',
			'breastfeed',
			'breast-feed',
			'trying to conceive',
			'my baby',
			'my child',
			'for my kid',
			'for my son',
			'for my daughter',
			// Existing conditions.
			'diabet',
			'hypertens',
			'high blood pressure',
			'kidney disease',
			'liver disease',
			'heart condition',
			'heart disease',
			'chemo',
			'cancer',
			'hiv',
			'blood thinner',
			'warfarin',
			'insulin',
			'thyroid',
			'epilep',
			'asthma',
		);
	}

	/**
	 * Classify a message deterministically.
	 *
	 * @return array{level:string,reason:string}
	 */
	public static function classify( string $message ): array {
		$m = ' ' . strtolower( wp_strip_all_tags( $message ) ) . ' ';

		foreach ( self::red_patterns() as $p ) {
			if ( false !== strpos( $m, strtolower( $p ) ) ) {
				return array(
					'level'  => self::RED,
					'reason' => 'Matched urgent/red term: "' . $p . '"',
				);
			}
		}
		foreach ( self::yellow_patterns() as $p ) {
			if ( false !== strpos( $m, strtolower( $p ) ) ) {
				return array(
					'level'  => self::YELLOW,
					'reason' => 'Matched health-judgement/yellow term: "' . $p . '"',
				);
			}
		}
		return array(
			'level'  => self::GREEN,
			'reason' => 'No health-risk terms detected.',
		);
	}

	/** Severity rank so callers can take the maximum. */
	public static function rank( string $level ): int {
		switch ( $level ) {
			case self::RED:
				return 3;
			case self::YELLOW:
				return 2;
			default:
				return 1;
		}
	}

	/** Combine two classifications; the higher severity always wins. */
	public static function escalate( string $current, string $candidate ): string {
		return self::rank( $candidate ) > self::rank( $current ) ? $candidate : $current;
	}

	/** Normalise arbitrary text to a known level (defaults to GREEN). */
	public static function normalise( string $level ): string {
		$level = strtolower( trim( $level ) );
		if ( self::RED === $level || self::YELLOW === $level || self::GREEN === $level ) {
			return $level;
		}
		return self::GREEN;
	}

	/** Safe, non-diagnostic holding reply for YELLOW. */
	public static function yellow_reply(): string {
		return __( 'Thank you so much for reaching out, and for trusting us with something this personal. Because your question is about your own health, I would not want to give a general answer that might not be right for you - you deserve guidance made for your situation. I have shared your question with a qualified Green World Health advisor, who will personally follow up with you very soon. If it feels urgent, please reach out to your nearest health facility right away - your wellbeing comes first, and we are here for you.', 'greenworld-core' );
	}

	/** Safe urgent-care reply for RED. */
	public static function red_reply(): string {
		return __( 'This may need urgent, in-person medical attention. Please contact a doctor or your nearest emergency service right away - I cannot diagnose or treat, and I would not want to delay proper care. I have flagged this to the Green World Health team as a priority. If you or someone else is in immediate danger, please call your local emergency number now.', 'greenworld-core' );
	}
}
