<?php
/**
 * Bday System Plugin for Joomla 4.x/5.x
 *
 * @copyright   Copyright (C) 2023 Conseilgouz. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 */
namespace ConseilGouz\Plugin\System\Bday\Extension;

defined('_JEXEC') or die;

use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;							   

class Bday extends CMSPlugin
{
	protected $db;
	protected $loaded;
	private $dob;
    protected $extdir;
    public function onAfterRenderModules(&$buffer,$params) {
        if ($this->loaded) return;  // already loaded => exit
        $this->loaded = true;

		$currApp = $this->getApplication();
		$currUser = $this->getApplication()->getIdentity();
		if ($currApp->isClient('administrator') || strpos($_SERVER["PHP_SELF"], "index.php") === false){
			return;
		}
		if ($this->params->get("popup_test") == '1') {
		    $currMenu = $currApp->getMenu()->getActive()->id;
		    $testMenu = $this->params->get("test_menu","");
		    if (((strlen($testMenu) > 0) && ($currMenu == $testMenu)) || (strlen($testMenu) == 0 )) {
		        $users = array();
		        $users[] = $currUser;
			    $res = $this->popup($users); // happy birthday.....
			    $buffer .= $res;
			     
		    } elseif (Associations::isEnabled()) { // recherche d'une association pour le multilingue
		        $menu_asso = Associations::getAssociations('com_menus', '#__menu', 'com_menus.item', $currMenu,'id', '', '');
		        foreach ($menu_asso as $un_menu) {
		           if ($un_menu->id == $testMenu) {
		               $users = array();
		               $users[] = $currUser;
		               $res = $this->popup($users); // happy birthday.....
					   $buffer .= $res;
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
		    $res = $this->popup($users); // happy birthday.....
			$buffer .= $res;
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
		catch (\RuntimeException $e)
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
		catch (\RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
			return array();
		}
	}
	//
	// Display Popup 
	//
	private function popup($usersList) {
		HTMLHelper::_('jquery.framework'); 
		$document = Factory::getDocument();

		$this->extdir = 'plg_'.$this->_type.'_'.$this->_name;
		
		$detail = $this->params->get("detail");
		$tag_id = 'plg_bday'; 
		$width = $detail->width_popup;
		$margin = str_replace(',',' ',$detail->margin_popup);
		$opacity = $detail->opacity_popup;
		$color = $detail->color_popup;
		$scroll_height = $this->params->get("sf_height","100px");
		$scroll_width = $this->params->get("sf_width","100%");
		$scroll_speed = $this->params->get("sf_speed","10");
		$direction = ($this->params->get("direction") == "0") ? "left" : "up";

		$dirext = 'media/'.$this->extdir;
		/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
		$wa->addInlineStyle($this->params->get('css_popup',''));
		$wa->registerAndUseStyle('bdaystyle',$dirext.'/css/style.css');
		$wa->registerAndUseStyle('bdayanimate',$dirext.'/css/animate.min.css');
		if ((bool)Factory::getConfig()->get('debug')) { // Mode debug
			$document->addScript(''.URI::base(true).'/'.$dirext.'/js/bday.js'); 
		} else {
			$wa->registerAndUseScript('bday',$dirext.'/js/bday.js');
		}
		if($this->params->get('show_btn_close_popup') == 1){
			$close_popup = '<div class="plg-close-popup"></div>';
		}else{
			$close_popup = '';
		}
		$class = "class='plg_popup_wrap ";
		$cookieValue = Factory::getApplication()->input->cookie->get($tag_id);
		if($cookieValue) {
			$style = " style='display: none;' ";
			$style_btn = " style='display: none; width:auto;' "; 
		}	else { // pas de cookie: on cache le bouton
			$style = " style='display: none; ' ";
			$style_btn = " style='display: none; width:auto;' "; 
		}
		$detail = $this->params->get("detail");
		
		$pos = $this->params->get('position');
		$class .= " popup-".$pos->horizontal_popup."'";
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
			    if (!$val) { // empty value : in test mode
			        $val = " ";
			    }
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
		$multi = false;
		if ((count($usersList) > 1) ||  ($this->params->get("popup_test") == '1'))  { // plusieurs anniv. ou démo : on fait défiler....
		    if ($this->params->get("direction") == "0") { // droite gauche
		        $users = str_replace("<p>","",$users);
		        $users = str_replace("</p>","",$users);
		    }
			$multi = true;
			$users = "<div id='plg_bday_scroll' onmouseover='stopscroll();' onmouseout='goscroll();' ><div class='str_wrap' ><div class='str_move str_origin'>".$users."</div></div></div>";
		}
		// users list dans le message
		$arr_css= array("{users}"=>$users);
		$perso = $this->params->get('content_popup'); // contenu du message complet
		$perso =  str_replace("{users}",$users,$perso);
// appel plugins de contenu	
		PluginHelper::importPlugin('com_content');
		$app = Factory::getApplication(); // Joomla 4.0
		$item = new \stdClass;
		$item->text = $perso;
		$item->params = $this->params;
		try {
			$app->triggerEvent('onContentPrepare', array ('com_content.article', &$item, &$item->params, 0)); // Joomla 4.0
		} catch (\Exception $e) {
			// echo 'Exception reçue : ',  $e->getMessage(), "\n";
		}		
		$perso = $item->text;

		$html .= '<div id="plg_bday" '.$class.' '.$style.'>'.
		  	 '<div class="relative">'.$close_popup.$perso.'</div>';
	    $html .= '</div>';

		$document->addScriptOptions($this->extdir, 
		array('test' => $this->params->get("popup_test",0),'width' => $width,'background' => $color, 'margin' => $margin, "opacity" => $opacity,
		"pos" => $pos->vertical_popup,"speffect" => $this->params->get('sp-effect','fadeIn'),"scroll_width" => $scroll_width,"scroll_height" => $scroll_height,
		"scroll_direction" => $direction, "scroll_speed" => $scroll_speed,"multi" => ($multi) ? 1 : 0));

		return $html;
	}
}
