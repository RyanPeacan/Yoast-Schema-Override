<?php
/**
 * Registers and renders the Schema Override metabox on page and post edit screens.
 *
 * @package YoastSchemaOverride
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YSO_Metabox
 */
class YSO_Metabox {

	/**
	 * Post types that show the metabox.
	 */
	const SUPPORTED_POST_TYPES = array( 'page', 'post' );

	/**
	 * Registers WordPress hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers the metabox on supported post types.
	 */
	public function add_meta_box() {
		foreach ( self::SUPPORTED_POST_TYPES as $post_type ) {
			add_meta_box(
				'yso_schema_override',
				__( 'Schema Override', 'yoast-schema-override' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Enqueues admin CSS and JS only on supported post-edit screens.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::SUPPORTED_POST_TYPES, true ) ) {
			return;
		}

		wp_enqueue_style(
			'yso-admin',
			YSO_PLUGIN_URL . 'admin/css/yso-admin.css',
			array(),
			YSO_VERSION
		);

		wp_enqueue_script(
			'yso-admin',
			YSO_PLUGIN_URL . 'admin/js/yso-admin.js',
			array( 'jquery' ),
			YSO_VERSION,
			true
		);

		// Make the post type available to JS for field switching.
		wp_localize_script(
			'yso-admin',
			'ysoData',
			array(
				'postType'    => $screen->post_type,
				'mediaTitle'  => __( 'Select Image', 'yoast-schema-override' ),
				'mediaButton' => __( 'Use this image', 'yoast-schema-override' ),
			)
		);

		// Media uploader.
		wp_enqueue_media();
	}

	/**
	 * Renders the metabox HTML.
	 *
	 * @param WP_Post $post The current post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'yso_save_meta_' . $post->ID, 'yso_nonce' );

		$override_enabled = get_post_meta( $post->ID, '_yso_override_enabled', true );
		$mode             = get_post_meta( $post->ID, '_yso_mode', true );
		$mode             = $mode ? $mode : 'simple';
		$simple_fields    = get_post_meta( $post->ID, '_yso_simple_fields', true );
		$simple_fields    = $simple_fields ? json_decode( $simple_fields, true ) : array();
		$advanced_json    = get_post_meta( $post->ID, '_yso_advanced_json', true );

		// Build defaults for simple fields from current post data.
		$defaults = $this->get_field_defaults( $post );

		// Merge saved values over defaults.
		$simple_fields = wp_parse_args( $simple_fields, $defaults );

		?>
		<div class="yso-metabox">

			<p class="yso-description">
				<?php esc_html_e( 'By default, Yoast SEO automatically generates the schema data for this page. Use the options below to replace that generated data with your own.', 'yoast-schema-override' ); ?>
			</p>

			<?php // ── Enable toggle ── ?>
			<div class="yso-field yso-field-toggle">
				<label class="yso-toggle-label">
					<input
						type="checkbox"
						id="yso_override_enabled"
						name="yso_override_enabled"
						value="1"
						<?php checked( $override_enabled, '1' ); ?>
					/>
					<span class="yso-toggle-text">
						<?php esc_html_e( 'Override the schema data for this page', 'yoast-schema-override' ); ?>
					</span>
				</label>
			</div>

			<?php // ── Conditional panel ── ?>
			<div class="yso-override-panel" <?php echo $override_enabled ? '' : 'style="display:none;"'; ?>>

				<hr class="yso-divider" />

				<?php // ── Mode selector ── ?>
				<div class="yso-field yso-field-mode">
					<p class="yso-field-label"><?php esc_html_e( 'How would you like to enter the schema data?', 'yoast-schema-override' ); ?></p>
					<label class="yso-radio-label">
						<input
							type="radio"
							name="yso_mode"
							value="simple"
							<?php checked( $mode, 'simple' ); ?>
						/>
						<span>
							<strong><?php esc_html_e( 'Simple Builder', 'yoast-schema-override' ); ?></strong>
							&mdash; <?php esc_html_e( 'Fill in individual fields (recommended for most users).', 'yoast-schema-override' ); ?>
						</span>
					</label>
					<label class="yso-radio-label">
						<input
							type="radio"
							name="yso_mode"
							value="advanced"
							<?php checked( $mode, 'advanced' ); ?>
						/>
						<span>
							<strong><?php esc_html_e( 'Advanced: Paste JSON', 'yoast-schema-override' ); ?></strong>
							&mdash; <?php esc_html_e( 'Paste a custom schema JSON object directly.', 'yoast-schema-override' ); ?>
						</span>
					</label>
				</div>

				<?php // ── Simple mode panel ── ?>
				<div class="yso-mode-panel yso-mode-simple" <?php echo ( 'advanced' === $mode ) ? 'style="display:none;"' : ''; ?>>
					<?php $this->render_simple_fields( $post, $simple_fields ); ?>
				</div>

				<?php // ── Advanced mode panel ── ?>
				<div class="yso-mode-panel yso-mode-advanced" <?php echo ( 'advanced' !== $mode ) ? 'style="display:none;"' : ''; ?>>
					<?php $this->render_advanced_field( $advanced_json ); ?>
				</div>

			</div><?php // end .yso-override-panel. ?>

		</div><?php // end .yso-metabox. ?>
		<?php
	}

	/**
	 * Renders the simple mode field set appropriate for the post type.
	 *
	 * @param WP_Post $post         The current post.
	 * @param array   $saved_fields Merged saved + default field values.
	 */
	private function render_simple_fields( $post, $saved_fields ) {
		if ( 'page' === $post->post_type ) {
			$this->render_webpage_fields( $saved_fields );
		} else {
			$this->render_article_fields( $saved_fields );
		}
	}

	/**
	 * Renders simple mode fields for pages (WebPage schema).
	 *
	 * @param array $f Field values.
	 */
	private function render_webpage_fields( $f ) {
		?>
		<div class="yso-field-group">
			<h4 class="yso-group-title"><?php esc_html_e( 'Page Schema Fields', 'yoast-schema-override' ); ?></h4>

			<?php
			$this->render_select_field(
				'type',
				__( 'Page Type', 'yoast-schema-override' ),
				$f['type'] ?? 'WebPage',
				array(
					'WebPage'        => __( 'WebPage (default)', 'yoast-schema-override' ),
					'AboutPage'      => __( 'About Page', 'yoast-schema-override' ),
					'ContactPage'    => __( 'Contact Page', 'yoast-schema-override' ),
					'FAQPage'        => __( 'FAQ Page', 'yoast-schema-override' ),
					'CollectionPage' => __( 'Collection / Archive Page', 'yoast-schema-override' ),
				),
				__( 'Choose the schema type that best describes this page.', 'yoast-schema-override' )
			);

			$this->render_text_field(
				'name',
				__( 'Page Name', 'yoast-schema-override' ),
				$f['name'] ?? '',
				__( 'The full name or title of the page. Defaults to the post title.', 'yoast-schema-override' )
			);

			$this->render_textarea_field(
				'description',
				__( 'Description', 'yoast-schema-override' ),
				$f['description'] ?? '',
				__( 'A short description of the page. Defaults to the Yoast meta description or excerpt.', 'yoast-schema-override' )
			);

			$this->render_url_field(
				'url',
				__( 'Page URL', 'yoast-schema-override' ),
				$f['url'] ?? '',
				__( 'The canonical URL of this page.', 'yoast-schema-override' )
			);

			$this->render_image_field(
				'image',
				__( 'Primary Image URL', 'yoast-schema-override' ),
				$f['image'] ?? '',
				__( 'URL of the primary image for this page. Defaults to the featured image.', 'yoast-schema-override' )
			);

			$this->render_date_field(
				'date_published',
				__( 'Date Published', 'yoast-schema-override' ),
				$f['date_published'] ?? '',
				__( 'ISO 8601 date when this page was first published. Defaults to the post published date.', 'yoast-schema-override' )
			);

			$this->render_date_field(
				'date_modified',
				__( 'Date Modified', 'yoast-schema-override' ),
				$f['date_modified'] ?? '',
				__( 'ISO 8601 date when this page was last modified. Defaults to the post modified date.', 'yoast-schema-override' )
			);

			$this->render_text_field(
				'in_language',
				__( 'Language', 'yoast-schema-override' ),
				$f['in_language'] ?? '',
				__( 'Language code for this page (e.g. en-US). Defaults to the site language.', 'yoast-schema-override' )
			);
			?>
		</div>
		<?php
	}

	/**
	 * Renders simple mode fields for posts (Article schema).
	 *
	 * @param array $f Field values.
	 */
	private function render_article_fields( $f ) {
		?>
		<div class="yso-field-group">
			<h4 class="yso-group-title"><?php esc_html_e( 'Article Schema Fields', 'yoast-schema-override' ); ?></h4>

			<?php
			$this->render_select_field(
				'type',
				__( 'Article Type', 'yoast-schema-override' ),
				$f['type'] ?? 'Article',
				array(
					'Article'     => __( 'Article (default)', 'yoast-schema-override' ),
					'BlogPosting' => __( 'Blog Post', 'yoast-schema-override' ),
					'NewsArticle' => __( 'News Article', 'yoast-schema-override' ),
				),
				__( 'Choose the schema type that best describes this post.', 'yoast-schema-override' )
			);

			$this->render_text_field(
				'headline',
				__( 'Headline', 'yoast-schema-override' ),
				$f['headline'] ?? '',
				__( 'The headline / title of the article (max 110 characters). Defaults to the post title.', 'yoast-schema-override' )
			);

			$this->render_textarea_field(
				'description',
				__( 'Description', 'yoast-schema-override' ),
				$f['description'] ?? '',
				__( 'A short summary of the article. Defaults to the post excerpt.', 'yoast-schema-override' )
			);

			$this->render_url_field(
				'url',
				__( 'Article URL', 'yoast-schema-override' ),
				$f['url'] ?? '',
				__( 'The canonical URL of this article.', 'yoast-schema-override' )
			);

			$this->render_image_field(
				'image',
				__( 'Primary Image URL', 'yoast-schema-override' ),
				$f['image'] ?? '',
				__( 'URL of the primary image for this article. Defaults to the featured image.', 'yoast-schema-override' )
			);

			$this->render_date_field(
				'date_published',
				__( 'Date Published', 'yoast-schema-override' ),
				$f['date_published'] ?? '',
				__( 'ISO 8601 date when this article was first published. Defaults to the post published date.', 'yoast-schema-override' )
			);

			$this->render_date_field(
				'date_modified',
				__( 'Date Modified', 'yoast-schema-override' ),
				$f['date_modified'] ?? '',
				__( 'ISO 8601 date when this article was last updated. Defaults to the post modified date.', 'yoast-schema-override' )
			);

			$this->render_text_field(
				'author_name',
				__( 'Author Name', 'yoast-schema-override' ),
				$f['author_name'] ?? '',
				__( 'Full name of the article\'s author. Defaults to the post author\'s display name.', 'yoast-schema-override' )
			);

			$this->render_url_field(
				'author_url',
				__( 'Author URL', 'yoast-schema-override' ),
				$f['author_url'] ?? '',
				__( 'A URL for the author\'s profile or website. Defaults to the author\'s archive page.', 'yoast-schema-override' )
			);

			$this->render_text_field(
				'in_language',
				__( 'Language', 'yoast-schema-override' ),
				$f['in_language'] ?? '',
				__( 'Language code for this article (e.g. en-US). Defaults to the site language.', 'yoast-schema-override' )
			);

			$this->render_text_field(
				'keywords',
				__( 'Keywords', 'yoast-schema-override' ),
				$f['keywords'] ?? '',
				__( 'Comma-separated keywords or topics for this article. Defaults to the Yoast focus keyphrase if set.', 'yoast-schema-override' )
			);
			?>
		</div>
		<?php
	}

	/**
	 * Renders the advanced mode textarea.
	 *
	 * @param string $saved_json Previously saved JSON string.
	 */
	private function render_advanced_field( $saved_json ) {
		$placeholder = '{' . "\n" .
			'  "@type": "WebPage",' . "\n" .
			'  "name": "My Page",' . "\n" .
			'  "description": "A short description."' . "\n" .
			'}';
		?>
		<div class="yso-field-group">
			<h4 class="yso-group-title"><?php esc_html_e( 'Custom Schema JSON', 'yoast-schema-override' ); ?></h4>
			<p class="yso-field-help">
				<?php esc_html_e( 'Paste your schema object below. Do not include the <script> tag or an "@context" property — those are added automatically.', 'yoast-schema-override' ); ?>
				<strong><?php esc_html_e( 'Must be valid JSON.', 'yoast-schema-override' ); ?></strong>
			</p>
			<textarea
				id="yso_advanced_json"
				name="yso_advanced_json"
				rows="14"
				class="yso-json-textarea large-text code"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				spellcheck="false"
			><?php echo esc_textarea( $saved_json ); ?></textarea>
		</div>
		<?php
	}

	// ── Field renderer helpers ────────────────────────────────────────────────

	/**
	 * Renders a text input field.
	 *
	 * @param string $key         Field key (used in name attribute).
	 * @param string $label       Human-readable label.
	 * @param string $value       Current value.
	 * @param string $description Help text shown below the field.
	 */
	private function render_text_field( $key, $label, $value, $description = '' ) {
		$id = 'yso_simple_' . esc_attr( $key );
		?>
		<div class="yso-field">
			<label for="<?php echo esc_attr( $id ); ?>" class="yso-label">
				<?php echo esc_html( $label ); ?>
			</label>
			<input
				type="text"
				id="<?php echo esc_attr( $id ); ?>"
				name="yso_simple_fields[<?php echo esc_attr( $key ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				class="yso-input large-text"
			/>
			<?php if ( $description ) : ?>
				<p class="yso-field-help"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders a textarea field.
	 *
	 * @param string $key         Field key.
	 * @param string $label       Human-readable label.
	 * @param string $value       Current value.
	 * @param string $description Help text.
	 */
	private function render_textarea_field( $key, $label, $value, $description = '' ) {
		$id = 'yso_simple_' . esc_attr( $key );
		?>
		<div class="yso-field">
			<label for="<?php echo esc_attr( $id ); ?>" class="yso-label">
				<?php echo esc_html( $label ); ?>
			</label>
			<textarea
				id="<?php echo esc_attr( $id ); ?>"
				name="yso_simple_fields[<?php echo esc_attr( $key ); ?>]"
				rows="3"
				class="yso-textarea large-text"
			><?php echo esc_textarea( $value ); ?></textarea>
			<?php if ( $description ) : ?>
				<p class="yso-field-help"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders a URL input field.
	 *
	 * @param string $key         Field key.
	 * @param string $label       Human-readable label.
	 * @param string $value       Current value.
	 * @param string $description Help text.
	 */
	private function render_url_field( $key, $label, $value, $description = '' ) {
		$id = 'yso_simple_' . esc_attr( $key );
		?>
		<div class="yso-field">
			<label for="<?php echo esc_attr( $id ); ?>" class="yso-label">
				<?php echo esc_html( $label ); ?>
			</label>
			<input
				type="url"
				id="<?php echo esc_attr( $id ); ?>"
				name="yso_simple_fields[<?php echo esc_attr( $key ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				class="yso-input large-text"
			/>
			<?php if ( $description ) : ?>
				<p class="yso-field-help"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders an image URL field with a media picker button.
	 *
	 * @param string $key         Field key.
	 * @param string $label       Human-readable label.
	 * @param string $value       Current value.
	 * @param string $description Help text.
	 */
	private function render_image_field( $key, $label, $value, $description = '' ) {
		$id = 'yso_simple_' . esc_attr( $key );
		?>
		<div class="yso-field">
			<label for="<?php echo esc_attr( $id ); ?>" class="yso-label">
				<?php echo esc_html( $label ); ?>
			</label>
			<div class="yso-image-row">
				<input
					type="url"
					id="<?php echo esc_attr( $id ); ?>"
					name="yso_simple_fields[<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( $value ); ?>"
					class="yso-input yso-image-input"
				/>
				<button
					type="button"
					class="button yso-media-picker"
					data-target="<?php echo esc_attr( $id ); ?>"
				><?php esc_html_e( 'Choose Image', 'yoast-schema-override' ); ?></button>
				<?php if ( $value ) : ?>
					<img
						src="<?php echo esc_url( $value ); ?>"
						alt=""
						class="yso-image-preview"
					/>
				<?php else : ?>
					<img src="" alt="" class="yso-image-preview" style="display:none;" />
				<?php endif; ?>
			</div>
			<?php if ( $description ) : ?>
				<p class="yso-field-help"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders a date/datetime-local input.
	 *
	 * @param string $key         Field key.
	 * @param string $label       Human-readable label.
	 * @param string $value       Current value (ISO 8601).
	 * @param string $description Help text.
	 */
	private function render_date_field( $key, $label, $value, $description = '' ) {
		$id = 'yso_simple_' . esc_attr( $key );
		// Convert stored ISO 8601 to datetime-local format (YYYY-MM-DDTHH:MM) for the input.
		$input_value = '';
		if ( $value ) {
			$ts          = strtotime( $value );
			$input_value = $ts ? gmdate( 'Y-m-d\TH:i', $ts ) : '';
		}
		?>
		<div class="yso-field">
			<label for="<?php echo esc_attr( $id ); ?>" class="yso-label">
				<?php echo esc_html( $label ); ?>
			</label>
			<input
				type="datetime-local"
				id="<?php echo esc_attr( $id ); ?>"
				name="yso_simple_fields[<?php echo esc_attr( $key ); ?>]"
				value="<?php echo esc_attr( $input_value ); ?>"
				class="yso-input"
			/>
			<?php if ( $description ) : ?>
				<p class="yso-field-help"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders a <select> field.
	 *
	 * @param string $key         Field key.
	 * @param string $label       Human-readable label.
	 * @param string $selected    Currently selected value.
	 * @param array  $options     Associative array of value => label.
	 * @param string $description Help text.
	 */
	private function render_select_field( $key, $label, $selected, $options, $description = '' ) {
		$id = 'yso_simple_' . esc_attr( $key );
		?>
		<div class="yso-field">
			<label for="<?php echo esc_attr( $id ); ?>" class="yso-label">
				<?php echo esc_html( $label ); ?>
			</label>
			<select
				id="<?php echo esc_attr( $id ); ?>"
				name="yso_simple_fields[<?php echo esc_attr( $key ); ?>]"
				class="yso-select"
			>
				<?php foreach ( $options as $val => $opt_label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $selected, $val ); ?>>
						<?php echo esc_html( $opt_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ( $description ) : ?>
				<p class="yso-field-help"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// ── Default values ────────────────────────────────────────────────────────

	/**
	 * Builds an array of auto-populated default values from the current post.
	 *
	 * @param WP_Post $post The current post.
	 * @return array
	 */
	private function get_field_defaults( $post ) {
		$author_id   = (int) $post->post_author;
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_url  = get_author_posts_url( $author_id );

		// Featured image URL.
		$image_url = '';
		$thumb_id  = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$thumb_src = wp_get_attachment_image_src( $thumb_id, 'full' );
			$image_url = $thumb_src ? $thumb_src[0] : '';
		}

		// Description: Yoast meta description → excerpt → ''.
		$description = '';
		if ( function_exists( 'YoastSEO' ) ) {
			$meta        = YoastSEO()->meta->for_post( $post->ID );
			$description = $meta ? $meta->description : '';
		}
		if ( ! $description ) {
			$description = has_excerpt( $post->ID ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : '';
		}

		// Keywords from Yoast focus keyphrase.
		$keywords = '';
		if ( function_exists( 'YoastSEO' ) ) {
			$yoast_meta = YoastSEO()->meta->for_post( $post->ID );
			$keywords   = $yoast_meta ? $yoast_meta->primary_focus_keyword : '';
		}

		$date_published = $post->post_date_gmt && '0000-00-00 00:00:00' !== $post->post_date_gmt
			? gmdate( 'Y-m-d\TH:i', strtotime( $post->post_date_gmt ) )
			: '';

		$date_modified = $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt
			? gmdate( 'Y-m-d\TH:i', strtotime( $post->post_modified_gmt ) )
			: '';

		return array(
			// Shared.
			'type'           => ( 'page' === $post->post_type ) ? 'WebPage' : 'Article',
			'description'    => $description,
			'url'            => get_permalink( $post->ID ),
			'image'          => $image_url,
			'date_published' => $date_published,
			'date_modified'  => $date_modified,
			'in_language'    => str_replace( '_', '-', get_bloginfo( 'language' ) ),
			// Page-specific.
			'name'           => $post->post_title,
			// Post-specific.
			'headline'       => $post->post_title,
			'author_name'    => $author_name,
			'author_url'     => $author_url,
			'keywords'       => $keywords,
		);
	}

	// ── Save logic ────────────────────────────────────────────────────────────

	/**
	 * Saves the metabox data on post save.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 */
	public function save_meta( $post_id, $post ) {
		// Autosave / revisions: do nothing.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only handle supported post types.
		if ( ! in_array( $post->post_type, self::SUPPORTED_POST_TYPES, true ) ) {
			return;
		}

		// Nonce check.
		if (
			! isset( $_POST['yso_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yso_nonce'] ) ), 'yso_save_meta_' . $post_id )
		) {
			return;
		}

		// Capability check.
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! current_user_can( $post_type_obj->cap->edit_post, $post_id ) ) {
			return;
		}

		// ── Override enabled toggle ──
		$override_enabled = isset( $_POST['yso_override_enabled'] ) ? '1' : '';
		update_post_meta( $post_id, '_yso_override_enabled', $override_enabled );

		// ── Mode ──
		$mode = isset( $_POST['yso_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['yso_mode'] ) ) : 'simple';
		$mode = in_array( $mode, array( 'simple', 'advanced' ), true ) ? $mode : 'simple';
		update_post_meta( $post_id, '_yso_mode', $mode );

		// ── Simple fields ──
		if ( isset( $_POST['yso_simple_fields'] ) && is_array( $_POST['yso_simple_fields'] ) ) {
			$raw   = array_map( 'sanitize_textarea_field', wp_unslash( $_POST['yso_simple_fields'] ) );
			$clean = $this->sanitize_simple_fields( $raw );
			update_post_meta( $post_id, '_yso_simple_fields', wp_json_encode( $clean ) );
		}

		// ── Advanced JSON ──
		if ( isset( $_POST['yso_advanced_json'] ) ) {
			$raw_json = sanitize_textarea_field( wp_unslash( $_POST['yso_advanced_json'] ) );

			if ( '' === $raw_json ) {
				update_post_meta( $post_id, '_yso_advanced_json', '' );
			} else {
				$decoded = json_decode( $raw_json, true );
				if ( null === $decoded ) {
					// Invalid JSON — keep previous value and flag an admin notice via transient.
					set_transient( 'yso_json_error_' . get_current_user_id(), true, 60 );
				} else {
					// Re-encode to normalise formatting before storage.
					update_post_meta( $post_id, '_yso_advanced_json', wp_json_encode( $decoded ) );
				}
			}
		}
	}

	/**
	 * Sanitizes the simple fields array received from $_POST.
	 *
	 * @param array $raw Raw field values.
	 * @return array Sanitized values.
	 */
	private function sanitize_simple_fields( $raw ) {
		$url_keys  = array( 'url', 'image', 'author_url' );
		$text_keys = array( 'type', 'name', 'headline', 'author_name', 'in_language', 'keywords', 'date_published', 'date_modified' );
		$clean     = array();

		foreach ( $text_keys as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( $raw[ $key ] );
			}
		}

		foreach ( $url_keys as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$clean[ $key ] = esc_url_raw( $raw[ $key ] );
			}
		}

		if ( isset( $raw['description'] ) ) {
			$clean['description'] = sanitize_textarea_field( $raw['description'] );
		}

		return $clean;
	}
}
