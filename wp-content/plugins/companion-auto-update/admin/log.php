<?php

// Get selected filter type
$filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all';

?>

<ul class="subsubsub">
	<li><a <?php if( $filter == 'all' ) { echo "class='current'"; } ?> href='<?php echo cau_url( 'log&filter=all' ); ?>'><?php esc_html_e( 'View full changelog', 'companion-auto-update' ); ?></a></li> |
	<li><a <?php if( $filter == 'plugins' ) { echo "class='current'"; } ?> href='<?php echo cau_url( 'log&filter=plugins' ); ?>'><?php esc_html_e( 'Plugins', 'companion-auto-update' ); ?></a></li> |
	<li><a <?php if( $filter == 'themes' ) { echo "class='current'"; } ?> href='<?php echo cau_url( 'log&filter=themes' ); ?>'><?php esc_html_e( 'Themes', 'companion-auto-update' ); ?></a></li> |
	<li><a <?php if( $filter == 'translations' ) { echo "class='current'"; } ?> href='<?php echo cau_url( 'log&filter=translations' ); ?>'><?php esc_html_e( 'Translations', 'companion-auto-update' ); ?></a></li>
</ul>

<div class='cau_spacing'></div>

<?php 
cau_fetch_log( 'all', 'table' );