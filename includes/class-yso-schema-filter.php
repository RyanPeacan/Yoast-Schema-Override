<?php
/**
 * Hooks into Yoast's wpseo_schema_graph filter to replace the primary content
 * node with the user-defined schema data.
 *
 * @package YoastSchemaOverride
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YSO_Schema_Filter
 */
class YSO_Schema_Filter {

	/**
	 * The primary schema types that represent content nodes.
	 *
	 * When a match is found in the Yoast graph, it will be replaced by the override.
	 *
	 * @var string[]
	 */
	const PRIMARY_TYPES = array(
		'WebPage',
		'AboutPage',
		'ContactPage',
		'FAQPage',
		'CollectionPage',
		'Article',
		'BlogPosting',
		'NewsArticle',
	);

	/**
	 * Registers WordPress hooks.
	 */
	public function register_hooks() {
		// Priority 20 — runs after Yoast has fully assembled the graph.
		add_filter( 'wpseo_schema_graph', array( $this, 'filter_schema_graph' ), 20, 2 );
	}

	/**
	 * Filters the Yoast schema graph, replacing the primary content node when
	 * an override is enabled for the current page/post.
	 *
	 * @param array $graph   The full schema @graph array assembled by Yoast.
	 * @param mixed $context Yoast Meta_Tags_Context object (passed through unused).
	 * @return array The (potentially modified) schema @graph array.
	 */
	public function filter_schema_graph( $graph, $context ) {
		// Only act on singular pages.
		if ( ! is_singular() ) {
			return $graph;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return $graph;
		}

		// Check that override is enabled for this post.
		$override_enabled = get_post_meta( $post_id, '_yso_override_enabled', true );
		if ( '1' !== $override_enabled ) {
			return $graph;
		}

		// Find the primary node in the existing graph and capture its @id.
		$primary_index = $this->find_primary_node_index( $graph );
		if ( false === $primary_index ) {
			// No matching node found — leave graph untouched.
			return $graph;
		}

		$original_id = isset( $graph[ $primary_index ]['@id'] ) ? $graph[ $primary_index ]['@id'] : '';

		// Build the replacement node.
		$mode        = get_post_meta( $post_id, '_yso_mode', true );
		$mode        = $mode ? $mode : 'simple';
		$replacement = $this->build_replacement( $post_id, $mode, $original_id );

		if ( null === $replacement || ! is_array( $replacement ) ) {
			// Could not build a valid replacement — leave graph untouched.
			return $graph;
		}

		// Ensure @id is always present (critical for Yoast's inter-node references).
		if ( $original_id && ! isset( $replacement['@id'] ) ) {
			$replacement['@id'] = $original_id;
		}

		// Swap the node in place.
		$graph[ $primary_index ] = $replacement;

		return $graph;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Finds the index of the primary content node in the graph.
	 *
	 * A node is "primary" when its @type (or one of its @type values if it is
	 * an array) matches one of the PRIMARY_TYPES constants.
	 *
	 * @param array $graph The full schema @graph array.
	 * @return int|false  The array index of the primary node, or false if not found.
	 */
	private function find_primary_node_index( $graph ) {
		if ( ! is_array( $graph ) ) {
			return false;
		}

		foreach ( $graph as $index => $node ) {
			if ( ! isset( $node['@type'] ) ) {
				continue;
			}

			$types = (array) $node['@type'];

			foreach ( $types as $type ) {
				if ( in_array( $type, self::PRIMARY_TYPES, true ) ) {
					return $index;
				}
			}
		}

		return false;
	}

	/**
	 * Builds the replacement schema node.
	 *
	 * @param int    $post_id     The post ID.
	 * @param string $mode        'simple' or 'advanced'.
	 * @param string $original_id The @id of the Yoast node being replaced.
	 * @return array|null  Replacement node array, or null on failure.
	 */
	private function build_replacement( $post_id, $mode, $original_id ) {
		if ( 'advanced' === $mode ) {
			return $this->build_from_advanced_json( $post_id, $original_id );
		}

		return $this->build_from_simple_fields( $post_id, $original_id );
	}

	/**
	 * Builds a replacement node using the simple field builder.
	 *
	 * @param int    $post_id     The post ID.
	 * @param string $original_id The @id of the original Yoast node.
	 * @return array|null
	 */
	private function build_from_simple_fields( $post_id, $original_id ) {
		$builder = new YSO_Simple_Schema();
		return $builder->build( $post_id, $original_id );
	}

	/**
	 * Builds a replacement node from the stored advanced JSON.
	 *
	 * @param int    $post_id     The post ID.
	 * @param string $original_id The @id of the original Yoast node.
	 * @return array|null  Decoded schema array, or null if JSON is absent / invalid.
	 */
	private function build_from_advanced_json( $post_id, $original_id ) {
		$raw = get_post_meta( $post_id, '_yso_advanced_json', true );

		if ( ! $raw ) {
			return null;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		// Always preserve the original @id so Yoast graph references keep working.
		if ( $original_id && ! isset( $decoded['@id'] ) ) {
			$decoded['@id'] = $original_id;
		}

		// Strip @context if the user accidentally included it — Yoast wraps the
		// entire graph in its own @context.
		unset( $decoded['@context'] );

		return $decoded;
	}
}
