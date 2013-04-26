		<style>
    #wprp_form input[type="text"] { width:100% }
    #wprp_form .widefat tbody th.check-column { padding-bottom:9px }
    #wprp_form tr.matched { background:#CDF9CD }
    </style>
<?php

$updated = null;

if( isset( $_POST['wprp_hidden'] ) && $_POST['wprp_hidden']=='Y' ){
  //Form data sent
  
  $wprp_select = array();
  $wprp_pattern = array();
  $wprp_apikey = array();
  
  if( isset( $_POST['wprp_select'] ) )
    $wprp_select = $_POST['wprp_select'];
  if( isset( $_POST['wprp_apikey'] ) )
    $wprp_apikey = $_POST['wprp_apikey'];

  $final = array();
  for( $i=0 , $c=max( count( $wprp_pattern ) , count( $wprp_apikey ) ) ; $i<$c ; $i++ ){
    if( isset( $wprp_apikey[$i] ) && $wprp_apikey[$i] && !isset( $wprp_select[$i] ) )
      $final[$i] = preg_replace( '/[^0-9A-F]+/' , '' , $wprp_apikey[$i] );
  }
  
  $updated = update_option( 'wpr_api_keychain' , array_values( $final ) );

}  

?>
    <div class="wrap">
			<h2><?php _e( 'WP Remote Settings', 'wprp' ); ?></h2>
<?php
if( !is_null( $updated ) ){
  if( $updated ){
?>
      <div class="updated"><p><strong><?php _e( 'Options saved.' , 'wprp' ); ?></strong></p></div> 
<?php
  }else{
?>
      <div class="error"><p><strong><?php _e( 'Option save failed.' , 'wprp' ); ?></strong></p></div> 
<?php
  }
}

$wprp = get_option( 'wpr_api_keychain' , array() );
?>
			<form id="wprp_form" name="wprp_form" method="post">
        <input type="hidden" name="wprp_hidden" value="Y" />
        <table class="wp-list-table widefat fixed" cellspacing="0">
          <thead>
            <tr>
              <th scope="col" id="cb" class="manage-column column-cb check-column"></th>
              <th scope="col" class="manage-column">API Key</th>
            </tr>
          </thead>
          <tfoot>
            <tr>
              <th scope="col" class="manage-column column-cb check-column"></th>
              <th scope="col" class="manage-column">API Key</th>
            </tr>
          </tfoot>
          <tbody id="the-list">
<?php
foreach( $wprp as $k => $v ){
?>
            <tr<?php echo ( $matched ? ' class="matched"' : '' ); ?>>
              <th scope="row" class="check-column">
                <input type="checkbox" name="wprp_select[<?php echo $k; ?>]" id="wprp_<?php echo $k; ?>" value="<?php echo $k; ?>" />
              </th>
              <td>
                <label for="wprp_apikey[<?php echo $k; ?>]"><input type="text" name="wprp_apikey[<?php echo $k; ?>]" id="wprp_apikey_<?php echo $k; ?>" value="<?php echo ( isset( $v ) && is_string( $v ) ? $v : '' ); ?>" /></label>
              </td>
            </tr>
<?php
}
$k = count( $wprp );
?>
            <tr>
              <th scope="row">&nbsp;</th>
              <td>
                <label for="wprp_apikey[<?php echo $k; ?>]"><input type="text" name="wprp_apikey[<?php echo $k; ?>]" id="wprp_apikey_<?php echo $k; ?>" value="" /></label>
              </td>
            </tr>
          </tbody>
        </table>
				<p class="submit">
          <input type="submit" name="Submit" value="<?php _e( 'Update Options' , 'wprp' ); ?>" />
				</p>
			</form>
		</div>
	