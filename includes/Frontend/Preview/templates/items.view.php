<?php
/**
 * Collection wrapper view
 *
 * @package droip
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<' . $vars['tag'] . ' ' . $vars['attributes'] . DROIP_APP_PREFIX . '-collection="wrapper"'. '>';
?>
<?php foreach ( $vars['children'] as $child ) : ?>
<?php
			/**
			 * $child is already escaped in collection
			 */
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $child;
		?>
<?php endforeach ?>
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '</' . $vars['tag'] . '>';