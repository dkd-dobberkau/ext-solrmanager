<?php

class Tx_Solrmanager_Controller_SolrController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	/**
	 * an instance of tx_solr_Search
	 *
	 * @var tx_solr_Search
	 */
	protected $search;

	/**
	 * The plugin's query
	 *
	 * @var tx_solr_Query
	 */
	protected $query = NULL;

	/**
	 * @var array
	 */
	protected $conf = array();

	/**
	 * @var string
	 */
	protected $searchQuery = '';

	/**
	 * Determines whether the solr server is available or not.
	 */
	protected $solrAvailable = false;

	protected $solrCores = array();

	/**
	 * The plugin's query
	 *
	 * @var Apache_Solr_HttpTransport_FileGetContents
	 */
	protected $httpTransport = NULL;

	protected $configurationUrl = '';
	protected $tomcatUrl = '';

	/* The content type for writing the elevate data in xml file */
	const WRITE_CONTENT_TYPE = 'text/xml; charset=UTF-8';


	/**
	 * Initializes the controller before invoking an action method.
	 *
	 * @return void
	 */
	protected function initializeAction() {
		$this->conf = $this->initializeConfiguration();
		$this->initializeQuery();
		$this->initializeSearch();
		$this->initializeUrls();
	}

	/**
	 * Initializes the controller
	 *
	 * @return void
	 */
	public function initialize() {
		$this->initializeAction();
	}

	/**
	 * Initializes the query from the GET query parameter.
	 *
	 */
	protected function initializeQuery()
	{
		$getParameter = $this->prefixId . '|q';

		if (!empty($this->conf['search.']['query.']['getParameter'])) {
			$getParameter = $this->conf['search.']['query.']['getParameter'];
		}

		$getParameterParts = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode('|', $getParameter, 2);
		if (count($getParameterParts) == 2) {
			$getParameters = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET($getParameterParts[0]);
			$this->rawUserQuery = $getParameters[$getParameterParts[1]];
		} else {
			$this->rawUserQuery = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET($getParameter);
		}

		// enforce API usage
		unset($this->piVars['q']);
	}


	/**
	 * Initializes the Solr connection and tests the connection through a ping. Also gets all the solr cores.
	 *
	 * @param	integer	A page ID.
	 * @param integer The language ID to get the configuration for as the path may differ. Optional, defaults to 0.
	 * @return void
	 */
	protected function initializeSearch($pageId = 1, $languageId = 0)
	{
		$solrConnectionManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_solr_ConnectionManager');
		$solrConnection = $solrConnectionManager->getConnectionByPageId($pageId, $languageId);
		$connections = $solrConnectionManager->getAllConnections();
		foreach ($connections as $connection) {
			$this->solrCores[] = rtrim(str_replace('/solr/','',$connection->getPath()),'/');
		}
		$this->search = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_solr_Search', $solrConnection);
		$this->solrAvailable = $this->search->ping();

	}

	/**
	 * Initializes the Solr configuration using the page uid 1
	 */
	protected function initializeConfiguration()
	{
		$sysPageObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('t3lib_pageSelect');
		$rootLine = $sysPageObj->getRootLine(1);
		$TSObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('t3lib_tsparser_ext');
		$TSObj->tt_track = 0;
		$TSObj->init();
		$TSObj->runThroughTemplates($rootLine);
		$TSObj->generateConfig();
		$solrConfiguration = $TSObj->setup['plugin.']['tx_solr.'];

		return $solrConfiguration;
	}

	/**
	 * Initializes search components.
	 */
	protected function initializeSearchComponents()
	{
		$searchComponents = array(
			'tx_solr_search_FacetingComponent',
			'tx_solr_search_HighlightingComponent',
			'tx_solr_search_LastSearchesComponent',
			'tx_solr_search_SortingComponent'
		);

		foreach ($searchComponents as $searchComponentName) {
			$searchComponent = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($searchComponentName);

			if ($searchComponent instanceof tx_solr_QueryAware) {
				$searchComponent->setQuery($this->query);
			}

			$searchComponent->initializeSearchComponent();
		}
	}

	protected function initializeUrls() {
		$this->tomcatUrl = $this->conf['solr.']['scheme'].'://'.$this->conf['solr.']['host'].':'.$this->conf['solr.']['port'].'/';
		$this->configurationUrl = $this->tomcatUrl.trim($this->conf['solr.']['path'],'/').'/configuration?type=';
	}


	/**
	 * NOTE: Marking Elevated Items in the Results with Solr 4.0 possible! e.g.: &fl=id,score,[elevated]
	 *
	 * @param string $query The query String
	 * @return array
	 */
	public function search($queryParameter) {
		$resultDocuments = array();
		$elevateQueryDocs = array();

		if ($queryParameter != null || $queryParameter != "") {
			$this->searchQuery = $queryParameter;
			# get to the appropriated search term saved doc ids
			$elevateQueries = $this->getElevateQueries();
			foreach($elevateQueries as $elevateQuery){
				if($elevateQuery[0] == $queryParameter) {
					$elevateQuerySize = count($elevateQuery);
					for($i=1; $i<$elevateQuerySize; $i++) {
						$elevateQueryDocs[] = $elevateQuery[$i];
					}
					break;
				}
			}
		}


		if ($this->solrAvailable) {
			$query = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_solr_Query', $this->searchQuery);
			$limit = 10;
			if (!empty($this->conf['search.']['results.']['resultsPerPage'])) {
				$limit = $this->conf['search.']['results.']['resultsPerPage'];
			}
			$query->setResultsPerPage($limit);

			// used when no search word is present
			$query->setAlternativeQuery('*:*');

			$this->query = $query;
			$this->initializeSearchComponents();

			$offSet = 0;
			$this->search->search($this->query, $offSet, NULL);
			$solrResults = $this->search->getResultDocuments();

			foreach ($solrResults as $result) {
				$fields   = $result->getFieldNames();
				$document = array();
				foreach ($fields as $field) {
					$fieldValue       = $result->getField($field);
					$document[$field] = $fieldValue["value"];
					if($field == 'id') {
						$document['elevate'] = (in_array($fieldValue["value"], $elevateQueryDocs)) ? '1' : '0';
					}
				}
				$resultDocuments[] = $document;
			}
		}
		else {
			$document = array();
			$document['title'] = $GLOBALS['LANG']->sL('LLL:EXT:solrmanager/Resources/Private/Language/locallang.xml:search.manager.solr.notavailable');
			$document['type'] = "";
			$document['content'] = $GLOBALS['LANG']->sL('LLL:EXT:solrmanager/Resources/Private/Language/locallang.xml:search.manager.solr.notavailable2');
			$resultDocuments[] = $document;
		}
		return $resultDocuments;
	}


	/**
	 * Writes the query with the doc ids in the elevate.xml
	 *
	 * @return boolean
	 */
	public function writeContentElevation($queryParameter, $solrdocs) {
		$success = false;

		if($queryParameter != '') {
			$url = $this->configurationUrl.'contentElevation';
			$query = '';
			$elevateQueries = $this->getElevateQueries();

			// save all the already existing queries
			foreach($elevateQueries as $elevateQuery){
				if($elevateQuery[0] != $queryParameter) {
					$query .= '<query text="'.$elevateQuery[0].'">';
					$elevateQuerySize = count($elevateQuery);
					for($i=1; $i<$elevateQuerySize; $i++) {
						$query .= '<doc id="'.$elevateQuery[$i].'" />';
					}
					$query .= '</query>';
				}
			}

			// save the new query if not empty
			// otherwise the query will not be saved -> elevate is now deleted
			if(!empty($solrdocs)) {

				$query .= '<query text="'.$queryParameter.'">';
				foreach($solrdocs as $solrdoc) {
					$query .= '<doc id="'.$solrdoc.'" />';
				}
				$query .= '</query>';
			}

			// send elevate query per post
			$rawPost = '<elevate>'.$query.'</elevate>';
			$httpTransport = $this->getHttpTransport();
			$httpResponse = $httpTransport->performPostRequest($url, $rawPost, WRITE_CONTENT_TYPE, false);
			if($httpResponse->getStatusCode() == 200) {
				$success = $this->reloadSolrcores();
			}
		}
		return $success;
	}


	/**
	 *
	 * Returns an array contained with n arrays.
	 * In an array the first element contains the query string and the other elements the doc ids.
	 *
	 * @return array
	 */
	public function getElevateQueries() {
		$elevateQueries = array();
		$url = $this->configurationUrl.'contentElevation';
		$httpTransport = $this->getHttpTransport();
		$httpResponse = $httpTransport->performGetRequest($url);
		if($httpResponse->getStatusCode() == 200) {
			$xml = simplexml_load_string(html_entity_decode($httpResponse->getBody()));
			$elevate = $xml->str->elevate;
			foreach($elevate->children() as $queryElements) {
				$elevateDocs = array();
				$elevateDocs[] = (string) $queryElements->attributes()->text;
				foreach($queryElements->children() as $docElements) {
					$elevateDocs[] = (string) $docElements->attributes()->id;
				}
				$elevateQueries[] = $elevateDocs;
			}
		}
		return $elevateQueries;
	}

	/**
	 *
	 * @return boolean Return true if query is in elevate.xml
	 */
	public function hasKeywordsInElevateQueries($queryParameter) {
		$hasKeywords = false;
		if($queryParameter != '') {
			$elevateQueries = $this->getElevateQueries();
			foreach($elevateQueries as $elevateQuery){
				if($elevateQuery[0] == $queryParameter) {
					$hasKeywords = true;
				}
			}
		}
		return $hasKeywords;
	}



	/**
	 * Get the current configured HTTP Transport
	 *
	 * @return HttpTransportInterface
	 */
	protected function getHttpTransport()
	{
		if($this->httpTransport == NULL) {
			$this->httpTransport = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Apache_Solr_HttpTransport_FileGetContents');
		}
		return $this->httpTransport;
	}


	/**
	 * Check the solr status
	 *
	 * @return boolean Returns TRUE on successful ping.
	 */
	public function getSolrstatus() {
		return $this->solrAvailable;
	}

	/**
	 * Reload the solr cores
	 *
	 * @return boolean
	 */
	protected function reloadSolrcores() {
		$success = true;
		$url = $this->tomcatUrl.'/solr/admin/cores?action=RELOAD&core=';
		foreach($this->solrCores as $core) {
			$httpTransport = $this->getHttpTransport();
			$httpResponse = $httpTransport->performHeadRequest($url.$core);
			if($httpResponse->getStatusCode() != 200) {
				$success = false;
			}
		}

		return $success;
	}
}