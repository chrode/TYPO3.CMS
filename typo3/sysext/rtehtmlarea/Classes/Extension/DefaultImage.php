<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008-2011 Stanislas Rolland <typo3(arobas)sjbr.ca>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Image plugin for htmlArea RTE
 *
 * @author Stanislas Rolland <typo3(arobas)sjbr.ca>
 *
 */
class tx_rtehtmlarea_defaultimage extends tx_rtehtmlarea_api {

	protected $extensionKey = 'rtehtmlarea';	// The key of the extension that is extending htmlArea RTE
	protected $pluginName = 'DefaultImage';		// The name of the plugin registered by the extension
	protected $relativePathToLocallangFile = '';	// Path to this main locallang file of the extension relative to the extension dir.
	protected $relativePathToSkin = 'extensions/DefaultImage/skin/htmlarea.css';		// Path to the skin (css) file relative to the extension dir.
	protected $htmlAreaRTE;				// Reference to the invoking object
	protected $thisConfig;				// Reference to RTE PageTSConfig
	protected $toolbar;				// Reference to RTE toolbar array
	protected $LOCAL_LANG; 				// Frontend language array

	protected $pluginButtons = 'image';
	protected $convertToolbarForHtmlAreaArray = array (
		'image'	=> 'InsertImage',
		);

	public function main($parentObject) {
			// Check if this should be enabled based on extension configuration and Page TSConfig
			// The 'Minimal' and 'Typical' default configurations include Page TSConfig that removes images on the way to the database
		return parent::main($parentObject)
			&& !($this->thisConfig['proc.']['entryHTMLparser_db.']['tags.']['img.']['allowedAttribs'] == '0' && $this->thisConfig['proc.']['entryHTMLparser_db.']['tags.']['img.']['rmTagIfNoAttrib'] == '1');
	}
	/**
	 * Return JS configuration of the htmlArea plugins registered by the extension
	 *
	 * @param	integer		Relative id of the RTE editing area in the form
	 *
	 * @return string		JS configuration for registered plugins
	 *
	 * The returned string will be a set of JS instructions defining the configuration that will be provided to the plugin(s)
	 * Each of the instructions should be of the form:
	 * 	RTEarea['.$RTEcounter.']["buttons"]["button-id"]["property"] = "value";
	 */
	public function buildJavascriptConfiguration($RTEcounter) {
		global $TSFE, $LANG;

		$registerRTEinJavascriptString = '';
		return $registerRTEinJavascriptString;
	}

} // end of class
?>