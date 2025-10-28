<?php
/**
 * Editor template php file.
 *
 * @package droip
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Droip\HelperFunctions;

$post_url_arr = HelperFunctions::get_post_url_arr_from_post_id( HelperFunctions::get_post_id_if_possible_from_url() );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>
	<?php echo esc_html_e( DROIP_APP_NAME, 'droip' ) . ' | ' . esc_html( get_the_title( HelperFunctions::get_post_id_if_possible_from_url() ) ); ?>
  </title>
  <?php wp_head(); ?>
</head>

<body class="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-tool">
		<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-editor-wrapper">
		<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-top-bar"></div>
		<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-left-bar"></div>
		<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-content-bar">
			<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-editor-bar">
			<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-builder">
				<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-canvas-preview">
				<?php $load_iframe_url = $post_url_arr['iframe_url']; ?>
				<iframe
					class="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-iframe-desktop <?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-iframe"
					name="<?php echo esc_html( DROIP_APP_IFRAME_ID ); ?>"
					data-url="<?php echo esc_url( $load_iframe_url ); ?>"
					id="<?php echo esc_html( DROIP_APP_IFRAME_ID ); ?>"></iframe>
				</div>
			</div>
			</div>
		</div>
		<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-right-bar"></div>
		<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-footer-bar"></div>
		<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-floating-elems">
			<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-alert-dialog-anchor-ele"></div>
		</div>
		</div>

		<div id="droip-builder-dnd-provider-dom"></div>

		<div id="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-loadingDiv"
		style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center;  overflow: hidden; z-index: 9;"
		>
			<div 
				class="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-loading-wrapper"
				style="width: 360px; height: 62px; display: flex ; justify-content: center; flex-direction: column; align-items: center; gap: 24px; overflow: hidden; margin-top: -5px;"
			>
				<svg xmlns="http://www.w3.org/2000/svg" width="75" height="31" fill="none">
					<g fill="var(--droip-body-color)" clipPath="url(#clip0_4164_13484)">
						<path d="M39.965 7.436c-4.828 0-8.333 3.339-8.333 7.94 0 4.619 3.506 7.97 8.333 7.97 2.353 0 4.485-.795 6.003-2.238 1.518-1.444 2.36-3.483 2.36-5.731 0-2.249-.839-4.277-2.36-5.717-1.521-1.44-3.648-2.224-6.003-2.224Zm3.123 11.263c-.782.79-1.889 1.226-3.118 1.226-2.552 0-4.334-1.87-4.334-4.548s1.782-4.548 4.334-4.548c1.23 0 2.336.435 3.118 1.227.814.824 1.245 1.973 1.245 3.32 0 1.349-.435 2.498-1.245 3.323ZM51.318 7.416h-.194V19.53a3.824 3.824 0 0 0 1.136 2.71 3.9 3.9 0 0 0 2.737 1.126h.194V11.251a3.825 3.825 0 0 0-1.137-2.71 3.9 3.9 0 0 0-2.736-1.125ZM30.61 8.748a7.784 7.784 0 0 0-2.328-.353 6.914 6.914 0 0 0-4.717 1.814l1.037-2.802h-.278a3.9 3.9 0 0 0-2.738 1.125 3.825 3.825 0 0 0-1.137 2.712V23.36h.194a3.9 3.9 0 0 0 2.739-1.126 3.825 3.825 0 0 0 1.136-2.712v-2.447c0-.927.166-1.847.491-2.717a3.457 3.457 0 0 1 1.276-1.627 3.509 3.509 0 0 1 3.183-.409l.183.066 1.31-3.515-.183-.066a2.59 2.59 0 0 0-.168-.06ZM66.374 7.471c-4.427 0-7.762 2.732-8.303 6.798a8.094 8.094 0 0 0-.062 1.08l-.024 14.413.227-.039a4.668 4.668 0 0 0 2.775-1.58 4.585 4.585 0 0 0 1.09-2.981v-1.803h4.674c4.698-.169 7.98-3.425 7.98-7.918.001-4.62-3.513-7.97-8.357-7.97Zm.182 12.732h-4.478v-.865l.027-3.94a4.737 4.737 0 0 1 .135-1.153c.51-2.007 2.202-3.305 4.312-3.305 2.62 0 4.453 1.905 4.453 4.631s-1.827 4.632-4.449 4.632ZM17.426 1.08a4.676 4.676 0 0 0-2.777 1.583 4.594 4.594 0 0 0-1.092 2.984V7.45H8.876C4.17 7.627.884 10.887.884 15.386c0 4.62 3.519 7.975 8.368 7.975 4.432 0 7.773-2.735 8.314-6.806a8.44 8.44 0 0 0 .06-1.08l.025-14.431-.225.036Zm-3.874 10.401-.026 3.94c.002.389-.043.776-.134 1.153-.511 2.01-2.206 3.309-4.32 3.309-2.625 0-4.462-1.907-4.462-4.637 0-2.73 1.833-4.636 4.463-4.636h4.484v.865l-.005.006ZM51.407 2.464h-.283v.192a3.73 3.73 0 0 0 1.108 2.65 3.802 3.802 0 0 0 2.676 1.098h.283v-.192a3.73 3.73 0 0 0-1.108-2.65 3.802 3.802 0 0 0-2.676-1.098Z"/>
					</g>
					<defs><clipPath id="clip0_4164_13484"><path fill="#fff" d="M.884.404H74.73v30H.884z"/></clipPath></defs>
				</svg>
				<div 
					class="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-loading" 
					style="height: 8px; border-radius: 10px; width: 100%; background: var(--droip-body-color-10); position: relative" 
				>
				   	<div 
							class="<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-loading-overlay"
							style="width: 2%; position: absolute; left: 0; top: 0; bottom: 0; background: linear-gradient(90deg, #8956FF 0%, #4662F2 100%); transition: width 3s ease; border-radius: 10px;"
						>

						</div>
				</div>
			</div>
		</div>
		<script>
		const loadingOverlay = document.querySelector('.<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-loading-overlay');
		let loadingDiv = document.getElementById("<?php echo esc_html( DROIP_CLASS_PREFIX ); ?>-loadingDiv");

		const loading = (parentage) => {
			loadingOverlay.style.width = parentage + '%';
		}
		window.loading = loading;
		window.removeLoading = () => {
			setTimeout(() => {
				loadingDiv?.remove();
			}, 2000);
		}

		const loadingInterval = setInterval(() => {
			let parentage = parseInt(loadingOverlay.style.width);
			let random = Math.floor(Math.random() * 5) + 1;

			if (parentage < 85) {
				loading(parentage + random);
			} else {
				clearInterval(loadingInterval);
			}
		}, 50);

		let mode = localStorage.getItem(`droipMode`) || 'dark';
		document.documentElement.setAttribute('data-mode', mode);
	

		if(mode === 'dark') {
			loadingDiv.style.backgroundColor = '#1d1d1d';
		} else {
			loadingDiv.style.backgroundColor = '#FFFFFF';
		}
		</script>
		</div>



		<svg xmlns="http://www.w3.org/2000/svg" version="1.1">
		<defs>
			<filter id="protanopia">
			<feColorMatrix in="SourceGraphic" type="matrix" values="0.567, 0.433, 0,     0, 0
					0.558, 0.442, 0,     0, 0
					0,     0.242, 0.758, 0, 0
					0,     0,     0,     1, 0" />
			</filter>
			<filter id="protanomaly">
			<feColorMatrix in="SourceGraphic" type="matrix" values="0.817, 0.183, 0,     0, 0
					0.333, 0.667, 0,     0, 0
					0,     0.125, 0.875, 0, 0
					0,     0,     0,     1, 0" />
			</filter>
			<filter id="deuteranopia">
			<feColorMatrix in="SourceGraphic" type="matrix" values="0.625, 0.375, 0,   0, 0
					0.7,   0.3,   0,   0, 0
					0,     0.3,   0.7, 0, 0
					0,     0,     0,   1, 0" />
			</filter>
			<filter id="deuteranomaly">
			<feColorMatrix in="SourceGraphic" type="matrix" values="0.8,   0.2,   0,     0, 0
					0.258, 0.742, 0,     0, 0
					0,     0.142, 0.858, 0, 0
					0,     0,     0,     1, 0" />
			</filter>
			<filter id="tritanopia">
			<feColorMatrix in="SourceGraphic" type="matrix" values="0.95, 0.05,  0,     0, 0
					0,    0.433, 0.567, 0, 0
					0,    0.475, 0.525, 0, 0
					0,    0,     0,     1, 0" />
			</filter>
			<filter id="tritanomaly">
			<feColorMatrix in="SourceGraphic" type="matrix" values="0.967, 0.033, 0,     0, 0
					0,     0.733, 0.267, 0, 0
					0,     0.183, 0.817, 0, 0
					0,     0,     0,     1, 0" />
			</filter>
			<filter id="achromatopsia">
			<feColorMatrix in="SourceGraphic" type="matrix" values="0.299, 0.587, 0.114, 0, 0
					0.299, 0.587, 0.114, 0, 0
					0.299, 0.587, 0.114, 0, 0
					0,     0,     0,     1, 0" />
			</filter>
			<filter id="achromatomaly">
			<feColorMatrix in="SourceGraphic" type="matrix" values="0.618, 0.320, 0.062, 0, 0
					0.163, 0.775, 0.062, 0, 0
					0.163, 0.320, 0.516, 0, 0
					0,     0,     0,     1, 0" />
			</filter>
			<filter id="blurred">
			<feGaussianBlur in="SourceGraphic" stdDeviation="1.5" />
			</filter>
		</defs>
		</svg>

		<?php wp_footer(); ?>
</body>

</html>
