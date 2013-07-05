<?php
/***************************************************************
 *  Copyright notice
 *  (c) 2013 Phuong Doan  <phuong.doan@dkd.de>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Controller of the Solr configuration extension
 *
 * @category    Controller
 */
class Tx_Solrmanager_Controller_SearchController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {
	/**
	 * @var string Key of the extension this controller belongs to
	 */
	protected $extensionName = 'solrmanager';
	
	/**
	 * @var \TYPO3\CMS\Core\Page\PageRenderer
	 */
	protected $pageRenderer;

	/**
	 * @var Tx_Solrmanager_Controller_SolrController
	 */
	protected $solr;

	
	/**
	 * Initializes the controller before invoking an action method.
	 *
	 * @return void
	 */
	protected function initializeAction() {
		$this->addStylesheets();
		$this->pageRenderer->addInlineLanguageLabelFile('EXT:solrmanager/Resources/Private/Language/locallang.xml');
		$this->initializeSolr();
	}
	 
	/**
	 * Simple action to list some stuff
	 */
	public function searchAction() {
		$requestArguments = $this->request->getArguments();

		if(array_key_exists("statusMessage", $requestArguments)){
			$this->view->assign('statusMessage', $requestArguments["statusMessage"]);
		}
		if(array_key_exists("errorMessage", $requestArguments)){
			$this->view->assign('errorMessage', $requestArguments["errorMessage"]);
		}
		if(array_key_exists("message", $requestArguments)){
			$this->view->assign('message', $requestArguments["message"]);
		}
	}
	 
	/**
	 *
	 * @return void
	 */
	public function resultAction() {
		$resultDocuments = array();
		$searchQuery = '';
		$configurationType = '';
		$showDeleteElevateButton = '-1';

		$argument = $this->request->getArgument('tx_solr');
		if(!empty($argument)) {
			$searchQuery = trim($argument['q']);

			$configurationType = $this->request->getArgument('configurationType');
			//$language = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('L');
			//$this->solr->setLanguage($language);
			$resultDocuments = $this->solr->search($searchQuery);

			if($this->solr->hasKeywordsInElevateQueries($searchQuery)) {
				$showDeleteElevateButton = '1';
			}
		}

		$showElevateButton = (empty($resultDocuments)) ? '-1' : '1';
		$this->view->assign('showDeleteElevateBtn',$showDeleteElevateButton);
		$this->view->assign('showElevateBtn',$showElevateButton);
		$this->view->assign('query',$searchQuery);
		$this->view->assign('configurationType',$configurationType);
		$this->view->assign('resultDocuments',$resultDocuments);
	}

	protected function initializeSolr() {
		$this->solr = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solrmanager_Controller_SolrController');
		if(!$this->solr->getSolrstatus()) {
			$this->solr->initialize();
		}
	}

	public function configAction() {
		// content elevation
		if($this->request->getArgument('configurationType') == 'contentElevation') {
			$query = $this->request->getArgument('query');
			$requestArguments = $this->request->getArguments();
			if(array_key_exists("submitElevate", $requestArguments)){
				$solrdocs = $this->request->getArgument('solrdocs');
			}
			else if(array_key_exists("deleteElevate", $requestArguments)){
				$solrdocs = array();
			}
			else if(array_key_exists("submitCancel", $requestArguments)){
				$this->redirect("search");
			}

			$success = $this->solr->writeContentElevation($query, $solrdocs);
			$errorMessage = $GLOBALS['LANG']->sL('LLL:EXT:solrmanager/Resources/Private/Language/locallang.xml:search.manager.elevation.status.failed');
			$statusMessage = '';
			if($success && !empty($solrdocs)) {
				$statusMessage = $GLOBALS['LANG']->sL('LLL:EXT:solrmanager/Resources/Private/Language/locallang.xml:search.manager.elevation.status.ok');
				$errorMessage = '';
			}
			else if($success && empty($solrdocs)) {
				$statusMessage = $GLOBALS['LANG']->sL('LLL:EXT:solrmanager/Resources/Private/Language/locallang.xml:search.manager.elevation.delete.status.ok');
				$errorMessage = '';
			}
			$this->redirect("search", NULL, NULL, array("statusMessage" => $statusMessage, "errorMessage" => $errorMessage));
		}
	}

	/**
	 * Finds the system's configured languages.
	 *
	 * @return	array	An array of language IDs
	 */
	protected function getSystemLanguages() {
		$languages = array(0);

		$languageRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid',
			'sys_language',
			'hidden = 0'
		);

		if (is_array($languageRecords)) {
			foreach ($languageRecords as $languageRecord) {
				$languages[] = $languageRecord['uid'];
			}
		}

		return $languages;
	}

	/**
	 * Gets the language name for a given lanuguage ID.
	 *
	 * @param	integer	$languageId language ID
	 * @return	string	Language name
	 */
	protected function getLanguageName($languageId) {
		$languageName = '';

		$language = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, title',
			'sys_language',
			'uid = ' . (integer) $languageId
		);

		if (count($language)) {
			$languageName = $language[0]['title'];
		} elseif ($languageId == 0) {
			$languageName = 'default';
		}

		return $languageName;
	}


	
	/**
	 * Processes a general request. The result can be returned by altering the given response.
	 *
 	 * @param \TYPO3\CMS\Extbase\Mvc\RequestInterface $request The request object
	 * @param \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response The response, modified by this handler
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
	 * @return void
	 */
	public function processRequest(\TYPO3\CMS\Extbase\Mvc\RequestInterface $request, \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response) {
		$this->template = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('template');
		$this->pageRenderer = $this->template->getPageRenderer();
	
		$GLOBALS['SOBE'] = new stdClass();
		$GLOBALS['SOBE']->doc = $this->template;
	
		parent::processRequest($request, $response);
	
		$pageHeader = $this->template->startpage(
				$GLOBALS['LANG']->sL('LLL:EXT:solrmanager/Resources/Private/Language/locallang.xml:module.title')
		);
		$pageEnd = $this->template->endPage();
	
		$response->setContent($pageHeader . $response->getContent() . $pageEnd);
	}
	
	protected function addStylesheets() {
		/* @var $doc mediumDoc */
		$doc = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('mediumDoc');
		$doc->getPageRenderer()->addCssFile(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('solrmanager') . 'Resources/Public/Stylesheets/solrmanager.css');
		$doc->getPageRenderer()->addJsFile(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('solrmanager') . 'Resources/Public/JavaScript/jquery.js','text/javascript', false);
		$doc->getPageRenderer()->addJsFile(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('solrmanager') . 'Resources/Public/JavaScript/solrmanager.js','text/javascript', false);
	}
}

?>