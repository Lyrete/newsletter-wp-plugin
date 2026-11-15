<?php
/**
 * Issue event selection.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Selects and sorts events for a newsletter issue.
 */
class Issue_Selection {

	/**
	 * Get event IDs overlapping a date period.
	 *
	 * @param string $period_start Y-m-d.
	 * @param string $period_end   Y-m-d.
	 * @return int[]
	 */
	public static function get_auto_event_ids( string $period_start, string $period_end ): array {
		$period_start = nlwp_sanitize_date( $period_start );
		$period_end   = nlwp_sanitize_date( $period_end );

		if ( '' === $period_start || '' === $period_end ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => Post_Types::EVENT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_nlwp_start_date',
						'value'   => $period_end,
						'compare' => '<=',
						'type'    => 'DATE',
					),
				),
			)
		);

		$ids = array();
		foreach ( $posts as $event_id ) {
			$start = nlwp_sanitize_date( (string) get_post_meta( $event_id, '_nlwp_start_date', true ) );
			$end   = nlwp_sanitize_date( (string) get_post_meta( $event_id, '_nlwp_end_date', true ) );

			if ( '' === $start ) {
				continue;
			}

			if ( '' !== $end ) {
				if ( $start <= $period_end && $end >= $period_start ) {
					$ids[] = (int) $event_id;
				}
			} elseif ( $start >= $period_start && $start <= $period_end ) {
				$ids[] = (int) $event_id;
			}
		}

		return self::sort_event_ids( $ids );
	}

	/**
	 * Sort event IDs by category order, start date and title.
	 *
	 * @param int[] $ids Event IDs.
	 * @return int[]
	 */
	public static function sort_event_ids( array $ids ): array {
		$ids = nlwp_sanitize_id_array( $ids );
		$ids = array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return Post_Types::EVENT === get_post_type( $id );
				}
			)
		);

		usort(
			$ids,
			static function ( $a, $b ) {
				$term_a = self::get_event_category_term( $a );
				$term_b = self::get_event_category_term( $b );

				$order_a = $term_a ? (int) get_term_meta( $term_a->term_id, '_nlwp_order', true ) : 9999;
				$order_b = $term_b ? (int) get_term_meta( $term_b->term_id, '_nlwp_order', true ) : 9999;

				if ( $order_a !== $order_b ) {
					return $order_a <=> $order_b;
				}

				$name_a = $term_a ? $term_a->name : '';
				$name_b = $term_b ? $term_b->name : '';
				if ( $name_a !== $name_b ) {
					return strcmp( $name_a, $name_b );
				}

				$start_a = nlwp_sanitize_date( (string) get_post_meta( $a, '_nlwp_start_date', true ) );
				$start_b = nlwp_sanitize_date( (string) get_post_meta( $b, '_nlwp_start_date', true ) );
				$start_a = '' === $start_a ? '9999-12-31' : $start_a;
				$start_b = '' === $start_b ? '9999-12-31' : $start_b;

				if ( $start_a !== $start_b ) {
					return strcmp( $start_a, $start_b );
				}

				return strcmp( get_the_title( $a ), get_the_title( $b ) );
			}
		);

		return $ids;
	}

	/**
	 * Sort terms by configured order and then name.
	 *
	 * @param array $terms Term objects.
	 * @return array
	 */
	public static function sort_terms( array $terms ): array {
		$order_map = self::get_category_order_map();

		usort(
			$terms,
			static function ( $a, $b ) use ( $order_map ) {
				$order_a = isset( $order_map[ $a->term_id ] ) ? (int) $order_map[ $a->term_id ] : 0;
				$order_b = isset( $order_map[ $b->term_id ] ) ? (int) $order_map[ $b->term_id ] : 0;
				if ( $order_a !== $order_b ) {
					return $order_a <=> $order_b;
				}
				return strcmp( $a->name, $b->name );
			}
		);

		return $terms;
	}

	/**
	 * Get the primary category term for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return \WP_Term|null
	 */
	public static function get_event_category_term( int $event_id ): ?\WP_Term {
		$terms = get_the_terms( $event_id, Taxonomy::TAXONOMY );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}

		$order_map = self::get_category_order_map();
		usort(
			$terms,
			static function ( $a, $b ) use ( $order_map ) {
				$order_a = isset( $order_map[ $a->term_id ] ) ? (int) $order_map[ $a->term_id ] : 9999;
				$order_b = isset( $order_map[ $b->term_id ] ) ? (int) $order_map[ $b->term_id ] : 9999;
				if ( $order_a !== $order_b ) {
					return $order_a <=> $order_b;
				}
				return strcmp( $a->name, $b->name );
			}
		);

		return $terms[0];
	}

	/**
	 * Get a term ID to order map.
	 *
	 * @return array<int,int>
	 */
	public static function get_category_order_map(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$map = array();
		foreach ( $terms as $term ) {
			$map[ $term->term_id ] = (int) get_term_meta( $term->term_id, '_nlwp_order', true );
		}

		return $map;
	}
}
