<?php
/*
 * Plugin Name: Block Post By Coment
 * Description: Bloquea el contenido de un post hasta que se haga un comentarío en otro post deseado
 * Version: 1.0
 * Author: Ulises Rendón
 * Author URI: https://twitter.com/fidelulises
 */
//Codigo para evitar problemas con sesiones
function register_my_session(){
    if( ! session_id() ) {
        session_start();
    }
}
add_action('init', 'register_my_session');

 //Añadimos campo personalizado al post
function customin(){
	 add_meta_box(
		 'blockby', 'Bloquear post por comentario', 'blockbyfield', 'post', 'side', 'default'
	 );
}
add_action('add_meta_boxes', 'customin');

//Generamos contenido del campo personalizado
function blockbyfield($post){
	$id = get_post_meta( $post->ID, 'blockby', true );
	echo '<input type="text" name="blockby" value="', sanitize_text_field($id), '" class="large-text" placeholder="Id del post que se requiere comentar" />';
}

//Procesamos guardado de nuestro campo
function save_blockby($post_id){
	// Si es un autoguardado nuestro formulario no se enviará
	if ( defined( 'DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;
	// Comprobamos los permisos de usuario
	if ( $_POST['post_type'] == 'page' ) {
		if ( !current_user_can( 'edit_page', $post_id ) ) return $post_id;
	} else {
		if ( !current_user_can( 'edit_post', $post_id ) ) return $post_id;
	}
	// Si existen entradas antiguas las recuperamos
  	$old_blockby = get_post_meta( $post_id, 'blockby', true );
	// Saneamos lo introducido por el usuario
	$new_blockby = sanitize_text_field( $_POST['blockby'] );
	// Actualizamos el campo meta en la base de datos
	update_post_meta( $post_id, 'blockby', (INT) $new_blockby, $old_blockby );
}
add_action( 'save_post', 'save_blockby' );

//Bloqueamos/desbloqueamos post dependiendo del caso
function filter_blockbyid($content){
	if ( is_singular() && in_the_loop() && is_main_query() ) {
		$blockby = get_post_meta(get_the_ID(), 'blockby', TRUE);

		if( $blockby ){
			//Obtenemos email del comentador
			$personmail = isset($_POST['personmail']) ? sanitize_email($_POST['personmail']) : '';
			if( isset($_SESSION['blockbyemail']) && empty($personmail) ) $personmail = sanitize_email($_SESSION['blockbyemail']);

			//Verificamos si ya se hizo el comentario
			$has_comment = false;
			if($personmail){
				$has_comment = get_comments(['post_id'=>$blockby,'author_email'=>$personmail]);
				$_SESSION['blockbyemail'] = $personmail;
			}

			//Obtenemos datos del post requerido
			$endpost = get_post( $blockby, 'OBJECT' );
			$endpostlink = get_permalink($endpost);

			//Creamos mensaje de alerta para solicitar comentario
			$divblock = "<div class='blockbyid'><h3>Contenido bloqueado!</h3><div class='blockbyid_msg'>Se requiere dejar un comentarío en el post &quot;<a href='$endpostlink'>{$endpost->post_title}</a>&quot; antes de poder acceder al contenido de esta entrada.</div></div>";

			$divblock .= '<form class="blockby_form" method="post" action="?unloockbc"><h5>Introduzca E-mail para validar comentarío</h5><div class="blockby_input"><input type="text" name="personmail" value="'.$personmail.'"></div><div><button type="submit">Comprobar comentarío</button></div></form>';

			if( isset($_GET['unloockbc']) && $personmail ) $divblock .= '<div class="blockby_error">No se ha encontrado un comentarío publicado con el E-mail introducido.</div>';

			if(!$has_comment) return $divblock;
		}
    }
	return $content;
}
add_filter( 'the_content', 'filter_blockbyid', 1 );
