1. solr-typo3-config-1.1.jar unter typo3lib ablegen
2. In solrconfig.xml einen RequestHandler einfügen:
	<requestHandler name="/configuration" class="org.typo3.solr.handler.admin.ConfigurationHandler" />

	