<?php
/**
 * Endpoint REST del core.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per ora gli endpoint servono al lettore delle lezioni: salvano la posizione
 * del video, il tempo guardato e il completamento. Richiedono un utente
 * collegato (cookie + nonce REST) con accesso alla lezione.
 */
final class AEL_Rest {

	public const NAMESPACE = 'ael/v1';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Rest|null
	 */
	private static ?AEL_Rest $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Rest {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Costruttore privato: si usa instance().
	 */
	private function __construct() {}

	/**
	 * Aggancia la registrazione delle route.
	 */
	public function boot(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registra le route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/lessons/(?P<id>\d+)/progress',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_progress' ),
				'permission_callback' => array( $this, 'can_track' ),
				'args'                => array(
					'position' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'seconds'  => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'percent'  => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/lessons/(?P<id>\d+)/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_completed' ),
				'permission_callback' => array( $this, 'can_track' ),
				'args'                => array(
					'completed' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
	}

	/**
	 * Permesso: utente collegato che può vedere la lezione.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return bool|WP_Error
	 */
	public function can_track( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'ael_not_logged_in', __( 'Devi accedere per salvare i progressi.', 'academy-elearning' ), array( 'status' => 401 ) );
		}
		$lesson_id = absint( $request['id'] );
		if ( ! $lesson_id || AEL_Content::LESSON !== get_post_type( $lesson_id ) ) {
			return new WP_Error( 'ael_not_found', __( 'Lezione non trovata.', 'academy-elearning' ), array( 'status' => 404 ) );
		}
		if ( ! AEL_Access::current_user_can_access_lesson( $lesson_id ) ) {
			return new WP_Error( 'ael_forbidden', __( 'Non hai accesso a questa lezione.', 'academy-elearning' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Stato da restituire al lettore.
	 *
	 * @param int $user_id   Utente.
	 * @param int $lesson_id Lezione.
	 * @return array<string, mixed>
	 */
	private function state( int $user_id, int $lesson_id ): array {
		$row       = AEL_Progress::lesson_row( $user_id, $lesson_id );
		$course_id = (int) get_post_meta( $lesson_id, '_ael_course_id', true );
		return array(
			'completed'      => $row && 'completed' === $row['status'],
			'position'       => $row ? (int) $row['last_position'] : 0,
			'course_percent' => $course_id ? AEL_Progress::percent( $user_id, $course_id ) : null,
		);
	}

	/**
	 * Salva posizione e tempo guardato; completa la lezione oltre la soglia.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 */
	public function save_progress( WP_REST_Request $request ): WP_REST_Response {
		$user_id   = get_current_user_id();
		$lesson_id = absint( $request['id'] );
		$percent   = max( 0.0, min( 100.0, (float) $request['percent'] ) );

		AEL_Progress::record( $user_id, $lesson_id, (int) $request['position'], (int) $request['seconds'] );

		/**
		 * Percentuale di video guardata oltre la quale la lezione risulta completata.
		 *
		 * @param int $threshold Soglia (predefinita 90).
		 */
		$threshold = (int) apply_filters( 'ael_completion_threshold', 90 );
		if ( $percent >= $threshold ) {
			AEL_Progress::set_completed( $user_id, $lesson_id, true );
		}

		return rest_ensure_response( $this->state( $user_id, $lesson_id ) );
	}

	/**
	 * Completamento manuale (o annullamento).
	 *
	 * @param WP_REST_Request $request Richiesta.
	 */
	public function set_completed( WP_REST_Request $request ): WP_REST_Response {
		$user_id   = get_current_user_id();
		$lesson_id = absint( $request['id'] );
		AEL_Progress::set_completed( $user_id, $lesson_id, (bool) rest_sanitize_boolean( $request['completed'] ) );
		return rest_ensure_response( $this->state( $user_id, $lesson_id ) );
	}
}
