<?php

/**
 * Collection view
 *
 * @package droip
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

?>

<?php
$encodedData = json_encode($vars['data']);
$attributes = $vars['attributes'];

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<' . $vars['tag'] . ' ' . $attributes . '>';
echo "<textarea style='display: none' $attributes>$encodedData</textarea>";
?>
<?php foreach ($vars['children'] as $child) : ?>
	<?php
	/**
	 * $child is already escaped in collection item
	 */
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $child;
	?>
<?php endforeach ?>
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '</' . $vars['tag'] . '>';
