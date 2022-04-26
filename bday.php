<?php
/**
 * Bday System Plugin
 *
 * @copyright   Copyright (C) 2021 Conseilgouz. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;							   

class PlgSystemBday extends CMSPlugin
{
	protected $db;
	protected $loaded;
	private $dob;
    protected $extdir;
	public function onAfterRender() {
        if ($this->loaded) return;  // already loaded => exit
        $this->loaded = true;

		$currApp = Factory::getApplication();
		$currUser = Factory::getUser();
		if ($currApp->isClient('administrator') || strpos($_SERVER["PHP_SELF"], "index.php") === false){
			return;
		}
		if ($this->params->get("popup_test") == '1') {
		    $currMenu = $currApp->getMenu()->getActive()->id;
		    $testMenu = $this->params->get("test_menu","");
		    if (((strlen($testMenu) > 0) && ($currMenu == $testMenu)) || (strlen($testMenu) == 0 )) {
		        $users = array();
		        $users[] = $currUser;
			     $this->popup($users); // happy birthday.....
		    } elseif (Associations::isEnabled()) { // recherche d'une association pour le multilingue
		        $menu_asso = Associations::getAssociations('com_menus', '#__menu', 'com_menus.item', $currMenu,'id', '', '');
		        foreach ($menu_asso as $un_menu) {
		           if ($un_menu->id == $testMenu) {
		               $users = array();
		               $users[] = $currUser;
		               $this->popup($users); // happy birthday.....
		           }
		        }
		    }
			return;
		}
		if ($currUser->guest == 1) return; // not loggued
		// Only render for HTML output
        if (Factory::getDocument()->getType() !== 'html' ) { return; }
		if ($this->params->get("all_users") == 'ALL') { // anniv. de tous les utilisateurs
			$userList = $this->getUserList(0); 
		} elseif ($this->params->get("all_users") == 'GROUP') { // un ou plusieurs groupes 
			$usergroup = $this->params->get("usergroup");
			$userList = $this->getUserListGroup($usergroup); 
		} else { // uniquement utilisateur loggued in
			$userList = $this->getUserList($currUser->id); 
		}
		$users = array();
		foreach ($userList as $user) {
			if ($this->params->get("user_info","joomla") == "joomla") {
				$dob =json_decode($user->profile_value, true) ;
			} else {
				$dob = $user->birthdate;
			}
		    if (date("md",strtotime($dob.' UTC')) == date("md")) { // dob stored in UTC mode
				$users[] = $user;
		    }
		}
		if (count($users) > 0) { // il y a des anniv.....
		    $this->popup($users); // happy birthday.....
		}
        return;
	}
	protected function getUserList($id) {
		$db    = Factory::getDbo();
		$order = "a.id";
		$query = $db->getQuery(true);
		if ($this->params->get("user_info","joomla") == "joomla") {
			$query->select($db->quoteName(array('a.id', 'a.name', 'a.username', 'a.email','profile_value')))
			->from('#__users AS a')
			->join('LEFT', '#__user_profiles AS pr ON pr.user_id = a.id')
				->where('profile_key LIKE \'profile.dob\'')
				->where('a.block = 0');
		} else { // kunena
			$query->select($db->quoteName(array('a.id', 'a.name', 'a.username', 'a.email','birthdate')))
				->from('#__users AS a')
				->join('LEFT', '#__kunena_users AS pr ON pr.userid = a.id')
				->where('a.block = 0');
		}
	    if ($id > 0) { $query->where('a.id = '.$id); }; // utilisateur en cours
		$db->setQuery($query);
		try
		{
			return (array) $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
			return array();
		}
	}
	// user groups
	protected function getUserListGroup($group) {
		$db    = Factory::getDbo();
		$order = "a.id";
		$query = $db->getQuery(true);
		if ($this->params->get("user_info","joomla") == "joomla") {
			$query->select($db->quoteName(array('a.id', 'a.name', 'a.username', 'a.email','profile_value')))
			->from('#__users AS a')
			->join('LEFT', '#__user_usergroup_map AS gr ON gr.user_id = a.id')
		    ->join('LEFT', '#__user_profiles AS pr ON pr.user_id = a.id')
			->where('profile_key LIKE \'profile.dob\'')
			->where('a.block = 0')
			->where('gr.group_id in ('.implode(",",$group).')');
		} else { // kunena
			$query->select($db->quoteName(array('a.id', 'a.name', 'a.username', 'a.email','birthdate')))
			->from('#__users AS a')
			->join('LEFT', '#__user_usergroup_map AS gr ON gr.user_id = a.id')
			->join('LEFT', '#__kunena_users AS pr ON pr.userid = a.id')
			->where('a.block = 0')
			->where('gr.group_id in ('.implode(",",$group).')');
		}
        if ($this->params->get("only_my_group") == '1') { // same user group
            $currUser = Factory::getUser();
            $query->where('gr.group_id in ('.implode(",",$currUser->groups).')');
        }
		$db->setQuery($query);
		try
		{
			return (array) $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
			return array();
		}
	}
	//
	// Display Popup 
	//
	private function popup($usersList) {
		$this->extdir = 'plg_'.$this->_type.'_'.$this->_name;
		$detail = $this->params->get("detail");
		$tag_id = 'plg_bday'; 
		$width = $detail->width_popup;
		$margin = str_replace(',',' ',$detail->margin_popup);
		$opacity = $detail->opacity_popup;
		$color = $detail->color_popup;
		$scroll_height = $this->params->get("sf_height","2em");
		$scroll_width = $this->params->get("sf_width","100%");
		$scroll_speed = $this->params->get("sf_speed","10");
		$style = '#plg_bday {'
		.'width:'.$width.'%;'
		.'background:'.$color.';'
		.'margin:'.$margin.';'
		.'opacity: '.$opacity.' !important;}'   
		.'#le_btn_plg_bday {'
		.'background-color:'.$color.';'
		.'}'
		.'#btn_plg_bday {'
		.'opacity:'.$opacity.' !important;' 
		.'}'		
		.'.plg_popup_wrap .relative{}'
		.'.plg_popup_wrap{position:fixed;width:100%;height:auto;display:none;}'
		.'.popup-top{top:0%;}'
		.'.popup-center{top:50%;}'
		.'.popup-bottom{bottom:1px;}'
		.'.popup-left{left:0px;}'
		.'.popup-right{right:0px;}'
		.'.plg_popup_wrap .relative .plg-close-popup{position:absolute;top:5px;right:5px;width: 27px;height: 25px;background:url("'.URI::root(true).'/media/'.$this->extdir.'/images/sp-close-popup.png");cursor:pointer;}'
		.'.str_move {white-space: nowrap; position: absolute;top: 0;left: 0;cursor: move;}'
		.'.str_wrap {overflow: hidden;width: '.$scroll_width.';height:'.$scroll_height.' !important;font-size: 12px;line-height: 16px;position: relative;-moz-user-select: none;-khtml-user-select: none;user-select: none;white-space: nowrap;}';
		$script = "<style>".$style.$this->params->get('css_popup')."</style>";
		$dir = URI::root(true).'/media/'.$this->extdir.'/js/velocity.min.js';
		$script .= "<script src='".$dir."' type='text/javascript'></script>";
		$dir = URI::root(true).'/media/'.$this->extdir.'/js/velocity.ui.min.js';
		$script .= "<script src='".$dir."' type='text/javascript' ></script>";
		$dir = URI::root(true).'/media/'.$this->extdir.'/js/jquery.liMarquee.min.js';
		$script .= "<script src='".$dir."' type='text/javascript' ></script>";

		if($this->params->get('show_btn_close_popup') == 1){
			$close_popup = '<div class="plg-close-popup"></div>';
		}else{
			$close_popup = '';
		}
		$class = "class='plg_popup_wrap ";
		$style = "style='left:0%' "; // on assume left
		$cookieValue = Factory::getApplication()->input->cookie->get($tag_id);
		if($cookieValue) {
			$style = "style='display: none; ";
			$style_btn = "style='display: none; width:auto; "; 
		}	else { // pas de cookie: on cache le bouton
			$style = "style='display: none; ";
			$style_btn = "style='display: none; width:auto; "; 
		}
		$detail = $this->params->get("detail");
		$width = $detail->width_popup;
		
		$pos = $this->params->get('position');
		$class .= " popup-".$pos->horizontal_popup."'";
		if($pos->vertical_popup == 'left') {
			$style .= "left:0%;' ";
			$style_btn .= "left:0%;'";
		}
		if($pos->vertical_popup == 'right') {
			$style .= "right:0%;' ";
			$style_btn .= "right:0%;' ";
		}
		if($pos->vertical_popup == 'center') {
			$center = (100 - $width) / 2;
			$style .= "left:".$center."%;' ";
			$style_btn .= "left:".$center."%' ";
		}
		$html = '';
		if ($this->params->get('title_button_popup','0') == 1) { // show title button if cookie present
			$html .= '<div id="btn_plg_bday" '.$class.' '.$style_btn.'><button id="le_btn_plg_bday" type="button">'.$this->params->get('title_button_txt','Anniv').'</button></div>';
		}
		// personnalisation de la zone users
		$users = "";
		foreach ($usersList as $user) {
			if ($this->params->get("user_info","joomla") == "joomla") {			
			    if (property_exists($user,'profile_value')) {
					$dob =json_decode($user->profile_value, true) ;
				} else {
					$dob = HtmlHelper::date('now', Text::_('DATE_FORMAT_FILTER_DATETIME'));
				}
				$age = date("Y") - date("Y",strtotime($dob.' UTC'));
			} else {
				$dob = $user->birthdate;
				$age = date("Y") - date("Y",strtotime($dob.' UTC'));
			}
			$bday = date("d/m/Y",strtotime($dob.' UTC'));
			$arr_user_css= array("{name}"=>$user->name,"{age}"=>$age,"{bday}"=>$bday,"{email}"=>$user->email);
			$perso = $this->params->get('content_user'); // contenu d'un utilisateur
			// suppression du formattage de l'éditeur
			if ($this->params->get("direction") == "0") { // droite gauche
				$perso = str_replace("<p>","",$perso);
				$perso = str_replace("</p>","",$perso);
			}
			// on remplace les zones prédéfinies
			foreach ($arr_user_css as $key => $val) {
				$perso = str_replace($key,$val,$perso);
			}
			$users .= $perso;
		}
		if ($this->params->get("popup_test") == '1') { // test mode
			$age = "25";
			$bday = date("d/m/Y");
			$arr_user_css = array("{name}"=>"Utilisateur 1","{age}"=>$age,"{bday}"=>$bday,"{email}"=>"monemail@monsite.com");
			$users = "";
			$perso = $this->params->get('content_user'); // contenu d'un utilisateur
			foreach ($arr_user_css as $key => $val) {
				$perso = str_replace($key,$val,$perso);
			}
			$users .= $perso;
			$age = "28";
			$bday = date("d/m/Y");
			$arr_user_css = array("{name}"=>"Autre utilisateur","{age}"=>$age,"{bday}"=>$bday,"{email}"=>"monemail@monsite.com");
			$perso = $this->params->get('content_user'); // contenu d'un utilisateur
			foreach ($arr_user_css as $key => $val) {
				$perso = str_replace($key,$val,$perso);
			}
			$users .= $perso;
		} 
		if ((count($usersList) > 1) ||  ($this->params->get("popup_test") == '1'))  { // plusieurs anniv. ou démo : on fait défiler....
			$users = "<div id='plg_bday_scroll'><div class='str_wrap'><div class='str_move str_origin'>".$users."</div></div></div>";
		}
		// users list dans le message
		$arr_css= array("{users}"=>$users);
		$perso = $this->params->get('content_popup'); // contenu du message complet
		$perso =  str_replace("{users}",$users,$perso);
// appel plugins de contenu	
		PluginHelper::importPlugin('com_content');
		$app = Factory::getApplication(); // Joomla 4.0
		$item = new stdClass;
		$item->text = $perso;
		$item->params = $this->params;
		try {
			$app->triggerEvent('onContentPrepare', array ('com_content.article', &$item, &$item->params, 0)); // Joomla 4.0
		} catch (Exception $e) {
			// echo 'Exception reçue : ',  $e->getMessage(), "\n";
		}		
		$perso = $item->text;

		$direction = ($this->params->get("direction") == "0") ? "left" : "up";
        $js_params = "{ direction:'".$direction."', loop: -1, scrolldelay: 0,scrollamount:".$scroll_speed.",circular:true,runshort:true,hoverstop:true,inverthover:false}";
	
		$html .= '<div id="plg_bday" '.$class.' '.$style.'>'.
		  	 '<div class="relative">'.$close_popup.$perso.'</div>';
	    $html .= '</div>';	
		$script .= "<script>jQuery(document).ready(function ($){
			var animateOptions = {
                    delay: 1000,
                    duration:800};
			function setCookie(c_name,value,exdays)
			{
				var exdate=new Date();
				exdate.setDate(exdate.getDate() + exdays);
				var c_value=escape(value) + ((exdays==null) ? '' : '; expires='+exdate.toUTCString()) + '; path=/';
				document.cookie=c_name + '=' + c_value;
			}
			$('#plg_bday .plg-close-popup').click(function(){ 
			$('#plg_bday').css('display','none');";
		if ($this->params->get("popup_test") == '0') { // mode normal : creation cookie
			$script .= "	setCookie('plg_bday',1,1); "; // duration : one day 
		}
		$script .= "	animateOptions = {delay: 100,duration:800};";
		if ($this->params->get('title_button_popup','0') == 1) { // show title button if cookie present
			$script .="$('#btn_plg_bday').velocity('transition.".$this->params->get('sp-effect','fadeIn')."',animateOptions);";
		 } 
		$script .= "});
		$('#btn_plg_bday').click(function(){ // title button
			$('#btn_plg_bday').css('display','none');
			animateOptions = {delay: 100,duration:800};
			$('#plg_bday').velocity('transition.".$this->params->get('sp-effect','fadeIn')."',animateOptions);
		});";
		$cookieValue = Factory::getApplication()->input->cookie->get($tag_id);
		if(!$cookieValue) {
			$script .= "$('#plg_bday').velocity('transition.".$this->params->get('sp-effect','fadeIn')."',animateOptions);";
		} else {
			$script .= "$('#btn_plg_bday').velocity('transition.".$this->params->get('sp-effect','fadeIn')."',animateOptions);";
		}
		$script .= "$('#plg_bday_scroll .str_wrap').liMarquee(" . $js_params . ");});</script>";
		$body = Factory::getApplication()->getBody(false);
		$body = str_replace('</body>', $html.$script.'</body>', $body);
		Factory::getApplication()->setBody($body);
		return true;
	}
}
