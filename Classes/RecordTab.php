<?php
namespace AOE\Linkhandler;

/***************************************************************
 *  Copyright notice
 *
 *  Copyright (c) 2008, Daniel Pötzinger <daniel.poetzinger@aoemedia.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * hook to adjust linkwizard (linkbrowser)
 *
 * @author Daniel Poetzinger (AOE media GmbH)
 * @package Linkhandler
 */
class RecordTab implements \AOE\Linkhandler\TabHandlerInterface {

	/**
	 * @var bool
	 */
	protected $isRte;

	/**
	 * @var \TYPO3\CMS\Rtehtmlarea\BrowseLinks
	 */
	protected $browseLinksObj;

	/**
	 * @var array
	 */
	protected $configuration;

	/**
	 * Initialize the class
	 *
	 * @param \TYPO3\CMS\Recordlist\Browser\ElementBrowser $browseLinksObj
	 * @param string $addPassOnParams
	 * @param array $configuration
	 * @param string $currentLinkValue
	 * @param bool $isRte
	 * @param int $currentPid
	 */
	public function __construct(\TYPO3\CMS\Recordlist\Browser\ElementBrowser $browseLinksObj, $addPassOnParams, $configuration, $currentLinkValue, $isRte, $currentPid) {
		$environment = '';
		$this->browseLinksObj = $browseLinksObj;

			// first step to refactoring (no dependenciy to $browseLinksObj), make the required methodcalls known in membervariables
		$this->isRte = $isRte;
		$this->expandPage = $browseLinksObj->expandPage;
		$this->configuration = $configuration;
		$this->pointer = $browseLinksObj->pointer;

		if (is_array(GeneralUtility::_GP('P'))) {
			$environment = GeneralUtility::implodeArrayForUrl('P', GeneralUtility::_GP('P'));
		}

		$this->addPassOnParams = $addPassOnParams . $environment;
	}

	/**
	 * Interface function. should return the correct info array that is required for the link wizard.
	 * It should detect if the current value is a link where this tabHandler should be responsible.
	 * else it should return a empty array
	 *
	 * @param string $href
	 * @param array $tabsConfig
	 * @static
	 * @return array
	 */
	static public function getLinkBrowserInfoArray($href, $tabsConfig) {
		$info = array();
		list($currentHandler, $table, $uid) = explode(':', $href);

			// check the linkhandler TSConfig and find out  which config is responsible for the current table:
		foreach ($tabsConfig as $key => $tabConfig) {

			if ($currentHandler == 'record' || $currentHandler == $tabConfig['overwriteHandler']) {
				if ($table == $tabConfig['listTables']) {
					$info['act'] = $key;
				}
			}
		}

		$info['recordTable'] = $table;
		$info['recordUid'] = $uid;

		return $info;
	}

	/**
	 * Build the content of an tab
	 *
	 * @return	string a tab for the selected link action
	 */
	public function getTabContent() {
		$content = '';
		if ($this->isRte) {
			if (!$this->configuration['noAttributesForm']) {
				$content .= $this->browseLinksObj->addAttributesForm();
			} elseif (array_key_exists('linkClassName', $this->configuration) && $this->configuration['linkClassName'] != '') {
				$content .= $this->addDummyAttributesForm($this->configuration['linkClassName']);
			}
		}

		/** @var \AOE\Linkhandler\Record\RecordTree $pagetree*/
		$pagetree = GeneralUtility::makeInstance('AOE\Linkhandler\Record\RecordTree');
		/** Initialize page tree, @see \TYPO3\CMS\Backend\Tree\View\AbstractTreeView */
		$pagetree->init();
		$pagetree->browselistObj = $this->browseLinksObj;
		if (array_key_exists('onlyPids', $this->configuration) && $this->configuration['onlyPids'] != '') {
			$pagetree->expandAll = TRUE;
            $pagetree->expandFirst = 1;
		}

		$tree = $pagetree->getBrowsableTree();
		$cElements = $this->expandPageRecords();
		$content .= '
		<!--
			Wrapper table for page tree / record list:
		-->
				<table border="0" cellpadding="0" cellspacing="0" id="typo3-linkPages">
					<tr>
						<td class="c-wCell" valign="top">' . $this->browseLinksObj->barheader($GLOBALS['LANG']->getLL('pageTree') . ':') . $tree . '</td>
						<td class="c-wCell" valign="top">' . $cElements . '</td>
					</tr>
				</table>
				';

		return $content;
	}

	/**
	 * For RTE: This displays all content elements on a page and lets you create a link to the element.
	 *
	 * @return	string HTML output. Returns content only if the ->expandPage value is set (pointing to a page uid to show tt_content records from ...)
	 */
	protected function expandPageRecords() {
		$out = '';

		if (
			$this->expandPage >= 0 &&
			\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($this->expandPage) &&
			$GLOBALS['BE_USER']->isInWebMount($this->expandPage)
		) {
			$tables = '*';

			if (isset($this->configuration['listTables'])) {
				$tables = $this->configuration['listTables'];
			}
				// Set array with table names to list:
			if (!strcmp(trim($tables), '*')) {
				$tablesArr = array_keys($GLOBALS['TCA']);
			} else {
				$tablesArr = GeneralUtility::trimExplode(',', $tables, 1);
			}
			reset($tablesArr);

				// Headline for selecting records:
			$out .= $this->browseLinksObj->barheader($GLOBALS['LANG']->getLL('selectRecords') . ':');

				// Create the header, showing the current page for which the listing is. Includes link to the page itself, if pages are amount allowed tables.
			$titleLen = intval($GLOBALS['BE_USER']->uc['titleLen']);
			$mainPageRec = \TYPO3\CMS\Backend\Utility\BackendUtility::getRecordWSOL('pages', $this->expandPage);
			$aTag = '';
			$aTagEnd = '';
			$aTag2 = '';
			if (in_array('pages', $tablesArr)) {
				$ficon = \TYPO3\CMS\Backend\Utility\IconUtility::getIcon('pages', $mainPageRec);
				$aTag = "<a href=\"#\" onclick=\"return insertElement('pages', '" . $mainPageRec['uid'] . "', 'db', " .
					GeneralUtility::quoteJSvalue($mainPageRec['title']) .
					", '', '', '" . $ficon . "', '',1);\">";
				$aTag2 = "<a href=\"#\" onclick=\"return insertElement('pages', '" . $mainPageRec['uid'] . "', 'db', " .
					GeneralUtility::quoteJSvalue($mainPageRec['title']) .
					", '', '', '" . $ficon . "', '',0);\">";
				$aTagEnd = '</a>';
			}
			$picon = \TYPO3\CMS\Backend\Utility\IconUtility::getIconImage('pages', $mainPageRec, $GLOBALS['BACK_PATH'], '');
			$pBicon = $aTag2 ? '<img' . \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($GLOBALS['BACK_PATH'], 'gfx/plusbullet2.gif','width="18" height="16"') . ' alt="" />' : '';
			$pText = htmlspecialchars(GeneralUtility::fixed_lgd_cs($mainPageRec['title'], $titleLen));
			$out .= $picon . $aTag2 . $pBicon . $aTagEnd . $aTag . $pText . $aTagEnd . '<br />';

				// Initialize the record listing:
			$id = $this->expandPage;
			$pointer = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($this->pointer, 0, 100000);
			$pagePermsClause = $GLOBALS['BE_USER']->getPagePermsClause(1);
			$pageinfo = \TYPO3\CMS\Backend\Utility\BackendUtility::readPageAccess($id, $pagePermsClause);

				// Generate the record list:
				// unfortunately we have to set weird dependencies.
			/** @var \AOE\Linkhandler\Record\ElementBrowserRecordList $dblist */
			$dblist = GeneralUtility::makeInstance('AOE\Linkhandler\Record\ElementBrowserRecordList');
			$dblist->setAddPassOnParameters($this->addPassOnParams);

			$dblist->backPath = $GLOBALS['BACK_PATH'];
			$dblist->thumbs = 0;
			$dblist->calcPerms = $GLOBALS['BE_USER']->calcPerms($pageinfo);
			$dblist->noControlPanels = 1;
			$dblist->clickMenuEnabled = 0;
			$dblist->tableList = implode(',', $tablesArr);

			if (array_key_exists('overwriteHandler', $this->configuration)) {
				$dblist->setOverwriteLinkHandler($this->configuration['overwriteHandler']);
			}

			$dblist->start($id, GeneralUtility::_GP('table'), $pointer,
				GeneralUtility::_GP('search_field'),
				GeneralUtility::_GP('search_levels'),
				GeneralUtility::_GP('showLimit')
			);

			$dblist->setDispFields();
			$dblist->generateList();

				//	Add the HTML for the record list to output variable:
			$out .= $dblist->HTMLcode;
			$out .= $dblist->getSearchBox();
		}

		return $out;
	}

	/**
	* Returns a form element with the typical elements that are present in the RTE attributesForm, but all fields are hidden.
	* This can be used to force a certain classname etc for the link
	 *
	* @param string $className classname
	* @return string The HTML of the Form
	*/
	protected  function addDummyAttributesForm($className = '') {
		$formTag = '<form id="ltargetform" name="ltargetform">' .
			'	<input type="hidden" name="anchor_class" value="' . $className . '">' .
			'	</form>';
		return $formTag;
	}
}
