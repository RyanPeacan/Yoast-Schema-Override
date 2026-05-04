<?php
/**
 * Builds a schema.org node array from the simple-mode field values stored in post meta.
 *
 * @package YoastSchemaOverride
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YSO_Simple_Schema
 */
class YSO_Simple_Schema {

	/**
	 * Schema types considered "Article-family".
	 *
	 * @var string[]
	 */
	const ARTICLE_TYPES = array( 'Article', 'BlogPosting', 'NewsArticle' );

	/**
	 * Builds and returns a schema node array for the given post.
	 *
	 * Reads the '_yso_simple_fields' post meta, fills any missing values with
	 * live post data, and returns a schema.org-compliant associative array.
	 *
	 * @param int    $post_id      The post ID.
	 * @param string $original_id  The @id value from the original Yoast node (preserved for graph linking).
	 * @return array|null  The schema node array, or null on failure.
	 */
	public function build( $post_id, $original_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$raw_fields = get_post_meta( $post_id, '_yso_simple_fields', true );
		$fields     = $raw_fields ? json_decode( $raw_fields, true ) : array();
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		$type = isset( $fields['type'] ) ? $fields['type'] : $this->default_type( $post );

		if ( in_array( $type, self::ARTICLE_TYPES, true ) ) {
			return $this->build_article( $post, $fields, $type, $original_id );
		}

		return $this->build_webpage( $post, $fields, $type, $original_id );
	}

	// ── Builders ─────────────────────────────────────────────────────────────

	/**
	 * Builds a WebPage-family schema node.
	 *
	 * @param WP_Post $post        The post object.
	 * @param array   $fields      Saved simple field values.
	 * @param string  $type        The @type value.
	 * @param string  $original_id The original Yoast @id for this node.
	 * @return array
	 */
	private function build_webpage( $post, $fields, $type, $original_id ) {
		$node = array(
			'@type'      => $type,
			'@id'        => $original_id,
			'name'       => $this->get( $fields, 'name', $post->post_title ),
			'url'        => $this->get( $fields, 'url', get_permalink( $post->ID ) ),
			'inLanguage' => $this->get( $fields, 'in_language', $this->site_language() ),
		);

		$description = $this->get( $fields, 'description', '' );
		if ( $description ) {
			$node['description'] = $description;
		}

		$image_url = $this->get( $fields, 'image', $this->featured_image_url( $post->ID ) );
		if ( $image_url ) {
			$node['image'] = array(
				'@type' => 'ImageObject',
				'url'   => $image_url,
			);
		}

		$date_published = $this->get( $fields, 'date_published', '' );
		if ( $date_published ) {
			$node['datePublished'] = $this->to_iso8601( $date_published );
		} elseif ( $post->post_date_gmt && '0000-00-00 00:00:00' !== $post->post_date_gmt ) {
			$node['datePublished'] = gmdate( \DATE_ATOM, strtotime( $post->post_date_gmt ) );
		}

		$date_modified = $this->get( $fields, 'date_modified', '' );
		if ( $date_modified ) {
			$node['dateModified'] = $this->to_iso8601( $date_modified );
		} elseif ( $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
			$node['dateModified'] = gmdate( \DATE_ATOM, strtotime( $post->post_modified_gmt ) );
		}

		return $node;
	}

	/**
	 * Builds an Article-family schema node.
	 *
	 * @param WP_Post $post        The post object.
	 * @param array   $fields      Saved simple field values.
	 * @param string  $type        The @type value.
	 * @param string  $original_id The original Yoast @id for this node.
	 * @return array
	 */
	private function build_article( $post, $fields, $type, $original_id ) {
		$node = array(
			'@type'      => $type,
			'@id'        => $original_id,
			'headline'   => $this->get( $fields, 'headline', $post->post_title ),
			'url'        => $this->get( $fields, 'url', get_permalink( $post->ID ) ),
			'inLanguage' => $this->get( $fields, 'in_language', $this->site_language() ),
		);

		$description = $this->get( $fields, 'description', '' );
		if ( ! $description && has_excerpt( $post->ID ) ) {
			$description = wp_strip_all_tags( get_the_excerpt( $post ) );
		}
		if ( $description ) {
			$node['description'] = $description;
		}

		$image_url = $this->get( $fields, 'image', $this->featured_image_url( $post->ID ) );
		if ( $image_url ) {
			$node['image'] = array(
				'@type' => 'ImageObject',
				'url'   => $image_url,
			);
		}

		$date_published = $this->get( $fields, 'date_published', '' );
		if ( $date_published ) {
			$node['datePublished'] = $this->to_iso8601( $date_published );
		} elseif ( $post->post_date_gmt && '0000-00-00 00:00:00' !== $post->post_date_gmt ) {
			$node['datePublished'] = gmdate( \DATE_ATOM, strtotime( $post->post_date_gmt ) );
		}

		$date_modified = $this->get( $fields, 'date_modified', '' );
		if ( $date_modified ) {
			$node['dateModified'] = $this->to_iso8601( $date_modified );
		} elseif ( $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
			$node['dateModified'] = gmdate( \DATE_ATOM, strtotime( $post->post_modified_gmt ) );
		}

		// Author.
		$author_id   = (int) $post->post_author;
		$author_name = $this->get( $fields, 'author_name', get_the_author_meta( 'display_name', $author_id ) );
		$author_url  = $this->get( $fields, 'author_url', get_author_posts_url( $author_id ) );

		$author_node = array( '@type' => 'Person' );
		if ( $author_name ) {
			$author_node['name'] = $author_name;
		}
		if ( $author_url ) {
			$author_node['url'] = $author_url;
		}
		$node['author'] = $author_node;

		// Keywords.
		$keywords = $this->get( $fields, 'keywords', '' );
		if ( $keywords ) {
			$node['keywords'] = $keywords;
		}

		return $node;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Returns the value from $fields[$key] if non-empty, otherwise $fallback.
	 *
	 * @param array  $fields   Saved fields array.
	 * @param string $key      Field key.
	 * @param mixed  $fallback Value to use when the saved field is empty.
	 * @return mixed
	 */
	private function get( $fields, $key, $fallback ) {
		return ( isset( $fields[ $key ] ) && '' !== $fields[ $key ] ) ? $fields[ $key ] : $fallback;
	}

	/**
	 * Returns the default schema @type based on post type.
	 *
	 * @param WP_Post $post The post object.
	 * @return string
	 */
	private function default_type( $post ) {
		return ( 'page' === $post->post_type ) ? 'WebPage' : 'Article';
	}

	/**
	 * Returns the full URL of the post's featured image (or empty string).
	 *
	 * @param int $post_id The post ID.
	 * @return string
	 */
	private function featured_image_url( $post_id ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumb_id ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $thumb_id, 'full' );
		return ( $src && ! empty( $src[0] ) ) ? $src[0] : '';
	}

	/**
	 * Returns the site language formatted as a BCP-47 code (e.g. "en-US").
	 *
	 * @return string
	 */
	private function site_language() {
		return str_replace( '_', '-', get_bloginfo( 'language' ) );
	}

	/**
	 * Converts a datetime string (including datetime-local format "YYYY-MM-DDTHH:MM")
	 * to a full ISO 8601 / RFC 3339 string.
	 *
	 * @param string $value The datetime string to convert.
	 * @return string  ISO 8601 string, or empty string on failure.
	 */
	private function to_iso8601( $value ) {
		if ( ! $value ) {
			return '';
		}
		$ts = strtotime( $value );
		return $ts ? gmdate( \DATE_ATOM, $ts ) : '';
	}
}
