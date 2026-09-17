<?php

declare(strict_types=1);

namespace KBMS\Knowledge;

use KBMS\Core\Hookable;

final class KnowledgePostType implements Hookable {

	public const POST_TYPE = 'kbms_item';

	public function registerHooks(): void {
		add_action( 'init', array( $this, 'register' ), 5 );
	}

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Knowledge', 'wp-kbms' ),
					'singular_name' => __( 'Knowledge Item', 'wp-kbms' ),
					'add_new_item'  => __( 'Add Knowledge Item', 'wp-kbms' ),
					'edit_item'     => __( 'Edit Knowledge Item', 'wp-kbms' ),
					'search_items'  => __( 'Search Knowledge', 'wp-kbms' ),
					'not_found'     => __( 'No knowledge items found.', 'wp-kbms' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'has_archive'       => true,
				'rewrite'           => array(
					'slug'       => 'knowledge',
					'with_front' => false,
				),
				'hierarchical'      => true,
				'supports'          => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions', 'custom-fields', 'page-attributes' ),
				'capability_type'   => array( 'kbms_item', 'kbms_items' ),
				'map_meta_cap'      => true,
				'delete_with_user'  => false,
				'show_in_nav_menus' => true,
				'menu_icon'         => 'dashicons-welcome-learn-more',
			)
		);

		register_taxonomy(
			'kbms_type',
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Knowledge Types', 'wp-kbms' ),
					'singular_name' => __( 'Knowledge Type', 'wp-kbms' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'hierarchical' => false,
				'capabilities' => array(
					'manage_terms' => 'manage_kbms',
					'edit_terms'   => 'manage_kbms',
					'delete_terms' => 'manage_kbms',
					'assign_terms' => 'edit_kbms_items',
				),
				'rewrite'      => array( 'slug' => 'knowledge/type' ),
			)
		);
		register_taxonomy(
			'kbms_topic',
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Knowledge Topics', 'wp-kbms' ),
					'singular_name' => __( 'Knowledge Topic', 'wp-kbms' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'hierarchical' => true,
				'capabilities' => array(
					'manage_terms' => 'manage_kbms',
					'edit_terms'   => 'manage_kbms',
					'delete_terms' => 'manage_kbms',
					'assign_terms' => 'edit_kbms_items',
				),
				'rewrite'      => array( 'slug' => 'knowledge/topic' ),
			)
		);
	}
}
