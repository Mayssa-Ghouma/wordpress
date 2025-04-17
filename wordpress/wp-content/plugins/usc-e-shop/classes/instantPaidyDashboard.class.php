<?php
/**
 * The instant Paidy Dashboard class.
 *
 * @package  Welcart
 * @author   Collne Inc.
 */

/**
 * Class InstantPaidyDashboard
 */
class InstantPaidyDashboard {
	/**
	 * The Welcart api server url.
	 *
	 * @var string BASE_API_URL
	 */
	const BASE_API_URL = 'https://api.welcart.com/';

	/**
	 * The paidy waiting status.
	 *
	 * @var string WAITING_STATUS
	 */
	const WAITING_STATUS = 1;

	/**
	 * The paidy approved status.
	 *
	 * @var string APPROVED_STATUS
	 */
	const APPROVED_STATUS = 2;

	/**
	 * The paidy denied status.
	 *
	 * @var string DENIED_STATUS
	 */
	const DENIED_STATUS = 3;

	/**
	 * The paidy canceled status.
	 *
	 * @var string CANCELED_STATUS
	 */
	const CANCELED_STATUS = 4;

	/**
	 * The annual value options.
	 *
	 * @var object $annual_value_options
	 */
	private $annual_value_options = array(
		'1' => '1億円未満',
		'2' => '1億円以上',
	);

	/**
	 * Inittal key to generate iv.
	 *
	 * @var string INITIAL
	 */
	const INITIAL = 'eXZscmRpb3NpOXNvbGJqaD';

	/**
	 * Algo
	 *
	 * @var string AES_128_CBC
	 */
	const AES_128_CBC = 'AES-128-CBC';

	/**
	 * The average prices options.
	 *
	 * @var object $average_price_options
	 */
	private $average_price_options = array(
		'1' => '5万円未満',
		'2' => '5万円以上',
	);

	/**
	 * The instance of this class.
	 *
	 * @var object $instance
	 */
	private static $instance = null;

	/**
	 * The error message.
	 *
	 * @var string $error
	 */
	private $error = '';

	/**
	 * The instant Paidy settings.
	 *
	 * @var string $settings
	 */
	private $settings = array(
		'status' => '',
	);

	/**
	 * Constructor
	 *
	 * @access private
	 */
	private function __construct() {}

	/**
	 * Create the instance of the "InstantPaidyWidget" class.
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->get_settings();
		}

		return self::$instance;
	}

	/**
	 * Receive the response of the Paidy server.
	 */
	public function receive_paidy_result() {
		$received        = false;
		$status          = (int) filter_input( INPUT_POST, 'status' );
		$is_approved     = self::APPROVED_STATUS === $status;
		$is_denied       = self::DENIED_STATUS === $status;
		$is_canceled     = self::CANCELED_STATUS === $status;
		$is_result_valid = $is_approved || $is_denied || $is_canceled;
		$current_setting = $this->get_settings();

		if ( ! $current_setting ) {
			wp_send_json_error();
			return;
		}

		if ( $is_approved ) {
			$public_live  = filter_input( INPUT_POST, 'public_live' );
			$secret_live  = filter_input( INPUT_POST, 'secret_live' );
			$public_test  = filter_input( INPUT_POST, 'public_test' );
			$secret_test  = filter_input( INPUT_POST, 'secret_test' );
			$is_valid_key = ! empty( $public_live ) && ! empty( $secret_live );

			if ( self::DENIED_STATUS === (int) $current_setting['status'] || self::APPROVED_STATUS === (int) $current_setting['status'] ) {

				$is_result_valid = false;

			} elseif ( $is_valid_key ) {

				$this->settings['status']      = self::APPROVED_STATUS;
				$this->settings['public_live'] = self::decrypt( $public_live );
				$this->settings['secret_live'] = self::decrypt( $secret_live );
				$this->settings['public_test'] = self::decrypt( $public_test );
				$this->settings['secret_test'] = self::decrypt( $secret_test );
				$this->activate_paidy( 'preparation' );
				$is_result_valid = true;

			} else {

				$is_result_valid = false;

			}

		} elseif ( $is_denied ) {

			if ( self::DENIED_STATUS === (int) $current_setting['status'] || self::APPROVED_STATUS === (int) $current_setting['status'] ) {

				$is_result_valid = false;

			} else {

				$this->settings['status'] = self::DENIED_STATUS;
				$this->deactivate_paidy();
				$is_result_valid = true;
			}

		} elseif ( $is_canceled ) {

			$this->settings['status'] = 0;
			$this->settings['denied'] = '';
			$this->settings['mode']   = '';
			$this->deactivate_paidy();
			$is_result_valid = true;

		}

		if ( $is_result_valid ) {
			$this->update_settings();
			$received = true;
		}

		if ( $received ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Decrypt the encrypted key value.
	 *
	 * @param string $encrypted_value The encrypted value.
	 * @return string $value
	 */
	public static function decrypt( $encrypted_value ) {
		$passphrase = get_option( 'usces_wcid' );

		if ( empty( trim( $encrypted_value ) ) ) {
			return $encrypted_value;
		}

		$iv              = base64_decode( self::INITIAL );
		$encrypted_value = base64_decode( $encrypted_value );
		$value           = openssl_decrypt( $encrypted_value, self::AES_128_CBC, $passphrase, OPENSSL_RAW_DATA, $iv );
		return $value;
	}

	/**
	 * Check if the Paidy payment is active or not.
	 *
	 * @return bool true|false
	 */
	public function is_paidy_active() {
		$options = get_option( 'usces', array() );
		$active  = isset( $options['acting_settings']['paidy']['activate'] ) && 'on' === $options['acting_settings']['paidy']['activate']
				&& isset( $options['acting_settings']['paidy']['paidy_activate'] ) && 'on' === $options['acting_settings']['paidy']['paidy_activate']
				&& ( '' === $this->settings['status'] || isset( $this->settings['mode'] ) && 'publish' === $this->settings['mode'] );
		return $active;
	}

	/**
	 * Implementation of hook "wp_dashboard_setup()".
	 */
	public function register_widget() {
		$user   = wp_get_current_user();
		$showed = ( in_array( 'wc_management', (array) $user->roles, true ) || in_array( 'administrator', (array) $user->roles, true ) )
									&& ! $this->is_paidy_active()
									&& empty( $this->settings['denied'] );
		if ( $showed ) {
			wp_add_dashboard_widget( 'dashboard_instant_paidy', 'ペイディ決済らくらく設定', array( $this, 'display' ) );
		}
	}

	/**
	 * Render dashboard instant Paidy widget.
	 */
	public function display() {
		global $usces;

		$this->enqueue_script();
		$company   = isset( $usces->options['company_name'] ) ? $usces->options['company_name'] : '';
		$email     = isset( $usces->options['inquiry_mail'] ) ? $usces->options['inquiry_mail'] : '';
		$tel       = isset( $usces->options['tel_number'] ) ? $usces->options['tel_number'] : '';
		$site_name = get_bloginfo( 'name' );
		$home_url  = home_url();

		$is_registed_paidy = ! empty( $this->settings['status'] );
		$status            = $is_registed_paidy ? $this->settings['status'] : '';
		$mode              = empty( $this->settings['mode'] ) ? '' : $this->settings['mode'];
		$paidy_pic         = USCES_PLUGIN_URL . '/images/paidy-pic.png';
		$is_waiting        = $is_registed_paidy && self::WAITING_STATUS === $status;
		$is_denied         = $is_registed_paidy && self::DENIED_STATUS === $status;
		$is_approved       = $is_registed_paidy && self::APPROVED_STATUS === $status;
		$is_canceled       = $is_registed_paidy && self::CANCELED_STATUS === $status;
		$is_test_mode      = $is_approved && 'test' === $mode;
		$is_publish_mode   = $is_approved && 'publish' === $mode;
		?>
		<?php if ( $is_registed_paidy ) : ?>
			<?php if ( $is_waiting ) : ?>
				<table class="paidy-waiting-screen">
					<tr>
						<td><span style="color:#E30E80; font-weight: bold;">ステータス：審査中です</span>
							<p>お申込みありがとうございました。<br>審査結果はメールおよび、このダッシュボードで通知いたします。</p>
							<ul style="margin-left: 20px; list-style-type: circle;">
								<li>審査には最大10営業日かかることがございます。</li>
								<li>審査に関するお問い合わせ：sales@paidy.com</li>
							</ul>
						</td>
					</tr>
				</table>
			<?php endif; ?>
			<?php if ( $is_denied ) : ?>
				<table class="paidy-denied-screen">
					<tr>
						<td>
							<span style="color:#E30E80; font-weight: bold;">ステータス：審査は否決されました</span>
							<p>
							慎重に審査を重ねました結果、誠に申し訳ございませんが、今回はお取引を見送らせていただくこととなりました。<br>このたびはご希望に添えない結果となり、誠に申し訳ございません。<br>審査内容の詳細については、お答えいたしかねますのでご了承ください。
							</p>
							<a href="#" class="button" style="background-color: #E30E80; color:white; float:left;">閉じる</a>
						</td>
					</tr>
				</table>
			<?php endif; ?>
			<?php if ( $is_approved ) : ?>
				<?php if ( ! $is_test_mode && ! $is_publish_mode ) : ?>
					<table class="paidy-approved-screen">
						<tr>
							<td>
								<span style="color:#E30E80; font-weight: bold;">ステータス：審査は承認されました</span>
								<p>審査が完了し、加盟店契約が成立いたしました。<br>ご利用条件は株式会社Paidyから送信されるメールの条件通知書をご確認ください。</p>
								<p>本番モードで公開前にWebhook URLをペイディ加盟店管理画面へ設定することを推奨します。<br>Webhook URLには次の値を設定してください。<br>[テスト・本番共通] <br>
									<pre><?php echo esc_url( home_url( '/' ) ); ?></pre>
									ペイディ加盟店管理画面については<a href="https://download.paidy.com/merchant/PaidyMerchantWebUserGuide.pdf"  style="color:#E30E80;" target="_blank">マニュアル</a>をご覧ください。
								</p>
								<p>下記のいずれかのボタンをクリックしてください。</p>
								<a href="#" class="button test-paidy-btn" style="width: 200px; background-color: #E30E80; color:white; float:left;">テストモードを有効化する</a>
								<br class="clear">
								<a href="#" class="button publish-paidy-btn" style="width: 200px; background-color: #E30E80; color:white; float:left; margin-top: 5px;">本番モードで公開する</a><br>
							</td>
						</tr>
					</table>
				<?php endif; ?>
				<?php if ( $is_test_mode ) : ?>
					<table class="paidy-test-mode-screen">
						<tr>
							<td>
								<p><b>ペイディのテスト決済が有効化されました。</b><br>
								基本設定の支払方法に「あと払い（ペイディ）」が追加されて「使用」の状態になっているのを確認後、購入テストを行ってください。</p>
								<p>テスト決済方法は<a href="https://paidy.com/docs/jp/testing.html" target="_blank" style="color:#E30E80;">こちら</a>をご覧ください。</p>
								<p>テスト完了後、以下のボタンから本番モードで公開してください。</p>
								<a href="#" class="button publish-paidy-btn" style="width: 200px; background-color: #E30E80; color:white; float:left; margin-top: 5px;">本番モードで公開する</a><br>
							</td>
						</tr>
					</table>
				<?php endif; ?>
				<table class="paidy-publish-mode-screen" style="display:none;">
					<tr>
						<td>
							<p><b>ペイディ決済が公開されました。</b></p>
							<p>本項目を閉じた後、操作方法などを確認する場合は クレジット決済設定 ＞ ペイディ ＞ 【資料】をご参照ください。</p>
							<p>　</p>
							<p><a href="#" class="button finish-paidy-btn" onClick="location.reload();" style="width: 200px; background-color: #E30E80; color:white; float:left; margin-top: 5px;">閉じる</a></p>
						</td>
					</tr>
				</table>
			<?php endif; ?>
		<?php else : ?>
			<table class="paidy-intro-screen">
				<tr>
					<td><a href="https://paidy.com/campaign/merchant/202404_WW/" target="_blank"><img src="<?php echo esc_url( $paidy_pic ); ?>" style="margin-right: 20px;" /></a></td>
					<td>
						ペイディの新規申し込みと設定が簡単に行えます。
						<br>
						<a href="#" class="button next-paidy-form-btn" style="background-color: #E30E80; color:white;">今すぐ始める</a>
					</td>
				</tr>
				<tr>
					<td colspan="2">導入コスト0円、決済手数料3.5％～、本経由申込で、なんとお試し1か月決済手数料無料！詳細は<a href="https://paidy.com/campaign/merchant/202404_WW/" target="_blank">こちら</a></td>
				</tr>
				<tr>
					<td colspan="2" style="text-align: right;"><a href="#" id="hide_paidy_widget">非表示</a></td>
				</tr>
			</table>
			<form id="paidy-form"  style="display:none;">
				<table class="paidy-form">
					<tr>
						<th colspan="2" style="border-bottom: 1px solid black; text-align: left;">お申し込みフロー</th>
					</tr>
					<tr>
						<td colspan="2">
							<ol style="margin-left: 20px;">
								<li>本申込み画面の項目を全て記入し、画面下の「上記へ同意して申込み」ボタンをクリックしてください。</li>
								<li>申込情報をもとにペイディにて加盟店審査を行います。最大10営業日かかることがございます。</li>
								<li>審査結果はメールおよびWelcartダッシュボードにて通知いたします。</li>
								<li>審査承認時は、Welcartのダッシュボードからペイディを有効化できます。審査否決時は、ペイディをご利用いただくことはできません。</li>
							</ol>
						</td>
					</tr>
					<tr><th colspan="2" style="border-bottom: 1px solid black; text-align: left;"><br>基本情報</th></tr>
					<tr>
						<th style="text-align:left;"><br>商号/屋号</th>
						<td><br>
							<input type="text" id="company" name="company" required class="validate" style="width: 100%;" value="<?php echo esc_attr( $company ); ?>"/>
						</td>
					</tr>
					<tr>
						<th style="text-align:left;">貴社のECサイト名</th>
						<td>
							<input type="text" name="site_name" required class="validate" style="width: 100%;" value="<?php echo esc_attr( $site_name ); ?>"/>
						</td>
					</tr>
					<tr>
						<th style="text-align:left;">貴社のECサイトURL</th>
						<td>
							<input type="url" name="site_url" required class="validate" style="width: 100%;" value="<?php echo esc_attr( $home_url ); ?>"/>
						</td>
					</tr>
					<tr>
						<th style="text-align:left;">ペイディ登録用メールアドレス</th>
						<td>
							<input type="email" name="email" required class="validate" style="width: 100%;" value="<?php echo esc_attr( $email ); ?>"/>
						</td>
					</tr>
					<tr>
						<th style="text-align:left;">ご担当窓口電話番号</th>
						<td>
							<input type="tel" name="tel" required class="validate" style="width: 100%;" value="<?php echo esc_attr( $tel ); ?>"/>
						</td>
					</tr>
					<tr>
						<th style="text-align:left;">代表者（姓名）</th>
						<td>
							<input type="text" name="name" required class="validate" style="width: 100%;"/>
						</td>
					</tr>
					<tr>
						<th style="text-align:left;">代表者カナ（セイメイ）</th>
						<td>
							<input type="text" name="name_kana" required class="validate" style="width: 100%;"/>
						</td>
					</tr>
					<tr>
						<th style="text-align:left;">代表者生年月日（西暦）</th>
						<td>
							<input type="date" name="birthday" required class="validate" style="width: 100%;"/>
						</td>
					</tr>
					<tr>
						<th style="text-align:left;">年間流通総額</th>
						<td>
							<?php foreach ( $this->annual_value_options as $key => $label ) : ?>
								<input type="radio" name="annual_value" value="<?php echo esc_attr( $key ); ?>" <?php checked( '1', $key ); ?>/><?php echo esc_html( $label ); ?>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th style="text-align:left;">ご注文あたりの平均購入額</th>
						<td>
							<?php foreach ( $this->average_price_options as $key => $label ) : ?>
								<input type="radio" name="average_price" value="<?php echo esc_attr( $key ); ?>" <?php checked( '1', $key ); ?> /><?php echo esc_html( $label ); ?>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th colspan="2" style="border-bottom: 1px solid black; text-align: left;"><br>セキュリティアンケート</th>
					</tr>
					<tr>
						<td colspan="2"><br>
							<input type="checkbox" id="squestion1" name="squestion1" value="1" checked /><label for="squestion1">カートシステム管理画面へのアクセス（ログイン）には、パスワードの入力が必須となっている。</label>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<input type="checkbox" id="squestion2" name="squestion2" value="1" checked /><label for="squestion2">カートシステムの管理画面は、ID、パスワードでのログイン制限の他、アクセス制限が可能となっている。</label>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<input type="checkbox" id="squestion3" name="squestion3" value="1" checked />
							<label for="squestion3">管理画面アクセス制限等について下記のうちいずれかの対応が可能となっている。</label>
							<ul style="margin-left: 20px; list-style-type: circle;">
								<li>管理者にてアクセス可能なIPアドレスを制限可能。</li>
								<li>管理画面にベーシック認証等のアクセス制限の設定が可能。</li>
								<li>その他の方法で対応をしている。</li>
							</ul>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<input type="checkbox" id="squestion4" name="squestion4" value="1" checked />
							<label for="squestion4">データディレクトリ*の露見に対する設定が下記のいずれかの方法でされている。</label>
							<ul style="margin-left: 20px; list-style-type: circle;">
								<li>公開ディレクトリには、重要なファイルを配置出来ないよう、特定のディレクトリを非公開にする。</li>
								<li>公開ディレクトリ以外に重要なファイルを配置する等の配慮がある。</li>
							</ul>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<input type="checkbox" id="squestion5" name="squestion5" value="1" checked />
							<label for="squestion5">WebサーバやWebアプリケーションによりアップロード可能な拡張子やファイルを制限する等の設定を行っている。</label>
							<ul style="margin-left: 20px; list-style-type: circle;">
								<li>その他の方法で対応している。</li>
							</ul>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<input type="checkbox" id="squestion6" name="squestion6" value="1" checked />
							<label for="squestion6">脆弱性診断またはペネトレーションテスト*を定期的（年1回またはシステム変更時）に実施している。</label>
							<ul style="margin-left: 20px; list-style-type: circle;">
								<li>脆弱性診断またはペネトレーションテストを定期的に実施し、必要な修正対応を行っている。</li>
								<li>SQLインジェクションの脆弱性やクロスサイトスクリプティングの脆弱性対策として、当該脆弱性の無いプラグインの使用やソフトウェアのバージョンアップを実施している。</li>
								<li>Webアプリケーションを開発またはカスタマイズされている場合には、セキュアコーディング済みであるか、ソースコードレビューを行い確認している。その際には、入力フォームの入力値のチェックも行っている。</li>
								<li>その他の方法で対応している。</li>
							</ul>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<input type="checkbox" id="squestion7" name="squestion7" value="1" checked />
							<label for="squestion7">マルウェア対策としてのウイルス対策ソフトの導入、運用を下記方法等で行っている。</label>
							<ul style="margin-left: 20px; list-style-type: circle;">
								<li>マルウェア検知/除去などの対策としてウイルス対策ソフトを導入して、シグネチャーの更新や定期的なフルスキャンなどを行っている。</li>
								<li>その他の方法で対応している。</li>
							</ul>
						</td>
					</tr>
					<tr>
						<td colspan="2"><br>
							<input type="checkbox" id="squestion8" name="squestion8" value="1" checked /><label for="squestion8">直近5年間に、特定商取引に関する法律による処分受けたことがない。</label>
						</td>
					</tr>
					<tr>
						<td colspan="2"><br>
							<input type="checkbox" id="squestion9" name="squestion9" value="1" checked /><label for="squestion9">直近5年間に、消費者契約法違反の行為を理由とした民事上の訴訟を提起され、敗訴判決を受けたことがない。</label>
						</td>
					</tr>
					<tr>
						<th colspan="2" style="border-bottom: 1px solid black; text-align: left;"><br>同意事項</th>
					</tr>
					<tr>
						<td colspan="2"><br>
						下記の内容についてご確認・ご同意ください。
						<ol>
							<li><a href="https://terms.paidy.com/docs/merchant-terms-and-conditions.pdf" target="_blank">ペイディ加盟店規約</a></li>
							<li><a href="https://terms.paidy.com/docs/agreement-on-handling-of-comprehensive-member-shop-and-member-shop-information.pdf" target="_blank">加盟店情報の取扱いに関する同意条項</a></li>
							<li>特定商取引法に基づく表記に弊社所定の表記を追加すること。<br><a href="https://merchant-support.paidy.com/hc/ja/articles/16629903258649" target="_blank">特商法に基づく表示サンプル</a></li>
							<li>プライバシーポリシーページに弊社所定の表記を追加すること。<br><a href="https://merchant-support.paidy.com/hc/ja/articles/16631714849561" target="_blank">プライバシーポリシーの記入例</a></li>
							<li>株式会社Paidyが、Welcartを介して貴社に代わり、ECサイトへAPIキーの設定を行うこと。</li>
							<li>当社（コルネ株式会社）は、以下の場合に、個人情報を第三者に提供いたします。
								<ul style="margin-left: 20px; list-style-type: circle;">
									<li>決済会社での加盟店審査等のために個人情報を開示する場合。</li>
								</ul>
							</li>
						</ol>
						</td>
					</tr>
					<tr id="msgs">
						<td colspan="2">
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<a href="javascript:void(0);" class="button send-paidy-form-btn" style="background-color: #E30E80; color:white; float:right;">上記内容へ同意して申し込み</a>
						</td>
					</tr>
				</table>
			</form>
		<?php endif; ?>
		<input id="loading-img" type="hidden" value="<?php echo esc_url( USCES_PLUGIN_URL . 'images/loading.gif' ); ?>">
		<input type="hidden" id="paidy_widget_nonce" name="paidy_widget_nonce" value="<?php echo esc_attr( wp_create_nonce( 'paidy_widget_nonce' ) ); ?>" />

		<?php
	}

	/**
	 * Implementation of hook wp_ajax_{paidy_application}
	 */
	public function handle_ajax() {
		$sub_action = filter_input( INPUT_POST, 'sub_action' );
		switch ( $sub_action ) {
			case 'registered':
				check_ajax_referer( 'paidy_widget_nonce', 'wcnonce' );
				$application = array(
					'company'       => filter_input( INPUT_POST, 'company' ),
					'site_name'     => filter_input( INPUT_POST, 'site_name' ),
					'site_url'      => filter_input( INPUT_POST, 'site_url' ),
					'email'         => filter_input( INPUT_POST, 'email' ),
					'tel'           => filter_input( INPUT_POST, 'tel' ),
					'name'          => filter_input( INPUT_POST, 'name' ),
					'name_kana'     => filter_input( INPUT_POST, 'name_kana' ),
					'birthday'      => filter_input( INPUT_POST, 'birthday' ),
					'annual_value'  => filter_input( INPUT_POST, 'annual_value' ),
					'average_price' => filter_input( INPUT_POST, 'average_price' ),
					'wcid'          => get_option( 'usces_wcid', '' ),
					'questionnaire' => filter_input( INPUT_POST, 'questionnaire' ),
				);

				$submited = $this->submit_paidy_application( $application );
				if ( $submited ) {
					$this->settings['status'] = self::WAITING_STATUS;
					$this->update_settings();
					wp_send_json_success();
				} else {
					wp_send_json_error( array( 'msg' => $this->error ) );
				}
				break;
			case 'close_paidy_widget':
				check_ajax_referer( 'paidy_widget_nonce', 'wcnonce' );
				$this->settings['denied'] = '1';
				$this->update_settings();
				wp_send_json_success();
				break;
			case 'set_paidy_test_mode':
				check_ajax_referer( 'paidy_widget_nonce', 'wcnonce' );
				$this->activate_paidy( 'test' );
				$this->settings['mode'] = 'test';
				$this->update_settings();
				wp_send_json_success();
				break;
			case 'set_paidy_publish_mode':
				check_ajax_referer( 'paidy_widget_nonce', 'wcnonce' );
				$this->activate_paidy( 'live' );
				$this->settings['mode'] = 'publish';
				$this->update_settings();
				wp_send_json_success();
				break;
			case 'hide_paidy_widget':
				check_ajax_referer( 'paidy_widget_nonce', 'wcnonce' );

				$user_id        = get_current_user_id();
				$hidden_widgets = get_user_meta( $user_id, 'metaboxhidden_dashboard', true );

				if ( is_array( $hidden_widgets ) ) {
					if ( ! in_array( 'dashboard_instant_paidy', $hidden_widgets ) ) {
						$hidden_widgets[] = 'dashboard_instant_paidy';
					}
				} else {
					$hidden_widgets = array( 'dashboard_instant_paidy' );
				}

				update_user_meta( $user_id, 'metaboxhidden_dashboard', $hidden_widgets );
				wp_send_json_success();
				break;
		}
	}

	/**
	 * Activate paidy payment.
	 *
	 * @param string $environment The environment.
	 * @return bool true|false
	 */
	private function activate_paidy( $environment ) {

		if ( 'preparation' === $environment ) {

			/**
			 * クレジット決済設定＞ペイディオプション
			 * Welcart Shop > Settlement Setting
			 */
			$paidy_key                                = 'paidy';
			$options                                  = get_option( 'usces', array() );
			$options['acting_settings'][ $paidy_key ] = array(
				'activate'        => 'on',
				'paidy_activate'  => 'off',
				'environment'     => 'test',
				'public_key'      => $this->settings['public_live'],
				'secret_key'      => $this->settings['secret_live'],
				'public_key_test' => $this->settings['public_test'],
				'secret_key_test' => $this->settings['secret_test'],
			);
			update_option( 'usces', $options );

		} elseif ( 'test' === $environment ) {

			/**
			 * クレジット決済設定＞ペイディオプション
			 * Welcart Shop > Settlement Setting
			*/
			$paidy_key                                = 'paidy';
			$options                                  = get_option( 'usces', array() );
			$options['acting_settings'][ $paidy_key ] = array(
				'activate'        => 'on',
				'paidy_activate'  => 'on',
				'environment'     => 'test',
				'public_key'      => $this->settings['public_live'],
				'secret_key'      => $this->settings['secret_live'],
				'public_key_test' => $this->settings['public_test'],
				'secret_key_test' => $this->settings['secret_test'],
			);
			update_option( 'usces', $options );

			/**
			 * クレジット決済設定＞利用中のクレジット決済モジュール.
			 * Welcart Shop > Settlement Setting > Selecting a Settlement Module.
			*/
			$settlement_selected = get_option( 'usces_settlement_selected', array() );
			if ( ! in_array( $paidy_key, $settlement_selected, true ) ) {
				$settlement_selected[] = $paidy_key;
				update_option( 'usces_settlement_selected', $settlement_selected );
			}

			/**
			 * 基本設定＞支払方法＞「決済種別」に追加.
			 * Welcart Shop > General Setting > payment method > Type of payment.
			 */
			$payment_structure                 = get_option( 'usces_payment_structure', array() );
			$payment_structure['acting_paidy'] = 'Paidy';
			ksort( $payment_structure );
			update_option( 'usces_payment_structure', $payment_structure );

			/**
			 * 基本設定＞「支払方法」に追加.
			 * Welcart Shop > General Setting > payment method.
			 */
			$usces_payment_methods = usces_get_system_option( 'usces_payment_method', 'settlement' );
			if ( empty( $usces_payment_methods['acting_paidy'] ) ) {
				$payment_methods = get_option( 'usces_payment_method', array() );
				$id              = $this->generate_payment_id( $payment_methods );
				$sort            = $this->generate_payment_sort( $payment_methods );

				$payment = array(
					'id'          => $id,
					'name'        => 'あと払い（ペイディ）',
					'explanation' => '<ul>
					<li>クレジットカード、事前登録不要。</li>
					<li>メールアドレスと携帯番号だけで、今すぐお買い物。</li>
					<li>1か月に何度お買い物しても、お支払いは翌月まとめて1回でOK。</li>
					<li>お支払いは翌月10日までに、コンビニ払い・銀行振込・口座振替で。</li>
					</ul>',
					'settlement'  => 'acting_paidy',
					'module'      => '',
					'sort'        => $sort,
					'use'         => 'activate',
				);
				usces_update_system_option( 'usces_payment_method', $id, $payment );
			} else {
				$acting_paidy        = $usces_payment_methods['acting_paidy'];
				$acting_paidy['use'] = 'activate';
				usces_update_system_option( 'usces_payment_method', $acting_paidy['id'], $acting_paidy );
			}

		} elseif ( 'live' === $environment ) {

			/**
			 * クレジット決済設定＞ペイディオプション
			 * Welcart Shop > Settlement Setting
			*/
			$paidy_key                                = 'paidy';
			$options                                  = get_option( 'usces', array() );
			$options['acting_settings'][ $paidy_key ] = array(
				'activate'        => 'on',
				'paidy_activate'  => 'on',
				'environment'     => 'live',
				'public_key'      => $this->settings['public_live'],
				'secret_key'      => $this->settings['secret_live'],
				'public_key_test' => $this->settings['public_test'],
				'secret_key_test' => $this->settings['secret_test'],
			);
			update_option( 'usces', $options );

			/**
			 * クレジット決済設定＞利用中のクレジット決済モジュール.
			 * Welcart Shop > Settlement Setting > Selecting a Settlement Module.
			*/
			$settlement_selected = get_option( 'usces_settlement_selected', array() );
			if ( ! in_array( $paidy_key, $settlement_selected, true ) ) {
				$settlement_selected[] = $paidy_key;
				update_option( 'usces_settlement_selected', $settlement_selected );
			}

			/**
			 * 基本設定＞支払方法＞「決済種別」に追加.
			 * Welcart Shop > General Setting > payment method > Type of payment.
			 */
			$payment_structure                 = get_option( 'usces_payment_structure', array() );
			$payment_structure['acting_paidy'] = 'Paidy';
			ksort( $payment_structure );
			update_option( 'usces_payment_structure', $payment_structure );

			/**
			 * 基本設定＞「支払方法」に追加.
			 * Welcart Shop > General Setting > payment method.
			 */
			$usces_payment_methods = usces_get_system_option( 'usces_payment_method', 'settlement' );
			if ( empty( $usces_payment_methods['acting_paidy'] ) ) {
				$payment_methods = get_option( 'usces_payment_method', array() );
				$id              = $this->generate_payment_id( $payment_methods );
				$sort            = $this->generate_payment_sort( $payment_methods );

				$payment = array(
					'id'          => $id,
					'name'        => 'あと払い（ペイディ）',
					'explanation' => '<ul>
					<li>クレジットカード、事前登録不要。</li>
					<li>メールアドレスと携帯番号だけで、今すぐお買い物。</li>
					<li>1か月に何度お買い物しても、お支払いは翌月まとめて1回でOK。</li>
					<li>お支払いは翌月10日までに、コンビニ払い・銀行振込・口座振替で。</li>
					</ul>',
					'settlement'  => 'acting_paidy',
					'module'      => '',
					'sort'        => $sort,
					'use'         => 'activate',
				);
				usces_update_system_option( 'usces_payment_method', $id, $payment );
			} else {
				$acting_paidy        = $usces_payment_methods['acting_paidy'];
				$acting_paidy['use'] = 'activate';
				usces_update_system_option( 'usces_payment_method', $acting_paidy['id'], $acting_paidy );
			}
		}

		return true;
	}

	/**
	 * Deactivate paidy payment.
	 *
	 * @return bool true|false
	 */
	private function deactivate_paidy() {
		/**
		 * クレジット決済設定＞ペイディオプション
		 * Welcart Shop > Settlement Setting
		*/
		$paidy_key                                = 'paidy';
		$options                                  = get_option( 'usces', array() );
		$options['acting_settings'][ $paidy_key ] = array(
			'activate'        => 'on',
			'paidy_activate'  => 'off',
			'environment'     => 'test',
			'public_key'      => '',
			'secret_key'      => '',
			'public_key_test' => '',
			'secret_key_test' => '',
		);
		update_option( 'usces', $options );

		/**
		 * 基本設定＞「支払方法」＞削除.
		 * Welcart Shop > General Setting > payment method.
		 */
		$usces_payment_methods = usces_get_system_option( 'usces_payment_method', 'settlement' );
		if ( ! empty( $usces_payment_methods['acting_paidy'] ) ) {
			$acting_paidy = $usces_payment_methods['acting_paidy'];
			usces_del_system_option( 'usces_payment_method', $acting_paidy['id'] );
		}

		/**
		 * 基本設定＞支払方法＞「決済種別」から削除.
		 * Welcart Shop > General Setting > payment method > Type of payment.
		 */
		$payment_structure = get_option( 'usces_payment_structure', array() );
		unset( $payment_structure['acting_paidy'] );
		ksort( $payment_structure );
		update_option( 'usces_payment_structure', $payment_structure );

		return true;
	}

	/**
	 * Generate the id of the payment method.
	 *
	 * @param array $payment_methods The payment methods.
	 * @return integer The id.
	 */
	private function generate_payment_id( $payment_methods ) {
		$ids = array_keys( $payment_methods );
		if ( empty( $ids ) ) {
			return 0;
		}

		$max_id = max( $ids );
		$max_id++;

		return $max_id;
	}

	/**
	 * Generate the max sort of the payment method.
	 *
	 * @param array $payment_methods The payment methods.
	 * @return integer $sort
	 */
	private function generate_payment_sort( $payment_methods ) {
		$sort = 0;
		if ( empty( $payment_methods ) ) {
			return $sort;
		}

		foreach ( $payment_methods as $payment_method ) {
			if ( $sort < $payment_method['sort'] ) {
				$sort = $payment_method['sort'];
			}
		}

		$sort++;
		return $sort;
	}

	/**
	 * Update Instant Paidy settings.
	 *
	 * @return bool true|false
	 */
	private function update_settings() {
		return update_option( 'usces_paidy_application', $this->settings );
	}

	/**
	 * Get Instant Paidy settings.
	 *
	 * @return array
	 */
	private function get_settings() {
		$this->settings = get_option( 'usces_paidy_application', $this->settings );
		return $this->settings;
	}

	/**
	 * Send the application to the Paidy application management server.
	 *
	 * @param array $application The application.
	 * @return bool true|false
	 */
	public function submit_paidy_application( $application ) {
		$url = self::BASE_API_URL . '/wp-json/paidy-management/application';
		$ch  = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $application );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$result = curl_exec( $ch );
		unset( $ch );
		$result = json_decode( $result );

		if ( empty( $result->success ) ) {
			$this->error = $result->message;
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Embbed javascript.
	 *
	 * @access private
	 * @return object $this itself.
	 */
	private function enqueue_script() {
		?>
	<script>
		jQuery(document).ready(function ($) {
			const instantPaidyWidget = {
				start: function () {
					this.listen();
				},
				listen: function () {
					// Registered Paidy screen.
					this.onClickNextPaidyFormBtn();
					this.onClickSendPaidyFormBtn();

					//Approved Paidy screen.
					this.onClickTestPaidyBtn();
					this.onClickPublishPaidyBtn();

					//Denied Paidy screen.
					this.onClickCloseBtnOnDeniedScreen();

					this.onClickHidePaidyWidget();
				},
				onClickCloseBtnOnDeniedScreen: function () {
					$('.paidy-denied-screen .button').on('click', function () {
						var nonce = $('#paidy_widget_nonce').val();

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'paidy_application',
								sub_action: 'close_paidy_widget',
								wcnonce: nonce,
							},
							beforeSend: function () {
								$('.paidy-denied-screen .button').attr('disabled','disabled');
								$('.paidy-denied-screen .button').after('<img id="loading" src="'+$('#loading-img').val()+'"/>');
							},
						}).done(function (res) {
							location.reload();
						});
					});
				},
				onClickTestPaidyBtn: function () {
					$('.paidy-approved-screen .test-paidy-btn').on(
						'click',
						function () {
							var nonce = $('#paidy_widget_nonce').val();

							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'paidy_application',
									sub_action: 'set_paidy_test_mode',
									wcnonce: nonce,
								},
								beforeSend: function () {
									$('.paidy-approved-screen .button').attr('disabled', 'disabled');
									$('.paidy-approved-screen .test-paidy-btn').after('<img id="loading" src="'+$('#loading-img').val()+'"/>');
								},
							}).done(function (res) {
								location.reload();
							});
						}
					);
				},
				onClickPublishPaidyBtn: function () {
					$('.paidy-approved-screen .publish-paidy-btn, .paidy-test-mode-screen .publish-paidy-btn').on('click', function () {
						var nonce = $('#paidy_widget_nonce').val();

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'paidy_application',
								sub_action: 'set_paidy_publish_mode',
								wcnonce: nonce,
							},
							beforeSend: function () {
								$('.paidy-approved-screen .button, .paidy-test-mode-screen .button').attr('disabled', 'disabled');
								$('.paidy-approved-screen .publish-paidy-btn, .paidy-test-mode-screen .publish-paidy-btn').after(
									'<img id="loading" src="'+$('#loading-img').val()+'"/>');
							},
						}).done(function (res) {
							$('.paidy-approved-screen, .paidy-test-mode-screen').hide();
							$('.paidy-publish-mode-screen').show();
						});
					});
				},
				onClickNextPaidyFormBtn: function () {
					const self = this;
					$('.paidy-intro-screen .next-paidy-form-btn').on(
						'click',
						function () {
							$('.paidy-intro-screen').hide();
							$('#dashboard_instant_paidy h2').html('ペイディ申し込み');
							$('#paidy-form').show();
						}
					);
				},
				onClickSendPaidyFormBtn: function () {
					const self = this;
					$('#paidy-form .send-paidy-form-btn').on('click', function () {
						var nonce = $('#paidy_widget_nonce').val();
						var question = '';

						for (var i = 1; i <= 9; i++) {
							var checkBoxId = '#squestion' + i;

							if ($(checkBoxId).is(':checked')) {
								question += '1';
							} else {
								question += '0';
							}
						}

						if (self.validatePaidyForm()) {
							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'paidy_application',
									sub_action: 'registered',
									wcnonce: nonce,
									company: $('#paidy-form input[name=company]').val(),
									site_name: $('#paidy-form input[name=site_name]').val(),
									site_url: $('#paidy-form input[name=site_url]').val(),
									email: $('#paidy-form input[name=email]').val(),
									tel: $('#paidy-form input[name=tel]').val(),
									name: $('#paidy-form input[name=name]').val(),
									name_kana: $('#paidy-form input[name=name_kana]').val(),
									birthday: $('#paidy-form input[name=birthday]').val(),
									annual_value: $('#paidy-form input[name=annual_value]:checked').val(),
									average_price: $('#paidy-form input[name=average_price]:checked').val(),
									questionnaire: question,
								},
								beforeSend: function () {
									$('#paidy-form input').attr('disabled', 'disabled');
									$('#paidy-form .send-paidy-form-btn').attr('disabled', 'disabled');
									$('#paidy-form #msgs td').html('<img id="loading" src="'+$('#loading-img').val()+'"/>');
								},
							}).done(function (res) {
								if (res.success) {
									location.reload();
								} else {
									$('#paidy-form input').removeAttr('disabled');
									$('#paidy-form .send-paidy-form-btn').removeAttr('disabled');
									$('#paidy-form #loading').remove();
									$('#paidy-form #msgs td').html('<div id="error" style="color:red;">'+res.data.msg+'</div>');
								}
							});
						}
					});
				},
				validatePaidyForm: function () {
					const self = this;
					let valid = true;
					const inputs = $('#paidy-form input.validate');
					inputs.each(function () {
						const input = $(this);
						if (self.isEmpty(input)) {
							valid = false;
						}
					});
					const formVaild = document.getElementById('paidy-form').reportValidity();
					valid = valid && formVaild;

					return valid;
				},
				isEmpty: function (ele) {
					const isEmpty = '' === ele.val().trim();
					if (isEmpty) {
						ele.val('');
					}
					return isEmpty;
				},
				onClickHidePaidyWidget: function () {
					$('#hide_paidy_widget').on('click', function (e) {
						e.preventDefault();

						var nonce = $('#paidy_widget_nonce').val();
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'paidy_application',
								sub_action: 'hide_paidy_widget',
								wcnonce: nonce
							},
							beforeSend: function () {
								$('#dashboard_instant_paidy').hide();
							},
						}).done(function (res) {
							console.log('Server response: ', res);
							location.reload();
						});
					});
				},
			};

			instantPaidyWidget.start();
		});
	</script>
		<?php
		return $this;
	}
}
