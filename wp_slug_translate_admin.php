<?php
function wp_slug_translate_admin(){
	add_options_page('WP Slug Translate Options', 'WP Slug Translate','manage_options', __FILE__, 'wp_slug_translate_page');
	add_action('admin_init','wp_slug_translate_register');
	add_action('admin_enqueue_scripts','wp_slug_translate_enqueue_scripts');
}

// 加载脚本和样式
function wp_slug_translate_enqueue_scripts($hook) {
	if (strpos($hook, 'wp_slug_translate') === false) {
		return;
	}

	// 加载 JavaScript
	wp_enqueue_script(
		'wp-slug-translate-admin',
		plugin_dir_url(__FILE__) . 'assets/js/admin.js',
		array('jquery'),
		'1.0.0',
		true
	);

	// 本地化脚本
	wp_localize_script('wp-slug-translate-admin', 'wp_slug_translate_data', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('wp_slug_translate_nonce')
	));
}

function wp_slug_translate_register(){
	register_setting('wst-settings','wp_slug_translate_apikey');
	register_setting('wst-settings','wp_slug_translate_apibase');
	register_setting('wst-settings','wp_slug_translate_model');
	register_setting('wst-settings','wp_slug_translate_language');
	register_setting('wst-settings','wp_slug_translate_secondmode');
	register_setting('wst-settings','wp_slug_translate_deactivate');
}
function wp_slug_translate_page(){
	function wp_slug_translate_reset(){
		update_option('wp_slug_translate_apikey','');
		update_option('wp_slug_translate_apibase','https://api.siliconflow.cn/v1');
		update_option('wp_slug_translate_model','deepseek-ai/DeepSeek-V3');
		update_option('wp_slug_translate_language','zh-CHS');
		update_option('wp_slug_translate_secondmode','');
		update_option('wp_slug_translate_deactivate','');
	}
	if(isset($_POST['wp_slug_translate_reset'])){
		if($_POST['wp_slug_translate_reset']=='reset'){
			wp_slug_translate_reset();
			echo '<div id="message" class="updated fade"><p><strong>' . __("Default settings restored!","WP-Slug-Translate") . '</strong></p></div>';
		}
	}
?>
<div class="wrap">
	
<?php screen_icon(); ?>
<h2>WP Slug Translate</h2>

<form action="options.php" method="post" enctype="multipart/form-data" name="wp_slug_translate_form">
<?php settings_fields('wst-settings'); ?>

<table class="form-table">
	<tr valign="top">
		<th scope="row">
			AI Translation Service<br />
			<span style="font-family:Tahoma,sans-serif;font-size:12px;">Compatible with OpenAI-compatible API providers like SiliconFlow, DeepSeek, etc.</span>
		</th>
		<td>
			<label>
				<input type="text" name="wp_slug_translate_apikey" value="<?php echo get_option('wp_slug_translate_apikey'); ?>" style="width:300px;height:24px;" />
				<code>API Key</code>
			</label><br />
			<label>
				<input type="text" name="wp_slug_translate_apibase" value="<?php echo get_option('wp_slug_translate_apibase'); ?>" style="width:300px;height:24px;" />
				<code>API Base URL</code>
			</label><br />
			<label>
				<code>Model Name</code>
				<select name="wp_slug_translate_model" id="wp-slug-translate-model" style="width:300px;height:28px;">
					<?php
					$models = wp_slug_translate_get_available_models();
					$current_model = get_option('wp_slug_translate_model', 'deepseek-ai/DeepSeek-V3');

					if ($models['success'] && !empty($models['models'])) {
						foreach ($models['models'] as $model_id => $model_name) {
							$selected = ($model_id == $current_model) ? 'selected="selected"' : '';
							echo '<option value="' . esc_attr($model_id) . '" ' . $selected . '>' . esc_html($model_name) . '</option>';
						}
					} else {
						// 如果没有可用模型,使用默认值
						echo '<option value="' . esc_attr($current_model) . '" selected="selected">' . esc_html($current_model) . '</option>';
					}
					?>
				</select>
				<button type="button" id="wp-slug-translate-refresh-models" class="button button-secondary" style="vertical-align: middle; height: 28px;">
					<span class="dashicons dashicons-update"></span>
					刷新
				</button>
				<span id="wp-slug-translate-models-status" style="margin-left: 10px; font-size: 13px;"></span>
				<?php if ($models['success']) : ?>
					<br /><span style="font-size: 12px; color: #666;">共 <strong id="wp-slug-translate-models-count"><?php echo $models['total']; ?></strong> 个可用模型</span>
				<?php endif; ?>
			</label>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<?php _e('Source Language','WP-Slug-Translate'); ?>
		</th>
		<td>
			From:
			<select name="wp_slug_translate_language">
				<option value="zh-CHS" <?php if (get_option('wp_slug_translate_language') == 'zh-CHS') { echo 'selected="selected"'; } ?>>zh-CHS - Chinese(Simplified)</option>
				<option value="zh-CHT" <?php if (get_option('wp_slug_translate_language') == 'zh-CHT') { echo 'selected="selected"'; } ?>>zh-CHT - Chinese(Traditional)</option>
				<option value="ar" <?php if (get_option('wp_slug_translate_language') == 'ar') { echo 'selected="selected"'; } ?>>ar - Arabic</option>
				<option value="bg" <?php if (get_option('wp_slug_translate_language') == 'bg') { echo 'selected="selected"'; } ?>>bg - Bulgarian</option>
				<option value="ca" <?php if (get_option('wp_slug_translate_language') == 'ca') { echo 'selected="selected"'; } ?>>ca - Catalan</option>
				<option value="cs" <?php if (get_option('wp_slug_translate_language') == 'cs') { echo 'selected="selected"'; } ?>>cs - Czech</option>
				<option value="da" <?php if (get_option('wp_slug_translate_language') == 'da') { echo 'selected="selected"'; } ?>>da - Danish</option>
				<option value="nl" <?php if (get_option('wp_slug_translate_language') == 'nl') { echo 'selected="selected"'; } ?>>nl - Dutch</option>
				<option value="en" <?php if (get_option('wp_slug_translate_language') == 'en') { echo 'selected="selected"'; } ?>>en - English</option>
				<option value="et" <?php if (get_option('wp_slug_translate_language') == 'et') { echo 'selected="selected"'; } ?>>et - Estonian</option>
				<option value="fi" <?php if (get_option('wp_slug_translate_language') == 'fi') { echo 'selected="selected"'; } ?>>fi - Finnish</option>
				<option value="fr" <?php if (get_option('wp_slug_translate_language') == 'fr') { echo 'selected="selected"'; } ?>>fr - French</option>
				<option value="de" <?php if (get_option('wp_slug_translate_language') == 'de') { echo 'selected="selected"'; } ?>>de - German</option>
				<option value="el" <?php if (get_option('wp_slug_translate_language') == 'el') { echo 'selected="selected"'; } ?>>el - Greek</option>
				<option value="ht" <?php if (get_option('wp_slug_translate_language') == 'ht') { echo 'selected="selected"'; } ?>>ht - Haitian Creole</option>
				<option value="he" <?php if (get_option('wp_slug_translate_language') == 'he') { echo 'selected="selected"'; } ?>>he - Hebrew</option>
				<option value="hi" <?php if (get_option('wp_slug_translate_language') == 'hi') { echo 'selected="selected"'; } ?>>hi - Hindi</option>
				<option value="mww" <?php if (get_option('wp_slug_translate_language') == 'mww') { echo 'selected="selected"'; } ?>>mww - Hmong Daw</option>
				<option value="hu" <?php if (get_option('wp_slug_translate_language') == 'hu') { echo 'selected="selected"'; } ?>>hu - Hungarian</option>
				<option value="id" <?php if (get_option('wp_slug_translate_language') == 'id') { echo 'selected="selected"'; } ?>>id - Indonesian</option>
				<option value="it" <?php if (get_option('wp_slug_translate_language') == 'it') { echo 'selected="selected"'; } ?>>it - Italian</option>
				<option value="ja" <?php if (get_option('wp_slug_translate_language') == 'ja') { echo 'selected="selected"'; } ?>>ja - Japanese</option>
				<option value="tlh" <?php if (get_option('wp_slug_translate_language') == 'tlh') { echo 'selected="selected"'; } ?>>tlh - Klingon</option>
				<option value="tlh-Qaak" <?php if (get_option('wp_slug_translate_language') == 'tlh-Qaak') { echo 'selected="selected"'; } ?>>tlh-Qaak - Klingon(pIqaD)</option>
				<option value="ko" <?php if (get_option('wp_slug_translate_language') == 'ko') { echo 'selected="selected"'; } ?>>ko - Korean</option>
				<option value="lv" <?php if (get_option('wp_slug_translate_language') == 'lv') { echo 'selected="selected"'; } ?>>lv - Latvian</option>
				<option value="lt" <?php if (get_option('wp_slug_translate_language') == 'lt') { echo 'selected="selected"'; } ?>>lt - Lithuanian</option>
				<option value="ms" <?php if (get_option('wp_slug_translate_language') == 'ms') { echo 'selected="selected"'; } ?>>ms - Malay</option>
				<option value="mt" <?php if (get_option('wp_slug_translate_language') == 'mt') { echo 'selected="selected"'; } ?>>mt - Maltese</option>
				<option value="no" <?php if (get_option('wp_slug_translate_language') == 'no') { echo 'selected="selected"'; } ?>>no - Norwegian</option>
				<option value="fa" <?php if (get_option('wp_slug_translate_language') == 'fa') { echo 'selected="selected"'; } ?>>fa - Persian</option>
				<option value="pl" <?php if (get_option('wp_slug_translate_language') == 'pl') { echo 'selected="selected"'; } ?>>pl - Polish</option>
				<option value="pt" <?php if (get_option('wp_slug_translate_language') == 'pt') { echo 'selected="selected"'; } ?>>pt - Portuguese</option>
				<option value="ro" <?php if (get_option('wp_slug_translate_language') == 'ro') { echo 'selected="selected"'; } ?>>ro - Romanian</option>
				<option value="ru" <?php if (get_option('wp_slug_translate_language') == 'ru') { echo 'selected="selected"'; } ?>>ru - Russian</option>
				<option value="sk" <?php if (get_option('wp_slug_translate_language') == 'sk') { echo 'selected="selected"'; } ?>>sk - Slovak</option>
				<option value="sl" <?php if (get_option('wp_slug_translate_language') == 'sl') { echo 'selected="selected"'; } ?>>sl - Slovenian</option>
				<option value="es" <?php if (get_option('wp_slug_translate_language') == 'es') { echo 'selected="selected"'; } ?>>es - Spanish</option>
				<option value="sv" <?php if (get_option('wp_slug_translate_language') == 'sv') { echo 'selected="selected"'; } ?>>sv - Swedish</option>
				<option value="th" <?php if (get_option('wp_slug_translate_language') == 'th') { echo 'selected="selected"'; } ?>>th - Thai</option>
				<option value="tr" <?php if (get_option('wp_slug_translate_language') == 'tr') { echo 'selected="selected"'; } ?>>tr - Turkish</option>
				<option value="uk" <?php if (get_option('wp_slug_translate_language') == 'uk') { echo 'selected="selected"'; } ?>>uk - Ukrainian</option>
				<option value="ur" <?php if (get_option('wp_slug_translate_language') == 'ur') { echo 'selected="selected"'; } ?>>ur - Urdu</option>
				<option value="vi" <?php if (get_option('wp_slug_translate_language') == 'vi') { echo 'selected="selected"'; } ?>>vi - Vietnamese</option>
				<option value="cy" <?php if (get_option('wp_slug_translate_language') == 'cy') { echo 'selected="selected"'; } ?>>cy - Welsh</option>
			</select>
			&nbsp;&nbsp;&nbsp;
			To:
			<code>en - English</code>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<?php _e('Second Mode','WP-Slug-Translate'); ?>
		</th>
		<td>
			<label>
				<input type="checkbox" name="wp_slug_translate_secondmode" value="yes" <?php if(get_option("wp_slug_translate_secondmode")=='yes') echo 'checked="checked"'; ?> />
				<?php _e('Running in the second mode.','WP-Slug-Translate'); ?>
			</label>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<?php _e('Delete Options','WP-Slug-Translate'); ?>
		</th>
		<td>
			<label>
				<input type="checkbox" name="wp_slug_translate_deactivate" value="yes" <?php if(get_option("wp_slug_translate_deactivate")=='yes') echo 'checked="checked"'; ?> />
				<?php _e('Delete options while deactivate this plugin.','WP-Slug-Translate'); ?>
			</label>
		</td>
	</tr>
</table>

<p class="submit">
<input type="submit" class="button-primary" name="Submit" value="<?php _e('Save Changes'); ?>" />
</p>

</form>

<form action="" method="post">
	<input type="hidden" name="wp_slug_translate_reset" value="reset" />
	<input type="submit" class="button" value="<?php _e('Reset'); ?>" />
</form>

<br />
<?php $fanyi_url = plugins_url('/img/fanyi.png', __FILE__);?>
<?php $donate_url = plugins_url('/img/paypal_32_32.jpg', __FILE__);?>
<?php $paypal_donate_url = plugins_url('/img/paypal_donate_email.jpg', __FILE__);?>
<?php $ali_donate_url = plugins_url('/img/alipay_donate_email.jpg', __FILE__);?>

<div class="icon32"><img src="<?php echo $fanyi_url; ?>" alt="fanyi" /></div>
<h2>Description</h2>
<p>
 1. WP Slug Translate can translate the post slug into english. It will take the post ID as slug when translation failure.<br />
 2. "AI Translation Service": Input your API Key, API Base URL and Model Name. Compatible with OpenAI-compatible providers like SiliconFlow, DeepSeek, etc.<br />
 3. "Source Language": Choose your language, 45 languages supported.<br />
 4. "Second Mode": Running in the second mode, compatible with some synchronous plugins.<br />
 5. When you have written an article, click "Publish", then the post slug will be automatically translated into English with hyphens between words.<br />
 6. For more information, please visit: <a href="https://github.com/uu0/wp-slug-translate" target="_blank">WP Slug Translate</a>
</p>

<div class="icon32"><img src="<?php echo $donate_url; ?>" alt="Donate" /></div>
<h2>Donate</h2>
<p>
If you find my work useful and you want to encourage the development of more free resources, you can do it by donating.
</p>
<p>
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=SCWY6NPFRR8EY" target="_blank"><img src="<?php echo $paypal_donate_url; ?>" alt="Paypal Donate" title="Paypal" /></a>
&nbsp;
<a href="https://www.alipay.com/" target="_blank"><img src="<?php echo $ali_donate_url; ?>" alt="Alipay Donate" title="Alipay" /></a>
</p>
<br />

<?php $blq_logo_url = plugins_url('/img/blq_32_32.jpg', __FILE__);?>
<div class="icon32"><img src="<?php echo $blq_logo_url; ?>" alt="WP Slug Translate" /></div>
<h2>Related Links</h2>
<ul style="margin:0 18px;">
<li><a href="https://github.com/uu0/wp-slug-translate" target="_blank">WP Slug Translate (GitHub)</a></li>
<li><a href="https://github.com/uu0" target="_blank">uu0 (GitHub)</a></li>
</ul>

<div style="text-align:center; margin:60px 0px 10px 0px;">&copy; <?php echo date("Y"); ?> uu0</div>

</div>
<?php 
}
add_action('admin_menu', 'wp_slug_translate_admin');
?>