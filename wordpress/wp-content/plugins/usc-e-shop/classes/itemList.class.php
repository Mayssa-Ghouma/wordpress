<?php
/**
 * Item List Class.
 *
 * @package Welcart
 */
class dataList {
	public $table;               /* テーブル名 */
	public $rows;                /* データ */
	public $action;              /* アクション */
	public $startRow;            /* 表示開始行番号 */
	public $maxRow;              /* 最大表示行数 */
	public $currentPage;         /* 現在のページNo */
	public $firstPage;           /* 最初のページNo */
	public $previousPage;        /* 前のページNo */
	public $nextPage;            /* 次のページNo */
	public $lastPage;            /* 最終ページNo */
	public $naviMaxButton;       /* ページネーション・ナビのボタンの数 */
	public $dataTableNavigation; /* ナヴィゲーションhtmlコード */
	public $dataTableNavigationBottom;
	public $arr_period;          /* 表示データ期間 */
	public $arr_search;          /* サーチ条件 */
	public $searchSql;           /* 簡易絞込みSQL */
	public $searchSwitchStatus;  /* サーチ表示スイッチ */
	public $columns;             /* データカラム */
	public $sortColumn;          /* 現在ソート中のフィールド */
	public $sortOldColumn;
	public $sortSwitchs;         /* 各フィールド毎の昇順降順スイッチ */
	public $userHeaderNames;     /* ユーザー指定のヘッダ名 */
	public $action_status, $action_message;
	public $pageLimit;           /* ページ制限 */
	public $exportMode;          /* IDのみ */
	public $data_cookie;
	public $totalRow;
	public $selectedRow;
	public $headers;

	/**
	 * Constructor.
	 *
	 * @param string $tableName Table name.
	 * @param array  $arr_column Columns.
	 */
	public function __construct( $tableName, $arr_column ) {
		$this->table   = $tableName;
		$this->columns = $arr_column;
		$this->rows    = array();

		$this->maxRow         = apply_filters( 'usces_filter_itemlist_maxrow', 30 );
		$this->naviMaxButton  = 11;
		$this->firstPage      = 1;
		$this->action_status  = 'none';
		$this->action_message = '';
		$this->pageLimit      = 'on';
		$this->exportMode     = false;
		$this->searchSql      = '';
		$this->getCookie();
		$this->SetDefaultParam();
		$this->SetParamByQuery();
		$this->validationSearchParameters();
		$this->setSearchSql();

		$this->arr_period = array(
			__( 'This month', 'usces' ),
			__( 'Last month', 'usces' ),
			__( 'The past one week', 'usces' ),
			__( 'Last 30 days', 'usces' ),
			__( 'Last 90days', 'usces' ),
			__( 'All', 'usces' ),
		);
	}

	/**
	 * Action.
	 *
	 * @return bool
	 */
	public function MakeTable() {

		$this->SetParam();
		$this->SetTotalRow();

		switch ( $this->action ) {

			case 'searchIn':
				check_admin_referer( 'item_master_list', 'wc_nonce' );
				$res = $this->GetRows();
				break;

			case 'searchOut':
				check_admin_referer( 'item_master_list', 'wc_nonce' );
				$res = $this->GetRows();
				break;

			case 'changeSort':
				check_admin_referer( 'item_master_list', 'wc_nonce' );
				$res = $this->GetRows();
				break;

			case 'changePage':
				check_admin_referer( 'item_master_list', 'wc_nonce' );
				$res = $this->GetRows();
				break;

			case 'collective_zaiko':
				if ( ! current_user_can( 'wel_others_products' ) ) {
					wp_die( esc_html__( 'You do not have sufficient privileges to perform this operation.', 'usces' ) );
				}
				check_admin_referer( 'item_master_list', 'wc_nonce' );
				usces_all_change_zaiko( $this );
				$res = $this->GetRows();
				break;

			case 'collective_display_status':
				if ( ! current_user_can( 'wel_others_products' ) ) {
					wp_die( esc_html__( 'You do not have sufficient privileges to perform this operation.', 'usces' ) );
				}
				check_admin_referer( 'item_master_list', 'wc_nonce' );
				usces_all_change_itemdisplay( $this );
				$res = $this->GetRows();
				break;

			case 'collective_delete':
				if ( ! current_user_can( 'wel_others_products' ) ) {
					wp_die( esc_html__( 'You do not have sufficient privileges to perform this operation.', 'usces' ) );
				}
				check_admin_referer( 'item_master_list', 'wc_nonce' );
				usces_all_delete_itemdata( $this );
				$res = $this->GetRows();
				break;

			case 'refresh':
				check_admin_referer( 'item_master_list', 'wc_nonce' );

			default:
				$res = $this->GetRows();
				break;
		}

		if ( ! $this->exportMode ) {
			$this->SetNavi();
			$this->SetHeaders();
		}

		if ( $res ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Default Parameters.
	 */
	public function SetDefaultParam() {
		$this->startRow           = isset( $this->data_cookie['startRow'] ) ? $this->data_cookie['startRow'] : 0;
		$this->totalRow           = isset( $this->data_cookie['totalRow'] ) ? $this->data_cookie['totalRow'] : 0;
		$this->selectedRow        = isset( $this->data_cookie['selectedRow'] ) ? $this->data_cookie['selectedRow'] : 0;
		$this->currentPage        = isset( $this->data_cookie['currentPage'] ) ? $this->data_cookie['currentPage'] : 1;
		$this->sortColumn         = ( isset( $this->data_cookie['sortColumn'] ) ) ? $this->data_cookie['sortColumn'] : apply_filters( 'usces_filter_item_class_sortColumn', 'post.ID' );
		$this->searchSwitchStatus = ( isset( $this->data_cookie['searchSwitchStatus'] ) ) ? $this->data_cookie['searchSwitchStatus'] : 'OFF';
		if ( isset( $this->data_cookie['arr_search'] ) ) {
			$this->arr_search = $this->data_cookie['arr_search'];
		} else {
			$arr_search       = array(
				'period' => '3',
				'column' => '',
				'word'   => array(),
			);
			$this->arr_search = apply_filters( 'usces_filter_item_class_arr_search', $arr_search, $this );
		}
		if ( isset( $this->data_cookie['sortSwitchs'] ) ) {
			$this->sortSwitchs = $this->data_cookie['sortSwitchs'];
		} else {
			foreach ( $this->columns as $value ) {
				$this->sortSwitchs[ $value ] = 'ASC';
			}
			$this->sortSwitchs[ $this->sortColumn ] = apply_filters( 'usces_filter_item_class_sortSwitchs', 'DESC' );
		}
	}

	/**
	 * Set Parameters.
	 */
	public function SetParam() {
		$this->startRow = ( $this->currentPage - 1 ) * $this->maxRow;
	}

	/**
	 * Set Parameters.
	 */
	public function SetParamByQuery() {
		if ( isset( $_REQUEST['changePage'] ) ) {

			$this->action             = 'changePage';
			$this->currentPage        = (int) $_REQUEST['changePage'];
			$this->sortColumn         = ( isset( $this->data_cookie['sortColumn'] ) ) ? $this->data_cookie['sortColumn'] : $this->sortColumn;
			$this->sortSwitchs        = ( isset( $this->data_cookie['sortSwitchs'] ) ) ? $this->data_cookie['sortSwitchs'] : $this->sortSwitchs;
			$this->userHeaderNames    = ( isset( $this->data_cookie['userHeaderNames'] ) ) ? $this->data_cookie['userHeaderNames'] : $this->userHeaderNames;
			$this->searchSwitchStatus = ( isset( $this->data_cookie['searchSwitchStatus'] ) ) ? $this->data_cookie['searchSwitchStatus'] : $this->searchSwitchStatus;
			$this->arr_search         = ( isset( $this->data_cookie['arr_search'] ) ) ? $this->data_cookie['arr_search'] : $this->arr_search;
			$this->totalRow           = ( isset( $this->data_cookie['totalRow'] ) ) ? $this->data_cookie['totalRow'] : $this->totalRow;
			$this->selectedRow        = ( isset( $this->data_cookie['selectedRow'] ) ) ? $this->data_cookie['selectedRow'] : $this->selectedRow;

		} elseif ( isset( $_REQUEST['changeSort'] ) ) {

			$this->action        = 'changeSort';
			$this->sortOldColumn = $this->sortColumn;
			$this->sortSwitchs   = ( isset( $this->data_cookie['sortSwitchs'] ) ) ? $this->data_cookie['sortSwitchs'] : $this->sortSwitchs;
			if ( 'default' === $_REQUEST['switch'] ) {
				// Restore default table.
				$this->sortColumn = 'post.ID';
				foreach ( $this->sortSwitchs as $key => $val ) {
					if ( $key === $this->sortColumn ) {
						$this->sortSwitchs[ $key ] = 'DESC';
						continue;
					}
					$this->sortSwitchs[ $key ] = 'ASC';
				}
			} else {
				// Validate sortColumn.
				if ( in_array( $_REQUEST['changeSort'], $this->columns ) ) {
					$this->sortColumn = $_REQUEST['changeSort'];
				} else {
					$this->sortColumn = 'ID'; // default.
				}
				// Validate sortSwitchs.
				if ( isset( $_REQUEST['switch'] ) && in_array( $_REQUEST['switch'], array( 'ASC', 'DESC' ) ) ) {
					$this->sortSwitchs[ $this->sortColumn ] = $_REQUEST['switch'];
				} else {
					$this->sortSwitchs[ $this->sortColumn ] = 'DESC'; // default.
				}
			}

			$this->currentPage        = ( isset( $this->data_cookie['currentPage'] ) ) ? $this->data_cookie['currentPage'] : $this->userHeaderNames;
			$this->userHeaderNames    = ( isset( $this->data_cookie['userHeaderNames'] ) ) ? $this->data_cookie['userHeaderNames'] : $this->userHeaderNames;
			$this->arr_search         = ( isset( $this->data_cookie['arr_search'] ) ) ? $this->data_cookie['arr_search'] : $this->arr_search;
			$this->searchSwitchStatus = ( isset( $this->data_cookie['searchSwitchStatus'] ) ) ? $this->data_cookie['searchSwitchStatus'] : $this->searchSwitchStatus;
			$this->totalRow           = ( isset( $this->data_cookie['totalRow'] ) ) ? $this->data_cookie['totalRow'] : $this->totalRow;
			$this->selectedRow        = ( isset( $this->data_cookie['selectedRow'] ) ) ? $this->data_cookie['selectedRow'] : $this->selectedRow;

		} elseif ( isset( $_REQUEST['searchIn'] ) ) {

			$this->action               = 'searchIn';
			$this->arr_search['column'] = isset( $_REQUEST['search']['column'] ) ? str_replace( ',', '', $_REQUEST['search']['column'] ) : '';
			$this->arr_search['word']   = isset( $_REQUEST['search']['word'] ) ? $_REQUEST['search']['word'] : '';
			$this->arr_search['period'] = isset( $_REQUEST['search']['period'] ) ? (int) $_REQUEST['search']['period'] : '';
			$this->searchSwitchStatus   = 'ON';

			$this->currentPage     = 1;
			$this->sortColumn      = ( isset( $this->data_cookie['sortColumn'] ) ) ? $this->data_cookie['sortColumn'] : $this->sortColumn;
			$this->sortSwitchs     = ( isset( $this->data_cookie['sortSwitchs'] ) ) ? $this->data_cookie['sortSwitchs'] : $this->sortSwitchs;
			$this->userHeaderNames = ( isset( $this->data_cookie['userHeaderNames'] ) ) ? $this->data_cookie['userHeaderNames'] : $this->userHeaderNames;
			$this->totalRow        = ( isset( $this->data_cookie['totalRow'] ) ) ? $this->data_cookie['totalRow'] : $this->totalRow;

		} elseif ( isset( $_REQUEST['searchOut'] ) ) {

			$this->action               = 'searchOut';
			$this->arr_search['column'] = '';
			$this->arr_search['word']   = '';
			$this->arr_search['period'] = ( isset( $this->data_cookie['arr_search']['period'] ) ) ? $this->data_cookie['arr_search']['period'] : $this->arr_search['period'];
			$this->searchSwitchStatus   = 'OFF';

			$this->currentPage     = 1;
			$this->sortColumn      = ( isset( $this->data_cookie['sortColumn'] ) ) ? $this->data_cookie['sortColumn'] : $this->sortColumn;
			$this->sortSwitchs     = ( isset( $this->data_cookie['sortSwitchs'] ) ) ? $this->data_cookie['sortSwitchs'] : $this->sortSwitchs;
			$this->userHeaderNames = ( isset( $this->data_cookie['userHeaderNames'] ) ) ? $this->data_cookie['userHeaderNames'] : $this->userHeaderNames;
			$this->totalRow        = ( isset( $this->data_cookie['totalRow'] ) ) ? $this->data_cookie['totalRow'] : $this->totalRow;

		} elseif ( isset( $_REQUEST['refresh'] ) ) {

			$this->action = 'refresh';

			$this->currentPage        = isset( $this->data_cookie['currentPage'] ) ? $this->data_cookie['currentPage'] : $this->currentPage;
			$this->sortColumn         = ( isset( $this->data_cookie['sortColumn'] ) ) ? $this->data_cookie['sortColumn'] : $this->sortColumn;
			$this->sortSwitchs        = ( isset( $this->data_cookie['sortSwitchs'] ) ) ? $this->data_cookie['sortSwitchs'] : $this->sortSwitchs;
			$this->userHeaderNames    = ( isset( $this->data_cookie['userHeaderNames'] ) ) ? $this->data_cookie['userHeaderNames'] : $this->userHeaderNames;
			$this->searchSwitchStatus = ( isset( $this->data_cookie['searchSwitchStatus'] ) ) ? $this->data_cookie['searchSwitchStatus'] : $this->searchSwitchStatus;
			$this->arr_search         = ( isset( $this->data_cookie['arr_search'] ) ) ? $this->data_cookie['arr_search'] : $this->arr_search;
			$this->totalRow           = ( isset( $this->data_cookie['totalRow'] ) ) ? $this->data_cookie['totalRow'] : $this->totalRow;
			$this->selectedRow        = ( isset( $this->data_cookie['selectedRow'] ) ) ? $this->data_cookie['selectedRow'] : $this->selectedRow;

		} elseif ( isset( $_REQUEST['collective'] ) ) {

			$this->action             = 'collective_' . str_replace( ',', '', $_POST['allchange']['column'] );
			$this->currentPage        = isset( $this->data_cookie['currentPage'] ) ? $this->data_cookie['currentPage'] : $this->currentPage;
			$this->sortColumn         = ( isset( $this->data_cookie['sortColumn'] ) ) ? $this->data_cookie['sortColumn'] : $this->sortColumn;
			$this->sortSwitchs        = ( isset( $this->data_cookie['sortSwitchs'] ) ) ? $this->data_cookie['sortSwitchs'] : $this->sortSwitchs;
			$this->userHeaderNames    = ( isset( $this->data_cookie['userHeaderNames'] ) ) ? $this->data_cookie['userHeaderNames'] : $this->userHeaderNames;
			$this->searchSwitchStatus = ( isset( $this->data_cookie['searchSwitchStatus'] ) ) ? $this->data_cookie['searchSwitchStatus'] : $this->searchSwitchStatus;
			$this->arr_search         = ( isset( $this->data_cookie['arr_search'] ) ) ? $this->data_cookie['arr_search'] : $this->arr_search;
			$this->totalRow           = ( isset( $this->data_cookie['totalRow'] ) ) ? $this->data_cookie['totalRow'] : $this->totalRow;
			$this->selectedRow        = ( isset( $this->data_cookie['selectedRow'] ) ) ? $this->data_cookie['selectedRow'] : $this->selectedRow;

		} else {
			$this->action = 'default';
		}
	}

	/**
	 * Validation Search Parameters.
	 */
	public function validationSearchParameters() {
		if ( 'none' != $this->arr_search['column'] && ! in_array( $this->arr_search['column'], $this->columns ) ) {
			if ( is_array( $this->arr_search['word'] ) && count( $this->arr_search['word'] ) && in_array( $key = key( $this->arr_search['word'] ), $this->columns ) ) {
				$this->arr_search['column'] = $key;
			} else {
				$this->arr_search['column'] = 'none';
			}
		}
	}

	/**
	 * Get Rows.
	 *
	 * @return array
	 */
	public function GetRows() {
		global $wpdb;
		$item_table = $wpdb->prefix . 'usces_item';
		$sku_table  = $wpdb->prefix . 'usces_skus';
		$where      = $this->GetWhere();
		$order      = ' ORDER BY ' . $this->sortColumn . ' ' . $this->sortSwitchs[ $this->sortColumn ];

		if ( $this->exportMode ) {

			$query =
				"SELECT post.ID, item.itemCode AS `item_code`, item.itemName AS `item_name` 
				FROM {$this->table} AS `post`
				LEFT JOIN {$item_table} AS `item` ON post.ID = item.post_id
				LEFT JOIN {$sku_table} AS `sku` ON post.ID = sku.post_id
				LEFT JOIN $wpdb->term_relationships AS `tr` ON post.ID = tr.object_id
				LEFT JOIN $wpdb->term_taxonomy AS `tt` ON tt.term_taxonomy_id = tr.term_taxonomy_id ";
		} else {

			$query =
				"SELECT post.ID, item.itemCode AS `item_code`, item.itemName AS `item_name`, item.itemPicts AS `pictids` 
				FROM {$this->table} AS `post`
				LEFT JOIN {$item_table} AS `item` ON post.ID = item.post_id
				LEFT JOIN {$sku_table} AS `sku` ON post.ID = sku.post_id
				LEFT JOIN $wpdb->term_relationships AS `tr` ON post.ID = tr.object_id
				LEFT JOIN $wpdb->term_taxonomy AS `tt` ON tt.term_taxonomy_id = tr.term_taxonomy_id ";
		}

		$query .= $where . $order; // . $limit;

		$rows              = $wpdb->get_results( $query, ARRAY_A );
		$this->selectedRow = ( $rows && is_array( $rows ) ) ? count( $rows ) : 0;
		if ( 'off' === $this->pageLimit ) {
			$this->rows = $rows;
		} else {
			$this->rows = array_slice( (array) $rows, $this->startRow, $this->maxRow );
		}

		return $this->rows;
	}

	/**
	 * Set Total Rows.
	 */
	public function SetTotalRow() {
		global $wpdb;
		$query          = "SELECT COUNT(ID) AS `ct` FROM {$this->table} WHERE post_mime_type = 'item' AND post_type = 'post' AND post_status <> 'trash'";
		$res            = $wpdb->get_var( $query );
		$this->totalRow = $res;
	}

	/**
	 * Where Condition.
	 *
	 * @return string
	 */
	public function GetWhere() {
		$sql_where = apply_filters( 'usces_filter_item_class_sql_where', '', $this->searchSql, $this );
		if ( '' != $this->searchSql ) {
			if ( 'display_status' == $this->arr_search['column'] ) {
				$str = "WHERE post.post_mime_type = 'item' AND post.post_type = 'post' AND " . $this->searchSql . $sql_where . ' GROUP BY post.ID';
			} else {
				$str = "WHERE post.post_mime_type = 'item' AND post.post_type = 'post' AND post.post_status <> 'trash' AND " . $this->searchSql . $sql_where . ' GROUP BY post.ID';
			}
		} else {
			$str = "WHERE post.post_mime_type = 'item' AND post.post_type = 'post' AND post.post_status <> 'trash'" . $sql_where . ' GROUP BY post.ID';
		}
		return $str;
	}

	/**
	 * Set query search sql condition base on arr_search for item list.
	 *
	 * @return dataList $searchSql string search sql condition.
	 */
	public function setSearchSql() {
		global $wpdb;
		switch ( $this->arr_search['column'] ) {
			case 'post_id':
				$column       = 'post.ID';
				$post_id_from = isset( $this->arr_search['word']['post_id_from'] ) ? $this->arr_search['word']['post_id_from'] : '';
				$post_id_to   = isset( $this->arr_search['word']['post_id_to'] ) ? $this->arr_search['word']['post_id_to'] : '';

				if ( ! empty( $post_id_from ) ) {
					$this->searchSql = $wpdb->prepare( $column . ' >= %d', (int) $post_id_from );
				}

				if ( ! empty( $post_id_from ) && ! empty( $post_id_to ) ) {
					$this->searchSql .= ' AND ';
				}

				if ( ! empty( $post_id_to ) ) {
					$this->searchSql .= $wpdb->prepare( $column . ' <= %d', (int) $post_id_to );
				}
				break;
			case 'item_code':
				$item_code       = isset( $this->arr_search['word']['item_code'] ) ? $this->arr_search['word']['item_code'] : '';
				$this->searchSql = 'item.itemCode LIKE ' . "'%" . esc_sql( $item_code ) . "%'";
				break;
			case 'item_name':
				$item_name       = isset( $this->arr_search['word']['item_name'] ) ? $this->arr_search['word']['item_name'] : '';
				$this->searchSql = 'item.itemName LIKE ' . "'%" . esc_sql( $item_name ) . "%'";
				break;
			case 'post_title':
				$post_title      = isset( $this->arr_search['word']['post_title'] ) ? $this->arr_search['word']['post_title'] : '';
				$this->searchSql = 'post.post_title LIKE ' . "'%" . esc_sql( $post_title ) . "%'";
				break;
			case 'zaiko_num':
				$this->searchSql = "sku.stocknum = '0'";
				break;
			case 'zaiko':
				$zaiko           = isset( $this->arr_search['word']['zaiko'] ) ? $this->arr_search['word']['zaiko'] : '';
				$this->searchSql = "sku.stock = '" . esc_sql( $zaiko ) . "'";
				break;
			case 'category':
				$category        = isset( $this->arr_search['word']['category'] ) ? $this->arr_search['word']['category'] : '';
				$this->searchSql = "tt.term_id = '" . esc_sql( $category ) . "'";
				break;
			case 'display_status':
				$display_status  = isset( $this->arr_search['word']['display_status'] ) ? $this->arr_search['word']['display_status'] : '';
				$this->searchSql = "post.post_status = '" . esc_sql( $display_status ) . "'";
				break;
		}
	}

	/**
	 * Set Navigation.
	 */
	public function SetNavi() {

		$this->lastPage     = ceil( $this->selectedRow / $this->maxRow );
		$this->previousPage = ( ( $this->currentPage - 1 ) == 0 ) ? 1 : $this->currentPage - 1;
		$this->nextPage     = ( ( $this->currentPage + 1 ) > $this->lastPage ) ? $this->lastPage : $this->currentPage + 1;
		$box                = array();

		for ( $i = 0; $i < $this->naviMaxButton; $i++ ) {
			if ( $i > $this->lastPage - 1 ) {
				break;
			}
			if ( $this->lastPage <= $this->naviMaxButton ) {
				$box[] = $i + 1;
			} else {
				if ( $this->currentPage <= 6 ) {
					$label = $i + 1;
					$box[] = $label;
				} else {
					$label = $i + 1 + $this->currentPage - 6;
					$box[] = $label;
					if ( $label == $this->lastPage ) {
						break;
					}
				}
			}
		}

		$html  = '';
		$html .= '<ul class="clearfix">';
		$html .= '<li class="rowsnum">' . $this->selectedRow . ' / ' . $this->totalRow . ' ' . __( 'cases', 'usces' ) . '</li>';
		if ( ( 1 == $this->currentPage ) || ( 0 == $this->selectedRow ) ) {
			$html .= '<li class="navigationStr">first&lt;&lt;</li>';
			$html .= '<li class="navigationStr">prev&lt;</li>';
		} else {
			$url       = admin_url( 'admin.php?page=usces_itemedit&changePage=1' );
			$nonce_url = wp_nonce_url( $url, 'item_master_list', 'wc_nonce' );
			$html     .= '<li class="navigationStr"><a href="' . $nonce_url . '">first&lt;&lt;</a></li>';
			$url       = admin_url( 'admin.php?page=usces_itemedit&changePage=' . $this->previousPage );
			$nonce_url = wp_nonce_url( $url, 'item_master_list', 'wc_nonce' );
			$html     .= '<li class="navigationStr"><a href="' . $nonce_url . '">prev&lt;</a></li>';
		}
		if ( $this->selectedRow > 0 ) {
			$box_count = count( $box );
			for ( $i = 0; $i < $box_count; $i++ ) {
				if ( $box[ $i ] == $this->currentPage ) {
					$html .= '<li class="navigationButtonSelected">' . $box[ $i ] . '</li>';
				} else {
					$url       = admin_url( 'admin.php?page=usces_itemedit&changePage=' . $box[ $i ] );
					$nonce_url = wp_nonce_url( $url, 'item_master_list', 'wc_nonce' );
					$html .= '<li class="navigationButton"><a href="' . $nonce_url . '">' . $box[ $i ] . '</a></li>';
				}
			}
		}
		if ( ( $this->currentPage == $this->lastPage ) || ( 0 == $this->selectedRow ) ) {
			$html .= '<li class="navigationStr">&gt;next</li>';
			$html .= '<li class="navigationStr">&gt;&gt;last</li>';
		} else {
			$url       = admin_url( 'admin.php?page=usces_itemedit&changePage=' . $this->nextPage );
			$nonce_url = wp_nonce_url( $url, 'item_master_list', 'wc_nonce' );
			$html     .= '<li class="navigationStr"><a href="' . $nonce_url . '">&gt;next</a></li>';
			$url       = admin_url( 'admin.php?page=usces_itemedit&changePage=' . $this->lastPage );
			$nonce_url = wp_nonce_url( $url, 'item_master_list', 'wc_nonce' );
			$html     .= '<li class="navigationStr"><a href="' . $nonce_url . '">&gt;&gt;last</a></li>';
		}
		$this->dataTableNavigationBottom = $html;
		if ( 'OFF' == $this->searchSwitchStatus ) {
			$html .= '<li class="navigationStr"><a style="cursor:pointer;" id="searchVisiLink">' . __( 'Show the Operation field', 'usces' ) . '</a>';
		} else {
			$html .= '<li class="navigationStr"><a style="cursor:pointer;" id="searchVisiLink">' . __( 'Hide the Operation field', 'usces' ) . '</a>';
		}
		$url       = admin_url( 'admin.php?page=usces_itemedit&refresh' );
		$nonce_url = wp_nonce_url( $url, 'item_master_list', 'wc_nonce' );
		$html     .= '<li class="refresh"><a href="' . $nonce_url . '">' . __( 'updates it to latest information', 'usces' ) . '</a></li>';
		$html     .= '</ul>';

		$this->dataTableNavigation = $html;
	}

	/**
	 * Get Cookie.
	 */
	public function getCookie() {
		$this->data_cookie = ( isset( $_COOKIE[ $this->table ] ) ) ? json_decode( str_replace( "\'", "'", str_replace( '\"', '"', $_COOKIE[ $this->table ] ) ), true ) : array();
	}

	/**
	 * Set Headers.
	 */
	public function SetHeaders() {
		foreach ( $this->columns as $key => $value ) {
			if ( $value == $this->sortColumn ) {
				if ( ( version_compare( USCES_MYSQL_VERSION, '5.0.0', '>=' ) && ( 'item_name' == $value || 'item_code' == $value ) ) || ( version_compare( USCES_MYSQL_VERSION, '5.0.0', '<' ) && 'post_title' == $value ) ) {
					if ( 'ASC' == $this->sortSwitchs[ $value ] ) {
						$str    = __( '[ASC]', 'usces' );
						$switch = 'DESC';
					} elseif ( 'DESC' == $this->sortSwitchs[ $value ] ) {
						$str    = __( '[DESC]', 'usces' );
						$switch = 'default';
					} else {
						$str    = '';
						$switch = 'ASC';
					}
					$url                     = admin_url( 'admin.php?page=usces_itemedit&changeSort=' . $value . '&switch=' . $switch );
					$nonce_url               = wp_nonce_url( $url, 'item_master_list', 'wc_nonce' );
					$this->headers[ $value ] = '<a href="' . $nonce_url . '"><span class="sortcolumn">' . $key . ' ' . $str . '</span></a>';
				} else {
					$this->headers[ $value ] = '<span class="sortcolumn">' . $key . '</span>';
				}
			} else {
				if ( ( version_compare( USCES_MYSQL_VERSION, '5.0.0', '>=' ) && ( 'item_name' == $value || 'item_code' == $value ) ) || ( version_compare( USCES_MYSQL_VERSION, '5.0.0', '<' ) && 'post_title' == $value ) ) {
					$switch                  = $this->sortSwitchs[ $value ];
					$url                     = admin_url( 'admin.php?page=usces_itemedit&changeSort=' . $value . '&switch=' . $switch );
					$nonce_url               = wp_nonce_url( $url, 'item_master_list', 'wc_nonce' );
					$this->headers[ $value ] = '<a href="' . $nonce_url . '"><span>' . $key . '</span></a>';
				} else {
					$this->headers[ $value ] = '<span class="sortcolumn">' . $key . '</span>';
				}
			}
		}
	}

	/**
	 * Get Search.
	 *
	 * @return string
	 */
	public function GetSearchs() {
		return $this->arr_search;
	}

	/**
	 * Get Headers.
	 *
	 * @return string
	 */
	public function GetListheaders() {
		return $this->headers;
	}

	/**
	 * Get Navigation.
	 *
	 * @return string
	 */
	public function GetDataTableNavigation() {
		return $this->dataTableNavigation;
	}

	/**
	 * Get Navigation Bottom.
	 *
	 * @return string
	 */
	public function GetDataTableNavigationBottom() {
		return $this->dataTableNavigationBottom;
	}

	/**
	 * Set Action Status and Action Message.
	 *
	 * @param string $status Action status.
	 * @param string $message Action message.
	 */
	public function set_action_status( $status, $message ) {
		$this->action_status  = $status;
		$this->action_message = $message;
	}

	/**
	 * Get Action Status.
	 *
	 * @return string
	 */
	public function get_action_status() {
		return $this->action_status;
	}

	/**
	 * Get Action Message.
	 *
	 * @return string
	 */
	public function get_action_message() {
		return $this->action_message;
	}
}
