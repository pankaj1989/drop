<?php
/**
 * Pagination view
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
	<?php foreach ( $vars['children'] as $child ) : ?>
		<?php
			/**
			 * $child is already escaped in pagination-item, pagination-number
			 */
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $child;
		?>
	<?php endforeach; ?>
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '</' . $vars['tag'] . '>';
