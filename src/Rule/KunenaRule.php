<?php
/**
* Plugin Bday
* Version			: 2.2.0
* Package			: Joomla 4.x/5.x
* copyright 		: Copyright (C) 2023 ConseilGouz. All rights reserved.
* license    		: https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
* From              : GMapFP Component Yahoo Météo for Joomla! 3.x
*/
namespace ConseilGouz\Plugin\System\Bday\Rule;
defined( '_JEXEC' ) or die( 'Restricted access' );
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;

class KunenaRule extends FormRule
{

	public function test(\SimpleXMLElement $element, $value, $group = null, Registry $input = null,Form $form = null) {
		if ( ($value =="joomla") && (!PluginHelper::isEnabled('user','profile')) ) {
			Factory::getApplication()->enqueueMessage('Veuillez activer le plugin profiles utilisateur','error');
			return false;
		}

		if ( ($value =="kunena") && (!ComponentHelper::isInstalled('com_kunena')) ) {
			Factory::getApplication()->enqueueMessage('Veuillez installer Kunena','error');
			return false;
		}
        return true;
		
	}
}