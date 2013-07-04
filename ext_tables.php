<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}


if (TYPO3_MODE == 'BE' && !(TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_INSTALL)) {
	/**
	 * Registers a Backend Module
	 */
	Tx_Extbase_Utility_Extension::registerModule(
			$_EXTKEY,
			'tools',    // Make module a submodule of 'web'
			'solrmanager',    // Submodule key
			'after:solr', // Position
			array(
					// An array holding the controller-action-combinations that are accessible
				'Search' => 'search,result,config'
			),
			array(
					'access' => 'user,group',
					'icon'   => 'EXT:' . $_EXTKEY . '/ext_icon.gif',
					'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_solrmanager.xlf',
			)
	);
	 
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Solr configuration (elevate, stopwords, synonyms)');

?>