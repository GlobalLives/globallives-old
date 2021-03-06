<?php

/*	==========================================================================
	Posts
	========================================================================== */

	add_theme_support( 'post-thumbnails' );
	add_image_size( 'small', 300, 200, true );

/*	==========================================================================
	Pages
	========================================================================== */

	add_post_type_support( 'page', 'excerpt' );

	add_filter( 'body_class', 'add_page_slug_body_class' );
	function add_page_slug_body_class( $classes ) {
		global $post;
		if ( isset( $post ) && ( $post->post_type == 'page' ) ) {
			$classes[] = $post->post_type . '-' . $post->post_name;
		}
		return $classes;
	}
		
/*	==========================================================================
	Participants
	========================================================================== */

	add_action( 'init', 'create_participant_post_type' );
	function create_participant_post_type() {
   
		register_post_type( 'participant', array(
			'labels' => array(
			    'name'			=> __( 'Participants' ),
			    'singular_name'	=> __( 'Participant' )
			),
			'public' => true,
			'supports' => array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes' ),
			'menu_position' => 5,
			'has_archive' => true,
			'rewrite' => array(
			    'slug'			=> 'participants',
			    'with_front'	=> true
			)
		));
	}
	function get_participant_thumbnail_url( $participant_id, $thumbnail_size = 'thumbnail' ) {
		$participant = get_post( $participant_id );
		$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id($participant->ID), $thumbnail_size );
		return $thumbnail[0];
	}
	function the_participant_thumbnail_url( $participant_id, $thumbnail_size = 'thumbnail' ) {
		echo get_participant_thumbnail_url( $participant_id, $thumbnail_size );
	}	
	
	function get_participant_clip_tags( $participant_id ) {
		$clip_tags = array();
		if ( $clips = get_field('clips',$participant_id) ) {
			foreach( $clips as $clip ) {
				$clip_tags += get_the_terms( $clip->ID, 'post_tag' );
			}
			return $clip_tags;
		} else {
			return false;
		}
	}
	
	function get_participant_crew_members( $participant_id ) {
		$crew_members = get_users(array(
			'meta_query' => array(array(
					'key' => 'shoots',
					'value' => '"' . $participant_id . '"',
					'compare' => 'LIKE'
			))
		));
		return $crew_members;
	}

/*	==========================================================================
	Clips
	========================================================================== */

	add_action( 'init', 'create_clip_post_type' );
	function create_clip_post_type() {
   
		register_taxonomy( 'clip_tags', 'clip', array(
			'label' => __( 'Clip Tags' ),
			'rewrite' => array( 'slug' => 'clip-tag' )
		));
   
		register_post_type( 'clip', array(
			'labels' => array(
			    'name'			=> __( 'Clips' ),
			    'singular_name'	=> __( 'Clip' )
			),
			'public' => true,
			'exclude_from_search' => true,
			'supports' => array( 'title', 'thumbnail', 'comments', 'page-attributes' ),
			'menu_position' => 5,
			'has_archive' => false,
			'taxonomies' => array('clip_tag')
		));
	}
	
/*	==========================================================================
	Users
	========================================================================== */

	add_action('init', 'set_profile_base');
	function set_profile_base() {
		global $wp_rewrite;
		$wp_rewrite->author_base = 'profile';
		$wp_rewrite->flush_rules();
	}

	function get_profile_thumbnail_url( $profile_id, $thumbnail_size = 'thumbnail' ) {
		$thumbnail = wp_get_attachment_image_src( get_field('avatar','user_'.$profile_id), $thumbnail_size );
		return $thumbnail[0];
	}
	function the_profile_thumbnail_url( $profile_id, $thumbnail_size = 'thumbnail' ) {
		echo get_profile_thumbnail_url( $profile_id, $thumbnail_size );
	}	

	function get_profile_activities( $user_id ) {
		$activities = array();
		$user = get_userdata( $user_id );
		// First get comments made by the user
		$comments = get_comments(array( 'user_id' => $user_id, 'status' => 'approve' ));
		foreach ($comments as $comment) {
			$activity = array(
				'activity_type' => 'comment',
				'activity_description' => __('wrote a comment on','glp') . ' <span class="activity-post">'.get_the_title($comment->comment_post_ID).'</span>',
				'activity_user' => $user_id,
				'activity_content' => $comment->comment_content,
				'activity_timestamp' => strtotime($comment->comment_date)
			);
			$activities[] = $activity;
		}
		// Add mentions
		$mentions = get_comments(array( 'status' => 'approve' ));
		foreach ($mentions as $mention) {
			if ( strpos($mention->comment_content, '@'.$user->user_login) === 0 ) {
				$activity = array(
					'activity_type' => 'mention',
					'activity_description' => __('mentioned this user on','glp') . ' <span class="activity-post">'.get_the_title($mention->comment_post_ID).'</span>',
					'activity_user' => $mention->user_id,
					'activity_content' => $mention->comment_content,
					'activity_timestamp' => strtotime($mention->comment_date)
				);
				$activities[] = $activity;
			}
		}
		// Sort activities by timestamp before returning
		usort($activities, 'profile_activity_compare');
		return $activities;
	}
	function profile_activity_compare($a, $b) {
		return $b['activity_timestamp'] - $a['activity_timestamp'];
	}
	
	function get_profile_last_active( $user_id ) {
		$activities = get_profile_activities( $user_id );
		$last_activity = $activities[0];
		return $last_activity['activity_timestamp'];
	}