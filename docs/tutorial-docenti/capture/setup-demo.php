<?php
/**
 * Crea sul sito di PROVA un docente, una scheda e un corso di esempio (dati inventati, immagini neutre)
 * per fare le schermate del tutorial. Uso: php -c <php.ini> setup-demo.php <cartella-immagini>
 */
$_SERVER['HTTP_HOST'] = 'academy-test.local';
require 'C:/Users/maril/Local Sites/academy-test/app/public/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = rtrim( $argv[1], '/' );

function demo_image( $dir, $name, $title ) {
	$bits = wp_upload_bits( $name, null, file_get_contents( "$dir/$name" ) );
	$id   = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $title, 'post_status' => 'inherit' ), $bits['file'] );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $bits['file'] ) );
	return $id;
}

if ( username_exists( 'docente.esempio' ) ) { echo "Esiste già.\n"; exit; }

$user = wp_insert_user( array( 'user_login' => 'docente.esempio', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'docente.esempio@example.com', 'display_name' => 'Elena Esempio', 'first_name' => 'Elena', 'last_name' => 'Esempio', 'role' => 'ael_teacher' ) );

$photo = demo_image( $dir, 'demo-docente.png', 'Elena Esempio' );
$cover = demo_image( $dir, 'demo-copertina-docente.png', 'Copertina di esempio' );
$ccov  = demo_image( $dir, 'demo-corso.png', 'Copertina corso di esempio' );

$teacher = wp_insert_post( array( 'post_type' => 'ael_teacher', 'post_status' => 'publish', 'post_title' => 'Elena Esempio', 'post_name' => 'elena-esempio', 'post_content' => 'Content creator da otto anni, lavoro con marchi e agenzie per costruire contenuti verticali che si guardano fino in fondo. Nei corsi ti mostro il metodo che uso ogni giorno.' ) );
set_post_thumbnail( $teacher, $photo );
update_post_meta( $teacher, '_ael_user_id', $user );
update_post_meta( $teacher, '_ael_tagline', 'Video verticali che si guardano fino alla fine' );
update_post_meta( $teacher, '_ael_cover_id', $cover );
update_post_meta( $teacher, '_ael_accent', '#e6007e' );
update_post_meta( $teacher, '_ael_links', AEL_Teacher_Profile::clean_links( array( array( 'label' => 'Sito', 'url' => 'https://example.com' ) ) ) );
update_post_meta( $teacher, '_ael_channels', AEL_Teacher_Profile::clean_channels( array(
	array( 'platform' => 'instagram', 'handle' => '@elena.esempio', 'url' => 'https://instagram.com/elena.esempio', 'followers' => '82400', 'engagement' => '4,6' ),
	array( 'platform' => 'tiktok', 'handle' => '@elena.esempio', 'url' => 'https://tiktok.com/@elena.esempio', 'followers' => '51300', 'engagement' => '6,8' ),
) ) );
update_post_meta( $teacher, '_ael_stats_date', 'novembre 2026' );

$course = wp_insert_post( array( 'post_type' => 'ael_course', 'post_status' => 'publish', 'post_title' => 'Reel verticali: dall\'idea alla pubblicazione', 'post_name' => 'reel-verticali', 'post_author' => $user, 'post_content' => 'Impari a pensare, girare e montare un video verticale pronto per Instagram e TikTok, con un metodo che funziona anche da telefono.' ) );
set_post_thumbnail( $course, $ccov );
update_post_meta( $course, '_ael_teacher_id', $teacher );
update_post_meta( $course, '_ael_subtitle', 'Un metodo in tre passi, da telefono' );
update_post_meta( $course, '_ael_level', 'principiante' );
update_post_meta( $course, '_ael_learn', "Scrivere un'idea in una frase\nGirare in verticale con la luce giusta\nMontare e pubblicare" );
update_post_meta( $course, '_ael_audience', "Chi inizia ora\nChi vuole migliorare i propri video" );

$m1 = wp_insert_post( array( 'post_type' => 'ael_module', 'post_status' => 'publish', 'post_title' => 'Prima di girare', 'post_parent' => $course, 'menu_order' => 0, 'post_author' => $user ) );
update_post_meta( $m1, '_ael_course_id', $course );
$m2 = wp_insert_post( array( 'post_type' => 'ael_module', 'post_status' => 'publish', 'post_title' => 'Montaggio e pubblicazione', 'post_parent' => $course, 'menu_order' => 1, 'post_author' => $user ) );
update_post_meta( $m2, '_ael_course_id', $course );
foreach ( array( array( $m1, 'L\'idea in una frase', 0, 4 ), array( $m1, 'Luce e inquadratura', 1, 6 ), array( $m2, 'Il primo taglio', 0, 8 ) ) as $l ) {
	$id = wp_insert_post( array( 'post_type' => 'ael_lesson', 'post_status' => 'publish', 'post_title' => $l[1], 'post_parent' => $l[0], 'menu_order' => $l[2], 'post_author' => $user ) );
	update_post_meta( $id, '_ael_course_id', $course );
	update_post_meta( $id, '_ael_video_source', 'youtube' );
	update_post_meta( $id, '_ael_video_ref', 'AbCdEfGhIjK' );
	update_post_meta( $id, '_ael_duration_min', $l[3] );
}
do_action( 'ael_program_changed', $course );

echo "user=$user teacher=$teacher course=$course\n";
$exp = time() + 7200;
file_put_contents( $argv[2], json_encode( array(
	array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $user, $exp, 'logged_in' ), 'path' => '/' ),
	array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $user, $exp, 'auth' ), 'path' => '/wp-admin' ),
	array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $user, $exp, 'auth' ), 'path' => '/wp-content/plugins' ),
) ) );
file_put_contents( dirname( $argv[2] ) . '/ids.json', json_encode( array( 'teacher' => $teacher, 'course' => $course, 'lesson' => $id ) ) );
