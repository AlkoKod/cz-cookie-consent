<?php
/**
 * Consent log list table.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the consent log in admin.
 */
class CZCC_Consent_Log_Table extends WP_List_Table {

	/**
	 * Blog scope (null = whole network).
	 *
	 * @var int|null
	 */
	private $blog_id;

	/**
	 * Constructor.
	 *
	 * @param int|null $blog_id Blog scope; null = all blogs (network admin).
	 */
	public function __construct( $blog_id ) {
		$this->blog_id = $blog_id;
		parent::__construct(
			array(
				'singular' => 'consent',
				'plural'   => 'consents',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'id'           => __( 'ID', 'cz-cookie-consent' ),
			'consent_uuid' => __( 'Consent ID', 'cz-cookie-consent' ),
			'categories'   => __( 'Categories', 'cz-cookie-consent' ),
			'gcm'          => __( 'Consent Mode', 'cz-cookie-consent' ),
			'language'     => __( 'Lang', 'cz-cookie-consent' ),
			'source'       => __( 'Source', 'cz-cookie-consent' ),
			'created_at'   => __( 'Created', 'cz-cookie-consent' ),
			'expires_at'   => __( 'Expires', 'cz-cookie-consent' ),
		);
		if ( null === $this->blog_id ) {
			$columns = array_merge( array( 'blog_id' => __( 'Site', 'cz-cookie-consent' ) ), $columns );
		}
		return $columns;
	}

	/**
	 * Loads items.
	 */
	public function prepare_items() {
		$per_page = 50;
		$paged    = max( 1, (int) ( isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source   = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = CZCC_Consent_Repository::query(
			array(
				'blog_id'  => $this->blog_id,
				'search'   => $search,
				'source'   => $source,
				'per_page' => $per_page,
				'paged'    => $paged,
			)
		);

		$this->items = $result['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			)
		);
	}

	/**
	 * Source filter dropdown above the table.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$current = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="alignleft actions">';
		echo '<select name="source">';
		echo '<option value="">' . esc_html__( 'All sources', 'cz-cookie-consent' ) . '</option>';
		foreach ( CZCC_Consent_Repository::sources() as $source ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $source ), selected( $current, $source, false ), esc_html( $source ) );
		}
		echo '</select>';
		submit_button( __( 'Filter', 'cz-cookie-consent' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'blog_id':
				$blog_id = (int) $item['blog_id'];
				if ( is_multisite() ) {
					$details = get_blog_details( $blog_id );
					return $details ? esc_html( $details->blogname . ' (#' . $blog_id . ')' ) : esc_html( '#' . $blog_id );
				}
				return esc_html( (string) $blog_id );

			case 'consent_uuid':
				return '<code>' . esc_html( $item['consent_uuid'] ) . '</code>' . ( $item['user_id'] ? '<br><small>user #' . (int) $item['user_id'] . '</small>' : '' );

			case 'categories':
				$categories = json_decode( (string) $item['categories'], true );
				return esc_html( is_array( $categories ) ? implode( ', ', $categories ) : '' );

			case 'gcm':
				$signals = array(
					'ads'  => $item['gcm_ad_storage'],
					'ana'  => $item['gcm_analytics_storage'],
					'aud'  => $item['gcm_ad_user_data'],
					'adp'  => $item['gcm_ad_personalization'],
					'fun'  => $item['gcm_functionality_storage'],
					'per'  => $item['gcm_personalization_storage'],
					'sec'  => $item['gcm_security_storage'],
				);
				$out = array();
				foreach ( $signals as $label => $value ) {
					$out[] = sprintf(
						'<span class="czcc-gcm czcc-gcm-%1$s" title="%2$s">%3$s</span>',
						esc_attr( 'granted' === $value ? 'granted' : 'denied' ),
						esc_attr( $label . ': ' . $value ),
						esc_html( $label )
					);
				}
				return implode( ' ', $out );

			case 'language':
			case 'source':
			case 'created_at':
			case 'expires_at':
			case 'id':
				return esc_html( (string) $item[ $column_name ] );

			default:
				return '';
		}
	}
}
