<?php
/**
 * HTML email report builder.
 *
 * @package updatebee
 */

namespace UpdateBee\Emails;

use UpdateBee\Functions;

/**
 * Shared email template rendering.
 */
class Emails {

	/**
	 * Build the update report HTML email body.
	 *
	 * @param array<int, array<string, mixed>> $updates            Log rows.
	 * @param string                           $report_title       Email title.
	 * @param string                           $report_description Intro paragraph.
	 * @param string                           $cron_hook          Cron hook for next-run footer.
	 * @return string HTML email body.
	 */
	public static function get_update_report_email( array $updates, string $report_title, string $report_description, string $cron_hook ): string {
		$template_path = __DIR__ . '/templates/update-report-blue.html';
		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		ob_start();
		include $template_path;
		$report = ob_get_clean();
		if ( ! is_string( $report ) ) {
			return '';
		}

		$report = str_replace( '{{email_title}}', $report_title, $report );
		$report = str_replace( '{{site_url}}', esc_url( home_url( '/' ) ), $report );
		$report = str_replace( '{{site_name}}', esc_html( get_bloginfo( 'name' ) ), $report );
		$report = str_replace( '{{introduction}}', $report_description, $report );

		$item_table = '<table class="heading_block block-6" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
		<tr>
			<td class="pad" style="text-align:center;width:100%;">
				<h1 style="margin: 0; color: #296bb9; direction: ltr; font-family: Verdana, Geneva, sans-serif; font-size: 22px; font-weight: 700; letter-spacing: normal; line-height: 120%; text-align: left; margin-top: 0; margin-bottom: 0;"><span class="tinyMce-placeholder">{{update_title}}</span></h1>
			</td>
		</tr>
	</table>
	<table class="html_block block-8" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
		<tr>
			<td class="pad" style="padding-top:10px;">
				<div style="font-family:Verdana, Geneva, sans-serif;text-align:center;" align="center"><table width="100%" border="0" align="left" cellspacing="2px" cellpadding="5px">{{updates}}</div>
			</td>
		</tr>
	</table>';

		$report_info_table_start = '<table width="100%" border="0" align="left" cellspacing="2px" cellpadding="5px"><thead><tr align="left" bgcolor="#296bb9" style="color:#FFFFFF"><th>' . esc_html__( 'Update name', 'updatebee' ) . '</th><th>' . esc_html__( 'Type', 'updatebee' ) . '</th><th>' . esc_html__( 'From', 'updatebee' ) . '</th><th>' . esc_html__( 'To', 'updatebee' ) . '</th><th>' . esc_html__( 'Date', 'updatebee' ) . '</th></thead><tbody align="left">';
		$report_info_table_end   = '</tbody></table>';

		$update_items = array();
		foreach ( $updates as $update ) {
			$normalized = self::normalize_log_row( $update );
			if ( empty( $normalized ) ) {
				continue;
			}

			ob_start();
			?>
			<tr bgcolor="#EAEAEA">
				<td><?php echo esc_html( $normalized['name'] ); ?></td>
				<td><?php echo esc_html( ucfirst( $normalized['update_type'] ) ); ?></td>
				<td><?php echo esc_html( $normalized['from_version'] ); ?></td>
				<td><?php echo esc_html( $normalized['to_version'] ); ?></td>
				<td><?php echo esc_html( $normalized['date'] ); ?></td>
			</tr>
			<?php
			$type = $normalized['update_type'];
			if ( ! isset( $update_items[ $type ] ) ) {
				$update_items[ $type ] = array();
			}
			$update_items[ $type ][] = (string) ob_get_clean();
		}
		ksort( $update_items );

		$output_html = '';
		foreach ( $update_items as $update_type => $update_item ) {
			$inner_html      = $report_info_table_start;
			$section_content = $item_table;
			$section_title   = self::get_type_section_title( (string) $update_type );

			foreach ( $update_item as $update_item_html ) {
				$inner_html .= $update_item_html;
			}
			$inner_html .= $report_info_table_end;

			$section_content = str_replace( '{{update_title}}', $section_title, $section_content );
			$section_content = str_replace( '{{updates}}', $inner_html, $section_content );
			$output_html    .= $section_content;
		}

		$report = str_replace( '{{report}}', $output_html, $report );

		$next_cron = wp_next_scheduled( $cron_hook );
		if ( $next_cron ) {
			$next_cron_text = __( 'Your next scheduled report will be sent on:', 'updatebee' ) . ' ' . get_date_from_gmt( gmdate( 'Y-m-d', $next_cron ), 'F j, Y' ) . ' ' . _x( 'at', 'at what time', 'updatebee' ) . ' ' . get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_cron ), 'g:i a' );
		} else {
			$next_cron_text = __( 'There are no scheduled email reports.', 'updatebee' );
		}

		$change_link = Functions::get_settings_url( 'emails' );
		$change_link = '<a href="' . esc_url( $change_link ) . '" target="_blank" style="text-decoration: underline; color: #e4af4b;" rel="noopener">' . esc_html__( 'Change', 'updatebee' ) . '</a>';

		$report = str_replace( '{{next_cron}}', $next_cron_text, $report );
		$report = str_replace( '{{change_link}}', $change_link, $report );

		return $report;
	}

	/**
	 * Normalize a log row for the email template.
	 *
	 * @param array<string, mixed> $row Log row from DB or hook batch.
	 * @return array<string, string>|array{}
	 */
	public static function normalize_log_row( array $row ): array {
		$name = isset( $row['name'] ) ? (string) $row['name'] : '';
		if ( '' === $name ) {
			return array();
		}

		return array(
			'name'          => $name,
			'update_type'   => isset( $row['update_type'] ) ? (string) $row['update_type'] : '',
			'from_version'  => isset( $row['from_version'] ) ? (string) $row['from_version'] : '',
			'to_version'    => isset( $row['to_version'] ) ? (string) $row['to_version'] : '',
			'date'          => isset( $row['date'] ) ? (string) $row['date'] : '',
			'update_status' => isset( $row['update_status'] ) ? (string) $row['update_status'] : '',
		);
	}

	/**
	 * Section heading for an update type bucket.
	 *
	 * @param string $update_type plugin|theme|core|translation.
	 * @return string
	 */
	private static function get_type_section_title( string $update_type ): string {
		switch ( $update_type ) {
			case 'plugin':
				return esc_html__( 'Plugins', 'updatebee' );
			case 'theme':
				return esc_html__( 'Themes', 'updatebee' );
			case 'translation':
				return esc_html__( 'Translations', 'updatebee' );
			case 'core':
				return esc_html__( 'WordPress core', 'updatebee' );
			default:
				return esc_html( ucfirst( $update_type ) );
		}
	}

	/**
	 * Expand safe CSS for HTML email tables.
	 *
	 * @param array<int, string> $css Allowed CSS properties.
	 * @return array<int, string>
	 */
	public static function allow_email_styles( array $css ): array {
		$extra = array(
			'display',
			'width',
			'height',
			'margin',
			'padding',
			'border',
			'color',
			'background',
			'background-color',
			'font-family',
			'font-size',
			'font-weight',
			'line-height',
			'text-align',
			'letter-spacing',
			'direction',
			'mso-table-lspace',
			'mso-table-rspace',
		);

		return array_values( array_unique( array_merge( $css, $extra ) ) );
	}
}
