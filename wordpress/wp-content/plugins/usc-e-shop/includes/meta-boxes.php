<?php
/**
 * Post related Meta Boxes
 *
 * @package Welcart
 */

//
// Post-related Meta Boxes.
//

/**
 * Display post submit form fields.
 *
 * @since 2.7.0
 *
 * @global string $action
 *
 * @param WP_Post $post Current post object.
 */
function usces_post_submit_meta_box( $post ) {
	global $action;

	$post_type        = $post->post_type;
	$post_type_object = get_post_type_object( $post_type );
	$can_publish      = current_user_can( $post_type_object->cap->publish_posts );
	?>
<div class="submitbox" id="submitpost">

<div id="minor-publishing">

	<?php // Hidden submit button early on so that the browser chooses the right button when form is submitted with Return key. ?>
	<div style="display:none;">
		<input type="submit" name="save" value="<?php esc_attr_e( 'Save' ); ?>" />
	</div>

	<div id="minor-publishing-actions">
		<div id="save-action">
			<?php
			if ( ! in_array( $post->post_status, array( 'publish', 'future', 'pending' ), true ) ) {
				$private_style = '';
				if ( 'private' === $post->post_status ) {
					$private_style = 'style="display:none"';
				}
				?>
				<input <?php echo $private_style; ?> type="submit" name="save" id="save-post" value="<?php esc_attr_e( 'Save Draft' ); ?>" tabindex="4" class="button button-highlighted" />
			<?php } elseif ( 'pending' === $post->post_status && $can_publish ) { ?>
				<input type="submit" name="save" id="save-post" value="<?php esc_attr_e( 'Save as Pending' ); ?>" tabindex="4" class="button button-highlighted" />
			<?php } ?>
		</div>

		<div id="preview-action">
			<?php
			if ( 'publish' === $post->post_status ) {
				$preview_link   = esc_url( get_permalink( $post->ID ) );
				$preview_button = __( 'Preview Changes' );
			} else {
				$preview_link   = esc_url( apply_filters( 'preview_post_link', add_query_arg( 'preview', 'true', get_permalink( $post->ID ) ), $post ) );
				$preview_button = __( 'Preview' );
			}
			?>
			<a class="preview button" href="<?php echo esc_url( $preview_link ); ?>" target="wp-preview" id="post-preview" tabindex="4"><?php echo esc_html( $preview_button ); ?></a>
			<input type="hidden" name="wp-preview" id="wp-preview" value="" />
		</div>

		<div class="clear"></div>
	</div>

	<div id="misc-publishing-actions">
		<?php
		$misc_pub_section_style = ( ! $can_publish ) ? ' misc-pub-section-last' : '';
		?>
		<div class="misc-pub-section<?php echo $misc_pub_section_style; ?>">
			<label for="post_status"><?php esc_html_e( 'Status:' ); ?></label>
			<span id="post-status-display">
				<?php
				switch ( $post->post_status ) {
					case 'private':
						esc_html_e( 'Privately Published' );
						break;
					case 'publish':
						esc_html_e( 'Published' );
						break;
					case 'future':
						esc_html_e( 'Scheduled' );
						break;
					case 'pending':
						esc_html_e( 'Pending Review' );
						break;
					case 'draft':
					case 'auto-draft':
						esc_html_e( 'Draft' );
						break;
					case 'auto-draft':
						esc_html_e( 'Unsaved' );
						break;
				}
				?>
			</span>

			<?php
			if ( 'publish' === $post->post_status || 'private' === $post->post_status || $can_publish ) {
				$private_style = '';
				if ( 'private' === $post->post_status ) {
					$private_style = 'style="display:none"';
				}
				?>
				<a href="#post_status" <?php echo $private_style; ?> class="edit-post-status hide-if-no-js" tabindex="4"><?php esc_html_e( 'Edit' ); ?></a>

				<div id="post-status-select" class="hide-if-js">
					<input type="hidden" name="hidden_post_status" id="hidden_post_status" value="<?php echo esc_attr( ( 'auto-draft' === $post->post_status ) ? 'draft' : $post->post_status ); ?>" />
					<select name="post_status" id="post_status" tabindex="4">
						<?php if ( 'publish' === $post->post_status ) : ?>
						<option<?php selected( $post->post_status, 'publish' ); ?> value='publish'><?php esc_html_e( 'Published' ); ?></option>
						<?php elseif ( 'private' === $post->post_status ) : ?>
						<option<?php selected( $post->post_status, 'private' ); ?> value='publish'><?php esc_html_e( 'Privately Published' ); ?></option>
						<?php elseif ( 'future' === $post->post_status ) : ?>
						<option<?php selected( $post->post_status, 'future' ); ?> value='future'><?php esc_html_e( 'Scheduled' ); ?></option>
						<?php endif; ?>
						<option<?php selected( $post->post_status, 'pending' ); ?> value='pending'><?php esc_html_e( 'Pending Review' ); ?></option>
						<?php if ( 'auto-draft' === $post->post_status ) : ?>
						<option<?php selected( $post->post_status, 'auto-draft' ); ?> value='draft'><?php esc_html_e( 'Draft' ); ?></option>
						<?php else : ?>
						<option<?php selected( $post->post_status, 'draft' ); ?> value='draft'><?php esc_html_e( 'Draft' ); ?></option>
						<?php endif; ?>
					</select>
					<a href="#post_status" class="save-post-status hide-if-no-js button"><?php esc_html_e( 'OK' ); ?></a>
					<a href="#post_status" class="cancel-post-status hide-if-no-js"><?php esc_html_e( 'Cancel' ); ?></a>
				</div>
				<?php
			}
			?>
		</div>

		<div class="misc-pub-section " id="visibility">
			<?php esc_html_e( 'Visibility:' ); ?>
			<span id="post-visibility-display">
				<?php
				if ( 'private' === $post->post_status ) {
					$post->post_password = '';
					$visibility          = 'private';
					$visibility_trans    = __( 'Private' );
				} elseif ( ! empty( $post->post_password ) ) {
					$visibility       = 'password';
					$visibility_trans = __( 'Password protected' );
				} elseif ( 'post' === $post_type && is_sticky( $post->ID ) ) {
					$visibility       = 'public';
					$visibility_trans = __( 'Public, Sticky' );
				} else {
					$visibility       = 'public';
					$visibility_trans = __( 'Public' );
				}

				echo esc_html( $visibility_trans );
				?>
			</span>

			<?php if ( $can_publish ) { ?>
				<a href="#visibility" class="edit-visibility hide-if-no-js"><?php esc_html_e( 'Edit' ); ?></a>

				<div id="post-visibility-select" class="hide-if-js">
					<input type="hidden" name="hidden_post_password" id="hidden-post-password" value="<?php echo esc_attr( $post->post_password ); ?>" />
					<?php if ( 'post' === $post_type ) : ?>
					<input type="checkbox" style="display:none" name="hidden_post_sticky" id="hidden-post-sticky" value="sticky" <?php checked( is_sticky( $post->ID ) ); ?> />
					<?php endif; ?>

					<input type="hidden" name="hidden_post_visibility" id="hidden-post-visibility" value="<?php echo esc_attr( $visibility ); ?>" />
					<input type="radio" name="visibility" id="visibility-radio-public" value="public" <?php checked( $visibility, 'public' ); ?> /> <label for="visibility-radio-public" class="selectit"><?php esc_html_e( 'Public' ); ?></label><br />

					<?php if ( 'post' === $post_type ) : ?>
					<span id="sticky-span"><input id="sticky" name="sticky" type="checkbox" value="sticky" <?php checked( is_sticky( $post->ID ) ); ?> tabindex="4" /> <label for="sticky" class="selectit"><?php esc_html_e( 'Stick this post to the front page' ); ?></label><br /></span>
					<?php endif; ?>

					<input type="radio" name="visibility" id="visibility-radio-password" value="password" <?php checked( $visibility, 'password' ); ?> /> <label for="visibility-radio-password" class="selectit"><?php esc_html_e( 'Password protected' ); ?></label><br />
					<span id="password-span"><label for="post_password"><?php esc_html_e( 'Password:' ); ?></label> <input type="text" name="post_password" id="post_password" value="<?php echo esc_attr( $post->post_password ); ?>" /><br /></span>

					<input type="radio" name="visibility" id="visibility-radio-private" value="private" <?php checked( $visibility, 'private' ); ?> /> <label for="visibility-radio-private" class="selectit"><?php esc_html_e( 'Private' ); ?></label><br />

					<p>
						<a href="#visibility" class="save-post-visibility hide-if-no-js button"><?php esc_html_e( 'OK' ); ?></a>
						<a href="#visibility" class="cancel-post-visibility hide-if-no-js"><?php esc_html_e( 'Cancel' ); ?></a>
					</p>
				</div>
			<?php } ?>
		</div>

		<?php
		/* translators: Publish box date format, see https://www.php.net/manual/datetime.format.php */
		$datef = __( 'M j, Y @ G:i' );

		if ( 0 !== $post->ID ) {
			if ( 'future' === $post->post_status ) {
				/* translators: Post date information. %s: Date on which the post is currently scheduled to be published. */
				$stamp = __( 'Scheduled for: <b>%1$s</b>', 'usces' );
			} elseif ( 'publish' === $post->post_status || 'private' === $post->post_status ) { // Already published.
				/* translators: Post date information. %s: Date on which the post was published. */
				$stamp = __( 'Published on: <b>%1$s</b>', 'usces' );
			} elseif ( '0000-00-00 00:00:00' === $post->post_date_gmt ) { // Draft, 1 or more saves, no date specified.
				$stamp = __( 'Publish <b>immediately</b>', 'usces' );
			} elseif ( time() < strtotime( $post->post_date_gmt . ' +0000' ) ) { // Draft, 1 or more saves, future date specified.
				/* translators: Post date information. %s: Date on which the post is to be published. */
				$stamp = __( 'Schedule for: <b>%1$s</b>', 'usces' );
			} else { // Draft, 1 or more saves, date specified.
				/* translators: Post date information. %s: Date on which the post is to be published. */
				$stamp = __( 'Publish on: <b>%1$s</b>', 'usces' );
			}
			$date = date_i18n( $datef, strtotime( $post->post_date ) );
		} else { // Draft (no saves, and thus no date specified).
			$stamp = __( 'Publish <b>immediately</b>' );
			$date  = date_i18n( $datef, strtotime( current_time( 'mysql' ) ) );
		}

		if ( $can_publish ) : // Contributors don't get to choose the date of publish.
			?>
			<div class="misc-pub-section curtime misc-pub-section-last">
				<span id="timestamp">
				<?php printf( $stamp, $date ); ?></span>
				<a href="#edit_timestamp" class="edit-timestamp hide-if-no-js" tabindex="4"><?php esc_html_e( 'Edit' ); ?></a>
				<div id="timestampdiv" class="hide-if-js"><?php touch_time( ( 'edit' === $action ), 1, 4 ); ?></div>
			</div>
			<?php
		endif;

		/**
		 * Fires after the post time/date setting in the Publish meta box.
		 *
		 * @since 2.9.0
		 * @since 4.4.0 Added the `$post` parameter.
		 *
		 * @param WP_Post $post WP_Post object for the current post.
		 */
		do_action( 'post_submitbox_misc_actions', $post );
		?>
	</div>
	<div class="clear"></div>
</div>

<div id="major-publishing-actions">
	<?php
	/**
	 * Fires at the beginning of the publishing actions section of the Publish meta box.
	 *
	 * @since 2.7.0
	 * @since 4.9.0 Added the `$post` parameter.
	 *
	 * @param WP_Post|null $post WP_Post object for the current post on Edit Post screen,
	 *                           null on Edit Link screen.
	 */
	do_action( 'post_submitbox_start', $post );
	?>
	<div id="delete-action">
		<?php
		if ( current_user_can( 'delete_post', $post->ID ) ) {
			if ( ! EMPTY_TRASH_DAYS ) {
				$delete_text = __( 'Delete Permanently' );
			} else {
				$delete_text = __( 'Move to Trash' );
			}
			?>
			<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>"><?php echo esc_html( $delete_text ); ?></a>
			<?php
		}
		?>
	</div>

	<div id="publishing-action">
		<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" id="ajax-loading" style="visibility:hidden;" alt="" />
		<?php
		if ( ! in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) || 0 === $post->ID ) :
			if ( $can_publish ) :
				if ( ! empty( $post->post_date_gmt ) && time() < strtotime( $post->post_date_gmt . ' +0000' ) ) :
					?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Schedule' ); ?>" />
				<input name="publish" type="submit" class="button-primary" id="publish" tabindex="5" accesskey="p" value="<?php esc_attr_e( 'Schedule' ); ?>" />
				<?php else : ?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Publish' ); ?>" />
				<input name="publish" type="submit" class="button-primary" id="publish" tabindex="5" accesskey="p" value="<?php esc_attr_e( 'Publish' ); ?>" />
					<?php
				endif;
			else :
				?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Submit for Review' ); ?>" />
				<input name="publish" type="submit" class="button-primary" id="publish" tabindex="5" accesskey="p" value="<?php esc_attr_e( 'Submit for Review' ); ?>" />
				<?php
			endif;
		else :
			?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Update' ); ?>" />
				<input name="save" type="submit" class="button-primary" id="publish" tabindex="5" accesskey="p" value="<?php esc_attr_e( 'Update' ); ?>" />
			<?php
		endif;
		?>
	</div>
	<div class="clear"></div>
</div>

</div>
	<?php
}

/**
 * Display post tags form fields.
 *
 * @since 2.6.0
 *
 * @param WP_Post $post Current post object.
 * @param array   $box {
 *     Tags meta box arguments.
 *
 *     @type string   $id       Meta box 'id' attribute.
 *     @type string   $title    Meta box title.
 *     @type callable $callback Meta box display callback.
 *     @type array    $args {
 *         Extra meta box arguments.
 *
 *         @type string $taxonomy Taxonomy. Default 'post_tag'.
 *     }
 * }
 */
function usces_post_tags_meta_box( $post, $box ) {
	$tax_name   = esc_attr( substr( $box['id'], 8 ) );
	$taxonomy   = get_taxonomy( $tax_name );
	$helps      = isset( $taxonomy->helps ) ? esc_attr( $taxonomy->helps ) : esc_attr__( 'Separate tags with commas' );
	$help_hint  = isset( $taxonomy->help_hint ) ? $taxonomy->help_hint : __( 'Add new tag', 'usces' );
	$help_nojs  = isset( $taxonomy->help_nojs ) ? $taxonomy->help_nojs : __( 'Add or remove tags' );
	$help_cloud = isset( $taxonomy->help_cloud ) ? $taxonomy->help_cloud : __( 'Choose from the most used tags' );

	$disabled = ! current_user_can( $taxonomy->cap->assign_terms ) ? 'disabled="disabled"' : '';
	?>
<div class="tagsdiv" id="<?php echo esc_attr( $tax_name ); ?>">
	<div class="jaxtag">
	<div class="nojs-tags hide-if-js">
	<p><?php echo esc_html( $help_nojs ); ?></p>
	<textarea name="<?php echo "tax_input[$tax_name]"; ?>" rows="3" cols="20" class="the-tags" id="tax-input-<?php echo esc_attr( $tax_name ); ?>" <?php echo esc_attr( $disabled ); ?>><?php echo esc_attr( get_terms_to_edit( $post->ID, $tax_name ) ); ?></textarea></div>
	<?php if ( current_user_can( $taxonomy->cap->assign_terms ) ) : ?>
	<div class="ajaxtag hide-if-no-js">
		<label class="screen-reader-text" for="new-tag-<?php echo esc_attr( $tax_name ); ?>"><?php echo esc_html( $box['title'] ); ?></label>
		<div class="taghint"><?php echo esc_html( $help_hint ); ?></div>
		<p><input type="text" id="new-tag-<?php echo esc_attr( $tax_name ); ?>" name="newtag[<?php echo esc_attr( $tax_name ); ?>]" class="newtag form-input-tip" size="16" autocomplete="off" value="" />
		<input type="button" class="button tagadd" value="<?php esc_attr_e( 'Add' ); ?>" tabindex="3" /></p>
	</div>
	<p class="howto"><?php echo esc_html( $helps ); ?></p>
	<?php endif; ?>
	</div>
	<div class="tagchecklist"></div>
</div>
	<?php if ( current_user_can( $taxonomy->cap->assign_terms ) ) : ?>
<p class="hide-if-no-js"><a href="#titlediv" class="tagcloud-link" id="link-<?php echo esc_attr( $tax_name ); ?>"><?php echo esc_html( $help_cloud ); ?></a></p>
	<?php else : ?>
<p><em><?php esc_html_e( 'You cannot modify this taxonomy.' ); ?></em></p>
		<?php
	endif;
}

/**
 * Display post categories form fields.
 *
 * @since 2.6.0
 *
 * @param WP_Post $post Current post object.
 * @param array   $box {
 *     Categories meta box arguments.
 *
 *     @type string   $id       Meta box 'id' attribute.
 *     @type string   $title    Meta box title.
 *     @type callable $callback Meta box display callback.
 *     @type array    $args {
 *         Extra meta box arguments.
 *
 *         @type string $taxonomy Taxonomy. Default 'category'.
 *     }
 * }
 */
function usces_post_categories_meta_box( $post, $box ) {
	$defaults = array( 'taxonomy' => 'category' );
	if ( ! isset( $box['args'] ) || ! is_array( $box['args'] ) ) {
		$args = array();
	} else {
		$args = $box['args'];
	}
	extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );
	$tax = get_taxonomy( $taxonomy );

	if ( 'category' === $args['taxonomy'] ) :
		?>
	<div id="taxonomy-<?php echo esc_attr( $taxonomy ); ?>" class="categorydiv">
		<ul id="<?php echo esc_attr( $taxonomy ); ?>-tabs" class="category-tabs">
			<li class="tabs"><a href="#<?php echo esc_attr( $taxonomy ); ?>-all" tabindex="3"><?php esc_html_e( 'Item Category', 'usces' ); ?></a></li>
			<li class="hide-if-no-js"><a href="#<?php echo esc_attr( $taxonomy ); ?>-pop" tabindex="3"><?php esc_html_e( 'Most Used', 'usces' ); ?></a></li>
		</ul>

		<div id="<?php echo esc_attr( $taxonomy ); ?>-all" class="tabs-panel">
			<?php
			$name = ( 'category' === $taxonomy ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
			// Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
			echo "<input type='hidden' name='{$name}[]' value='0' />";
			?>
			<ul id="<?php echo esc_attr( $taxonomy ); ?>checklist" class="list:<?php echo esc_attr( $taxonomy ); ?> categorychecklist form-no-clear">
				<?php wp_terms_checklist( $post->ID, array( 'taxonomy' => $taxonomy ) ); ?>
			</ul>
		</div>
		<div id="<?php echo esc_attr( $taxonomy ); ?>-pop" class="tabs-panel" style="display: none;">
			<?php
			$name = ( 'category' === $taxonomy ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
			// Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
			echo "<input type='hidden' name='{$name}[]' value='0' />";
			?>
			<ul id="<?php echo esc_attr( $taxonomy ); ?>checklist-pop" class="list:<?php echo esc_attr( $taxonomy ); ?> categorychecklist form-no-clear">
				<?php $popular_ids = wp_popular_terms_checklist( $taxonomy ); ?>
			</ul>
		</div>
	</div>
		<?php
	else :
		?>
	<div id="taxonomy-<?php echo esc_attr( $taxonomy ); ?>" class="categorydiv">
		<ul id="<?php echo esc_attr( $taxonomy ); ?>-tabs" class="category-tabs">
			<li class="tabs"><a href="#<?php echo esc_attr( $taxonomy ); ?>-all" tabindex="3"><?php echo esc_attr( $tax->labels->all_items ); ?></a></li>
			<li class="hide-if-no-js"><a href="#<?php echo esc_attr( $taxonomy ); ?>-pop" tabindex="3"><?php esc_html_e( 'Most Used', 'usces' ); ?></a></li>
		</ul>

		<div id="<?php echo esc_attr( $taxonomy ); ?>-all" class="tabs-panel">
			<?php
			$name = ( 'category' === $taxonomy ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
			// Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
			echo "<input type='hidden' name='{$name}[]' value='0' />";
			?>
			<ul id="<?php echo esc_attr( $taxonomy ); ?>checklist" class="list:<?php echo esc_attr( $taxonomy ); ?> categorychecklist form-no-clear">
				<?php wp_terms_checklist( $post->ID, array( 'taxonomy' => $taxonomy ) ); ?>
			</ul>
		</div>
		<div id="<?php echo esc_attr( $taxonomy ); ?>-pop" class="tabs-panel" style="display: none;">
			<ul id="<?php echo esc_attr( $taxonomy ); ?>checklist-pop" class="categorychecklist form-no-clear" >
				<?php $popular_ids = wp_popular_terms_checklist( $taxonomy ); ?>
			</ul>
		</div>

		<?php if ( ! current_user_can( $tax->cap->assign_terms ) ) : ?>
		<p><em><?php esc_html_e( 'You cannot modify this taxonomy.' ); ?></em></p>
		<?php endif; ?>

		<?php if ( current_user_can( $tax->cap->edit_terms ) ) : ?>
			<div id="<?php echo esc_attr( $taxonomy ); ?>-adder" class="wp-hidden-children">
				<h4>
					<a id="<?php echo esc_attr( $taxonomy ); ?>-add-toggle" href="#<?php echo esc_attr( $taxonomy ); ?>-add" class="hide-if-no-js" tabindex="3">
						<?php
						/* translators: %s: add new taxonomy label */
						printf( __( '+ %s' ), $tax->labels->add_new_item );
						?>
					</a>
				</h4>
				<p id="<?php echo esc_attr( $taxonomy ); ?>-add" class="category-add wp-hidden-child">
					<label class="screen-reader-text" for="new<?php echo esc_attr( $taxonomy ); ?>"><?php echo esc_attr( $tax->labels->add_new_item ); ?></label>
					<input type="text" name="new<?php echo esc_attr( $taxonomy ); ?>" id="new<?php echo esc_attr( $taxonomy ); ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $tax->labels->new_item_name ); ?>" tabindex="3" aria-required="true"/>
					<label class="screen-reader-text" for="new<?php echo esc_attr( $taxonomy ); ?>_parent">
						<?php echo esc_attr( $tax->labels->parent_item_colon ); ?>
					</label>
					<?php
					$parent_dropdown_args = array(
						'taxonomy'         => $taxonomy,
						'hide_empty'       => 0,
						'name'             => 'new' . $taxonomy . '_parent',
						'orderby'          => 'name',
						'hierarchical'     => 1,
						'show_option_none' => '&mdash; ' . $tax->labels->parent_item . ' &mdash;',
						'tab_index'        => 3,
					);
					wp_dropdown_categories( $parent_dropdown_args );
					?>
					<input type="button" id="<?php echo esc_attr( $taxonomy ); ?>-add-submit" class="add:<?php echo esc_attr( $taxonomy ); ?>checklist:<?php echo esc_attr( $taxonomy ); ?>-add button category-add-sumbit" value="<?php echo esc_attr( $tax->labels->add_new_item ); ?>" tabindex="3" />
					<?php wp_nonce_field( 'add-' . $taxonomy, '_ajax_nonce-add-' . $taxonomy, false ); ?>
					<span id="<?php echo esc_attr( $taxonomy ); ?>-ajax-response"></span>
				</p>
			</div>
		<?php endif; ?>
	</div>
		<?php
	endif;
}

/**
 * Display post excerpt form fields.
 *
 * @since 2.6.0
 *
 * @param WP_Post $post Current post object.
 */
function usces_post_excerpt_meta_box( $post ) {
	?>
<label class="screen-reader-text" for="excerpt"><?php esc_html_e( 'Excerpt' ); ?></label>
<textarea rows="1" cols="40" name="excerpt" tabindex="6" id="excerpt"><?php wel_esc_script_e( $post->post_excerpt ); ?></textarea>
<p>
	<?php
	printf(
		/* translators: %s: Documentation URL. */
		__( 'Excerpts are optional hand-crafted summaries of your content that can be used in your theme. <a href="%s">Learn more about manual excerpts</a>.', 'usces' ),
		'https://codex.wordpress.org/Excerpt'
	);
	?>
</p>
	<?php
}

/**
 * Display trackback links form fields.
 *
 * @since 2.6.0
 *
 * @param WP_Post $post Current post object.
 */
function usces_post_trackback_meta_box( $post ) {
	$form_trackback = '<input type="text" name="trackback_url" id="trackback_url" class="code" tabindex="7" value="' . esc_attr( str_replace( "\n", ' ', $post->to_ping ) ) . '" />';
	if ( '' !== $post->pinged ) {
		$pings          = '<p>' . __( 'Already pinged:' ) . '</p><ul>';
		$post->pinged   = usces_change_line_break( $post->pinged );
		$already_pinged = explode( "\n", $post->pinged );
		foreach ( $already_pinged as $pinged_url ) {
			$pings .= "\n\t<li>" . esc_html( $pinged_url ) . '</li>';
		}
		$pings .= '</ul>';
	}
	?>
<p>
	<label for="trackback_url"><?php esc_html_e( 'Send trackbacks to:' ); ?></label>
	<?php wel_esc_script_e( $form_trackback ); ?>
</p>
<p id="trackback-url-desc" class="howto"><?php esc_html_e( 'Separate multiple URLs with spaces' ); ?></p>
<p>
	<?php
	printf(
		/* translators: %s: Documentation URL. */
		__( 'Trackbacks are a way to notify legacy blog systems that you&#8217;ve linked to them. If you link other WordPress sites, they&#8217;ll be notified automatically using <a href="%s">pingbacks</a>, no other action necessary.', 'usces' ),
		'https://codex.wordpress.org/Introduction_to_Blogging#Managing_Comments'
	);
	?>
</p>
	<?php
	if ( ! empty( $pings ) ) {
		wel_esc_script_e( $pings );
	}
}

/**
 * Display custom fields form fields.
 *
 * @since 2.6.0
 *
 * @param WP_Post $post Current post object.
 */
function usces_post_custom_meta_box( $post ) {
	?>
<div id="postcustomstuff">
<div id="ajax-response"></div>
	<?php
	$metadata = has_meta( $post->ID );
	list_meta( $metadata );
	meta_form();
	?>
</div>
<p>
	<?php
	printf(
		/* translators: %s: Documentation URL. */
		__( 'Custom fields can be used to add extra metadata to a post that you can <a href="%s">use in your theme</a>.', 'usces' ),
		'https://codex.wordpress.org/Using_Custom_Fields'
	);
	?>
</p>
	<?php
}

/**
 * Display comments status form fields.
 *
 * @since 2.6.0
 *
 * @param WP_Post $post Current post object.
 */
function usces_post_comment_status_meta_box( $post ) {
	?>
<input name="advanced_view" type="hidden" value="1" />
<p class="meta-options">
	<label for="comment_status" class="selectit"><input name="comment_status" type="checkbox" id="comment_status" value="open" <?php checked( $post->comment_status, 'open' ); ?> /> <?php esc_html_e( 'Allow comments.', 'usces' ); ?></label><br />
	<label for="ping_status" class="selectit"><input name="ping_status" type="checkbox" id="ping_status" value="open" <?php checked( $post->ping_status, 'open' ); ?> />
		<?php
		printf(
			/* translators: %s: Documentation URL. */
			__( 'Allow <a href="%s">trackbacks and pingbacks</a> on this page.', 'usces' ),
			__( 'https://wordpress.org/documentation/article/introduction-to-blogging/#managing-comments' )
		);
		?>
	</label>
	<?php
	/**
	 * Fires at the end of the Discussion meta box on the post editing screen.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_Post $post WP_Post object of the current post.
	 */
	do_action( 'post_comment_status_meta_box-options', $post );
	?>
</p>
	<?php
}

/**
 * Display comments for post table header
 *
 * @since 3.0.0
 *
 * @param array $result Table header rows.
 * @return array
 */
function usces_post_comment_meta_box_thead( $result ) {
	unset( $result['cb'], $result['response'] );
	return $result;
}

/**
 * Display comments for post.
 *
 * @since 2.8.0
 *
 * @param WP_Post $post Current post object.
 */
function usces_post_comment_meta_box( $post ) {
	wp_nonce_field( 'get-comments', 'add_comment_nonce', false );
	?>
	<p class="hide-if-no-js" id="add-new-comment"><a class="button" href="#commentstatusdiv" onclick="window.commentReply && commentReply.addcomment(<?php echo esc_js( $post->ID ); ?>);return false;"><?php esc_html_e( 'Add comment' ); ?></a></p>
	<?php

	$total         = get_comments(
		array(
			'post_id' => $post->ID,
			'number'  => 1,
			'count'   => true,
		)
	);
	$wp_list_table = _get_list_table( 'WP_Post_Comments_List_Table' );
	$wp_list_table->display( true );

	if ( 1 > $total ) {
		echo '<p id="no-comments">' . __( 'No comments yet.' ) . '</p>';
	} else {
		$hidden = get_hidden_meta_boxes( get_current_screen() );
		if ( ! in_array( 'commentsdiv', $hidden, true ) ) {
			?>
			<script type="text/javascript">jQuery(document).ready(function(){commentsBox.get(<?php echo esc_js( $total ); ?>, 10);});</script>
			<?php
		}
		?>
		<p class="hide-if-no-js" id="show-comments"><a href="#commentstatusdiv" onclick="commentsBox.load(<?php echo esc_js( $total ); ?>);return false;"><?php esc_html_e( 'Show comments' ); ?></a> <span class="spinner"></span></p>
		<?php
	}

	wp_comment_trashnotice();
}

/**
 * Display slug form fields.
 *
 * @since 2.6.0
 *
 * @param WP_Post $post Current post object.
 */
function usces_post_slug_meta_box( $post ) {
	?>
<label class="screen-reader-text" for="post_name"><?php esc_html_e( 'Slug' ); ?></label><input name="post_name" type="text" size="13" id="post_name" value="<?php echo esc_attr( $post->post_name ); ?>" />
	<?php
}

/**
 * Display form field with list of authors.
 *
 * @since 2.6.0
 *
 * @param WP_Post $post Current post object.
 */
function usces_post_author_meta_box( $post ) {
	global $wp_version, $user_ID;
	?>
<label class="screen-reader-text" for="post_author_override"><?php esc_html_e( 'Author' ); ?></label>
	<?php
	$params = array(
		'who'              => 'authors',
		'name'             => 'post_author_override',
		'selected'         => empty( $post->ID ) ? $user_ID : $post->post_author,
		'include_selected' => true,
	);
	if ( version_compare( $wp_version, '5.9-alpha', '>=' ) ) {
		$params['capability'] = array( 'edit_posts' );
		unset( $params['who'] );
	}
	wp_dropdown_users( $params );
}

/**
 * Display list of revisions.
 *
 * @since 2.6.0
 *
 * @param WP_Post $post Current post object.
 */
function usces_post_revisions_meta_box( $post ) {
	wp_list_post_revisions();
}

//
// Page-related Meta Boxes.
//

/**
 * Display page attributes form fields.
 *
 * @since 2.7.0
 *
 * @param WP_Post $post Current post object.
 */
function usces_page_attributes_meta_box( $post ) {
	$post_type_object = get_post_type_object( $post->post_type );
	if ( $post_type_object->hierarchical ) :
		$pages = wp_dropdown_pages(
			array(
				'post_type'        => $post->post_type,
				'exclude_tree'     => $post->ID,
				'selected'         => $post->post_parent,
				'name'             => 'parent_id',
				'show_option_none' => __( '(no parent)' ),
				'sort_column'      => 'menu_order, post_title',
				'echo'             => 0,
			)
		);
		if ( ! empty( $pages ) ) :
			?>
<p><strong><?php esc_html_e( 'Parent' ); ?></strong></p>
<label class="screen-reader-text" for="parent_id"><?php esc_html_e( 'Parent' ); ?></label>
			<?php wel_esc_script_e( $pages ); ?>
			<?php
		endif; // End empty pages check.
	endif; // End hierarchical check.
	$page_count = count( get_page_templates() );
	if ( 'page' === $post->post_type && 0 !== $page_count ) :
		$template = ! empty( $post->page_template ) ? $post->page_template : false;
		?>
<p><strong><?php esc_html_e( 'Template' ); ?></strong></p>
<label class="screen-reader-text" for="page_template"><?php esc_html_e( 'Page Template' ); ?></label>
<select name="page_template" id="page_template">
	<option value="default"><?php esc_html_e( 'Default Template' ); ?></option>
		<?php page_template_dropdown( $template ); ?>
</select>
		<?php
	endif;
	?>
<p><strong><?php esc_html_e( 'Order' ); ?></strong></p>
<p><label class="screen-reader-text" for="menu_order"><?php esc_html_e( 'Order' ); ?></label><input name="menu_order" type="text" size="4" id="menu_order" value="<?php echo esc_attr( $post->menu_order ); ?>" /></p>
	<?php
	if ( 'page' === $post->post_type ) :
		?>
<p><?php esc_html_e( 'Need help? Use the Help tab in the upper right of your screen.' ); ?></p>
		<?php
	endif;
}

//
// Link-related Meta Boxes.
//

/**
 * Display link create form fields.
 *
 * @since 2.7.0
 *
 * @param object $link Current link object.
 */
function usces_link_submit_meta_box( $link ) {
	?>
<div class="submitbox" id="submitlink">

<div id="minor-publishing">

	<?php // Hidden submit button early on so that the browser chooses the right button when form is submitted with Return key. ?>
<div style="display:none;">
	<input type="submit" name="save" value="<?php esc_attr_e( 'Save' ); ?>" />
</div>

<div id="minor-publishing-actions">
<div id="preview-action">
	<?php if ( ! empty( $link->link_id ) ) : ?>
	<a class="preview button" href="<?php echo esc_url( $link->link_url ); ?>" target="_blank" tabindex="4"><?php esc_html_e( 'Visit Link' ); ?></a>
	<?php endif; ?>
</div>
<div class="clear"></div>
</div>

<div id="misc-publishing-actions">
<div class="misc-pub-section misc-pub-section-last">
	<label for="link_private" class="selectit"><input id="link_private" name="link_visible" type="checkbox" value="N" <?php checked( $link->link_visible, 'N' ); ?> /> <?php esc_html_e( 'Keep this link private' ); ?></label>
</div>
</div>

</div>

<div id="major-publishing-actions">
	<?php
	/** This action is documented in wp-admin/includes/meta-boxes.php */
	do_action( 'post_submitbox_start', null );
	?>
<div id="delete-action">
	<?php if ( ! empty( $_GET['action'] ) && 'edit' === $_GET['action'] && current_user_can( 'manage_links' ) ) : ?>
	<a class="submitdelete deletion" href="<?php echo wp_nonce_url( "link.php?action=delete&amp;link_id=$link->link_id", 'delete-bookmark_' . $link->link_id ); ?>" onclick="if(confirm('<?php echo esc_js( sprintf( __( "You are about to delete this link '%s'\n  'Cancel' to stop, 'OK' to delete." ), $link->link_name ) ); ?>')){return true;}return false;"><?php esc_html_e( 'Delete' ); ?></a>
	<?php endif; ?>
</div>

<div id="publishing-action">
	<?php if ( ! empty( $link->link_id ) ) : ?>
	<input name="save" type="submit" class="button-primary" id="publish" tabindex="4" accesskey="p" value="<?php esc_attr_e( 'Update Link' ); ?>" />
	<?php else : ?>
	<input name="save" type="submit" class="button-primary" id="publish" tabindex="4" accesskey="p" value="<?php esc_attr_e( 'Add Link' ); ?>" />
	<?php endif; ?>
</div>
<div class="clear"></div>
</div>
	<?php
	/**
	 * Fires at the end of the Publish box in the Link editing screen.
	 *
	 * @since 2.5.0
	 */
	do_action( 'submitlink_box' );
	?>
<div class="clear"></div>
</div>
	<?php
}

/**
 * Display link categories form fields.
 *
 * @since 2.6.0
 *
 * @param object $link Current link object.
 */
function usces_link_categories_meta_box( $link ) {
	?>
<ul id="category-tabs" class="category-tabs">
	<li class="tabs"><a href="#categories-all"><?php esc_html_e( 'All Categories' ); ?></a></li>
	<li class="hide-if-no-js"><a href="#categories-pop"><?php esc_html_e( 'Most Used', 'usces' ); ?></a></li>
</ul>

<div id="categories-all" class="tabs-panel">
	<ul id="categorychecklist" class="list:category categorychecklist form-no-clear">
		<?php
		if ( isset( $link->link_id ) ) {
			wp_link_category_checklist( $link->link_id );
		} else {
			wp_link_category_checklist();
		}
		?>
	</ul>
</div>

<div id="categories-pop" class="tabs-panel" style="display: none;">
	<ul id="categorychecklist-pop" class="categorychecklist form-no-clear">
		<?php wp_popular_terms_checklist( 'link_category' ); ?>
	</ul>
</div>

<div id="category-adder" class="wp-hidden-children">
	<h4><a id="category-add-toggle" href="#category-add"><?php _e( '+ Add New Category' ); ?></a></h4>
	<p id="link-category-add" class="wp-hidden-child">
		<label class="screen-reader-text" for="newcat"><?php _e( '+ Add New Category' ); ?></label>
		<input type="text" name="newcat" id="newcat" class="form-required form-input-tip" value="<?php esc_attr_e( 'New category name' ); ?>" aria-required="true" />
		<input type="button" id="category-add-submit" class="add:categorychecklist:linkcategorydiv button" value="<?php esc_attr_e( 'Add' ); ?>" />
		<?php wp_nonce_field( 'add-link-category', '_ajax_nonce', false ); ?>
		<span id="category-ajax-response"></span>
	</p>
</div>
	<?php
}

/**
 * Display form fields for changing link target.
 *
 * @since 2.6.0
 *
 * @param object $link Current link object.
 */
function usces_link_target_meta_box( $link ) {
	?>
<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Target' ); ?></span></legend>
<p><label for="link_target_blank" class="selectit">
<input id="link_target_blank" type="radio" name="link_target" value="_blank" <?php echo ( isset( $link->link_target ) && ( '_blank' === $link->link_target ) ? 'checked="checked"' : '' ); ?> />
	<?php _e( '<code>_blank</code> &mdash; new window or tab.' ); ?></label></p>
<p><label for="link_target_top" class="selectit">
<input id="link_target_top" type="radio" name="link_target" value="_top" <?php echo ( isset( $link->link_target ) && ( '_top' === $link->link_target ) ? 'checked="checked"' : '' ); ?> />
	<?php _e( '<code>_top</code> &mdash; current window or tab, with no frames.' ); ?></label></p>
<p><label for="link_target_none" class="selectit">
<input id="link_target_none" type="radio" name="link_target" value="" <?php echo ( isset( $link->link_target ) && ( '' === $link->link_target ) ? 'checked="checked"' : '' ); ?> />
	<?php _e( '<code>_none</code> &mdash; same window or tab.' ); ?></label></p>
</fieldset>
<p><?php esc_html_e( 'Choose the target frame for your link.' ); ?></p>
	<?php
}

/**
 * Displays 'checked' checkboxes attribute for XFN microformat options.
 *
 * @since 1.0.1
 *
 * @global object $link Current link object.
 *
 * @param string $xfn_relationship XFN relationship category. Possible values are:
 *                                 'friendship', 'physical', 'professional',
 *                                 'geographical', 'family', 'romantic', 'identity'.
 * @param string $xfn_value        Optional. The XFN value to mark as checked
 *                                 if it matches the current link's relationship.
 *                                 Default empty string.
 * @param mixed  $deprecated       Deprecated. Not used.
 */
function usces_xfn_check( $xfn_relationship, $xfn_value = '', $deprecated = '' ) {
	global $link;

	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '0.0' ); // Never implemented.
	}

	$link_rel = isset( $link->link_rel ) ? $link->link_rel : ''; // In PHP 5.3: $link_rel = $link->link_rel ?: '';
	$rels     = preg_split( '/\s+/', $link_rel );

	// Mark the specified value as checked if it matches the current link's relationship.
	if ( '' !== $xfn_value && in_array( $xfn_value, $rels, true ) ) {
		echo ' checked="checked"';
	}

	if ( '' === $xfn_value ) {
		// Mark the 'none' value as checked if the current link does not match the specified relationship.
		if ( 'family' === $xfn_relationship
			&& ! array_intersect( $link_rels, array( 'child', 'parent', 'sibling', 'spouse', 'kin' ) )
		) {
			echo ' checked="checked"';
		}
		if ( 'friendship' === $xfn_relationship
			&& ! array_intersect( $link_rels, array( 'friend', 'acquaintance', 'contact' ) )
		) {
			echo ' checked="checked"';
		}
		if ( 'geographical' === $xfn_relationship
			&& ! array_intersect( $link_rels, array( 'co-resident', 'neighbor' ) )
		) {
			echo ' checked="checked"';
		}
		// Mark the 'me' value as checked if it matches the current link's relationship.
		if ( 'identity' === $xfn_relationship && in_array( 'me', $rels ) ) {
			echo ' checked="checked"';
		}
	}
}

/**
 * Display xfn form fields.
 *
 * @since 2.6.0
 *
 * @param object $link Current link object.
 */
function usces_link_xfn_meta_box( $link ) {
	?>
<table class="editform" style="width: 100%;" cellspacing="2" cellpadding="5">
	<tr>
		<th style="width: 20%;" scope="row"><label for="link_rel"><?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'rel:' ); ?></label></th>
		<td style="width: 80%;"><input type="text" name="link_rel" id="link_rel" size="50" value="<?php echo ( isset( $link->link_rel ) ? esc_attr( $link->link_rel ) : '' ); ?>" /></td>
	</tr>
	<tr>
		<td colspan="2">
			<table cellpadding="3" cellspacing="5" class="form-table">
				<tr>
					<th scope="row"> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'identity' ); ?> </th>
					<td><fieldset><legend class="screen-reader-text"><span> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'identity' ); ?> </span></legend>
						<label for="me">
						<input type="checkbox" name="identity" value="me" id="me" <?php xfn_check( 'identity', 'me' ); ?> />
						<?php esc_html_e( 'another web address of mine' ); ?></label>
					</fieldset></td>
				</tr>
				<tr>
					<th scope="row"> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'friendship' ); ?> </th>
					<td><fieldset><legend class="screen-reader-text"><span> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'friendship' ); ?> </span></legend>
						<label for="contact">
						<input class="valinp" type="radio" name="friendship" value="contact" id="contact" <?php xfn_check( 'friendship', 'contact' ); ?> /> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'contact' ); ?></label>
						<label for="acquaintance">
						<input class="valinp" type="radio" name="friendship" value="acquaintance" id="acquaintance" <?php xfn_check( 'friendship', 'acquaintance' ); ?> /> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'acquaintance' ); ?></label>
						<label for="friend">
						<input class="valinp" type="radio" name="friendship" value="friend" id="friend" <?php xfn_check( 'friendship', 'friend' ); ?> /> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'friend' ); ?></label>
						<label for="friendship">
						<input name="friendship" type="radio" class="valinp" value="" id="friendship" <?php xfn_check( 'friendship' ); ?> /> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'none' ); ?></label>
					</fieldset></td>
				</tr>
				<tr>
					<th scope="row"> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'physical' ); ?> </th>
					<td><fieldset><legend class="screen-reader-text"><span> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'physical' ); ?> </span></legend>
						<label for="met">
						<input class="valinp" type="checkbox" name="physical" value="met" id="met" <?php xfn_check( 'physical', 'met' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'met' ); ?></label>
					</fieldset></td>
				</tr>
				<tr>
					<th scope="row"> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'professional' ); ?> </th>
					<td><fieldset><legend class="screen-reader-text"><span> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'professional' ); ?> </span></legend>
						<label for="co-worker">
						<input class="valinp" type="checkbox" name="professional" value="co-worker" id="co-worker" <?php xfn_check( 'professional', 'co-worker' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'co-worker' ); ?></label>
						<label for="colleague">
						<input class="valinp" type="checkbox" name="professional" value="colleague" id="colleague" <?php xfn_check( 'professional', 'colleague' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'colleague' ); ?></label>
					</fieldset></td>
				</tr>
				<tr>
					<th scope="row"> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'geographical' ); ?> </th>
					<td><fieldset><legend class="screen-reader-text"><span> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'geographical' ); ?> </span></legend>
						<label for="co-resident">
						<input class="valinp" type="radio" name="geographical" value="co-resident" id="co-resident" <?php xfn_check( 'geographical', 'co-resident' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'co-resident' ); ?></label>
						<label for="neighbor">
						<input class="valinp" type="radio" name="geographical" value="neighbor" id="neighbor" <?php xfn_check( 'geographical', 'neighbor' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'neighbor' ); ?></label>
						<label for="geographical">
						<input class="valinp" type="radio" name="geographical" value="" id="geographical" <?php xfn_check( 'geographical' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'none' ); ?></label>
					</fieldset></td>
				</tr>
				<tr>
					<th scope="row"> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'family' ); ?> </th>
					<td><fieldset><legend class="screen-reader-text"><span> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'family' ); ?> </span></legend>
						<label for="child">
						<input class="valinp" type="radio" name="family" value="child" id="child" <?php xfn_check( 'family', 'child' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'child' ); ?></label>
						<label for="kin">
						<input class="valinp" type="radio" name="family" value="kin" id="kin" <?php xfn_check( 'family', 'kin' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'kin' ); ?></label>
						<label for="parent">
						<input class="valinp" type="radio" name="family" value="parent" id="parent" <?php xfn_check( 'family', 'parent' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'parent' ); ?></label>
						<label for="sibling">
						<input class="valinp" type="radio" name="family" value="sibling" id="sibling" <?php xfn_check( 'family', 'sibling' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'sibling' ); ?></label>
						<label for="spouse">
						<input class="valinp" type="radio" name="family" value="spouse" id="spouse" <?php xfn_check( 'family', 'spouse' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'spouse' ); ?></label>
						<label for="family">
						<input class="valinp" type="radio" name="family" value="" id="family" <?php xfn_check( 'family' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'none' ); ?></label>
					</fieldset></td>
				</tr>
				<tr>
					<th scope="row"> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'romantic' ); ?> </th>
					<td><fieldset><legend class="screen-reader-text"><span> <?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'romantic' ); ?> </span></legend>
						<label for="muse">
						<input class="valinp" type="checkbox" name="romantic" value="muse" id="muse" <?php xfn_check( 'romantic', 'muse' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'muse' ); ?></label>
						<label for="crush">
						<input class="valinp" type="checkbox" name="romantic" value="crush" id="crush" <?php xfn_check( 'romantic', 'crush' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'crush' ); ?></label>
						<label for="date">
						<input class="valinp" type="checkbox" name="romantic" value="date" id="date" <?php xfn_check( 'romantic', 'date' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'date' ); ?></label>
						<label for="romantic">
						<input class="valinp" type="checkbox" name="romantic" value="sweetheart" id="romantic" <?php xfn_check( 'romantic', 'sweetheart' ); ?> />
						<?php /* translators: xfn: http://gmpg.org/xfn/ */ esc_html_e( 'sweetheart' ); ?></label>
					</fieldset></td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<p><?php _e( 'If the link is to a person, you can specify your relationship with them using the above form. If you would like to learn more about the idea check out <a href="http://gmpg.org/xfn/">XFN</a>.' ); ?></p>
	<?php
}

/**
 * Display advanced link options form fields.
 *
 * @since 2.6.0
 *
 * @param object $link Current link object.
 */
function usces_link_advanced_meta_box( $link ) {
	?>
<table class="form-table" style="width: 100%;" cellspacing="2" cellpadding="5">
	<tr class="form-field">
		<th valign="top" scope="row"><label for="link_image"><?php esc_html_e( 'Image Address' ); ?></label></th>
		<td><input type="text" name="link_image" class="code" id="link_image" size="50" value="<?php echo ( isset( $link->link_image ) ? esc_attr( $link->link_image ) : '' ); ?>" style="width: 95%" /></td>
	</tr>
	<tr class="form-field">
		<th valign="top" scope="row"><label for="rss_uri"><?php esc_html_e( 'RSS Address' ); ?></label></th>
		<td><input name="link_rss" class="code" type="text" id="rss_uri" value="<?php echo ( isset( $link->link_rss ) ? esc_attr( $link->link_rss ) : '' ); ?>" size="50" style="width: 95%" /></td>
	</tr>
	<tr class="form-field">
		<th valign="top" scope="row"><label for="link_notes"><?php esc_html_e( 'Notes' ); ?></label></th>
		<td><textarea name="link_notes" id="link_notes" cols="50" rows="10" style="width: 95%"><?php echo ( isset( $link->link_notes ) ? $link->link_notes : '' ); ?></textarea></td>
	</tr>
	<tr class="form-field">
		<th valign="top" scope="row"><label for="link_rating"><?php esc_html_e( 'Rating' ); ?></label></th>
		<td><select name="link_rating" id="link_rating" size="1">
		<?php
		for ( $r = 0; $r <= 10; $r++ ) {
			echo '<option value="' . esc_attr( $r ) . '" ';
			if ( isset( $link->link_rating ) && $link->link_rating == $r ) {
				echo 'selected="selected"';
			}
			echo '>' . $r . '</option>';
		}
		?>
		</select>&nbsp;<?php esc_html_e( '(Leave at 0 for no rating.)' ); ?>
		</td>
	</tr>
</table>
	<?php
}

/**
 * Display post thumbnail meta box.
 *
 * @since 2.9.0
 */
function usces_post_thumbnail_meta_box() {
	global $post;
	$thumbnail_id = get_post_meta( $post->ID, '_thumbnail_id', true );
	echo _wp_post_thumbnail_html( $thumbnail_id, $post->ID );
}

/**
 * Handle load old item image box.
 *
 * @param object $post post info.
 */
function post_item_pict_box( $post ) {
	global $usces, $current_screen;
	$item_picts    = array();
	$item_sumnails = array();
	$post_id       = isset( $post->ID ) ? $post->ID : 0;
	$product       = wel_get_product( $post_id );
	$item_code     = $product['itemCode'];

	if ( ! empty( $item_code ) ) {
		$pictid             = (int) $usces->get_mainpictid( $item_code, false );
		$item_picts[]       = wp_get_attachment_image( $pictid, array( 260, 200 ), true );
		$item_sumnails[]    = wp_get_attachment_image( $pictid, array( 50, 50 ), true );
		$item_pictids       = $usces->get_pictids( $item_code, false );
		$item_pictids_count = ( $item_pictids && is_array( $item_pictids ) ) ? count( $item_pictids ) : 0;
		for ( $i = 0; $i < $item_pictids_count; $i++ ) {
			$item_picts[]    = wp_get_attachment_image( $item_pictids[ $i ], array( 260, 200 ), true );
			$item_sumnails[] = wp_get_attachment_image( $item_pictids[ $i ], array( 50, 50 ), true );
		}
	}
	?>
	<div class="item-main-pict">
		<div id="item-select-pict">
	<?php
	if ( isset( $item_picts[0] ) ) {
		wel_esc_script_e( $item_picts[0] );
	}
	?>
		</div>
		<div class="clearfix">
	<?php $item_sumnails_count = count( $item_sumnails ); ?>
	<?php for ( $i = 0; $i < $item_sumnails_count; $i++ ) { ?>
			<div class="subpict"><a onclick='uscesItem.cahngepict("<?php echo str_replace( '"', '\"', $item_picts[ $i ] ); ?>");'><?php wel_esc_script_e( $item_sumnails[ $i ] ); ?></a></div>
	<?php } ?>
		</div>
	</div>
	<?php
}
