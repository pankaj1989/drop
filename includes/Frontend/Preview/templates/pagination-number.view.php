<?php
/**
 * Pagination number view
 *
 * @package droip
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<' . $vars['tag'] . ' ' . $vars['attributes'] . '>';
?>
	<?php echo esc_html( $vars['page_number'] ); ?>
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '</' . $vars['tag'] . '>';
