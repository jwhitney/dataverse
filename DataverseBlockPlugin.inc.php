<?php

/**
 * @file plugins/generic/dataverse/DataverseBlockPlugin.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DataverseBlockPlugin
 * @ingroup plugins_generic_dataverse
 *
 * @brief Class for block component of Dataverse plugin
 */

import('lib.pkp.classes.plugins.BlockPlugin');

class DataverseBlockPlugin extends BlockPlugin {
	/** @var $parentPluginName string Name of parent plugin */
	var $parentPluginName;

	function DataverseBlockPlugin($parentPluginName) {
		$this->parentPluginName = $parentPluginName;
	}

	/**
	 * Hide this plugin from the management interface (it's subsidiary)
	 */
	function getHideManagement() {
		return true;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'DataverseBlockPlugin';
	}

	/**
	 * Get the display name of this plugin.
	 * @return String
	 */
	function getDisplayName() {
		return __('plugins.generic.dataverse.block.displayName');
	}

	/**
	 * Get a description of the plugin.
	 */
	function getDescription() {
		return __('plugins.generic.dataverse.description');
	}

	/**
	 * Get the Dataverse plugin
	 * @return object
	 */
	function &getDataversePlugin() {
		$plugin =& PluginRegistry::getPlugin('generic', $this->parentPluginName);
		return $plugin;
	}

	/**
	 * Override the builtin to get the correct plugin path.
	 * @return string
	 */
	function getPluginPath() {
		$plugin =& $this->getDataversePlugin();
		return $plugin->getPluginPath();
	}
  
	/**
	 * Override the builtin to get the correct template path.
	 * @return string
	 */
	function getTemplatePath() {
		$plugin =& $this->getDataversePlugin();
		return $plugin->getTemplatePath();
	}  
  


	/**
	 * Get the HTML contents for this block.
	 * @param $templateMgr object
	 * @return $string
	 */
	function getContents(&$templateMgr) {
    // Show the block on pages of articles with Dataverse studies
		switch (Request::getRequestedPage() . '/' . Request::getRequestedOp()) {
			case 'article/view':
        $plugin =& $this->getDataversePlugin();        
        $article =& $templateMgr->get_template_vars('article');
        if(!isset($article)) return '';
        $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($article->getId());
        if (!isset($study)) return '';
        // Fetch citation for study
        $templateMgr->assign(
                'dataCitation', 
                str_replace(
                        $study->getPersistentUri(),
                        '<a href="'. $study->getPersistentUri() .'">'. $study->getPersistentUri() .'</a>',
                        $study->getDataCitation()
                        ),
                $study->getDataCitation()
                );
        $templateMgr->assign('articleId', $article->getId());
				return parent::getContents($templateMgr);
			default:
				return '';
		}    
	}

}

?>
