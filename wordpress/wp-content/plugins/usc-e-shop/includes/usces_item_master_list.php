<?php
/**
 * Item administration list.
 *
 * Manage Item actions: search, batch processing, etc.
 *
 * @package Welcart
 */

require_once USCES_PLUGIN_DIR . '/classes/itemList.class.php';
global $wpdb, $post;
$wpdb->show_errors();

$tableName = $wpdb->posts;
if ( version_compare( USCES_MYSQL_VERSION, '5.0.0', '>=' ) ) {
	$arr_column = array(
		__( 'Post ID', 'usces' )        => 'post_id',
		__( 'item code', 'usces' )      => 'item_code',
		__( 'item name', 'usces' )      => 'item_name',
		__( 'SKU code', 'usces' )       => 'sku_key',
		apply_filters( 'usces_filter_sellingprice_label', __( 'selling price', 'usces' ), null, null ) => 'price',
		__( 'stock', 'usces' )          => 'zaiko_num',
		__( 'stock status', 'usces' )   => 'zaiko',
		__( 'Categories', 'usces' )     => 'category',
		__( 'display status', 'usces' ) => 'display_status',
	);
} else {
	$arr_column = array(
		__( 'Post ID', 'usces' )        => 'post_id',
		__( 'item code', 'usces' )      => 'item_code',
		__( 'page title', 'usces' )     => 'post_title',
		__( 'SKU code', 'usces' )       => 'sku_key',
		apply_filters( 'usces_filter_sellingprice_label', __( 'selling price', 'usces' ), null, null ) => 'price',
		__( 'stock', 'usces' )          => 'zaiko_num',
		__( 'stock status', 'usces' )   => 'zaiko',
		__( 'Categories', 'usces' )     => 'category',
		__( 'display status', 'usces' ) => 'display_status',
	);
}

$DT                        = new dataList( $tableName, $arr_column );
$res                       = $DT->MakeTable();
$arr_search                = $DT->GetSearchs();
$arr_header                = apply_filters( 'usces_filter_itemlist_header', $DT->GetListheaders() );
$dataTableNavigation       = $DT->GetDataTableNavigation();
$dataTableNavigationBottom = $DT->GetDataTableNavigationBottom();
$rows                      = $DT->rows;
$zaiko_status              = get_option( 'usces_zaiko_status' );
$usces_status              = isset( $_REQUEST['usces_status'] ) ? $_REQUEST['usces_status'] : $DT->get_action_status();
$usces_message             = isset( $_REQUEST['usces_message'] ) ? urldecode( $_REQUEST['usces_message'] ) : $DT->get_action_message();
$curent_url                = urlencode( esc_url( USCES_ADMIN_URL . '?' . $_SERVER['QUERY_STRING'] ) );
$usces_opt_item            = get_option( 'usces_opt_item' );

$usces_admin_path = '';
$admin_perse      = explode( '/', $_SERVER['REQUEST_URI'] );
$apct             = count( $admin_perse ) - 1;
for ( $ap = 0; $ap < $apct; $ap++ ) {
	$usces_admin_path .= $admin_perse[ $ap ] . '/';
}
?>
<script type="text/javascript">
jQuery(function($){

	var wc_nonce = $( "#wc_nonce" ).val();

	$("input[name='allcheck']").click(function () {
		if( $(this).prop("checked") ){
			$("input[name*='listcheck']").prop( "checked", true );
		}else{
			$("input[name*='listcheck']").prop( "checked", false );
		}
	});

	$("#searchselect").change(function () {
		operation.change_search_field();
	});

	$("#changeselect").change(function () {
		operation.change_collective_field();
	});

	$("#collective_change").click(function () {
		if( $("input[name*='listcheck']:checked").length == 0 ) {
			alert("<?php esc_html_e( 'no items are selected', 'usces' ); ?>");
			$("#itemlistaction").val('');
			return false;
		}
		var coll = $("#changeselect").val();
		var mes = '';
		if( coll == 'zaiko' ){
			mes = <?php echo sprintf( __( "'Stock status of items which you have checked will be changed into ' + %s + ' Stock status of each SKU will be all shown as ' + %s + ' Do you agree with this oparation?'", 'usces' ),
							'$("select\[name=\"change\[word\]\[zaiko\]\"\] option:selected").html()',
							'$("select\[name=\"change\[word\]\[zaiko\]\"\] option:selected").html()'); ?>;
		}else if( coll == 'display_status' ){
			mes = <?php echo sprintf( __( "'Are you sure of changing all the items you have checked in to ' + %s + ' ?'", 'usces' ),
							'$("select\[name=\"change\[word\]\[display_status\]\"\] option:selected").html()'); ?>;
		}else if(coll == 'delete'){
			mes = <?php _e( "'Are you sure of deleting all the items you have checked in bulk?'", 'usces' ); ?>;
		}
		if( mes != '' ) {
			if( !confirm(mes) ){
				$("#itemlistaction").val('');
				return false;
			}
		}
		<?php do_action( 'usces_action_item_list_collective_change_js' ); ?>
		$("#itemlistaction").val('collective');
		return true;
	});

	operation = {
		change_search_field :function (){

			var label = '';
			var html = '';
			var column = $("#searchselect").val();

			if( 'post_id' === column ) {
				label = '<?php esc_html_e( 'key words', 'usces' ); ?>';
				html  = '<input name="search[word][post_id_from]" type="number" min="1" value="<?php if ( isset( $arr_search['word']['post_id_from'] ) ) echo esc_attr( $arr_search['word']['post_id_from'] ); ?>" class="searchword" maxlength="50" />';
				html  += '<?php esc_html_e( ' - ', 'usces' ); ?><input name="search[word][post_id_to]" type="number" min="1" value="<?php if ( isset( $arr_search['word']['post_id_to'] ) ) echo esc_attr( $arr_search['word']['post_id_to'] ); ?>" class="searchword" maxlength="50" />';
			}else if( column == 'item_name' ) {
				label = '<?php esc_html_e( 'key words', 'usces' ); ?>';
				html = '<input name="search[word][item_name]" type="text" value="<?php if ( isset( $arr_search['word']['item_name'] ) ) echo esc_attr( $arr_search['word']['item_name'] ); ?>" class="searchword" maxlength="50" />';
			}else if( column == 'item_code' ) {
				label = '<?php esc_html_e( 'key words', 'usces' ); ?>';
				html = '<input name="search[word][item_code]" type="text" value="<?php if ( isset( $arr_search['word']['item_code'] ) ) echo esc_attr( $arr_search['word']['item_code'] ); ?>" class="searchword" maxlength="50" />';
			}else if( column == 'post_title' ) {
				label = '<?php esc_html_e( 'key words', 'usces' ); ?>';
				html = '<input name="search[word][post_title]" type="text" value="<?php if ( isset( $arr_search['word']['post_title'] ) ) echo esc_attr( $arr_search['word']['post_title'] ); ?>" class="searchword" maxlength="50" />';
			}else if( column == 'zaiko_num' ) {
				label = '';
				html = '';
			}else if( column == 'zaiko' ) {
				label = '';
				html = '<select name="search[word][zaiko]" class="searchselect">';
			<?php
			foreach ( $zaiko_status as $zkey => $zvalue ) {
				if ( isset( $arr_search['word']['zaiko'] ) && $zkey == $arr_search['word']['zaiko'] ) {
					$zselected = ' selected="selected"';
				} else {
					$zselected = '';
				}
				?>
				html += '<option value="<?php echo esc_attr( $zkey ); ?>"<?php echo esc_attr( $zselected ); ?>><?php echo esc_html( $zvalue ); ?></option>';
			<?php } ?>
				html += '</select>';
			}else if( column == 'category' ) {
				label = '';
				html = '<select name="search[word][category]" class="searchselect">';
			<?php
			$category_args = apply_filters( 'usces_filter_itemlist_searchselect_category_args', array( 'child_of' => USCES_ITEM_CAT_PARENT_ID, 'hide_empty' => 0 ) );
			$categories    = get_categories( $category_args );
			foreach ( $categories as $ckey => $cvalue ) {
				if ( isset( $arr_search['word']['category'] ) && $cvalue->term_id == $arr_search['word']['category'] ) {
					$cselected = ' selected="selected"';
				} else {
					$cselected = '';
				}
				?>
				html += '<option value="<?php echo esc_attr( $cvalue->term_id ); ?>"<?php echo esc_attr( $cselected ); ?>><?php echo esc_html( $cvalue->name ); ?></option>';
			<?php } ?>
				html += '</select>';
			}else if( column == 'display_status' ) {
				label = '';
				html = '<select name="search[word][display_status]" class="searchselect">';
				html += '<option value="publish"<?php if ( isset( $arr_search['word']['display_status'] ) && 'publish' == $arr_search['word']['display_status'] ) echo ' selected="selected"'; ?>><?php esc_html_e( 'Published', 'usces' ); ?></option>';
				html += '<option value="future"<?php if ( isset( $arr_search['word']['display_status'] ) && 'future' == $arr_search['word']['display_status'] ) echo ' selected="selected"'; ?>><?php esc_html_e( 'Scheduled', 'usces' ); ?></option>';
				html += '<option value="draft"<?php if ( isset( $arr_search['word']['display_status'] ) && 'draft' == $arr_search['word']['display_status'] ) echo ' selected="selected"'; ?>><?php esc_html_e( 'Draft', 'usces' ); ?></option>';
				html += '<option value="pending"<?php if ( isset( $arr_search['word']['display_status'] ) && 'pending' == $arr_search['word']['display_status'] ) echo ' selected="selected"'; ?>><?php esc_html_e( 'Pending Review', 'usces' ); ?></option>';
				html += '<option value="private"<?php if ( isset( $arr_search['word']['display_status'] ) && 'private' == $arr_search['word']['display_status'] ) echo ' selected="selected"'; ?>><?php esc_html_e( 'Closed', 'usces' ); ?></option>';
				html += '<option value="trash"<?php if ( isset( $arr_search['word']['display_status'] ) && 'trash' == $arr_search['word']['display_status'] ) echo ' selected="selected"'; ?>><?php esc_html_e( 'Trash', 'usces' ); ?></option>';
				html += '</select>';
			}

			$("#searchlabel").html( label );
			$("#searchfield").html( html );

		},

		change_collective_field :function (){

			var label = '';
			var html = '';
			var column = $("#changeselect").val();

			if( column == 'zaiko' ) {
				label = '';
				html = '<select name="change[word][zaiko]" class="searchselect">';
		<?php foreach ( $zaiko_status as $zkey => $zvalue ) { ?>
				html += '<option value="<?php echo esc_html( $zkey ); ?>"><?php echo esc_html( $zvalue ); ?></option>';
		<?php } ?>
				html += '</select>';
			}else if( column == 'display_status' ) {
				label = '';
				html = '<select name="change[word][display_status]" class="searchselect">';
				html += '<option value="publish"><?php esc_html_e( 'Published', 'usces' ); ?></option>';
				html += '<option value="draft"><?php esc_html_e( 'Draft', 'usces' ); ?></option>';
				html += '<option value="private"><?php esc_html_e( 'Closed', 'usces' ); ?></option>';
				html += '</select>';
			}else if( column == 'delete' ) {
				label = '';
				html = '';
			}

			$("#changelabel").html( label );
			$("#changefield").html( html );

		}
	};

	// Dialog generation
	$("#upload_dialog").dialog({
		bgiframe: true,
		autoOpen: false,
		height: 470,
		width: 520,
		modal: true,
		buttons: {
			'<?php esc_html_e( 'Cancel', 'usces' ); ?>': function() {
				$(this).dialog('close');
			}
		},
		close: function() {
			$("#usces_upcsv").val('');
		}
	});
	$('#up_itemlist').click(function() {
		$('#upload_dialog').dialog( 'option' , 'title' , '<?php esc_html_e( 'Collective registration item', 'usces' ); ?>' );
		$('#dialogExp').html( '<?php _e( 'Upload prescribed CSV file and perform the collective registration of the article.<br />Please choose a file, and push the registration start.', 'usces' ); ?>' );
		$('#upload_dialog').dialog( 'open' );
	});
	$("#dlItemListDialog").dialog({
		bgiframe: true,
		autoOpen: false,
		height: 360,
		width: 520,
		resizable: true,
		modal: true,
		buttons: {
			'<?php esc_html_e( 'close', 'usces' ); ?>': function() {
				$(this).dialog('close');
			}
		},
		close: function() {
		}
	});
	$('#dl_item').click(function() {
		var args = '&search[column]=' + $( ':input[name="search[column]"]' ).val();
		const searchField = $( '#searchselect' ).val();
		if ( 'post_id' == searchField ) {
			args +=
				'&search[word][post_id_from]=' +
				$( ':input[name="search[word][post_id_from]"]' ).val();
			args +=
				'&search[word][post_id_to]=' +
				$( ':input[name="search[word][post_id_to]"]' ).val();
		} else {
			args +=
				'&search[word][' +
				searchField +
				']=' +
				$( ':input[name="search[word][' + searchField + ']"]' ).val();
		}
		args += '&searchSwitchStatus=' + $( ':input[name="searchSwitchStatus"]' ).val();
		args += '&ftype=csv' + '&mode=' + $( ':input[name="download_mode"]:checked' ).val();
		if($('#chk_header').prop('checked')) {
			args += '&chk_header=on';
		}
		location.href = "<?php echo esc_url( USCES_ADMIN_URL ); ?>?page=usces_itemedit&action=dlitemlist&wc_nonce=" + wc_nonce +"&noheader=true"+args<?php echo apply_filters( 'usces_filter_item_list_dlargs', '' ); ?>;
	});
	$('#dl_itemlist').click(function() {
		$('#dlItemListDialog').dialog('open');
	});

	<?php echo apply_filters( 'usces_filter_item_list_page_js', '' ); ?>

});

function deleteconfirm(item_id){
	if(confirm(<?php _e( "'Are you sure of deleting the item, item code ' + item_id + ' ?'", 'usces' ); ?>)){
		return true;
	}else{
		return false;
	}
};

jQuery(document).ready(function($){
	$("table#mainDataTable tr:even").addClass("rowSelection_even");
	$("table#mainDataTable tr").hover(function() {
		$(this).addClass("rowSelection_hilight");
	},
	function() {
		$(this).removeClass("rowSelection_hilight");
	});
	(function setCookie() {
		<?php
		$data_cookie                       = array();
		$data_cookie['startRow']           = $DT->startRow;        /* 表示開始行番号 */
		$data_cookie['sortColumn']         = $DT->sortColumn;      /* 現在ソート中のフィールド */
		$data_cookie['totalRow']           = $DT->totalRow;        /* 全行数 */
		$data_cookie['selectedRow']        = $DT->selectedRow;     /* 絞り込まれた行数 */
		$data_cookie['currentPage']        = $DT->currentPage;     /* 現在のページNo */
		$data_cookie['previousPage']       = $DT->previousPage;    /* 前のページNo */
		$data_cookie['nextPage']           = $DT->nextPage;        /* 次のページNo */
		$data_cookie['lastPage']           = $DT->lastPage;        /* 最終ページNo */
		$data_cookie['userHeaderNames']    = $DT->userHeaderNames; /* 全てのフィールド */
		$data_cookie['sortSwitchs']        = $DT->sortSwitchs;     /* 各フィールド毎の昇順降順スイッチ */
		$data_cookie['arr_search']         = $DT->arr_search;
		$data_cookie['searchSwitchStatus'] = $DT->searchSwitchStatus;
		?>
		$.cookie('<?php echo "{$DT->table}"?>', '<?php echo str_replace( "'", "\'", json_encode( $data_cookie ) ); ?>', {  path: "<?php echo esc_attr( $usces_admin_path ); ?>", domain: ""});
	})();
	$(document).on( "click", "#searchVisiLink", function() {
		if( $("#searchBox").css("display") == "block" ) {
			$("#searchBox").css("display", "none");
			$("#searchVisiLink").html('<?php esc_html_e( 'Show the Operation field', 'usces' ); ?>');
		} else {
			$("#searchBox").css("display", "block");
			$("#searchVisiLink").html('<?php esc_html_e( 'Hide the Operation field', 'usces' ); ?>');
		}
	});
<?php if ( 'ON' === $DT->searchSwitchStatus ) { ?>
$("#searchBox").css("display", "block");
<?php } ?>

operation.change_search_field();

});
</script>
<div class="wrap">
<div class="usces_admin">
<form action="<?php echo esc_url( USCES_ADMIN_URL . '?page=usces_itemedit' ); ?>" method="post" name="tablesearch">
<h1>Welcart Shop <?php esc_html_e( 'Item list', 'usces' ); ?></h1>
<p class="version_info">Version <?php echo esc_html( USCES_VERSION ); ?></p>
<?php usces_admin_action_status( $usces_status, $usces_message ); ?>
<?php wp_nonce_field( 'item_master_list', 'wc_nonce' ); ?>

<div id="datatable">
<div id="tablenavi"><?php wel_esc_script_e( $dataTableNavigation ); ?></div>

<div id="tablesearch">
<div id="searchBox">
	<table id="search_table">
	<tr>
		<td><?php esc_html_e( 'search fields', 'usces' ); ?></td>
		<td><select name="search[column]" class="searchselect" id="searchselect">
			<option value="none"> </option>
<?php
foreach ( $arr_column as $key => $value ) :
	if ( $value === $arr_search['column'] ) {
		$selected = ' selected="selected"';
	} else {
		$selected = '';
	}
	if ( 'sku_key' !== $value && 'price' !== $value ) :
		if ( 'zaiko_num' === $value ) {
			?>
			<option value="<?php echo esc_attr( $value ); ?>"<?php echo esc_attr( $selected ); ?>><?php esc_html_e( 'items without stock', 'usces' ); ?></option>
			<?php
		} elseif ( version_compare( USCES_MYSQL_VERSION, '5.0.0', '<' ) && 'item_code' === $value ) {
			continue;
		} else {
			?>
			<option value="<?php echo esc_attr( $value ); ?>"<?php echo esc_attr( $selected ); ?>><?php echo esc_html( $key ); ?></option>
			<?php
		}
	endif;
endforeach;
?>
		</select></td>
		<td id="searchlabel"></td>
		<td id="searchfield"></td>
		<td><input name="searchIn" type="submit" class="searchbutton button" value="<?php esc_attr_e( 'Search', 'usces' ); ?>" />
		<input name="searchOut" type="submit" class="searchbutton button" value="<?php esc_attr_e( 'Cancellation', 'usces' ); ?>" />
		</td>
	</tr>
	</table>
	<table id="change_table">
	<tr>
		<td><?php esc_html_e( 'Oparation in bulk', 'usces' ); ?></td>
		<td><select name="allchange[column]" class="searchselect" id="changeselect">
			<option value="none"> </option>
			<option value="zaiko"><?php esc_html_e( 'Changes in stock status', 'usces' ); ?></option>
			<option value="display_status"><?php esc_html_e( 'Edit the display status', 'usces' ); ?></option>
			<option value="delete"><?php esc_html_e( 'Delete in bulk', 'usces' ); ?></option>
			<?php echo apply_filters( 'usces_filter_item_list_allchange_column', '' ); ?>
		</select></td>
		<td id="changelabel"></td>
		<td id="changefield"></td>
		<td><input name="collective" type="submit" class="searchbutton button"" id="collective_change" value="<?php esc_attr_e( 'start', 'usces' ); ?>" />
		<!--<a href="#" id="up_dlg"><?php esc_html_e( 'Collective registration item', 'usces' ); ?></a>-->
		</td>
	</tr>
	</table>
	<table id="dl_list_table">
	<tr>
		<?php do_action( 'usces_action_dl_item_list_table' ); ?>
		<?php echo apply_filters( 'usces_filter_dl_item_list_table', '' ); ?>
		<td><input type="button" id="up_itemlist" class="searchbutton button" value="<?php esc_attr_e( 'Collective registration item', 'usces' ); ?>" /></td>
		<td><input type="button" id="dl_itemlist" class="searchbutton button" value="<?php esc_attr_e( 'Download Item List', 'usces' ); ?>" /></td>
	</tr>
	</table>
<div<?php if ( has_action( 'usces_action_item_list_searchbox_bottom' ) ) echo ' class="searchbox_bottom"'; ?>>
<?php do_action( 'usces_action_item_list_searchbox_bottom' ); ?>
</div>
<input name="action" id="itemlistaction" type="hidden" />
</div><!-- #searchBox -->
</div><!-- #tablesearch -->

<table id="mainDataTable" cellspacing="1">
	<tr>
		<th scope="col"><input name="allcheck" type="checkbox" value="" /></th>
		<th scope="col">&nbsp;</th>
<?php
foreach ( (array) $arr_header as $key => $value ) :
	if ( 'post_id' === $key ) {
		continue;
	}
	if ( 'item_code' === $key ) :
		?>
		<th scope="col"><?php wel_esc_script_e( $value ); ?>&nbsp;/&nbsp;
	<?php elseif ( 'item_name' === $key || 'post_title' === $key ) : ?>
		<?php wel_esc_script_e( $value ); ?></th>
	<?php elseif ( 'price' === $key ) : ?>
		<th scope="col"><?php wel_esc_script_e( $value ); ?>(<?php usces_crcode(); ?>)</th>
	<?php else : ?>
		<th scope="col"><?php wel_esc_script_e( $value ); ?></th>
		<?php
	endif;
endforeach;
?>
	</tr>
<?php
foreach ( (array) $rows as $array ) :
	$post_id              = $array['ID'];
	$product              = wel_get_product( $post_id );
	$post                 = $product['_pst'];
	$array['sku']         = $product['_sku'];
	$array['category']    = '';
	$array['post_status'] = $post->post_status;
	$array['item_code']   = $product['itemCode'];
	$array['item_name']   = $product['itemName'];
	$pctid                = wel_get_main_pict_id_by_code( $product['itemCode'] );

	$array      = apply_filters( 'usces_filter_itemlist_body', $array );
	$item_image = wp_get_attachment_image( $pctid, array( 50, 50 ), true );
	?>
	<tr>
	<td width="20px" align="center"><input name="listcheck[]" type="checkbox" value="<?php echo (int) $post_id; ?>" /></td>
	<td width="50px">
		<a href="<?php echo esc_url( USCES_ADMIN_URL . '?page=usces_itemedit&action=edit&post=' . $post_id . '&usces_referer=' . $curent_url ); ?>" title="<?php echo esc_attr( $array['item_name'] ); ?>">
			<?php wel_esc_script_e( apply_filters( 'usces_filter_item_master_thumbnail', $item_image, $pctid, $array ) ); ?>
		</a>
	</td>
	<?php
	foreach ( (array) $array as $key => $value ) :
		if ( 'item_code' === $key ) :
			?>
			<td class="item">
			<?php if ( '' != $value ) : ?>
				<strong><?php echo esc_html( $value ); ?></strong>
			<?php else : ?>
				&nbsp;
			<?php endif; ?>
			<br />
			<?php
		elseif ( 'item_name' === $key ) :
			if ( '' != $value ) :
				?>
				<strong><?php echo esc_html( $value ); ?></strong>
			<?php else : ?>
				&nbsp;
			<?php endif; ?>
			<ul class="item_list_navi">
			<?php
			if ( current_user_can( 'edit_post', $post_id ) ) {
				?>
				<li><a href="<?php echo esc_url( USCES_ADMIN_URL . '?page=usces_itemedit&action=edit&post=' . $post_id . '&usces_referer=' . $curent_url ); ?>"><?php esc_html_e( 'edit', 'usces' ); ?></a></li>
				<li>&nbsp;|&nbsp;</li>
				<?php
			}

			if ( current_user_can( 'delete_post', $post_id ) ) {
				if ( 'trash' === $post->post_status ) {
					$actions['untrash'] = "<li><a title='" . esc_attr( __( 'Restore this post from the Trash' ) ) . "' href='" . wp_nonce_url( "post.php?action=untrash&amp;post=$post_id", 'untrash-post_' . $post_id ) . "'>" . __( 'Restore' ) . '</a></li><li>&nbsp;|&nbsp;</li>';
					wel_esc_script_e( $actions['untrash'] );
				} elseif ( EMPTY_TRASH_DAYS ) {
					$actions['trash'] = "<li><a class='submitdelete' title='" . esc_attr( __( 'Move this post to the Trash' ) ) . "' href='" . get_delete_post_link( $post_id ) . "'>" . __( 'Trash', 'usces' ) . '</a></li>';
					wel_esc_script_e( $actions['trash'] );
				}
				if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
					$actions['delete'] = "<li><a class='submitdelete' title='" . esc_attr( __( 'Delete this post permanently' ) ) . "' href='" . wp_nonce_url( "post.php?action=delete&amp;post=$post_id", 'delete-post_' . $post_id ) . "'>" . __( 'Delete Permanently' ) . '</a></li>';
					wel_esc_script_e( $actions['delete'] );
				}
				echo '<li>&nbsp;|&nbsp;</li>';
			}
			?>
				<li><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php esc_html_e( 'Visible' ); ?></a></li>
			</ul>
			</td>
			<?php
		elseif ( 'sku' === $key ) :
			if ( is_array( $value ) ) {
				$no_sku = ( 0 === count( $value ) ) ? '&nbsp;' : '';
			} else {
				$no_sku = '&nbsp;';
			}
			ob_start();
			?>
			<td class="sku">
			<?php
			$i = 0;
			foreach ( (array) $value as $skey => $sv ) {
				$bgc = ( $i % 2 === 1 ) ? ' bgc1' : ' bgc2';
				$i++;
				?>
				<div class="skuline<?php echo esc_attr( $bgc ); ?>"><?php echo esc_html( $sv['code'] ); ?>
				<?php
				if ( ! empty( $sv['name'] ) ) {
					echo '(' . esc_html( $sv['name'] ) . ')';
				}
				?>
				</div>
				<?php
			}
			echo esc_attr( $no_sku );
			?>
			</td>
			<td class="price">
			<?php
			$i = 0;
			foreach ( (array) $value as $skey => $sv ) {
				$bgc = ( $i % 2 === 1 ) ? ' bgc1' : ' bgc2';
				$i++;
				?>
				<div class="priceline<?php echo esc_attr( $bgc ); ?>"><?php usces_crform( $sv['price'], true, false ); ?></div>
				<?php
			}
			echo esc_attr( $no_sku );
			?>
			</td>
			<td class="zaikonum">
			<?php
			$i = 0;
			foreach ( (array) $value as $skey => $sv ) {
				$stocknum = ( ! WCUtils::is_blank( $sv['stocknum'] ) ) ? esc_html( $sv['stocknum'] ) : '&nbsp';
				$bgc      = ( $i % 2 === 1 ) ? ' bgc1' : ' bgc2';
				$i++;
				?>
				<div class="priceline<?php echo esc_attr( $bgc ); ?>"><?php echo $stocknum; ?></div>
				<?php
			}
			echo esc_attr( $no_sku );
			?>
			</td>
			<td class="zaiko">
			<?php
			$i = 0;
			foreach ( (array) $value as $skey => $sv ) {
				$zaikokey = $sv['stock'];
				$bgc      = ( $i % 2 === 1 ) ? ' bgc1' : ' bgc2';
				$i++;
				?>
				<div class="zaikoline<?php echo esc_attr( $bgc ); ?>"><?php echo esc_html( $zaiko_status[ $zaikokey ] ); ?></div>
				<?php
			}
			echo esc_attr( $no_sku );
			?>
			</td>
			<?php
			$skuargs  = compact( 'no_sku', 'key', 'value', 'i', 'zaiko_status', 'post_id' );
			$skufield = apply_filters( 'usces_filter_itemlist_skufield', ob_get_contents(), $skuargs );
			ob_end_clean();
			echo $skufield; // no escape due to filter.

		elseif ( 'category' === $key ) :
			?>
			<td class="listcat">
			<?php
			$cat_ids = wp_get_post_categories( $post_id );
			if ( ! empty( $cat_ids ) ) {
				$out = array();
				foreach ( $cat_ids as $id ) {
					$out[] = get_cat_name( $id );
				}
				echo esc_html( join( ', ', $out ) );
			} else {
				esc_html_e( 'Uncategorized' );
			}
			?>
			</td>
		<?php elseif ( 'post_status' === $key ) : ?>
			<td>
			<?php
			switch ( $value ) {
				case 'publish':
					esc_html_e( 'Published', 'usces' );
					break;
				case 'future':
					esc_html_e( 'Scheduled', 'usces' );
					break;
				case 'draft':
					esc_html_e( 'Draft', 'usces' );
					break;
				case 'pending':
					esc_html_e( 'Pending Review', 'usces' );
					break;
				case 'trash':
					esc_html_e( 'Trash', 'usces' );
					break;
				default:
					esc_html_e( 'Closed', 'usces' );
			}
			if ( ! empty( $post->post_password ) ) {
				echo '<br />' . esc_html__( 'Password protected' );
			}
			?>
			</td>
		<?php endif; ?>
		<?php do_action( 'usces_action_itemlist_detail', $key, $value ); ?>
	<?php endforeach; ?>
	</tr>
<?php endforeach; ?>
</table><!-- #mainDataTable -->
<div id="tablenavi_bottom"><?php wel_esc_script_e( $dataTableNavigationBottom ); ?></div>
</div><!-- #datatable -->
<?php echo apply_filters( 'usces_filter_item_list_footer', '' ); ?>
</form>

<div id="upload_dialog" class="upload_dialog">
<?php if ( ! current_user_can( 'wel_others_products' ) ) : ?>
	<p><?php esc_html_e( 'You have no permission to upload files.', 'usces' ); ?></p>
<?php else : ?>
	<p id="dialogExp"></p>
	<form action="<?php echo esc_url( USCES_ADMIN_URL . '?page=usces_itemedit' ); ?>" method="post" enctype="multipart/form-data" name="upform" id="upform" onsubmit="if( jQuery('#usces_upcsv').val() == '' ){alert('<?php esc_attr_e( 'File is not selected.', 'usces' ); ?>'); return false; }else{jQuery('#dialogExp').html('<span><?php esc_attr_e( 'Uploading now', 'usces' ); ?></span>');}">
	<fieldset>
		<input type="radio" name="upload_mode" id="upload_mode_all" value="all" checked="checked" /><label for="upload_mode_all"><?php esc_html_e( 'All columns', 'usces' ); ?></label>
		<input type="radio" name="upload_mode" id="upload_mode_stock" value="stock" /><label for="upload_mode_stock"><?php esc_html_e( 'Stock columns', 'usces' ); ?></label>
		<input type="radio" name="upload_mode" id="upload_mode_sku" value="sku" /><label for="upload_mode_sku"><?php esc_html_e( 'SKU columns', 'usces' ); ?></label>
		<input type="radio" name="upload_mode" id="upload_mode_meta" value="meta" /><label for="upload_mode_meta"><?php esc_html_e( 'Custom Field columns', 'usces' ); ?></label>
		<?php echo apply_filters( 'usces_filter_item_list_upload_mode', '' ); ?>
	</fieldset>
	<fieldset id="usces_upcsv_button">
		<input name="usces_upcsv" type="file" id="usces_upcsv" style="width:100%" />
	</fieldset>
		<?php echo apply_filters( 'usces_filter_item_list_upload_dialog', '' ); ?>
	<input name="itemcsv" type="submit" id="upcsv" class="button" value="<?php esc_attr_e( 'Registration start', 'usces' ); ?>" />
	<input name="checkcsv" type="submit" id="checkcsv" class="button" value="<?php esc_attr_e( 'Data check', 'usces' ); ?>" />
	<input name="action" type="hidden" value="upload_register" />
	</form>
	<p><?php esc_html_e( 'Indication is updated after upload completion.', 'usces' ); ?></p>
<?php endif; ?>
</div><!-- #upload_dialog -->
<div id="dlItemListDialog" title="<?php esc_attr_e( 'Download Item List', 'usces' ); ?>">
	<p><?php esc_html_e( 'Choose the file format, and push the download.', 'usces' ); ?></p>
	<fieldset>
		<input type="radio" name="download_mode" id="download_mode_all" value="all" checked="checked" /><label for="download_mode_all"><?php esc_html_e( 'All columns', 'usces' ); ?></label>
		<input type="radio" name="download_mode" id="download_mode_stock" value="stock" /><label for="download_mode_stock"><?php esc_html_e( 'Stock columns', 'usces' ); ?></label>
		<input type="radio" name="download_mode" id="download_mode_sku" value="sku" /><label for="download_mode_sku"><?php esc_html_e( 'SKU columns', 'usces' ); ?></label>
		<input type="radio" name="download_mode" id="download_mode_custom" value="meta" /><label for="download_mode_custom"><?php esc_html_e( 'Custom Field columns', 'usces' ); ?></label>
<?php echo apply_filters( 'usces_filter_item_list_download_mode', '' ); ?>
	</fieldset>
	<fieldset>
		<?php $chk_header = ( isset( $usces_opt_item['chk_header'] ) && 1 === (int) $usces_opt_item['chk_header'] ) ? ' checked="checked"' : ''; ?>
		<label for="chk_header"><input type="checkbox" class="check_item" id="chk_header" value="1"<?php echo esc_attr( $chk_header ); ?> /><?php esc_html_e( 'To add a subject title at the first line', 'usces' ); ?></label>
<?php echo apply_filters( 'usces_filter_item_list_download_dialog', '' ); ?>
	</fieldset>
	<div><input type="button" class="button" id="dl_item" value="<?php esc_attr_e( 'Download', 'usces' ); ?>" /></div>
<?php do_action( 'usces_action_item_list_download_dialog' ); ?>
</div><!-- #dlItemListDialog -->
<?php do_action( 'usces_action_item_list_footer' ); ?>

</div><!-- .usces_admin -->
</div><!-- .wrap -->
[memory peak usage] <?php echo esc_attr( round( memory_get_peak_usage() / 1048576, 1 ) ); ?>Mb
