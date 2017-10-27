<?php
/**
 * Plugin Name: WooCommerce Warranty List
 * Description: Add a button to Order listing to download a custom Warranty List
 * Plugin URI: 
 * Version: 0.1
 * Author: Peter Kulcsár - COOOL /beamkiller/
 * Author URI: http://hu.linkedin.com/in/peterkulcsar/
 * Copyright: ©2017 Peter Kulcsár - COOOL
*/
if (!defined('ABSPATH'))
    exit;

ob_start(); 	

class WC_Warranty_List {

	public static $plugin_prefix;
	public static $plugin_url;
	public static $plugin_path;
	public static $plugin_basename;
	public static $version;

    //Construct
    public function __construct() {

		//Default variables
		self::$plugin_prefix = 'wc_warranty_list_';
		self::$plugin_basename = plugin_basename(__FILE__);
		self::$plugin_url = plugin_dir_url(self::$plugin_basename);
		self::$plugin_path = trailingslashit(dirname(__FILE__));
		self::$version = '0.1';

		add_filter( 'woocommerce_shipping_settings', array( $this, 'settings' ) );
		add_action( 'woocommerce_admin_order_actions_end', array( $this, 'order_row_button' ),20,1);
		add_action( 'admin_init',array( $this, 'generate_warranty_list_pdf' ));

    }

	//Settings
	public function settings($settings)
    {
        $updated_settings = array();
        foreach ($settings as $section) {
            if (isset($section['id']) && 'shipping_options' == $section['id'] && isset($section['type']) && 'sectionend' == $section['type']) {
                $shipping_methods = array();
                global $woocommerce;
            }
            $updated_settings[] = $section;
        }
        return $updated_settings;
    }

	//Order list button
	public function order_row_button($order) {
		?>
		<a href="<?php echo admin_url( "?download_warranty_pdf=1&order_id=$order->id" ); ?>" class="button tips dpd-small-button" target="_blank" alt="" data-tip="<?php _e('Jótállási jegy','wc-szamlazz'); ?>">
			<img src="<?php echo WC_Warranty_List::$plugin_url . 'warranty_mini.png'; ?>" alt="" width="16" height="16">
		</a>
		<?php
	}

	//Generate HTML for PDF document
	public function generate_warranty_list_pdf() {

		if (!empty($_GET['download_warranty_pdf'])) {

			$args = array(
				'post_type' => 'shop_order',
				'post_status' => 'any',
				'posts_per_page' => '-1'
			);

			if(isset($_GET['order_id'])) {
				if($_GET['order_id'] != '') {
					$orderids = explode('|',$_GET['order_id']);
					$args['post__in'] = $orderids;
				}
			} else {

				//2.2 előtt taxonomy volt a rendelés státusz
				global $woocommerce;
				if($woocommerce->version<2.2) {
					$args['tax_query'] = array(
						array(
							'taxonomy' => 'shop_order_status',
							'field' => 'slug',
							'terms' => array('processing')
						)
					);
				} else {
					$args['post_status'] =  array( 'wc-processing');
				}

			}

			$orders = new WP_Query($args);
			
			while ( $orders->have_posts() ) : $orders->the_post();
				global $post;
				$order_id = get_the_ID();
				$order = new WC_Order($order_id);
				
				//Payment method
				$order_payment_method = $order->payment_method;
				
				//Shipping method
				$shipping_items = $order->get_items( 'shipping' );
				foreach($shipping_items as $el){
				  $order_shipping_method_id = $el['method_id'] ;
				}
				
				/*  https://www.html2pdfrocket.com/
					FREE HTML TO PDF API
				*/
				
				//KERESKEDO ADATAI
				$kereskedo_adatai = '';
				
				if($order->shipping_company){
					$kereskedo_adatai = $order->shipping_company . '<br>' . $order->shipping_first_name .' '.$order->shipping_last_name . '<br>';
				} else {
					$kereskedo_adatai = $order->shipping_first_name .' '.$order->shipping_last_name . '<br>';
				}
				
				$kereskedo_adatai .= $order->shipping_address_1 . '<br>';
				
				if ($order->shipping_address_2){
					$kereskedo_adatai .= $order->shipping_address_2 . '<br>';
				}
				
				$kereskedo_adatai .= $order->billing_postcode .', '. $order->billing_city . '<br>';
				
				//SZAMLA SORSZAMA
				$szamla_sorszama = str_replace("\r", '', get_post_meta($order_id,'_wc_szamlazz',true));
				
				//TERMEK LISTA
				$termek_lista = '';
				
				
				
				foreach( $order->get_items() as $item ) {
					
					if( has_term( array (25,20), 'product_cat', $item['product_id']) ){
						continue;
					}
					
					$_sale_price = get_post_meta ($item['product_id'], "_sale_price", true);
					
					$termek_lista .= '<tr class="item">';
					$termek_lista .= '<td>' . $item['name'] . '</td>';
					$termek_lista .= '<td>' . get_post_meta ($item['product_id'], "_sku", true) . '</td>';
					$termek_lista .= '<td align="center">' . $item['qty']. '</td>';
					
					if ($_sale_price){
						$termek_lista .= '<td>' . get_post_meta ($item['product_id'], "_sale_price", true)/* round ($order->get_item_total( $item ) * 1.27 , 0) */ . ' Ft </td>';
					} else {
						$termek_lista .= '<td>' . get_post_meta ($item['product_id'], "_regular_price", true)/* round ($order->get_item_total( $item ) * 1.27 , 0) */ . ' Ft </td>';
					}
					$termek_lista .= '</tr>';
					
				}
					
				$url = 'http://api.html2pdfrocket.com/pdf';
				$data = '<!doctype html>
						<html>
						<head>
							<meta charset="utf-8">
							<title>Jótállási jegy: '.$order_id.'</title>
							
							<style>
							.invoice-box{
								max-width:800px;
								margin:auto;
								padding:10px;
								/* border:1px solid #eee;
								box-shadow:0 0 10px rgba(0, 0, 0, .15); */
								font-size:14px;
								line-height:22px;
								font-family:"Helvetica Neue", "Helvetica", Helvetica, Arial, sans-serif;
								color:#555;
							}
							
							.text{
								font-size:10px !important;
							}
							
							.invoice-box table{
								width:100%;
								line-height:inherit;
								text-align:left;
							}
							
							.invoice-box table td{
								padding:5px;
								vertical-align:top;
							}
							
							.invoice-box table tr td:nth-child(2){
								text-align:right;
							}
							
							.invoice-box table tr.top table td{
								padding-bottom:10px;
							}
							
							.invoice-box table tr.top table td.title{
								font-size:45px;
								line-height:45px;
								color:#333;
							}
							
							.invoice-box table tr.information table td{
								padding-bottom:20px;
							}
							
							.invoice-box table tr.heading td{
								background:#eee;
								border-bottom:1px solid #ddd;
								font-weight:bold;
							}
							
							.invoice-box table tr.details td{
								padding-bottom:20px;
							}
							
							.invoice-box table tr.item td{
								border-bottom:1px solid #eee;
							}
							
							.invoice-box table tr.item.last td{
								border-bottom:none;
							}
							
							.invoice-box table tr.total td:nth-child(2){
								border-top:2px solid #eee;
								font-weight:bold;
							}
							
							@media only screen and (max-width: 600px) {
								.invoice-box table tr.top table td{
									width:100%;
									display:block;
									text-align:center;
								}
								
								.invoice-box table tr.information table td{
									width:100%;
									display:block;
									text-align:center;
								}
							}
							</style>
						</head>

						<body>
							<div class="invoice-box">
							
								<h2 style="text-align:center">Jótállási jegy</h2>
								
								<table cellpadding="0" cellspacing="0">
									<tr class="top">
										<td colspan="4">
											<table>
												<tr>
													<td class="title logo">
														<img src="http://www.logoground.com/uploads/201510872015-10-274087393AmericanStore.jpg" style="width:100%; max-width:300px;">
													</td>
													
													<td>
														<b>Jótállási jegy:</b> '.$order_id.'<br>
														<b>Rendelés dátuma:</b> '.substr($order->order_date,0,10).'<br>
														<b>Számla sorszáma:</b> '.$szamla_sorszama.'<br>
													</td>
												</tr>
											</table>
										</td>
									</tr>
									
									<tr class="information">
										<td colspan="4">
											<table>
												<tr>
													<td>
														Alma.hu Store<br>
														2900 Cegléd  Mihály utca 20.<br>
														Web: alma.hu<br>
														Telefonszám: +3630xxxxx<br>
														E-mail: xxxxxxxx@gmail.com<br>
													</td>
													
													<td>
														<b>Kereskedő adatai (PH):</b><br>
														<img src="https://cdn.pixabay.com/photo/2014/11/09/08/06/signature-523237__340.jpg" style="width:100%; max-width:250px;">
													</td>
												</tr>
											</table>
										</td>
									</tr>
									
									<tr>
										<td colspan="4"><b>A jótállás időtartama: 24 hónap a termékre, 6 hónap az akkumulátorokra.</b></td>
									</tr>
									<tr>
										<td colspan="4"><b>A jótállási jegy a mellékelt számlával együtt érvényes!</b></td>
									</tr>
									
									<tr class="heading">
										<td >
											Termék neve
										</td>
										
										<td>
											SKU
										</td>
										
										<td align="center">
											Mennyiség
										</td>
										
										<td>
											Egységár
										</td>
									</tr>
									
									' . $termek_lista . '
									
									<tr>
										<td colspan="4">&nbsp;</td>
									</tr>
									
									<tr class="heading">
										<td colspan="2">
											Garanciális munkák #1
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									
									<tr>
										<td colspan="2">
											A hiba bejelentésének napja:
										</td>
										
										<td colspan="2" style="text-align:left;">
											A kijavítás átvétel napja:
										</td>
									</tr>
									<tr>
										<td colspan="2">
											A jótállás érvényességének új időpontja:
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr >
										<td colspan="2">
											A hiba oka, leírása:
										</td>
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr >
										<td colspan="2">
											A javítás módja:
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr >
										<td colspan="2">
											Az érintett alkatrész megnevezése:
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									
									<tr class="heading">
										<td colspan="2">
											Garanciális munkák #2
										</td>
											
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr>
										<td colspan="2">
											A hiba bejelentésének napja:
										</td>
										
										<td colspan="2" style="text-align:left;">
											A kijavítás átvétel napja:
										</td>
									</tr>
									<tr>
										<td colspan="2">
											A jótállás érvényességének új időpontja:
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr >
										<td colspan="2">
											A hiba oka, leírása:
										</td>
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr >
										<td colspan="2">
											A javítás módja:
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr >
										<td colspan="2">
											Az érintett alkatrész megnevezése:
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									
									
									<tr class="heading">
										<td colspan="2">
											Garanciális munkák #3
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr>
										<td colspan="2">
											A hiba bejelentésének napja:
										</td>
										
										<td colspan="2" style="text-align:left;">
											A kijavítás átvétel napja:
										</td>
									</tr>
									<tr>
										<td colspan="2">
											A jótállás érvényességének új időpontja:
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr >
										<td colspan="2">
											A hiba oka, leírása:
										</td>
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr >
										<td colspan="2">
											A javítás módja:
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									<tr >
										<td colspan="2" class="details">
											Az érintett alkatrész megnevezése:
										</td>
										
										<td colspan="2">
											&nbsp;
										</td>
									</tr>
									
								</table>
								
								<div class="text">
								<h2>TÁJÉKOZTATÓ A JÓTÁLLÁSI JOGOKRÓL </h2>
									<p>A jótállás időtartama 24 hónap. Beépített akkumulátorokra 6 hónap. A jótállási határidő a fogyasztási cikk fogyasztó részére történő átadása, vagy ha az üzembe helyezést a vállalkozás vagy annak megbízottja végzi, az üzembe helyezés napjával kezdődik. </p>
									<p>Nem tartozik jótállás alá a hiba, ha annak oka a termék fogyasztó részére való átadását követően lépett fel, így például, ha a hibát </p>
									<ul>
										<li>szakszerűtlen üzembe helyezés (kivéve, ha az üzembe helyezést a vállalkozás, vagy annak megbízottja végezte el, illetve ha a szakszerűtlen üzembe helyezés a használati-kezelési útmutató hibájára vezethető vissza)</li>
										<li>rendeltetésellenes használat, a használati-kezelési útmutatóban foglaltak figyelmen kívül hagyása,</li>
										<li>helytelen tárolás, helytelen kezelés, rongálás,</li>
										<li>elemi kár, természeti csapás okozta.</li>
									</ul>
									<p>Jótállás keretébe tartozó hiba esetén a fogyasztó</p>
									<ul>
										<li>elsősorban – választása szerint – kijavítást vagy kicserélést követelhet, kivéve, ha a választott jótállási igény teljesítése lehetetlen, vagy ha az a vállalkozásnak a másik jótállási igény teljesítésével összehasonlítva aránytalan többletköltséget eredményezne, figyelembe véve a szolgáltatás hibátlan állapotban képviselt értékét, a szerződésszegés súlyát és a jótállási igény teljesítésével a fogyasztónak okozott érdeksérelmet.</li>
										<li>ha a vállalkozás a kijavítást vagy a kicserélést nem vállalta,  e kötelezettségének megfelelő határidőn belül, a fogyasztó érdekeit kímélve nem tud eleget tenni, vagy ha a fogyasztónak a kijavításhoz vagy a kicseréléshez fűződő érdeke megszűnt, a fogyasztó – választása szerint – a vételár arányos leszállítását igényelheti, a hibát a vállalkozás költségére maga kijavíthatja vagy mással kijavíttathatja, vagy elállhat a szerződéstől. Jelentéktelen hiba miatt elállásnak nincs helye.</li>
									</ul>
									
									<p>A fogyasztó a választott jogáról másikra térhet át. Az áttéréssel okozott költséget köteles a vállalkozásnak megfizetni, kivéve, ha az áttérésre a vállalkozás adott okot, vagy az áttérés egyébként indokolt volt.</p>
									
									<p>Ha a fogyasztó a termék meghibásodása miatt a vásárlástól (üzembe helyezéstől) számított három munkanapon belül érvényesít csereigényt, a vállalkozás nem hivatkozhat aránytalan többletköltségre, hanem köteles a terméket kicserélni, feltéve, hogy a meghibásodás a rendeltetésszerű használatot akadályozza.</p>
									
									<p>A kijavítást vagy kicserélést – a termék tulajdonságaira és a fogyasztó által elvárható rendeltetésére figyelemmel – megfelelő határidőn belül, a fogyasztó érdekeit kímélve kell elvégezni. A vállalkozásnak törekednie kell arra, hogy a kijavítást vagy kicserélést legfeljebb tizenöt napon belül elvégezze. </p>
									
									<p>A kijavítás során a termékbe csak új alkatrész kerülhet beépítésre.
									Nem számít bele a jótállási időbe a kijavítási időnek az a része, amely alatt a fogyasztó a terméket nem tudja rendeltetésszerűen használni. A jótállási idő a terméknek vagy a termék részének kicserélése (kijavítása) esetén a kicserélt (kijavított) termékre (termékrészre), valamint a kijavítás következményeként jelentkező hiba tekintetében újból kezdődik.</p>
									
									<p>A jótállási kötelezettség teljesítésével kapcsolatos költségek a vállalkozást terhelik.</p>
									
									<p>A rögzített bekötésű, illetve a 10 kg-nál súlyosabb, vagy tömegközlekedési eszközön kézi csomagként nem szállítható terméket – a járművek kivételével – az üzemeltetés helyén kell megjavítani. Ha a javítás az üzemeltetés helyén nem végezhető el, a le- és felszerelésről, valamint az el- és visszaszállításról a forgalmazó gondoskodik.</p>
									
									<p>A jótállás nem érinti a fogyasztó jogszabályból eredő – így különösen kellék- és termékszavatossági, illetve kártérítési – jogainak érvényesítését.</p>
									
									<p>Fogyasztói jogvita esetén a fogyasztó a megyei (fővárosi) kereskedelmi és iparkamarák mellett működő békéltető testület eljárását is kezdeményezheti.</p>
									
									<p>A jótállási igény a jótállási jeggyel érvényesíthető. Jótállási jegy fogyasztó rendelkezésére bocsátásának elmaradása esetén a szerződés megkötését bizonyítottnak kell tekinteni, ha az ellenérték megfizetését igazoló bizonylatot - az általános forgalmi adóról szóló törvény alapján kibocsátott számlát vagy nyugtát - a fogyasztó bemutatja. Ebben az esetben a jótállásból eredő jogok az ellenérték megfizetését igazoló bizonylattal érvényesíthetőek.</p>
									
									<p>A fogyasztó jótállási igényét a vállalkozásnál érvényesítheti.  A vállalkozás a minőségi kifogás bejelentésekor a fogyasztó és vállalkozás közötti szerződés keretében eladott dolgokra vonatkozó szavatossági és jótállási igények intézésének eljárási szabályairól szóló 19/2014. (IV. 29.) NGM rendelet (a továbbiakban: NGM rendelet) 4. §-a szerint köteles – az ott meghatározott tartalommal – jegyzőkönyvet felvenni és annak másolatát haladéktalanul és igazolható módon a fogyasztó rendelkezésére bocsátani. 
									<p>A vállalkozás, illetve a javítószolgálat (szerviz) a termék javításra való átvételekor az NGM rendelet 6. §-a szerinti elismervény átadására köteles. </p>
									 
									 <p>[1] A jótállási kötelezettség teljesítése azt a vállalkozást terheli, amelyet a fogyasztóval kötött szerződés a szerződés tárgyát képező szolgáltatás nyújtására kötelez.</p>
								</div>
								
							</div>
						</body>
						</html>';

				// use key 'http' even if you send the request to https://...
				
				$apikey = 'xxx';
				
				$postdata = http_build_query(
					array(
						'apikey' => $apikey,
						'value' => $data
					)
				);
				
				
				$options = array(
					'http' => array(
						'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
						'method'  => 'POST',
						'content' => $postdata,
					),
				);
				$context  = stream_context_create($options);
				$result = file_get_contents($url, false, $context);

				// set the pdf data as download content:
				header('Content-type: application/pdf');
				header('Content-Disposition: attachment; filename="jotallasi_jegy_'.$order_id.'.pdf"');
				
				ob_clean();
				echo($result);

			endwhile;

			exit();
		}

	}
}

$GLOBALS['wc_warranty_list'] = new WC_Warranty_List();

