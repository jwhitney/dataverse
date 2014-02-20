<?php

/**
 * @file plugins/generic/dataverse/dataversePlugin.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class dataversePlugin
 * @ingroup plugins_generic_dataverse
 *
 * @brief dataverse plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('plugins.generic.dataverse.classes.DataversePackager');

require('lib/pkp/lib/swordappv2/swordappclient.php');

define('DATAVERSE_PLUGIN_HTTP_STATUS_OK', 200);
define('DATAVERSE_PLUGIN_HTTP_STATUS_CREATED', 201);
define('DATAVERSE_PLUGIN_HTTP_STATUS_NO_CONTENT', 204);
define('DATAVERSE_PLUGIN_TOU_POLICY_SEPARATOR', '---');
define('DATAVERSE_PLUGIN_SUBJECT_SEPARATOR', ';');
define('DATAVERSE_PLUGIN_CITATION_FORMAT_APA', 'APA');

// Study release options
define('DATAVERSE_PLUGIN_RELEASE_ARTICLE_ACCEPTED',  0x01);
define('DATAVERSE_PLUGIN_RELEASE_ARTICLE_PUBLISHED', 0x02);

class DataversePlugin extends GenericPlugin {

	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
     * @param $path String
	 * @return boolean True iff plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
    $success = parent::register($category, $path);
    if ($success && $this->getEnabled()) {
      // Dataverse Study objects
      $this->import('classes.DataverseStudyDAO');
      $dataverseStudyDao = new DataverseStudyDAO($this->getName());
			$returner =& DAORegistry::registerDAO('DataverseStudyDAO', $dataverseStudyDao);
      // Files associated with Dataverse studies
      $this->import('classes.DataverseFileDAO');      
      $dataverseFileDao = new DataverseFileDAO($this->getName());      
      $returner =& DAORegistry::registerDAO('DataverseFileDAO', $dataverseFileDao);
          
      // Handler for public (?) access to Dataverse-related information (i.e., terms of Use)
      HookRegistry::register('LoadHandler', array(&$this, 'setupPublicHandler'));
      // Add data citation to submissions & reading tools  
      HookRegistry::register('TemplateManager::display', array(&$this, 'handleTemplateDisplay'));
      // Add data citation to article landing page
      HookRegistry::register('Templates::Article::MoreInfo', array(&$this, 'addDataCitationArticle'));
      // Enable TinyMCEditor in textarea fields
      HookRegistry::register('TinyMCEPlugin::getEnableFields', array(&$this, 'getTinyMCEEnabledFields'));
      // Include data policy in About page
      HookRegistry::register('Templates::About::Index::Policies', array(&$this, 'addPolicyLinks'));
      // Add Dataverse deposit options to author submission suppfile form: 
      HookRegistry::register('Templates::Author::Submit::SuppFile::AdditionalMetadata', array(&$this, 'addSuppFileOptions'));
      HookRegistry::register('authorsubmitsuppfileform::initdata', array(&$this, 'suppFileFormInitData'));
      HookRegistry::register('authorsubmitsuppfileform::readuservars', array(&$this, 'suppFileFormReadUserVars'));
      HookRegistry::register('authorsubmitsuppfileform::execute', array(&$this, 'authorSuppFileFormExecute'));
      // Add Dataverse deposit options to suppfile form for completed submissions
      HookRegistry::register('Templates::Submission::SuppFile::AdditionalMetadata', array(&$this, 'addSuppFileOptions'));
      HookRegistry::register('suppfileform::initdata', array(&$this, 'suppFileFormInitData'));
      HookRegistry::register('suppfileform::readuservars', array(&$this, 'suppFileFormReadUserVars'));
      HookRegistry::register('suppfileform::execute', array(&$this, 'suppFileFormExecute'));
      // Handle suppfile insertion: prevent duplicate insertion of a suppfile
      HookRegistry::register('suppfiledao::_insertsuppfile', array(&$this, 'handleSuppFileInsertion'));
      // Handle suppfile deletion: only necessary for completed submissions
      HookRegistry::register('suppfiledao::_deletesuppfilebyid', array(&$this, 'handleSuppFileDeletion'));
      // Add form validator to check whether submission includes data files 
      HookRegistry::register('authorsubmitstep4form::Constructor', array(&$this, 'addAuthorSubmitFormValidator'));
      // Create study for author submissions
      HookRegistry::register('Author::SubmitHandler::saveSubmit', array(&$this, 'handleAuthorSubmission'));
      // Update cataloguing information when submission metadata is edited
      HookRegistry::register('metadataform::execute', array(&$this, 'handleMetadataUpdate'));
      // Release or delete studies according to editor decision
      HookRegistry::register('SectionEditorAction::unsuitableSubmission', array(&$this, 'handleUnsuitableSubmission'));
      HookRegistry::register('SectionEditorAction::recordDecision', array(&$this, 'handleEditorDecision'));
      // Release studies on article publication
      HookRegistry::register('articledao::_updatearticle', array(&$this, 'handleArticleUpdate'));
    }
    return $success;
	}

	function getDisplayName() {
		return __('plugins.generic.dataverse.displayName');
	}

	function getDescription() {
		return __('plugins.generic.dataverse.description');
	}

	function getInstallSchemaFile() {
    return $this->getPluginPath() . '/schema.xml';
	}

	function getHandlerPath() {
		return $this->getPluginPath() . '/pages/';
	}
  
	function getTemplatePath() {
    return parent::getTemplatePath() . 'templates/';
	}  
	
	/**
	 * Display verbs for the management interface.
	 */
	function getManagementVerbs() {
		$verbs = array();
    if ($this->getEnabled()) {
      $verbs[] = array('connect', __('plugins.generic.dataverse.settings.connect'));
      $verbs[] = array('select', __('plugins.generic.dataverse.settings.selectDataverse')); 
      $verbs[] = array('settings', __('plugins.generic.dataverse.settings'));
    }
		return parent::getManagementVerbs($verbs);
	}

	/**
     * Execute a management verb on this plugin
     * @param $verb string
     * @param $args array
     * @param $message string Result status message
     * @param $messageParams array Parameters for the message key
     * @return boolean
     */
    function manage($verb, $args, &$message, &$messageParams) {
      if (!parent::manage($verb, $args, $message, $messageParams)) return false;

      $templateMgr =& TemplateManager::getManager();
      $templateMgr->register_function('plugin_url', array(&$this, 'smartyPluginUrl'));
      $journal =& Request::getJournal();
      
      switch ($verb) {
        case 'connect':
          $this->import('classes.form.DataverseAuthForm');
          $form = new DataverseAuthForm($this, $journal->getId());
          if (Request::getUserVar('save')) {
            $form->readInputData();
            if ($form->validate()) {
              $form->execute();
               Request::redirect(null, 'manager', 'plugin', array('generic', $this->getName(), 'select'));
              return false;
            } else {
              $form->display();
            }
          } else {
            $form->initData();
            $form->display();
          }
          return true;
        case 'select':
          $this->import('classes.form.DataverseSelectForm');
          $form = new DataverseSelectForm($this, $journal->getId());
          if (Request::getUserVar('save')) {
            $form->readInputData();
            if ($form->validate()) {
              $form->execute();
               Request::redirect(null, 'manager', 'plugin', array('generic', $this->getName(), 'settings'));
              return false;
            } else {
              $form->display();
            }
          } else {
            $form->initData();
            $form->display();
          }          
          return true;
        case 'settings':
          $this->import('classes.form.SettingsForm');
          $form = new SettingsForm($this, $journal->getId());
          if (Request::getUserVar('save')) {
            $form->readInputData();
            if ($form->validate()) {
              $form->execute();
              Request::redirect(null, 'manager', 'plugin', array('generic'));
              return false;
            } else {
              $form->display();
            }
          } else {
            $form->initData();
            $form->display();
          }
          return true;
      }

    }

	/**
	 * Extend the {url ...} smarty to support externalFeed plugin.
	 */
	function smartyPluginUrl($params, &$smarty) {
		$path = array($this->getCategory(), $this->getName());
		if (is_array($params['path'])) {
			$params['path'] = array_merge($path, $params['path']);
		} elseif (!empty($params['path'])) {
			$params['path'] = array_merge($path, array($params['path']));
		} else {
			$params['path'] = $path;
		}

		if (!empty($params['id'])) {
			$params['path'] = array_merge($params['path'], array($params['id']));
			unset($params['id']);
		}
		return $smarty->smartyUrl($params, $smarty);
	}
  
  /**
	 * Callback invoked to set up public access to data files
	 */
	function setupPublicHandler($hookName, $params) {
		$page =& $params[0];
		if ($page == 'dataverse') {
			$op =& $params[1];
			if ($op) {
				$publicPages = array(
          'index',
          'dataAvailabilityPolicy',
					'termsOfUse',
				);

				if (in_array($op, $publicPages)) {
					define('HANDLER_CLASS', 'DataverseHandler');
					define('DATAVERSE_PLUGIN_NAME', $this->getName());
					AppLocale::requireComponents(LOCALE_COMPONENT_APPLICATION_COMMON);
					$handlerFile =& $params[2];
					$handlerFile = $this->getHandlerPath() . 'DataverseHandler.inc.php';
				}
			}
		}
	}  
  
	/**
	 * Hook callback: add data citation to submissions, published articles, and
   * reading tools.
	 */
	function handleTemplateDisplay($hookName, $args) {
		$templateMgr =& $args[0];
		$template =& $args[1];

		switch ($template) {
      case 'author/submission.tpl':      
      case 'sectionEditor/submission.tpl':
        $templateMgr->register_outputfilter(array(&$this, 'submissionOutputFilter'));
				break;      
      case 'rt/suppFiles.tpl':
      case 'rt/suppFilesView.tpl':        
      case 'rt/metadata.tpl':
        $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');        
        $article =& $templateMgr->get_template_vars('article');
        $study =& $dataverseStudyDao->getStudyBySubmissionId($article->getId());
        if (!isset($study)) return false;
        $dataverseFileDao =& DAORegistry::getDAO('DataverseFileDAO');
        $dvFiles =& $dataverseFileDao->getDataverseFilesBySubmissionId($article->getId());
        $dvFileIndex = array();
        foreach ($dvFiles as $dvFile) {
          $dvFileIndex[$dvFile->getSuppFileId()] = true;
        }
        $templateMgr->assign_by_ref('study', $study);        
        $templateMgr->assign('dvFileIndex', $dvFileIndex);
        $templateMgr->assign('dataCitation', str_replace(
                $study->getPersistentUri(),
                '<a href="'. $study->getPersistentUri() .'" target="_blank">'. $study->getPersistentUri() .'</a>',
                $study->getDataCitation()));        
        $templateMgr->display($this->getTemplatePath() .'/'. $template);
        return true;
		}
		return false;
	}

  /**
   * Output filter: add data citation to editor & author view of submission summary
   */
  function submissionOutputFilter($output, &$smarty) {
    $submission =& $smarty->get_template_vars('submission');
    if (!isset($submission)) return $output;
      
    $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
    $study =& $dataverseStudyDao->getStudyBySubmissionId($submission->getId());
    if (!isset($study)) return $output;
    
    $index = strpos($output, '<td class="label">'. __('submission.submitter'));
    if ($index !== false) {
      $newOutput = substr($output,0,$index);
      $newOutput .= '<td class="label">'.  __('plugins.generic.dataverse.dataCitation') .'</td>';
      $newOutput .= '<td class="value" colspan="2">';
      $newOutput .= str_replace($study->getPersistentUri(), '<a href="'. $study->getPersistentUri() .'">'. $study->getPersistentUri() .'</a>', $study->getDataCitation());
      $newOutput .= '</td></tr><tr>';
      $newOutput .= substr($output, $index);
      $output =& $newOutput;
    }
		$smarty->unregister_outputfilter('submissionSummaryOutputFilter');
    return $output;
	}
  
  /**
   * Add data citation to article landing page.
   * @param String $hookName
   * @param array $args
   */
  function addDataCitationArticle($hookName, $args) {
		$smarty =& $args[1];
		$output =& $args[2];
    
		$templateMgr =& TemplateManager::getManager();    
    $article =& $templateMgr->get_template_vars('article');
    
    $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
    $study =& $dataverseStudyDao->getStudyBySubmissionId($article->getId());
    if (!isset($study)) return false;
    
    $templateMgr->assign('dataCitation', str_replace(
            $study->getPersistentUri(),
            '<a href="'. $study->getPersistentUri() .'" target="_blank">'. $study->getPersistentUri() .'</a>',
            $study->getDataCitation()));    

    $output .= $templateMgr->fetch($this->getTemplatePath() . 'dataCitationArticle.tpl');
		return false;
  }  
  
  /**
   * Hook into TinyMCE for the text areas on the settings form.
   * @param String $hookName
   * @param array $args
   * @return boolean
   */
  function getTinyMCEEnabledFields($hookName, $args) {
    $fields =& $args[1];
    $fields = array(
        'dataAvailability',
        'termsOfUse',
        );
    return false;
  }

  /**
   * Add link to data availability policy
   * @param String $hookName
   * @param array $args
   */
  function addPolicyLinks($hookName, $args) {
    $journal =& Request::getJournal();
    $dataPAvailability = $this->getSetting($journal->getId(), 'dataAvailability');
    if (!empty($dataPAvailability)) {
      $smarty =& $args[1];
      $output =& $args[2];
      $templateMgr =& TemplateManager::getManager();    
      $output .= '<li>&#187; <a href="'. $templateMgr->smartyUrl(array('page' => 'dataverse', 'op'=>'dataAvailabilityPolicy'), $smarty) .'">';
      $output .= __('plugins.generic.dataverse.settings.dataAvailabilityPolicy');
      $output .= '</a></li>';
    }
    return false;
  }
  
  /**
   * Add Dataverse deposit options to suppfile form
   * @param string $hookName
   * @param array $args
   */
  function addSuppFileOptions($hookName, $args) {
    $smarty =& $args[1];
    $output =& $args[2];
    $journal =& Request::getJournal();
    
    // Show study details, if a study exists for this article
    $articleId = $smarty->get_template_vars('articleId');    
    $dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
    $study = $dataverseStudyDao->getStudyBySubmissionId($articleId);
    if (isset($study)) {
      $smarty->assign('dataCitation', 
              str_replace(
                      $study->getPersistentUri(),
                      '<a href="'. $study->getPersistentUri() .'">'. $study->getPersistentUri() .'</a>',
                      $study->getDataCitation()));
    }
    $output .= $smarty->fetch($this->getTemplatePath() . 'suppFileOptions.tpl');
    return false;
  }
  
  /**
   * Initialize suppfile form with Dataverse-specific metadata
   * @param type $hookName
   * @param type $args
   */
  function suppFileFormInitData($hookName, $args) {
    $form =& $args[0];
    $dvFile = null;
    if (isset($form->suppFile) && $form->suppFile->getId()) {
      $dvFileDao =& DAORegistry::getDAO('DataverseFileDAO');
      $dvFile =& $dvFileDao->getDataverseFileBySuppFileId($form->suppFile->getId(), isset($form->article) ? $form->article->getId() : $form->articleId);
    }
    $form->setData('depositSuppFile', isset($dvFile));
    return false;
  }

  /**
   * Read form data
   * @param string $hookName
   * @param array $args
   */
  function suppFileFormReadUserVars($hookName, $args) {
    $form =& $args[0];
    $vars =& $args[1];
    // Add Dataverse-specific fields to list of form vars
    $vars[] = 'depositSuppFile';
    return false;
  }
  
  /**
   * Handle Dataverse-specific options in suppfile forms for new submissions
   * @param string $hookName
   * @param array $args
   */
  function authorSuppFileFormExecute($hookName, $args) {
    $form =& $args[0];
    if ($form->suppFile && $form->suppFile->getId()) {
      $dvFileDao =& DAORegistry::getDAO('DataverseFileDAO');
      // This is the initial submission: no study has been created, no files yet sent to Dataverse. 
      $dvFile = $dvFileDao->getDataverseFileBySuppFileId($form->suppFile->getId(), $form->articleId);
      if ($form->getData('depositSuppFile') && !isset($dvFile)) {
        // File not yet marked for deposit 
        $this->import('classes.DataverseFile');
        $dvFile = new DataverseFile();
        $dvFile->setSuppFileId($form->suppFile->getId());
        $dvFile->setSubmissionId($form->articleId);
        $dvFileDao->insertDataverseFile($dvFile);
      }
      if (!$form->getData('depositSuppFile') && isset($dvFile)) {
        // File exists in database but not marked for deposit -- delete it
        $dvFileDao->deleteDataverseFile($dvFile);        
      }
    }
    return false;
  }
  
  /**
   * Handle Dataverse-specific options in submitted suppfile form
   * @param string $hookName
   * @param array $args
   */
  function suppFileFormExecute($hookName, $args) {  
    $form =& $args[0];
    
    // If $form->suppFile does not have an ID, it is a new suppfile.
    if (!$form->suppFile->getId()) {
      $form->setSuppFileData($form->suppFile);
      $suppFileDao =& DAORegistry::getDAO('SuppFileDAO');
      $suppFileDao->insertSuppFile($form->suppFile);
      $form->suppFileId = $form->suppFile->getId();
      
      // Reload $form->suppFile from DAO to populate parent class attributes
      $form->suppFile =& $suppFileDao->getSuppFile($form->suppFileId, $form->article->getId());
    }
      
    // $form->suppFile now has an ID but may not have a file ID: suppfile form
    // can be submitted without uploading a file. 
    if (!$form->suppFile->getFileId()) return false;

    $dvStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');      
    $dvFileDao =& DAORegistry::getDAO('DataverseFileDAO');
    if ($form->getData('depositSuppFile')) {
      // If the file is already in Dataverse, may need to update study information
      $dvFile =& $dvFileDao->getDataverseFileBySuppFileId($form->suppFile->getId(), $form->article->getId());
      if (isset($dvFile)) {
        $study =& $dvStudyDao->getStudyBySubmissionId($form->article->getId());
        $this->updateStudy($form->article, $study) ? $this->_sendNotification('plugins.generic.dataverse.notification.studyUpdated', NOTIFICATION_TYPE_SUCCESS) : 
          $this->_sendNotification('plugins.generic.dataverse.notification.errorUpdatingStudy', NOTIFICATION_TYPE_ERROR);
        return true;
      }
      
      // If a study does not exist for this submission, create one
      $study =& $dvStudyDao->getStudyBySubmissionId($form->article->getId());
      if (!isset($study)) { 
        $study =& $this->createStudy($form->article);
        if (isset($study)) {
          $this->_sendNotification('plugins.generic.dataverse.notification.studyCreated', NOTIFICATION_TYPE_SUCCESS);
        }
        else {
          $this->_sendNotification('plugins.generic.dataverse.notification.errorCreatingStudy', NOTIFICATION_TYPE_ERROR);
          return false; /** @fixme notify, failed to create study */          
        }
      }
      
      // Study exists or has been created. Add suppfile to study
      $dvFile =& $this->addFileToStudy($study, $form->suppFile);
      isset($dvFile) ? $this->_sendNotification('plugins.generic.dataverse.notification.fileAdded', NOTIFICATION_TYPE_SUCCESS) : 
          $this->_sendNotification('plugins.generic.dataverse.notification.errorAddingFile', NOTIFICATION_TYPE_ERROR);
    }
    else {
      // Deposit is not checked. If file is not in Dataverse, do nothing.
      $dvFile =& $dvFileDao->getDataverseFileBySuppFileId($form->suppFile->getId(), $form->article->getId());
      if (!isset($dvFile)) return false;
      
      // Otherwise, delete file from Dataverse and update article suppfile settings.
      if (!$this->deleteFile($dvFile)) {
        $this->_sendNotification('plugins.generic.dataverse.notification.errorDeletingFile', NOTIFICATION_TYPE_ERROR);
        return false;
      }
      $dvFileDao->deleteDataverseFile($dvFile);
      // Deleting a file may affect study cataloguing information & data citation
      $study =& $dvStudyDao->getStudyBySubmissionId($dvFile->getSubmissionId());
      $this->updateStudy($form->article, $study);      
      $this->_sendNotification('plugins.generic.dataverse.notification.fileDeleted', NOTIFICATION_TYPE_SUCCESS);                
    }
  }
  
  /**
   * Prevent re-insertion of suppfile inserted by SuppFileForm::execute callback
   * @param string $hookName
   * @param array $args
   * @return boolean
   */
  function handleSuppFileInsertion($hookName, $args) {
    $params =& $args[1];
    $fileId = $params[1];    
    $articleId = $params[2];
    
    $suppFileDao =& DAORegistry::getDAO('SuppFileDAO');
    return $suppFileDao->suppFileExistsByFileId($articleId, $fileId);
  }
  
  /**
   * Remove data file from Dataverse study, if present
   * @param type $hookName
   * @param type $args
   */
  function handleSuppFileDeletion($hookName, $args) {
    $params =& $args[1];
    $suppFileId = is_array($params) ? $params[0] : $params;
    $submissionId = is_array($params) ? $params[1] : '';
    
    // Does a Dataverse file exist for this suppfile?
    $dvFileDao =& DAORegistry::getDAO('DataverseFileDAO');
    $dvFile =& $dvFileDao->getDataverseFileBySuppFileId($suppFileId, $submissionId ? $submissionId : '');
    if (!isset($dvFile)) return false;

    // If submission is incomplete, file will not yet be in Dataverse
    $dvFileDeposited = false;
    if ($dvFile->getContentSourceUri()) {
      // File is in Dataverse. Set flag so we can notify later.
      $dvFileDeposited = true;
      if (!$this->deleteFile($dvFile)) {
        $this->_sendNotification('plugins.generic.dataverse.notification.errorDeletingFile', NOTIFICATION_TYPE_ERROR);
        return false;
      }
    }
    $dvFileDao->deleteDataverseFile($dvFile);
    // Deleting the file may require an update to study metadata
    $dvStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
    $study =& $dvStudyDao->getStudyBySubmissionId($dvFile->getSubmissionId());
    $articleDao =& DAORegistry::getDAO('ArticleDAO');
    $journal =& Request::getJournal();    
    $article =& $articleDao->getArticle($study->getSubmissionId(), $journal->getId(), true);
    if (isset($study) && isset($article)) {
      $this->updateStudy($article, $study);
    }
    
    if ($dvFileDeposited) $this->_sendNotification('plugins.generic.dataverse.notification.fileDeleted', NOTIFICATION_TYPE_SUCCESS);
    return false;
  }
  
  /**
   * Add a custom form validator to verify data files included with submission
   * @param string $hookName
   * @param array $args
   */
  function addAuthorSubmitFormValidator($hookName, $args) {
    $form =& $args[0];
    $form->addCheck(new FormValidatorCustom($form, '', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.requireDataError', array(&$this, 'validateRequiredData'), array($form)));
  }
  
  /**
   * Verify data files have been provided, if required 
   * @return boolean
   */
  function validateRequiredData($fieldValue, $form) {
    $journal =& Request::getJournal();
    if (!$this->getSetting($journal->getId(), 'requireData')) return true;
    // Data files must be provided.
    $dvFileDao =& DAORegistry::getDAO('DataverseFileDAO');
    $dvFiles =& $dvFileDao->getDataverseFilesBySubmissionId($form->articleId);
    return count($dvFiles);
  }
  
  /**
   * Callback is invoked when author completes submission. Create draft study
   * if author has uploaded data files.
   * @param string $hookName   * @param array $args
   */
  function handleAuthorSubmission($hookName, $args) {
    $step =& $args[0];
    $article =& $args[1];
    if ($step == 5) {
      // Author has completed submission. Check if submission has suppfiles to deposit.
      $dvFileDao =& DAORegistry::getDAO('DataverseFileDAO');
      $dvFiles =& $dvFileDao->getDataverseFilesBySubmissionId($article->getId());
      if ($dvFiles) {
        // Create a study for the new submission
        $study =& $this->createStudy($article, $dvFiles);
        isset($study) ? $this->_sendNotification('plugins.generic.dataverse.notification.studyCreated', NOTIFICATION_TYPE_SUCCESS) : 
          $this->_sendNotification('plugins.generic.dataverse.notification.errorCreatingStudy', NOTIFICATION_TYPE_ERROR);
      }
    }
    return false;
  }
  
  /**
   * If submission has a Dataverse study, update cataloguing information
   * @param string $hookName
   * @param array $args
   */
  function handleMetadataUpdate($hookName, $args) {
    $form =& $args[0];
    $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
    $study =& $dataverseStudyDao->getStudyBySubmissionId($form->article->getId());
    if (!isset($study)) return false;
    
    // Update & notify
    $study =& $this->updateStudy($form->article, $study);
    isset($study) ? $this->_sendNotification('plugins.generic.dataverse.notification.studyUpdated', NOTIFICATION_TYPE_SUCCESS) :
          $this->_sendNotification('plugins.generic.dataverse.notification.errorUpdatingStudy', NOTIFICATION_TYPE_ERROR);
    return false;
  }
  
  /**
   * Callback is invoked when an editor records a decision on a submission. 
   * @param String $hookName
   * @param array $args
   */
  function handleEditorDecision($hookName, $args) {
    $submission =& $args[0];
    $decision =& $args[1];
     
    // Plugin may be configured to release on publication: defer decision 
    if ($this->getSetting($submission->getJournalId(), 'studyRelease') == DATAVERSE_PLUGIN_RELEASE_ARTICLE_PUBLISHED) {
      return false;
    }

    // Find study associated with submission
    $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
    $study =& $dataverseStudyDao->getStudyBySubmissionId($submission->getId());
    
    if (isset($study)) {
      // Editor decision on a submission with a draft study in Dataverse
      if ($decision['decision'] == SUBMISSION_EDITOR_DECISION_ACCEPT) {
        $this->releaseStudy($study) ? 
          $this->_sendNotification('plugins.generic.dataverse.notification.studyReleased', NOTIFICATION_TYPE_SUCCESS) :
          $this->_sendNotification('plugins.generic.dataverse.notification.errorReleasingStudy', NOTIFICATION_TYPE_ERROR);
      }
      if ($decision['decision'] == SUBMISSION_EDITOR_DECISION_DECLINE) {
        // Draft studies will be deleted; released studies will be deaccesioned
        $this->deleteStudy($study) ? 
          $this->_sendNotification('plugins.generic.dataverse.notification.studyDeleted', NOTIFICATION_TYPE_SUCCESS) :
          $this->_sendNotification('plugins.generic.dataverse.notification.errorDeletingStudy', NOTIFICATION_TYPE_ERROR);
      }
    }
    return false;
  }
  
  /**
   * Release study on article publication
   * @param string $hookName
   * @param array $args
   */
  function handleArticleUpdate($hookName, $args) {
    $journal =& Request::getJournal();
    $params =& $args[1];
    $articleId = $params[count($params)-1];
    $status = $params[6];
    
    if ($this->getSetting($journal->getId(), 'studyRelease') == DATAVERSE_PLUGIN_RELEASE_ARTICLE_PUBLISHED) {
      // See if study exists for submission
      $dvStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
      $study =& $dvStudyDao->getStudyBySubmissionId($articleId);
      if (isset($study) && $status == STATUS_PUBLISHED) { 
        /** @fixme notify here but  don't swamp w/ study-released notifications when an issue is published */
        $this->releaseStudy($study); 
      }
    }
    return false;
  }
  
  /**
   * Callback invoked when editor rejects unsuitable submissions
   * @param string $hookName
   * @param array $args
   */
  function handleUnsuitableSubmission($hookName, $args) {
    $submission =& $args[0];    
    $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
    $study =& $dataverseStudyDao->getStudyBySubmissionId($submission->getId());
    if (isset($study)) {
        $this->deleteStudy($study) ? 
          $this->_sendNotification('plugins.generic.dataverse.notification.studyDeleted', NOTIFICATION_TYPE_SUCCESS) :
          $this->_sendNotification('plugins.generic.dataverse.notification.errorDeletingStudy', NOTIFICATION_TYPE_ERROR);
    }
    return false;    
  }
  
  /**
   * Request service document at specified URL
   * 
   * @param string $sdUrl service document URL
   * @param type $user username 
   * @param type $password password
   * @param type $onBehalfOf issue request on behalf of user $onBehalfOf
   */
  function getServiceDocument($sdUrl, $user, $password, $onBehalfOf = NULL) {
    // allow insecure SSL connections
    $client = $this->_initSwordClient();
    return $client->servicedocument($sdUrl, $user, $password, $onBehalfOf);
  } 
  
  /**
   * Request terms of use of Dataverse configured for plugin
   * @return string
   */
  function getTermsOfUse() {
    $journal =& Request::getJournal();
    $sd = $this->getServiceDocument(
            $this->getSetting($journal->getId(), 'sdUri'), 
            $this->getSetting($journal->getId(), 'username'), 
            $this->getSetting($journal->getId(), 'password')
          );
    
    $dvTermsOfUse = '';
    if ($sd->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_OK) {
      $dvUri = $this->getSetting($journal->getId(), 'dvUri');
        
      // Find workspaces defined in service document
      foreach ($sd->sac_workspaces as $workspace) {
        foreach ($workspace->sac_collections as $collection) {
          if ($collection->sac_href[0] == $dvUri) {
            // TOU constructed from policies at dataverse, collection, study
            //  levels and separated by hyphens. Kludge in some line breaks.
            $dvTermsOfUse = str_replace(
                    DATAVERSE_PLUGIN_TOU_POLICY_SEPARATOR, 
                    '<br/>'. DATAVERSE_PLUGIN_TOU_POLICY_SEPARATOR .'<br/>', 
                    $collection->sac_collpolicy
                    );
            // Store DV terms of use as a fallback 
            /** @fixme excessive */
            $this->updateSetting($journal->getId(), 'dvTermsOfUse', $dvTermsOfUse, 'string');
            break;
          }
        }
      }
    }
    return $dvTermsOfUse;
  }

  /**
   * Create a Dataverse study
   * @param Article $article
   * @return DataverseStudy
   */
  function &createStudy(&$article, $dvFiles = array()) {
    $journal =& Request::getJournal();
    
    // Go no further if plugin is not configured.
    if (!$this->getSetting($journal->getId(), 'dvUri')) return false;

    $packager = new DataversePackager();
    $suppFileDao =& DAORegistry::getDAO('SuppFileDAO');     
    
    // Add article metadata
    $packager->addMetadata('title', $article->getLocalizedTitle());
    $packager->addMetadata('description', $article->getLocalizedAbstract());
    foreach ($article->getAuthors() as $author) {
      $packager->addMetadata('creator', $author->getFullName(true));
    }
    // subject: academic disciplines
    $split = '/\s*'. DATAVERSE_PLUGIN_SUBJECT_SEPARATOR .'\s*/';
    foreach(preg_split($split, $article->getLocalizedDiscipline(), NULL, PREG_SPLIT_NO_EMPTY) as $subject) {
      $packager->addMetadata('subject', $subject);
    }
    // subject: subject classifications
    foreach(preg_split($split, $article->getLocalizedSubjectClass(), NULL, PREG_SPLIT_NO_EMPTY) as $subject) {
      $packager->addMetadata('subject', $subject);
    }
    // subject:  keywords    
    foreach(preg_split($split, $article->getLocalizedSubject(), NULL, PREG_SPLIT_NO_EMPTY) as $subject) {
      $packager->addMetadata('subject', $subject);
    }
    // geographic coverage
    foreach(preg_split($split, $article->getLocalizedCoverageGeo(), NULL, PREG_SPLIT_NO_EMPTY) as $coverage) {
      $packager->addMetadata('coverage', $coverage);
    }
    // publisher
    $packager->addMetadata('publisher', $journal->getSetting('publisherInstitution'));
    // rights
    $packager->addMetadata('rights', $journal->getLocalizedSetting('copyrightNotice'));
    // isReferencedBy
    $packager->addMetadata('isReferencedBy', $this->getCitation($article));
    // Include (some) suppfile metadata in study
    foreach ($dvFiles as $dvFile) {
      $suppFile =& $suppFileDao->getSuppFile($dvFile->getSuppFileId(), $article->getId());
      if (isset($suppFile)) {
        // subject
        foreach(preg_split($split, $suppFile->getSuppFileSubject(), NULL, PREG_SPLIT_NO_EMPTY) as $subject) {
          $packager->addMetadata('subject', $subject);
        }
        // Type of file
        if ($suppFile->getType()) $packager->addMetadata('type', $suppFile->getType());
        // Type of file, user-defined:
        if ($suppFile->getSuppFileTypeOther()) $packager->addMetadata('type', $suppFile->getSuppFileTypeOther());
      }
    }
    // Write Atom entry file
    $packager->createAtomEntry();
    
    // Create the study in Dataverse
    $client = $this->_initSwordClient();
    $depositReceipt = $client->depositAtomEntry(
            $this->getSetting($article->getJournalId(), 'dvUri'), 
            $this->getSetting($article->getJournalId(), 'username'), 
            $this->getSetting($article->getJournalId(), 'password'),
            '',  // on behalf of: no one
            $packager->getAtomEntryFilePath());
    
    // Exit & notify if study failed to be created
    if ($depositReceipt->sac_status != DATAVERSE_PLUGIN_HTTP_STATUS_CREATED) return false;
    
    // Insert new Dataverse study for this submission
    $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');      
        
    $this->import('classes.DataverseStudy');
    $study = new DataverseStudy();
    $study->setSubmissionId($article->getId());
    $study->setEditUri($depositReceipt->sac_edit_iri);
    $study->setEditMediaUri($depositReceipt->sac_edit_media_iri);
    $study->setStatementUri($depositReceipt->sac_state_iri_atom);
    $study->setDataCitation($depositReceipt->sac_dcterms['bibliographicCitation'][0]);
        
    // Persistent URI may be present, as an altenate 
    foreach ($depositReceipt->sac_links as $link) {
      if ($link->sac_linkrel == 'alternate') {
        $study->setPersistentUri($link->sac_linkhref);
        break;
      }
    }
    $dataverseStudyDao->insertStudy($study);
    
    // Fine. Now add the files, if any are present.
    for ($i=0; $i<sizeof($dvFiles); $i++) {
      $dvFile =& $dvFiles[$i];
      $suppFile =& $suppFileDao->getSuppFile($dvFile->getSuppFileId(), $article->getId());
      /** @fixme add path & original filename, not the object */
      $dvFileIndex[str_replace(' ', '_', $suppFile->getOriginalFileName())] =& $dvFile;      
      $packager->addFile($suppFile);
    }
    
    // Create the deposit package & add package to Dataverse
    $packager->createPackage();
    $depositReceipt = $client->deposit(
            $study->getEditMediaUri(),
            $this->getSetting($journal->getId(), 'username'),
            $this->getSetting($journal->getId(), 'password'),
            '', // on behalf of: no one
            $packager->getPackageFilePath(),
            $packager->getPackaging(),
            $packager->getContentType(),
            false); // in progress? false 
    
    if ($depositReceipt->sac_status != DATAVERSE_PLUGIN_HTTP_STATUS_CREATED) return false;
    
    // Get the study statement & update the local file list
    $studyStatement = $client->retrieveAtomStatement(
            $study->getStatementUri(),
            $this->getSetting($journal->getId(), 'username'),
            $this->getSetting($journal->getId(), 'password'),
            '' // on behalf of
          );
    
    if (!isset($studyStatement)) return false;

    // Update each Dataverse file with study id & content source URI
    $dvFileDao =& DAORegistry::getDAO('DataverseFileDAO');
    foreach ($studyStatement->sac_entries as $entry) {
      $dvUriFileName = substr($entry->sac_content_source, strrpos($entry->sac_content_source, '/')+1);
      if (array_key_exists($dvUriFileName, $dvFileIndex)) {
        $dvFile =& $dvFileIndex[$dvUriFileName];
        $dvFile->setContentSourceUri($entry->sac_content_source);
        $dvFile->setStudyId($study->getId());
        $dvFileDao->updateDataverseFile($dvFile);
      }
    }

    // Done.
    return $study;
  }
  
  /**
   * Update cataloguing information for an existing study
   * @param Article $article
   * @param DataverseStudy $study
   */
  function &updateStudy(&$article, &$study) {
    $journal =& Request::getJournal();    
    $packager = new DataversePackager();
    // Add article metadata
    $packager->addMetadata('title', $article->getLocalizedTitle());
    $packager->addMetadata('description', $article->getLocalizedAbstract());
    foreach ($article->getAuthors() as $author) {
      $packager->addMetadata('creator', $author->getFullName(true));
    }
    // subject: academic disciplines
    $split = '/\s*'. DATAVERSE_PLUGIN_SUBJECT_SEPARATOR .'\s*/';
    foreach(preg_split($split, $article->getLocalizedDiscipline(), NULL, PREG_SPLIT_NO_EMPTY) as $subject) {
      $packager->addMetadata('subject', $subject);
    }
    // subject: subject classifications
    foreach(preg_split($split, $article->getLocalizedSubjectClass(), NULL, PREG_SPLIT_NO_EMPTY) as $subject) {
      $packager->addMetadata('subject', $subject);
    }
    // subject:  keywords    
    foreach(preg_split($split, $article->getLocalizedSubject(), NULL, PREG_SPLIT_NO_EMPTY) as $subject) {
      $packager->addMetadata('subject', $subject);
    }    
    // geographic coverage
    foreach(preg_split($split, $article->getLocalizedCoverageGeo(), NULL, PREG_SPLIT_NO_EMPTY) as $coverage) {
      $packager->addMetadata('coverage', $coverage);
    }
    // rights
    $packager->addMetadata('rights', $journal->getLocalizedSetting('copyrightNotice'));
    // publisher
    $packager->addMetadata('publisher', $journal->getSetting('publisherInstitution'));
    // metadata for published articles: public IDs, publication dates
    $pubIdAttributes = array();    
    if ($article->getStatus()==STATUS_PUBLISHED) {
      // publication date
      $publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
      $publishedArticle =& $publishedArticleDao->getPublishedArticleByArticleId($article->getId(), $article->getJournalId(), TRUE);
      $packager->addMetadata('date', strftime('%Y-%m-%d', strtotime($publishedArticle->getDatePublished())));
      // isReferencedBy: If article is published, add a persistent URL to citation using specified pubid plugin
      $pubIdPlugin =& PluginRegistry::getPlugin('pubIds', $this->getSetting($article->getJournalId(), 'pubIdPlugin'));
      if ($pubIdPlugin && $pubIdPlugin->getEnabled()) {
        $pubIdAttributes['agency'] = $pubIdPlugin->getDisplayName();
        $pubIdAttributes['IDNo'] = $article->getPubId($pubIdPlugin->getPubIdType());
        $pubIdAttributes['holdingsURI'] = $pubIdPlugin->getResolvingUrl($article->getJournalId(), $pubIdAttributes['IDNo']);
      }
      else {
        // If no pub id plugin selected, use OJS URL
        $pubIdAttributes['holdingsURI'] = Request::url($journal->getPath(), 'article', 'view', array($article->getId()));
      }
    }
    // isReferencedBy
    $packager->addMetadata('isReferencedBy', $this->getCitation($article), $pubIdAttributes);
    // Include (some) suppfile metadata in study
    $suppFileDao =& DAORegistry::getDAO('SuppFileDAO');    
    $dataverseFileDao =& DAORegistry::getDAO('DataverseFileDAO');
    $dvFiles =& $dataverseFileDao->getDataverseFilesByStudyId($study->getId());
    foreach ($dvFiles as $dvFile) {
      $suppFile =& $suppFileDao->getSuppFile($dvFile->getSuppFileId(), $article->getId());
      if (isset($suppFile)) {
        // subject
        foreach(preg_split($split, $suppFile->getSuppFileSubject(), NULL, PREG_SPLIT_NO_EMPTY) as $subject) {
          $packager->addMetadata('subject', $subject);
        }
        // Type of file
        if ($suppFile->getType()) $packager->addMetadata('type', $suppFile->getType());
        // Type of file, user-defined:
        if ($suppFile->getSuppFileTypeOther()) $packager->addMetadata('type', $suppFile->getSuppFileTypeOther());
      }
    }    
    // Write atom entry to file
    $packager->createAtomEntry();
    
    // Update the study in Dataverse
    $client = $this->_initSwordClient();
    $depositReceipt = $client->replaceMetadata(
            $study->getEditUri(),
            $this->getSetting($article->getJournalId(), 'username'), 
            $this->getSetting($article->getJournalId(), 'password'),            
            '', // on behalf of
            $packager->getAtomEntryFilePath());
    
    if ($depositReceipt->sac_status != DATAVERSE_PLUGIN_HTTP_STATUS_OK) return false;

    // Updating the metadata may have updated the data citation
    $study->setDataCitation($depositReceipt->sac_dcterms['bibliographicCitation'][0]);
    $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
    $dataverseStudyDao->updateStudy($study);

    return $study;
  }

  /**
   * Add a file to an existing study
   * @param DataverseStudy $study
   * @param DataverseFile $dvFile
   */
  function &addFileToStudy(&$study, &$suppFile) {
    $packager = new DataversePackager();
    $packager->addFile($suppFile);
    $packager->createPackage();
    
    // Deposit the package
    $journal =& Request::getJournal();
    $client = $this->_initSwordClient();    
    $depositReceipt = $client->deposit(
            $study->getEditMediaUri(),
            $this->getSetting($journal->getId(), 'username'),
            $this->getSetting($journal->getId(), 'password'),
            '', // on behalf of: no one
            $packager->getPackageFilePath(),
            $packager->getPackaging(),
            $packager->getContentType(),
            false); // in progress? false 
    
    if ($depositReceipt->sac_status != DATAVERSE_PLUGIN_HTTP_STATUS_CREATED) return false;
    
    // Get the study statement & update the Dataverse file with content source URI
    $studyStatement = $client->retrieveAtomStatement(
            $study->getStatementUri(),
            $this->getSetting($journal->getId(), 'username'),
            $this->getSetting($journal->getId(), 'password'),
            '' // on behalf of
          );

    // Need the study statement to update Dataverse files
    if (!isset($studyStatement)) return false;

    // Create a new Dataverse file for inserted suppfile
    $this->import('classes.DataverseFile');
    $dvFile = new DataverseFile();
    $dvFile->setSuppFileId($suppFile->getId());
    $dvFile->setStudyId($study->getId());
    $dvFile->setSubmissionId($study->getSubmissionId());

    foreach ($studyStatement->sac_entries as $entry) {
      $dvUriFileName = substr($entry->sac_content_source, strrpos($entry->sac_content_source, '/')+1);
      if ($dvUriFileName == str_replace(' ', '_', $suppFile->getOriginalFileName())) {
        $dvFile->setContentSourceUri($entry->sac_content_source);
        break;
      }
    }
    /** @fixme what if we can't relate the file to a statement entry? */     
    if (!$dvFile->getContentSourceUri()) return false;
    
    $dvFileDao =& DAORegistry::getDAO('DataverseFileDAO');
    $dvFileDao->insertDataverseFile($dvFile);
    
    // Finally, file may have metadata that needs to be in study cataloguing information
    $articleDao =& DAORegistry::getDAO('ArticleDAO');
    $article =& $articleDao->getArticle($study->getSubmissionId(), $journal->getId(), true);
    $this->updateStudy($article, $study);

    return $dvFile;
  }
  
  /**
   * Release draft study
   * @param DataverseStudy $study
   */
  function releaseStudy(&$study) {
    $journal =& Request::getJournal();              
    $client = $this->_initSwordClient();    
    $response = $client->completeIncompleteDeposit(
            $study->getEditUri(),
            $this->getSetting($journal->getId(), 'username'),
            $this->getSetting($journal->getId(), 'password'),   
            ''); // on behalf of
    
    if ($response->sac_status == 200) {
      // Ok! Study released. Notify journal manager if Dataverse not released.
      $dvDepositReciept = $client->retrieveDepositReceipt(
              $this->getSetting($journal->getId(), 'dvUri'), 
              $this->getSetting($journal->getId(), 'username'),
              $this->getSetting($journal->getId(), 'password'), 
              ''); // on behalf of
      if ($dvDepositReciept->sac_status == 200) {
        $dvDepositReceiptXml = @new SimpleXMLElement($dvDepositReciept->sac_xml);
        $dvReleasedNodes = $dvDepositReceiptXml->children('http://purl.org/net/sword/terms/state')->dataverseHasBeenReleased;
        if (!empty($dvReleasedNodes) && $dvReleasedNodes[0] == 'false') {
          // Notify the JM the DV must be released
          $request =& Application::getRequest();          
          import('classes.notification.NotificationManager');
          $notificationManager = new NotificationManager();
          $roleDao =& DAORegistry::getDAO('RoleDAO');
          $journalManagers =& $roleDao->getUsersByRoleId(ROLE_ID_JOURNAL_MANAGER, $journal->getId());
          $contents = __('plugins.generic.dataverse.notification.releaseDataverse', array('dvnUri' => $this->getSetting($journal->getId(), 'dvnUri')));
          while ($journalManagers && !$journalManagers->eof()) {
            $journalManager =& $journalManagers->next();
            $notificationManager->createNotification(
                  $request,
                  $journalManager->getId(),
                  NOTIFICATION_TYPE_ERROR,
                  $journal->getId(),
                  ASSOC_TYPE_JOURNAL,
                  $journal->getId(),
                  NOTIFICATION_LEVEL_NORMAL,
                  array('contents' => $contents)
                );
            unset($journalManager);
          } // end notifying JMs
        } // end if (dv not released)
      } // end if (deposit receipt retrieved)
      // Study was released
      return true;
    }
    return false;
  }
  
  
  /**
   * Delete draft study or deaccession released study
   * @fixme iff deleting a draft of a previously-released study, update citation.
   * @param DataverseStudy $study
   */
  function deleteStudy(&$study) {
    $journal =& Request::getJournal();              
    $client = $this->_initSwordClient();
    $response = $client->deleteContainer(
                $study->getEditUri(), 
                $this->getSetting($journal->getId(), 'username'),
                $this->getSetting($journal->getId(), 'password'),                
                ''); // on behalf of 
    
    if ($response->sac_status == DATAVERSE_PLUGIN_HTTP_STATUS_NO_CONTENT) {
      $dvFileDao =& DAORegistry::getDAO('DataverseFileDAO');
      $dvFileDao->deleteDataverseFilesByStudyId($study->getId());
      $dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
      $dataverseStudyDao->deleteStudy($study);
      return true;
    }
    return false;
  }
  
  /**
   * Delete a file from a study
   * @param string $contentSourceUri
   */
  function deleteFile(&$dvFile) {
    $journal =& Request::getJournal();
    $client = $this->_initSwordClient();
    $response = $client->deleteResourceContent(
              $dvFile->getContentSourceUri(),
              $this->getSetting($journal->getId(), 'username'),
              $this->getSetting($journal->getId(), 'password'),
              '' // on behalf of
            );
    
    return ($response->sac_status == 204);
  }

  /**
   * Wrapper function for initializing SWORDv2 client with default cURL options
   * 
   * @param array $options 
   */
  function _initSwordClient($options = array(CURLOPT_SSL_VERIFYPEER => FALSE)) {
    return new SWORDAPPClient($options);
  }
  


  /**
   * Workaround to avoid using citation formation plugins. Returns formatted 
   * citation for $article
   * @param type $article
   * @return string
   */
  function getCitation($article) {
    $citationFormat = $this->getSetting($article->getJournalId(), 'citationFormat');
    $journal =& Request::getJournal();
    $issueDao = DAORegistry::getDAO('IssueDAO');
    $issue =& $issueDao->getIssueByArticleId($article->getId(), $article->getJournalId());

    $templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('article', $article);
    if ($article->getStatus() == STATUS_PUBLISHED) {
      $publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
      $publishedArticle =& $publishedArticleDao->getPublishedArticleByArticleId($article->getId(), $article->getJournalId(), TRUE);
      $templateMgr->assign_by_ref('publishedArticle', $publishedArticle);
    }
		$templateMgr->assign_by_ref('issue', $issue);
		$templateMgr->assign_by_ref('journal', $journal); 
    
    return $templateMgr->fetch($this->getTemplatePath() .'citation'. $citationFormat .'.tpl');
  }

	/**
	 * Add a notification.
	 * @param $request Request
	 * @param $message string An i18n key.
	 * @param $notificationType integer One of the NOTIFICATION_TYPE_* constants.
	 * @param $param string An additional parameter for the message.
	 */
	function _sendNotification($message, $notificationType, $param = null) {
		static $notificationManager = null;

		if (is_null($notificationManager)) {
			import('classes.notification.NotificationManager');
			$notificationManager = new NotificationManager();
		}

		if (!is_null($param)) {
			$params = array('param' => $param);
		} else {
			$params = null;
		}
    
    $request =& Application::getRequest();
		$user =& $request->getUser();
		$notificationManager->createTrivialNotification(
			$user->getId(),
			$notificationType,
			array('contents' => __($message, $params))
		);
	}  
}

?>
