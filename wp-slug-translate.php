<?php
/*
Plugin Name: WP Slug Translate
Plugin URI: https://github.com/uu0/wp-slug-translate
Description: WP Slug Translate can translate the post slug into English. It will take the post ID as slug when translation failure.
Version: 1.0.0
Author: uu0
Author URI: https://github.com/uu0
Text Domain: WP-Slug-Translate
Domain Path: /lang
*/

define("APIKEY",get_option('wp_slug_translate_apikey'));
define("APIBASE",get_option('wp_slug_translate_apibase'));
define("MODEL",get_option('wp_slug_translate_model'));
define("SOURCE",get_option('wp_slug_translate_language'));
define("TARGET","en");

function load_wp_slug_translate_lang(){
	$currentLocale = get_locale();
	if(!empty($currentLocale)){
		$moFile = dirname(__FILE__) . "/lang/wp-slug-translate-" . $currentLocale . ".mo";
		if(@file_exists($moFile) && is_readable($moFile)) load_textdomain('WP-Slug-Translate',$moFile);
	}
}
add_filter('init','load_wp_slug_translate_lang');

// 获取可用模型列表
function wp_slug_translate_get_available_models($force_refresh = false) {
	$api_key = get_option('wp_slug_translate_apikey', '');
	$api_base = get_option('wp_slug_translate_apibase', 'https://api.siliconflow.cn/v1');

	// 如果强制刷新,从API获取
	if ($force_refresh) {
		if (empty($api_key)) {
			return new WP_Error('no_api_key', 'API密钥未配置');
		}

		$endpoint = rtrim($api_base, '/') . '/models';

		$response = wp_remote_get($endpoint, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
			'sslverify' => false
		));

		if (is_wp_error($response)) {
			error_log('WP Slug Translate - 请求失败: ' . $response->get_error_message());
			return $response;
		}

		$http_code = wp_remote_retrieve_response_code($response);
		if ($http_code !== 200) {
			$body = wp_remote_retrieve_body($response);
			error_log('WP Slug Translate - HTTP错误: ' . $http_code . ' - Body: ' . substr($body, 0, 500));
			return new WP_Error('http_error', 'API请求失败，HTTP状态码: ' . $http_code);
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			// 记录原始响应以便调试
			error_log('WP Slug Translate - JSON解析失败: ' . json_last_error_msg());
			error_log('WP Slug Translate - 原始响应: ' . substr($body, 0, 500));
			return new WP_Error('json_decode_error', '无法解析API响应，请检查API URL配置是否正确');
		}

		if (!isset($data['data'])) {
			error_log('WP Slug Translate - API响应缺少data字段: ' . print_r($data, true));
			return new WP_Error('invalid_response', 'API返回数据格式不正确');
		}

		// 提取聊天模型
		$models = $data['data'];
		$chat_models = array();

		foreach ($models as $model) {
			$model_id = $model['id'];
			$display_name = isset($model['display_name']) ? $model['display_name'] : $model_id;

			// 只包含聊天模型,排除图片生成模型
			if (strpos(strtolower($model_id), 'flux') === false &&
				strpos(strtolower($model_id), 'stable') === false &&
				strpos(strtolower($model_id), 'sd') === false &&
				strpos(strtolower($model_id), 'diffusion') === false) {
				$chat_models[$model_id] = $display_name;
			}
		}

		// 按名称排序
		asort($chat_models);

		// 缓存结果
		update_option('wp_slug_translate_available_models', $chat_models);
		update_option('wp_slug_translate_models_last_update', current_time('timestamp'));

		return array(
			'success' => true,
			'models' => $chat_models,
			'total' => count($chat_models)
		);
	}

	// 尝试从缓存获取
	$cached_models = get_option('wp_slug_translate_available_models', array());
	if (!empty($cached_models)) {
		return array(
			'success' => true,
			'models' => $cached_models,
			'total' => count($cached_models)
		);
	}

	// 如果没有缓存,返回空数组
	return array(
		'success' => true,
		'models' => array(),
		'total' => 0
	);
}

// AJAX 处理刷新模型列表
function wp_slug_translate_refresh_models_ajax() {
	// 检查权限
	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('message' => '权限不足'));
	}

	// 检查nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_slug_translate_nonce')) {
		wp_send_json_error(array('message' => '安全验证失败'));
	}

	// 从API获取模型列表(强制刷新)
	$result = wp_slug_translate_get_available_models(true);

	if (is_wp_error($result)) {
		wp_send_json_error(array(
			'message' => $result->get_error_message()
		));
	}

	wp_send_json_success(array(
		'message' => '模型列表刷新成功',
		'models' => $result['models'],
		'total' => $result['total']
	));
}
add_action('wp_ajax_wp_slug_translate_refresh_models', 'wp_slug_translate_refresh_models_ajax');

class WstHttpRequest
{
	function curlRequest($url, $header = array(), $postData = ''){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		if(!empty($header)){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		if(!empty($postData)){
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
		}
		$curlResponse = curl_exec($ch);
		curl_close($ch);
		return $curlResponse;
	}
}

class WstMicrosoftTranslator extends WstHttpRequest
{
	private $_apiKey = APIKEY;
	private $_apiBase = APIBASE;
	private $_model = MODEL;
	private $_fromLanguage = SOURCE;
	private $_toLanguage = TARGET;

	function translate($inputStr){
		$translateUrl = rtrim($this->_apiBase, '/') . '/chat/completions';
		$authHeader = "Authorization: Bearer " . $this->_apiKey;
		$header = array($authHeader, "Content-Type: application/json");

		$prompt = "Translate the following text from {$this->_fromLanguage} to {$this->_toLanguage}. Return only the translated text, nothing else.";
		$postData = array(
			'model' => $this->_model,
			'messages' => array(
				array(
					'role' => 'system',
					'content' => 'You are a professional translator.'
				),
				array(
					'role' => 'user',
					'content' => $prompt . "\n\nText: " . $inputStr
				)
			),
			'temperature' => 0.3,
			'max_tokens' => 1000
		);

		$curlResponse = $this->curlRequest($translateUrl, $header, json_encode($postData));
		$jsonObj = json_decode($curlResponse);

		$translatedStr = '';
		if(!empty($jsonObj->choices) && !empty($jsonObj->choices[0]->message->content)){
			$translatedStr = $jsonObj->choices[0]->message->content;
		}

		return $translatedStr;
	}

}

if(get_option("wp_slug_translate_secondmode")!='yes'){

function wp_slug_translate($postid){
	global $wpdb;
	$sql = "SELECT post_title,post_name FROM $wpdb->posts WHERE ID = '$postid'";
	$results = $wpdb->get_results($sql);	
	$post_title = $results[0]->post_title;
	$post_name = $results[0]->post_name;

	if( !substr_count($post_name,'%') && !is_numeric($post_name) ){
		if(substr_count($post_name,'_')){
			$wst_post_name = str_replace('_','-',$post_name);
			$sql = "UPDATE $wpdb->posts SET post_name = '$wst_post_name' WHERE ID = '$postid'";
			$wpdb->query($sql);
		}
		return true;
	}

	$post_title = str_replace(array('_','/'),array(' ',' '),$post_title);
	$wst_microsoft= new WstMicrosoftTranslator();
	$wst_title = sanitize_title( $wst_microsoft->translate($post_title) );
	if( strlen($wst_title) < 2 ){
		$wst_title = $postid;
	}
		
	$sql = "UPDATE $wpdb->posts SET post_name = '$wst_title' WHERE ID = '$postid'";		
	$wpdb->query($sql);
}
//add_action('publish_post', 'wp_slug_translate', 1);
//add_action('edit_post', 'wp_slug_translate', 1);
add_action('save_post', 'wp_slug_translate', 1);

}else{

function wp_slug_translate($postname){
	$post_name = $postname;
	$post_title = $_POST['post_title'];
	
	if( !empty($post_name) && !is_numeric($post_name) ) return str_replace('_','-',$post_name);

	$post_title = str_replace(array('_','/'),array(' ',' '),$post_title);
	$wst_microsoft= new WstMicrosoftTranslator();
	$wst_title = sanitize_title( $wst_microsoft->translate($post_title) );
	
	return $wst_title;
}
add_filter('name_save_pre', 'wp_slug_translate', 1);

}

function wp_slug_translate_activate(){
	add_option('wp_slug_translate_apikey','');
	add_option('wp_slug_translate_apibase','https://api.siliconflow.cn/v1');
	add_option('wp_slug_translate_model','deepseek-ai/DeepSeek-V3');
	add_option('wp_slug_translate_language','zh-CHS');
	add_option('wp_slug_translate_secondmode','');
	add_option('wp_slug_translate_deactivate','');
}
register_activation_hook( __FILE__, 'wp_slug_translate_activate' );

if(get_option("wp_slug_translate_deactivate")=='yes'){
	function wp_slug_translate_deactivate(){
		delete_option('wp_slug_translate_apikey');
		delete_option('wp_slug_translate_apibase');
		delete_option('wp_slug_translate_model');
		delete_option('wp_slug_translate_language');
		delete_option('wp_slug_translate_secondmode');
		delete_option('wp_slug_translate_deactivate');
	}
	register_deactivation_hook( __FILE__, 'wp_slug_translate_deactivate' );
}

function wp_slug_translate_settings_link($action_links,$plugin_file){
	if($plugin_file==plugin_basename(__FILE__)){
		$wst_settings_link = '<a href="options-general.php?page=' . dirname(plugin_basename(__FILE__)) . '/wp_slug_translate_admin.php">' . __("Settings") . '</a>';
		array_unshift($action_links,$wst_settings_link);
	}
	return $action_links;
}
add_filter('plugin_action_links','wp_slug_translate_settings_link',10,2); 

if(is_admin()){require_once('wp_slug_translate_admin.php');}

?>