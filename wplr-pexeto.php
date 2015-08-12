<?php
/*
Plugin Name: Pexeto for Lightroom
Description: Pexeto Extension for Lightroom through the WP/LR Sync plugin.
Version: 0.1.0
Author: Jordy Meow
Author URI: http://www.meow.fr
*/

class WPLR_Extension_Pixeto {

  public function __construct() {

    // Init
    add_filter( 'wplr_extensions', array( $this, 'extensions' ), 10, 1 );

    // Create / Update
    add_action( 'wplr_create_folder', array( $this, 'create_folder' ), 10, 3 );
    add_action( 'wplr_update_folder', array( $this, 'update_folder' ), 10, 2 );
    add_action( 'wplr_create_collection', array( $this, 'create_collection' ), 10, 3 );
    add_action( 'wplr_update_collection', array( $this, 'update_collection' ), 10, 2 );
    add_action( "wplr_move_folder", array( $this, 'move_folder' ), 10, 3 );
    add_action( "wplr_move_collection", array( $this, 'move_collection' ), 10, 3 );

    // Delete
    add_action( "wplr_remove_collection", array( $this, 'remove_collection' ), 10, 1 );
    add_action( "wplr_remove_folder", array( $this, 'remove_folder' ), 10, 1 );

    // Media
    add_action( "wplr_add_media_to_collection", array( $this, 'add_media_to_collection' ), 10, 2 );
    add_action( "wplr_remove_media_from_collection", array( $this, 'remove_media_from_collection' ), 10, 2 );

    // Extra
    //add_action( 'wplr_reset', array( $this, 'reset' ), 10, 0 );
    //add_action( "wplr_clean", array( $this, 'clean' ), 10, 1 );
    //add_action( "wplr_remove_media", array( $this, 'remove_media' ), 10, 1 );
  }

  // It's fairly important to add the current extension name to this in order to show to the users
  // that the extensions is available and loaded.
  function extensions( $extensions ) {
    array_push( $extensions, 'Pexeto Themes' );
    return $extensions;
  }

  function create_collection( $collectionId, $inFolderId, $collection, $isFolder = false ) {
    global $wplr;

    // If exists already, avoid re-creating
    $hasMeta = $wplr->get_meta( "pexeto_gallery_id", $collectionId );
    if ( !empty( $hasMeta ) )
      return;

    // Create the collection.
    $post = array(
      'post_title'    => wp_strip_all_tags( $collection['name'] ),
      'post_content'  => $isFolder ? '' : '[gallery ids="" link="file"]', // if folder, nothing, if collection, let's start a gallery
      'post_status'   => 'publish',
      'post_type'     => 'portfolio'
    );
    $id = wp_insert_post( $post );

    // Add a meta to retrieve easily the LR ID for that collection from a WP Post ID
    $wplr->set_meta( 'pexeto_gallery_id', $collectionId, $id );

    add_post_meta( $id, 'action_value', 'lightbox', true );
    add_post_meta( $id, 'img_columns_value', 1, true );
    add_post_meta( $id, 'img_rows_value', 1, true );
    add_post_meta( $id, 'crop_value', 'c', true );
    add_post_meta( $id, 'layout_value', 'right', true );
    add_post_meta( $id, 'sidebar_value', 'default', true );

    // Associate this portfolio to a category
    $parentTermId = $wplr->get_meta( "pexeto_term_id", $inFolderId );
    if ( $parentTermId ) {
      $term = get_term_by( 'term_id', $parentTermId, 'portfolio_category' );
      if ( !empty( $term ) )
        wp_set_post_terms( $id, $term->term_id, 'portfolio_category' );
    }
  }

  // Create the folder (category)
  function create_folder( $folderId, $inFolderId, $folder ) {
    global $wplr;
    $parentTermId = $wplr->get_meta( "pexeto_term_id", $inFolderId );
    $result = wp_insert_term( $folder['name'], 'portfolio_category', array( 'parent' => $parentTermId ) );
    if ( is_wp_error( $result ) ) {
      error_log( "Issue while creating the folder " . $folder['name'] . "." );
      error_log( $result->get_error_message() );
      return;
    }
    $wplr->set_meta( 'pexeto_term_id', $folderId, $result['term_id'] );
  }

  // Updated the collection (gallery) with new information.
  // Currently, that would be only its name.
  function update_collection( $collectionId, $collection ) {
    global $wplr;
    $id = $wplr->get_meta( "pexeto_gallery_id", $collectionId );
    $post = array( 'ID' => $id, 'post_title' => wp_strip_all_tags( $collection['name'] ) );
    wp_update_post( $post );
  }

  // Update the folder (category) with new information.
  // Currently, that would be only its name.
  function update_folder( $folderId, $folder ) {
    global $wplr;
    $termId = $wplr->get_meta( "pexeto_term_id", $folderId );
    wp_update_term( $termId, 'portfolio_category', array( 'name' => $folder['name'] ) );
  }

  // Move the folder (category) under another one.
  // If the folder is empty, then it is the root.
  function move_folder( $folderId, $inFolderId, $previousFolderId ) {
    global $wplr;
    $termId = $wplr->get_meta( "pexeto_term_id", $folderId );
    $parentTermId = null;
    if ( !empty( $inFolderId ) )
      $parentTermId = $wplr->get_meta( "pexeto_term_id", $inFolderId );
    wp_update_term( $termId, 'portfolio_category', array( 'parent' => $parentTermId ) );
  }

  // Move the collection (gallery) under another folder (category).
  // If the folder is empty, then it is the root.
  function move_collection( $collectionId, $folderId, $previousFolderId ) {
    global $wplr;
    $galleryId = $wplr->get_meta( "pexeto_gallery_id", $collectionId );
    $parentTermId = empty( $folderId ) ? null : $wplr->get_meta( "pexeto_term_id", $folderId );
    $previousTermId = empty( $previousFolderId ) ? null : $wplr->get_meta( "pexeto_term_id", $previousFolderId );

    // Remove the previous term (category) and add the new one
    wp_remove_object_terms( $galleryId, $previousTermId, 'portfolio_category' );
    wp_set_post_terms( $galleryId, $parentTermId, 'portfolio_category' );
  }

  // Add media to a collection.
  // The $mediaId is actually the WordPress Post/Attachment ID.
  function add_media_to_collection( $mediaId, $collectionId, $isRemove = false ) {
    global $wplr;
    $id = $wplr->get_meta( "pexeto_gallery_id", $collectionId );
    $content = get_post_field( 'post_content', $id );
    preg_match_all( '/\[gallery.*ids="([0-9,]*).*"\]/', $content, $results );
    if ( !empty( $results ) && !empty( $results[1] ) ) {
      $str = $results[1][0];
      $ids = !empty( $str ) ? explode( ',', $str ) : array();
      $index = array_search( $mediaId, $ids, false );
      if ( $isRemove ) {
        if ( $index !== FALSE )
          unset( $ids[$index] );
      }
      else {
        // If mediaId already there then exit.
        if ( $index !== FALSE )
          return;
        array_push( $ids, $mediaId );
      }
      // Replace the array within the gallery shortcode.
      $content = str_replace( 'ids="' . $str, 'ids="' . implode( ',', $ids ), $content );
      $post = array( 'ID' => $id, 'post_content' => $content );
      wp_update_post( $post );

      // Add a default featured image if none
      add_post_meta( $id, '_thumbnail_id', $mediaId, true );

      // Expression uses the Preview Value but not the other Pexeto themes
      if ( defined( PEXETO_SHORTNAME ) && PEXETO_SHORTNAME == 'expr' ) {
        $hasPreviewValue = get_post_meta( $id, 'preview_value', null );
        if ( empty( $hasPreviewValue ) )
          add_post_meta( $id, 'preview_value', wp_get_attachment_url( $mediaId ), true );
      }
    }
  }

  // Remove the media from the collection.
  function remove_media_from_collection( $mediaId, $collectionId ) {
    global $wplr;
    $this->add_media_to_collection( $mediaId, $collectionId, true );

    // Need to delete the featured image if it was this media
    $postId = $wplr->get_meta( "pexeto_gallery_id", $collectionId );
    $thumbnailId = get_post_meta( $postId, '_thumbnail_id', -1 );
    if ( $thumbnailId == $mediaId )
      delete_post_meta( $postId, '_thumbnail_id' );
  }

  // Delete the collection.
  function remove_collection( $collectionId ) {
    global $wplr;
    $id = $wplr->get_meta( "pexeto_gallery_id", $collectionId );
    wp_delete_post( $id, true );
    $wplr->delete_meta( 'pexeto_gallery_id', $collectionId );
  }

  // Delete the folder.
  function remove_folder( $folderId ) {
    global $wplr;
    $id = $wplr->get_meta( "pexeto_term_id", $folderId );
    wp_delete_term( $id, 'portfolio_category' );
    $wplr->delete_meta( 'pexeto_term_id', $folderId );
  }
}

new WPLR_Extension_Pixeto;

?>
