<?php

/*
  Plugin Name: Express Posts
  Plugin URI: http://vancoder.ca/plugins/express-posts
  Description: A brief description of the Plugin.
  Version: 1.2
  Author: Vancoder
  Author URI: http://vancoder.ca

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class Express_Posts extends WP_Widget {

	function __construct() {
		$widget_ops = array( 'classname' => 'express_posts', 'description' => 'Display either a subset of posts, the children of a page or it\'s siblings' );
		$this->WP_Widget( 'express_posts', 'Express Posts', $widget_ops );
		add_action( 'admin_print_scripts-widgets.php', array( __CLASS__, 'express_posts_scripts' ) );
	}

	function express_posts_scripts() {
		global $pagenow;
		wp_register_script( 'express-posts', plugins_url( '/express-posts.js', __FILE__ ), array( 'jquery' ), '1.0' );
		wp_enqueue_script( 'express-posts' );
	}

	function widget( $args, $instance ) {
		global $post;
		extract( $args, EXTR_SKIP );
		$quantity = ( ( empty( $instance['quantity'] ) || ! $quantity = absint( $instance['quantity'] ) ) ? 5 : $instance['quantity'] );

		$defaults = array(
			'show_widget_title' => 0,
			'show_excerpt'      => 0,
			'categories'        => array(),
		);
		$instance = wp_parse_args( ( array ) $instance, $defaults );


		switch ( $instance['relationship'] ) {

			case 'subset':
				$args = array(
					'category__in'        => $instance['categories'],
					'posts_per_page'      => $quantity,
					'ignore_sticky_posts' => 1,
					'orderby'             => ( substr( $instance['ordering'], stripos( $instance['ordering'], ' ' ) ) ),
					'order'               => ( 'date asc' == $instance['ordering'] ? 'ASC' : 'DESC' ),
				);
				break;

			case 'children':
				$ancestors = get_post_ancestors( $post->ID );
				if ( 'show' == $instance['children_generations_filter'] ) {
					if ( ! in_array( count( $ancestors ) + 1, $instance['children_generations'] ) ) {
						break;
					}
				}
				if ( 'hide' == $instance['children_generations_filter'] ) {
					if ( in_array( count( $ancestors ) + 1, $instance['children_generations'] ) ) {
						break;
					}
				}
				$args = array(
					'post_parent'    => $post->ID,
					'posts_per_page' => - 1,
					'orderby'        => $instance['children_ordering'],
					'order'          => 'ASC',
					'post_type'      => 'page',
				);

				break;

			case 'siblings':
				$ancestors = get_post_ancestors( $post->ID );

				if ( 'show' == $instance['siblings_generations_filter'] ) {
					if ( ! in_array( count( $ancestors ) + 1, $instance['siblings_generations'] ) ) {
						break;
					}
				}
				if ( 'hide' == $instance['siblings_generations_filter'] ) {
					if ( in_array( count( $ancestors ) + 1, $instance['siblings_generations'] ) ) {
						break;
					}
				}

				if ( $ancestors ) {
					$parent_post_id = $ancestors[0];
					$args           = array(
						'post_parent'    => $parent_post_id,
						'posts_per_page' => - 1,
						'orderby'        => $instance['siblings_ordering'],
						'order'          => 'ASC',
						'post_type'      => 'page',
						'post__not_in'   => array( $post->ID ),
					);
				}
				break;
		}

		if ( ! isset( $args ) ) {
			return;
		}

		$args['no_found_rows'] = true;

		$query = new WP_Query( $args );

		if ( $query->posts ) {


			echo str_replace( 'widget-container', 'widget-container express_posts-' . $instance['relationship'], $before_widget );


			if ( $instance['show_widget_title'] ) {

				// Does title include a placeholder?
				if ( stripos( $instance['title'], '[' ) ) {

					if ( 'children' == $instance['relationship'] ) {
						// If relationship is children, replace placeholder with current post title
						$instance['title'] = str_ireplace( '[title]', $post->post_title, $instance['title'] );
					}

					if ( 'siblings' == $instance['relationship'] ) {
						// If relationship is siblings and current post has a parent, replace placeholder with parent's title
						$instance['title'] = str_ireplace( '[title]', get_the_title( $parent_post_id ), $instance['title'] );
					}
				}
				echo $before_title . $instance['title'] . $after_title;
			}

			echo '<ul>';
			while ( $query->have_posts() ) {
				$query->the_post();
				echo '<li><a href="' . get_permalink() . '" title="' . get_the_title() . '" class="title">';

				if ( get_the_title() ) {
					the_title();
				} else {
					the_ID();
				}
				echo '</a>';

				if ( 'subset' == $instance['relationship'] ) {
					echo( 'none' == $instance['date_format'] ? '' : '<div class="entry-date">' . ( 'default' == $instance['date_format'] ? get_the_time( get_option( 'date_format' ) ) : get_the_time( $instance['custom_date_format'] ) ) . '</div>' );
					if ( $instance['show_excerpt'] ) {
						the_excerpt();
					}
				}
				echo '</li>';
			}
			echo '</ul>';

			if ( $instance['footer'] ) {
				echo '<div class="footer">' . $instance['footer'] . '</div>';
			}


			echo $after_widget;
		}

		wp_reset_postdata();
	}

	function form( $instance ) {
		$defaults = array(
			'title'                       => '',
			'show_widget_title'           => 0,
			'show_excerpt'                => 0,
			'categories'                  => array(),
			'children_generations_filter' => 'none',
			'children_generations'        => array(),
			'siblings_generations_filter' => 'none',
			'siblings_generations'        => array(),
			'date_format'                 => 'default',
			'quantity'                    => 5,
			'ordering'                    => 'date desc',
			'children_ordering'           => 'menu_order',
			'siblings_ordering'           => 'menu_order',
			'custom_date_format'          => 'F j, Y',
			'relationship'                => 'subset',
			'footer'                      => '',
		);
		$instance = wp_parse_args( ( array ) $instance, $defaults );


		$title = strip_tags( $instance['title'] );
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>"/><br/>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'show_widget_title' ); ?>" name="<?php echo $this->get_field_name( 'show_widget_title' ); ?>" <?php checked( $instance['show_widget_title'], 1, true ); ?> value="1"/> <label for="<?php echo $this->get_field_id( 'show_widget_title' ); ?>">Show title</label></p>

		<p><input type="radio" name="<?php echo $this->get_field_name( 'relationship' ); ?>" value="subset" id="<?php echo $this->get_field_id( 'relationship' ); ?>-1" class="express-posts-relationship" <?php checked( $instance['relationship'], 'subset' ); ?> /> <label for="<?php echo $this->get_field_id( 'relationship' ); ?>-1">Posts subset</label><br/>
			<input type="radio" name="<?php echo $this->get_field_name( 'relationship' ); ?>" value="children" id="<?php echo $this->get_field_id( 'relationship' ); ?>-2" class="express-posts-relationship" <?php checked( $instance['relationship'], 'children' ); ?> /> <label for="<?php echo $this->get_field_id( 'relationship' ); ?>-2">Child pages</label><br/>
			<input type="radio" name="<?php echo $this->get_field_name( 'relationship' ); ?>" value="siblings" id="<?php echo $this->get_field_id( 'relationship' ); ?>-3" class="express-posts-relationship" <?php checked( $instance['relationship'], 'siblings' ); ?> /> <label for="<?php echo $this->get_field_id( 'relationship' ); ?>-3">Sibling pages</label></p>

		<fieldset class="subset" style="display: <?php echo( 'subset' == $instance['relationship'] ? 'block' : 'none' ); ?>">
			<p><label for="<?php echo $this->get_field_id( 'quantity' ); ?>"><?php _e( 'Quantity:' ); ?></label>
				<input id="<?php echo $this->get_field_id( 'quantity' ); ?>" name="<?php echo $this->get_field_name( 'quantity' ); ?>" type="text" value="<?php echo esc_attr( $instance['quantity'] ); ?>" size="3"/></p>

			<p><input type="checkbox" id="<?php echo $this->get_field_id( 'show_excerpt' ); ?>" name="<?php echo $this->get_field_name( 'show_excerpt' ); ?>" <?php checked( $instance['show_excerpt'], 1, true ); ?> value="1"/> <label for="<?php echo $this->get_field_id( 'show_excerpt' ); ?>">Show excerpt</label></p>

			<p><label for="<?php echo $this->get_field_id( 'ordering' ); ?>"><?php _e( 'Order by' ); ?>:</label>
				<select id="<?php echo $this->get_field_id( 'ordering' ); ?>" name="<?php echo $this->get_field_name( 'ordering' ); ?>">
					<option value="date desc"<?php selected( $instance['ordering'], 'date desc' ); ?>>Date (Newest to oldest)</option>
					<option value="date asc"<?php selected( $instance['ordering'], 'date asc' ); ?>>Date (Oldest to newest)</option>
					<option value="title"<?php selected( $instance['ordering'], 'title' ); ?>>Title</option>
					<option value="rand"<?php selected( $instance['ordering'], 'rand' ); ?>>Random</option>
				</select></p>

			<p><label for="<?php echo $this->get_field_id( 'date_format' ); ?>"><?php _e( 'Date' ); ?>:</label>
				<select id="<?php echo $this->get_field_id( 'date_format' ); ?>" name="<?php echo $this->get_field_name( 'date_format' ); ?>">
					<option value="default"<?php selected( $instance['date_format'], 'default' ); ?>>Show date: default format</option>
					<option value="custom"<?php selected( $instance['date_format'], 'custom' ); ?>>Show date: custom format</option>
					<option value="none"<?php selected( $instance['date_format'], 'none' ); ?>>Don't show date</option>
				</select></p>

			<p><label for="<?php echo $this->get_field_id( 'custom_date_format' ); ?>"><?php _e( 'Custom date format:' ); ?></label>
				<input id="<?php echo $this->get_field_id( 'custom_date_format' ); ?>" name="<?php echo $this->get_field_name( 'custom_date_format' ); ?>" type="text" value="<?php echo esc_attr( $instance['custom_date_format'] ); ?>" size="3"/></p>

			<p><label for="<?php echo $this->get_field_id( 'categories' ); ?>"><?php _e( 'Categories:' ); ?></label><br/>
				<?php
				foreach (
					get_categories( array(
						'type'       => 'post',
						'hide_empty' => 0,
					) ) as $category
				) {

					$option = '<input type="checkbox" id="' . $this->get_field_id( 'categories' ) . '-' . $category->term_id . '" name="' . $this->get_field_name( 'categories' ) . '[' . $category->term_id . ']" ' . checked( in_array( $category->term_id, $instance['categories'] ), true, false );
					$option .= ' value="' . $category->term_id . '" /> <label for="' . $this->get_field_id( 'categories' ) . '-' . $category->term_id . '">' . $category->cat_name . '</label><br />';
					echo $option;
				}
				?>
			</p>

			<p><label for="<?php echo $this->get_field_id( 'footer' ); ?>"><?php _e( 'Footer:' ); ?></label> <input class="widefat" id="<?php echo $this->get_field_id( 'footer' ); ?>" name="<?php echo $this->get_field_name( 'footer' ); ?>" type="text" value="<?php echo esc_attr( $instance['footer'] ); ?>"/><br/></p>

		</fieldset>

		<fieldset class="children" style="display: <?php echo( 'children' == $instance['relationship'] ? 'block' : 'none' ); ?>">

			<p><label for="<?php echo $this->get_field_id( 'children_ordering' ); ?>"><?php _e( 'Order by' ); ?>:</label>
				<select id="<?php echo $this->get_field_id( 'children_ordering' ); ?>" name="<?php echo $this->get_field_name( 'children_ordering' ); ?>">
					<option value="menu_order"<?php selected( $instance['children_ordering'], 'menu_order' ); ?>>Menu order</option>
					<option value="title"<?php selected( $instance['children_ordering'], 'title' ); ?>>Title</option>
				</select></p>

			<p>You can use [title] in the title field as a placeholder for the current post's title.</p>

			<p><select name="<?php echo $this->get_field_name( 'children_generations_filter' ); ?>" id="<?php echo $this->get_field_id( 'children_generations_filter' ); ?>">
					<option value="none">Filters</option>
					<option value="show"<?php selected( $instance['children_generations_filter'], 'show' ); ?>>Show widget on these generations</option>
					<option value="hide"<?php selected( $instance['children_generations_filter'], 'hide' ); ?>>Hide widget on these generations</option>
				</select></p>

			<p><label for="<?php echo $this->get_field_id( 'children_generations' ); ?>"><?php _e( 'Generations:' ); ?></label><br/>
				<?php
				for ( $generation = 1; $generation < 11; $generation ++ ) {
					$option = '<input type="checkbox" id="' . $this->get_field_id( 'children_generations' ) . '-' . $generation . '" name="' . $this->get_field_name( 'children_generations' ) . '[' . $generation . ']" ' . checked( in_array( $generation, $instance['children_generations'] ), true, false );
					$option .= ' value="' . $generation . '" /> <label for="' . $this->get_field_id( 'children_generations' ) . '-' . $generation . '">' . $this->get_ordinal( $generation ) . '</label><br />';
					echo $option;
				}
				?>
			</p>

		</fieldset>

		<fieldset class="siblings" style="display: <?php echo( 'siblings' == $instance['relationship'] ? 'block' : 'none' ); ?>">

			<p><label for="<?php echo $this->get_field_id( 'siblings_ordering' ); ?>"><?php _e( 'Order by' ); ?>:</label>
				<select id="<?php echo $this->get_field_id( 'siblings_ordering' ); ?>" name="<?php echo $this->get_field_name( 'siblings_ordering' ); ?>">
					<option value="menu_order"<?php selected( $instance['siblings_ordering'], 'menu_order' ); ?>>Menu order</option>
					<option value="title"<?php selected( $instance['siblings_ordering'], 'title' ); ?>>Title</option>
				</select></p>

			<p>You can use [title] in the title field as a placeholder for the current post's parent's title.</p>

			<p><select name="<?php echo $this->get_field_name( 'siblings_generations_filter' ); ?>" id="<?php echo $this->get_field_id( 'siblings_generations_filter' ); ?>">
					<option value="none">Filters</option>
					<option value="show"<?php selected( $instance['siblings_generations_filter'], 'show' ); ?>>Show widget on these generations</option>
					<option value="hide"<?php selected( $instance['siblings_generations_filter'], 'hide' ); ?>>Hide widget on these generations</option>
				</select></p>

			<p><label for="<?php echo $this->get_field_id( 'siblings_generations' ); ?>"><?php _e( 'Generations:' ); ?></label><br/>
				<?php
				for ( $generation = 1; $generation < 11; $generation ++ ) {
					$option = '<input type="checkbox" id="' . $this->get_field_id( 'siblings_generations' ) . '-' . $generation . '" name="' . $this->get_field_name( 'siblings_generations' ) . '[' . $generation . ']" ' . checked( in_array( $generation, $instance['siblings_generations'] ), true, false );
					$option .= ' value="' . $generation . '" /> <label for="' . $this->get_field_id( 'siblings_generations' ) . '-' . $generation . '">' . $this->get_ordinal( $generation ) . '</label><br />';
					echo $option;
				}
				?>
			</p>

		</fieldset>
		<?php
	}

	function get_ordinal( $number ) {
		$suffix = array( 'th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th' );
		if ( ( $number % 100 ) >= 11 && ( $number % 100 ) <= 13 ) {
			$ordinal = $number . 'th';
		} else {
			$ordinal = $number . $suffix[ $number % 10 ];
		}

		return $ordinal;
	}
}

function express_posts_widget_init() {
	register_widget( 'Express_Posts' );
}

add_action( 'widgets_init', 'express_posts_widget_init' );
