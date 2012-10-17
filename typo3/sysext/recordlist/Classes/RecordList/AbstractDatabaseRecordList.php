<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2009 Kasper Skårhøj (kasperYYYY@typo3.com)
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
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Child class for rendering of Web > List (not the final class. see class.db_list_extra)
 * Shared between Web>List (db_list.php) and Web>Page (sysext/cms/layout/db_layout.php)
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage core
 * @see localRecordList
 */
class recordList extends t3lib_recordList {

		// External, static:
		// Specify a list of tables which are the only ones allowed to be displayed.
	var $tableList = '';
		// Return URL
	var $returnUrl = '';
		// Boolean. Thumbnails on records containing files (pictures)
	var $thumbs = 0;
		// default Max items shown per table in "multi-table mode", may be overridden by tables.php
	var $itemsLimitPerTable = 20;
		// default Max items shown per table in "single-table mode", may be overridden by tables.php
	var $itemsLimitSingleTable = 100;
	var $widthGif = '<img src="clear.gif" width="1" height="4" hspace="160" alt="" />';
		// Current script name
	var $script = 'index.php';
		// Indicates if all available fields for a user should be selected or not.
	var $allFields = 0;
		// Whether to show localization view or not.
	var $localizationView = FALSE;

		// Internal, static: GPvar:
		// If set, csvList is outputted.
	var $csvOutput = FALSE;
		// Field, to sort list by
	var $sortField;
		// Field, indicating to sort in reverse order.
	var $sortRev;
		// Array, containing which fields to display in extended mode
	var $displayFields;
		// String, can contain the field name from a table which must have duplicate values marked.
	var $duplicateField;

		// Internal, static:
		// Page id
	var $id;
		// Tablename if single-table mode
	var $table = '';
		// If TRUE, records are listed only if a specific table is selected.
	var $listOnlyInSingleTableMode = FALSE;
		// Pointer for browsing list
	var $firstElementNumber = 0;
		// Search string
	var $searchString = '';
		// Levels to search down.
	var $searchLevels = '';
		// Number of records to show
	var $showLimit = 0;
		// Query part for either a list of ids "pid IN (1,2,3)" or a single id "pid = 123" from
		// which to select/search etc. (when search-levels are set high). See start()
	var $pidSelect = '';
		// Page select permissions
	var $perms_clause = '';
		// Some permissions...
	var $calcPerms = 0;
		// Mode for what happens when a user clicks the title of a record.
	var $clickTitleMode = '';
		// Shared module configuration, used by localization features
	var $modSharedTSconfig = array();
		// Loaded with page record with version overlay if any.
	var $pageRecord = array();
		// Tables which should not get listed
	var $hideTables = '';

	/**
	 * Tables which should not list their translations
	 * @var $hideTranslations string
	 */
	public $hideTranslations = '';
		//TSconfig which overwrites TCA-Settings
	var $tableTSconfigOverTCA = array();
		// Array of collapsed / uncollapsed tables in multi table view
	var $tablesCollapsed = array();

		// Internal, dynamic:
		// JavaScript code accumulation
	var $JScode = '';
		// HTML output
	var $HTMLcode = '';
		// "LIMIT " in SQL...
	var $iLimit = 0;
		// Counting the elements no matter what...
	var $eCounter = 0;
		// Set to the total number of items for a table when selecting.
	var $totalItems = '';
		// Cache for record path
	var $recPath_cache = array();
		// Fields to display for the current table
	var $setFields = array();
		// Used for tracking next/prev uids
	var $currentTable = array();
		// Used for tracking duplicate values of fields
	var $duplicateStack = array();
		// module configuratio
	var $modTSconfig;

	/**
	 * Initializes the list generation
	 *
	 * @param integer $id Page id for which the list is rendered. Must be >= 0
	 * @param string $table Tablename - if extended mode where only one table is listed at a time.
	 * @param integer $pointer Browsing pointer.
	 * @param string $search Search word, if any
	 * @param integer $levels Number of levels to search down the page tree
	 * @param integer $showLimit Limit of records to be listed.
	 * @return void
	 */
	function start($id, $table, $pointer, $search = '', $levels = '', $showLimit = 0) {

			// Setting internal variables:
			// sets the parent id
		$this->id = intval($id);
		if ($GLOBALS['TCA'][$table]) {
				// Setting single table mode, if table exists:
			$this->table = $table;
		}
		$this->firstElementNumber = $pointer;
		$this->searchString = trim($search);
		$this->searchLevels = trim($levels);
		$this->showLimit = t3lib_utility_Math::forceIntegerInRange($showLimit, 0, 10000);

			// Setting GPvars:
		$this->csvOutput = t3lib_div::_GP('csv') ? TRUE : FALSE;
		$this->sortField = t3lib_div::_GP('sortField');
		$this->sortRev = t3lib_div::_GP('sortRev');
		$this->displayFields = t3lib_div::_GP('displayFields');
		$this->duplicateField = t3lib_div::_GP('duplicateField');

		if (t3lib_div::_GP('justLocalized')) {
			$this->localizationRedirect(t3lib_div::_GP('justLocalized'));
		}

			// Init dynamic vars:
		$this->counter = 0;
		$this->JScode = '';
		$this->HTMLcode = '';

			// Limits
		if (isset($this->modTSconfig['properties']['itemsLimitPerTable'])) {
			$this->itemsLimitPerTable = t3lib_utility_Math::forceIntegerInRange(intval($this->modTSconfig['properties']['itemsLimitPerTable']), 1, 10000);
		}
		if (isset($this->modTSconfig['properties']['itemsLimitSingleTable'])) {
			$this->itemsLimitSingleTable = t3lib_utility_Math::forceIntegerInRange(intval($this->modTSconfig['properties']['itemsLimitSingleTable']), 1, 10000);
		}

			// Set search levels:
		$searchLevels = intval($this->searchLevels);
		$this->perms_clause = $GLOBALS['BE_USER']->getPagePermsClause(1);

			// This will hide records from display - it has nothing todo with user rights!!
		if ($pidList = $GLOBALS['BE_USER']->getTSConfigVal('options.hideRecords.pages')) {
			if ($pidList = $GLOBALS['TYPO3_DB']->cleanIntList($pidList)) {
				$this->perms_clause .= ' AND pages.uid NOT IN ('.$pidList.')';
			}
		}

			// Get configuration of collapsed tables from user uc and merge with sanitized GP vars
		$this->tablesCollapsed = is_array($GLOBALS['BE_USER']->uc['moduleData']['list']) ? $GLOBALS['BE_USER']->uc['moduleData']['list'] : array();
		$collapseOverride = t3lib_div::_GP('collapse');
		if (is_array($collapseOverride)) {
			foreach ($collapseOverride as $collapseTable => $collapseValue) {
				if (is_array($GLOBALS['TCA'][$collapseTable]) && ($collapseValue == 0 || $collapseValue == 1)) {
					$this->tablesCollapsed[$collapseTable] = $collapseValue;
				}
			}
			// Save modified user uc
			$GLOBALS['BE_USER']->uc['moduleData']['list'] = $this->tablesCollapsed;
			$GLOBALS['BE_USER']->writeUC($GLOBALS['BE_USER']->uc);

			$returnUrl = t3lib_div::sanitizeLocalUrl(t3lib_div::_GP('returnUrl'));
			if ($returnUrl !== '') {
				t3lib_utility_Http::redirect($returnUrl);
			}
		}

		if ($searchLevels > 0) {
			$tree = $this->getTreeObject($this->id, $searchLevels, $this->perms_clause);
			$pidList = implode(',', $GLOBALS['TYPO3_DB']->cleanIntArray($tree->ids));
			$this->pidSelect = 'pid IN (' . $pidList . ')';
		} elseif ($searchLevels < 0) {
				// Search everywhere
			$this->pidSelect = '1=1';
		} else {
			$this->pidSelect = 'pid=' . intval($id);
		}

			// Initialize languages:
		if ($this->localizationView) {
			$this->initializeLanguages();
		}
	}

	/**
	 * Traverses the table(s) to be listed and renders the output code for each:
	 * The HTML is accumulated in $this->HTMLcode
	 * Finishes off with a stopper-gif
	 *
	 * @return void
	 */
	function generateList() {

			// Set page record in header
		$this->pageRecord = t3lib_BEfunc::getRecordWSOL('pages', $this->id);

			// Traverse the TCA table array:
		foreach ($GLOBALS['TCA'] as $tableName => $value) {

				// Checking if the table should be rendered:
				// Checks that we see only permitted/requested tables:
			if ((!$this->table || $tableName == $this->table) && (!$this->tableList || t3lib_div::inList($this->tableList, $tableName)) && $GLOBALS['BE_USER']->check('tables_select', $tableName)) {

					// Load full table definitions:
				t3lib_div::loadTCA($tableName);

					// Don't show table if hidden by TCA ctrl section
				$hideTable = $GLOBALS['TCA'][$tableName]['ctrl']['hideTable'] ? TRUE : FALSE;
					// Don't show table if hidden by pageTSconfig mod.web_list.hideTables
				if (in_array($tableName, t3lib_div::trimExplode(',', $this->hideTables))) {
					$hideTable = TRUE;
				}
					// Override previous selection if table is enabled or hidden by TSconfig TCA override mod.web_list.table
				if (isset($this->tableTSconfigOverTCA[$tableName.'.']['hideTable'])) {
					$hideTable = $this->tableTSconfigOverTCA[$tableName.'.']['hideTable'] ? TRUE : FALSE;
				}
				if ($hideTable) {
					continue;
				}

					// iLimit is set depending on whether we're in single- or multi-table mode
				if ($this->table) {
					$this->iLimit= (isset($GLOBALS['TCA'][$tableName]['interface']['maxSingleDBListItems'])
						? intval($GLOBALS['TCA'][$tableName]['interface']['maxSingleDBListItems']) :
						$this->itemsLimitSingleTable);
				} else {
					$this->iLimit = (isset($GLOBALS['TCA'][$tableName]['interface']['maxDBListItems'])
						? intval($GLOBALS['TCA'][$tableName]['interface']['maxDBListItems'])
						: $this->itemsLimitPerTable);
				}
				if ($this->showLimit) {
					$this->iLimit = $this->showLimit;
				}

					// Setting fields to select:
				if ($this->allFields) {
					$fields = $this->makeFieldList($tableName);
					$fields[]='tstamp';
					$fields[]='crdate';
					$fields[]='_PATH_';
					$fields[]='_CONTROL_';
					if (is_array($this->setFields[$tableName])) {
						$fields = array_intersect($fields, $this->setFields[$tableName]);
					} else {
						$fields = array();
					}
				} else {
					$fields = array();
				}

					// Find ID to use (might be different for "versioning_followPages" tables)
				if (intval($this->searchLevels) == 0) {
					$this->pidSelect = 'pid=' . intval($this->id);
				}

					// Finally, render the list:
				$this->HTMLcode .= $this->getTable($tableName, $this->id, implode(',', $fields));
			}
		}
	}

	/**
	 * Creates the search box
	 *
	 * @param boolean $formFields If TRUE, the search box is wrapped in its own form-tags
	 * @return string HTML for the search box
	 */
	function getSearchBox($formFields = 1) {

			// Setting form-elements, if applicable:
		$formElements = array('','');
		if ($formFields) {
			$formElements = array('<form action="' . htmlspecialchars($this->listURL('', -1, 'firstElementNumber')) . '" method="post">', '</form>');
		}

			// Make level selector:
		$opt = array();
		$parts = explode('|', $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.enterSearchLevels'));
		foreach ($parts as $kv => $label) {
			$opt[] = '<option value="'.$kv.'"'.($kv==intval($this->searchLevels)?' selected="selected"':'').'>'.htmlspecialchars($label).'</option>';
		}
		$lMenu = '<select name="search_levels">' . implode('', $opt) . '</select>';

			// Table with the search box:
		$content = '<div class="db_list-searchbox-form">
			'.$formElements[0].'

				<!--
					Search box:
				-->
				<table border="0" cellpadding="0" cellspacing="0" id="typo3-dblist-search">
					<tr>
						<td><label for="search_field">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.enterSearchString', 1) . '</label></td>
						<td><input type="text" name="search_field" id="search_field" value="' . htmlspecialchars($this->searchString) . '"' . $GLOBALS['TBE_TEMPLATE']->formWidth(10) . ' /></td>
						<td>' . $lMenu . '</td>
						<td><input type="submit" name="search" value="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.search', 1) . '" /></td>
					</tr>
					<tr>
						<td><label for="showLimit">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.showRecords', 1) . ':</label></td>
						<td colspan="3"><input type="text" name="showLimit" id="showLimit" value="' . htmlspecialchars($this->showLimit ? $this->showLimit : '') . '"' . $GLOBALS['TBE_TEMPLATE']->formWidth(4) . ' /></td>
					</tr>
				</table>
			' . $formElements[1] . '</div>';

		return $content;
	}

	/**
	 * Creates the display of sys_notes for the page.
	 * Relies on the "sys_note" extension to be loaded.
	 *
	 * @return string HTML for the sys-notes (if any)
	 * @deprecated since 6.0, will be removed two versions later
	 */
	function showSysNotesForPage() {
		t3lib_div::logDeprecatedFunction();
		return '';
	}

	/******************************
	 *
	 * Various helper functions
	 *
	 ******************************/

	/**
	 * Setting the field names to display in extended list.
	 * Sets the internal variable $this->setFields
	 *
	 * @return void
	 */
	function setDispFields() {

			// Getting from session:
		$dispFields = $GLOBALS['BE_USER']->getModuleData('list/displayFields');

			// If fields has been inputted, then set those as the value and push it to session variable:
		if (is_array($this->displayFields)) {
			reset($this->displayFields);
			$tKey = key($this->displayFields);
			$dispFields[$tKey] = $this->displayFields[$tKey];
			$GLOBALS['BE_USER']->pushModuleData('list/displayFields', $dispFields);
		}

			// Setting result:
		$this->setFields=$dispFields;
	}

	/**
	 * Create thumbnail code for record/field
	 *
	 * @param array $row Record array
	 * @param string $table Table (record is from)
	 * @param string $field Field name for which thumbsnail are to be rendered.
	 * @return string HTML for thumbnails, if any.
	 */
	function thumbCode($row, $table, $field) {
		return t3lib_BEfunc::thumbCode($row, $table, $field, $this->backPath);
	}

	/**
	 * Returns the SQL-query array to select the records from a table $table with pid = $id
	 *
	 * @param string $table Table name
	 * @param integer $id Page id (NOT USED! $this->pidSelect is used instead)
	 * @param string $addWhere Additional part for where clause
	 * @param string $fieldList Field list to select, * for all (for "SELECT [fieldlist] FROM ...")
	 * @return array Returns query array
	 */
	function makeQueryArray($table, $id, $addWhere = '', $fieldList = '*') {
		$hookObjectsArr = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list.inc']['makeQueryArray'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/class.db_list.inc']['makeQueryArray'] as $classRef) {
				$hookObjectsArr[] = t3lib_div::getUserObj($classRef);
			}
		}

			// Set ORDER BY:
		$orderBy = ($GLOBALS['TCA'][$table]['ctrl']['sortby']
			? 'ORDER BY ' . $GLOBALS['TCA'][$table]['ctrl']['sortby']
			: $GLOBALS['TCA'][$table]['ctrl']['default_sortby']);
		if ($this->sortField) {
			if (in_array($this->sortField, $this->makeFieldList($table, 1))) {
				$orderBy = 'ORDER BY '.$this->sortField;
				if ($this->sortRev) {
					$orderBy .= ' DESC';
				}
			}
		}

			// Set LIMIT:
		$limit = $this->iLimit ? ($this->firstElementNumber ? $this->firstElementNumber.',' : '').($this->iLimit+1) : '';

			// Filtering on displayable pages (permissions):
		$pC = ($table == 'pages' && $this->perms_clause)?' AND '.$this->perms_clause:'';

			// Adding search constraints:
		$search = $this->makeSearchString($table, $id);

			// Compiling query array:
		$queryParts = array(
			'SELECT' => $fieldList,
			'FROM' => $table,
			'WHERE' => $this->pidSelect.
						' '.$pC.
						t3lib_BEfunc::deleteClause($table).
						t3lib_BEfunc::versioningPlaceholderClause($table).
						' '.$addWhere.
						' '.$search,
			'GROUPBY' => '',
			'ORDERBY' => $GLOBALS['TYPO3_DB']->stripOrderBy($orderBy),
			'LIMIT' => $limit
		);

			// Filter out records that are translated, if TSconfig mod.web_list.hideTranslations is set
		if ((in_array($table, t3lib_div::trimExplode(',', $this->hideTranslations)) || $this->hideTranslations === '*')
			&& !empty($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])
			&& strcmp($table, 'pages_language_overlay')) {
			$queryParts['WHERE'] .= ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=0 ';
		}

			// Apply hook as requested in http://bugs.typo3.org/view.php?id=4361
		foreach ($hookObjectsArr as $hookObj) {
			if (method_exists($hookObj, 'makeQueryArray_post')) {
				$_params = array(
					'orderBy' => $orderBy,
					'limit' => $limit,
					'pC' => $pC,
					'search' => $search,
				);
				$hookObj->makeQueryArray_post($queryParts, $this, $table, $id, $addWhere, $fieldList, $_params);
			}
		}

			// Return query:
		return $queryParts;
	}

	/**
	 * Based on input query array (query for selecting count(*) from a table) it will select the number of records and set the value in $this->totalItems
	 *
	 * @param array $queryParts Query array
	 * @return void
	 * @see makeQueryArray()
	 */
	function setTotalItems($queryParts) {
		$this->totalItems = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
			'*',
			$queryParts['FROM'],
			$queryParts['WHERE']
		);
	}

	/**
	 * Creates part of query for searching after a word ($this->searchString)
	 * fields in input table.
	 *
	 * @param string $table Table, in which the fields are being searched.
	 * @param integer $currentPid Page id for the possible search limit. -1 only if called from an old XCLASS.
	 * @return string Returns part of WHERE-clause for searching, if applicable.
	 */
	function makeSearchString($table, $currentPid = -1) {
		$result = '';

		$currentPid = intval($currentPid);
		$tablePidField = ($table == 'pages' ? 'uid' : 'pid');

			// Make query, only if table is valid and a search string is actually defined:
		if ($this->searchString) {
			$result = ' AND 0=1';
			$searchableFields = $this->getSearchFields($table);
			if (count($searchableFields) > 0) {
					// Loading full table description - we need to traverse fields:
				t3lib_div::loadTCA($table);

				if (t3lib_utility_Math::canBeInterpretedAsInteger($this->searchString)) {
					$whereParts = array(
						'uid=' . $this->searchString
					);

					foreach ($searchableFields as $fieldName) {
						if (isset($GLOBALS['TCA'][$table]['columns'][$fieldName])) {
							$fieldConfig = &$GLOBALS['TCA'][$table]['columns'][$fieldName]['config'];
							if ($fieldConfig['type'] == 'input' && $fieldConfig['eval'] && t3lib_div::inList($fieldConfig['eval'], 'int')) {
								$condition = $fieldName . '=' . $this->searchString;
								if (is_array($fieldConfig['search']) && in_array('pidonly', $fieldConfig['search']) && $currentPid > 0) {
									$condition = '(' . $condition . ' AND ' . $tablePidField . '=' . $currentPid . ')';
								}
								$whereParts[] = $condition;
							}
						}
					}
				} else {
					$whereParts = array();
					$like = '\'%' .
						$GLOBALS['TYPO3_DB']->quoteStr($GLOBALS['TYPO3_DB']->escapeStrForLike($this->searchString, $table), $table) .
						'%\'';
					foreach ($searchableFields as $fieldName) {
						if (isset($GLOBALS['TCA'][$table]['columns'][$fieldName])) {
							$fieldConfig = &$GLOBALS['TCA'][$table]['columns'][$fieldName]['config'];
							$format = 'LCASE(%s) LIKE LCASE(%s)';
							if (is_array($fieldConfig['search'])) {
								if (in_array('case', $fieldConfig['search'])) {
									$format = '%s LIKE %s';
								}
								if (in_array('pidonly', $fieldConfig['search']) && $currentPid > 0) {
									$format = '(' . $format . ' AND ' . $tablePidField . '=' . $currentPid . ')';
								}
								if ($fieldConfig['search']['andWhere']) {
									$format = '((' . $fieldConfig['search']['andWhere'] . ') AND (' . $format . '))';
								}
							}
							if ($fieldConfig['type'] == 'text' ||
								$fieldConfig['type'] == 'flex' ||
								($fieldConfig['type'] == 'input' && (!$fieldConfig['eval'] || !preg_match('/date|time|int/', $fieldConfig['eval'])))) {
								$whereParts[] = sprintf($format, $fieldName, $like);
							}
						}
					}
				}

					// If search-fields were defined (and there always are) we create the query:
				if (count($whereParts)) {
					$result = ' AND (' . implode(' OR ', $whereParts) . ')';
				}
			}
		}
		return $result;
	}

	/**
	 * Fetches a list of fields to use in the Backend search for the given table.
	 *
	 * @param string $tableName
	 * @return array
	 */
	protected function getSearchFields($tableName) {
		$fieldArray = array();
		$fieldListWasSet = FALSE;

			// Get fields from ctrl section of TCA first
		if (isset($GLOBALS['TCA'][$tableName]['ctrl']['searchFields'])) {
			$fieldArray = t3lib_div::trimExplode(',', $GLOBALS['TCA'][$tableName]['ctrl']['searchFields'], TRUE);
			$fieldListWasSet = TRUE;
		}

			// Call hook to add or change the list
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['mod_list']['getSearchFieldList'])) {
			$hookParameters = array(
				'tableHasSearchConfiguration' => $fieldListWasSet,
				'tableName' => $tableName,
				'searchFields' => &$fieldArray,
				'searchString' => $this->searchString
			);
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['mod_list']['getSearchFieldList'] as $hookFunction) {
				t3lib_div::callUserFunction($hookFunction, $hookParameters, $this);
			}
		}

		return $fieldArray;
	}

	/**
	 * Returns the title (based on $code) of a table ($table) with the proper link around. For headers over tables.
	 * The link will cause the display of all extended mode or not for the table.
	 *
	 * @param string $table Table name
	 * @param string $code Table label
	 * @return string The linked table label
	 */
	function linkWrapTable($table, $code) {
		if ($this->table != $table) {
			return '<a href="' . htmlspecialchars($this->listURL('', $table, 'firstElementNumber')) . '">' . $code . '</a>';
		} else {
			return '<a href="' . htmlspecialchars($this->listURL('', '', 'sortField,sortRev,table,firstElementNumber')) . '">' . $code . '</a>';
		}
	}

	/**
	 * Returns the title (based on $code) of a record (from table $table) with the proper link around (that is for 'pages'-records a link to the level of that record...)
	 *
	 * @param string $table Table name
	 * @param integer $uid Item uid
	 * @param string $code Item title (not htmlspecialchars()'ed yet)
	 * @param array $row Item row
	 * @return string The item title. Ready for HTML output (is htmlspecialchars()'ed)
	 */
	function linkWrapItems($table, $uid, $code, $row) {
		$origCode = $code;

			// If the title is blank, make a "no title" label:
		if (!strcmp($code, '')) {
			$code = '<i>[' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.no_title', 1) . ']</i> - ' . htmlspecialchars(t3lib_div::fixed_lgd_cs(t3lib_BEfunc::getRecordTitle($table, $row), $GLOBALS['BE_USER']->uc['titleLen']));
		} else {
			$code = htmlspecialchars(t3lib_div::fixed_lgd_cs($code, $this->fixedL));
			if ($code != htmlspecialchars($origCode)) {
				$code = '<span title="'.htmlspecialchars($origCode).'">'.$code.'</span>';
			}
		}

		switch((string)$this->clickTitleMode) {
			case 'edit':
					// If the listed table is 'pages' we have to request the permission settings for each page:
				if ($table == 'pages') {
					$localCalcPerms = $GLOBALS['BE_USER']->calcPerms(t3lib_BEfunc::getRecord('pages', $row['uid']));
					$permsEdit = $localCalcPerms&2;
				} else {
					$permsEdit = $this->calcPerms&16;
				}

					// "Edit" link: ( Only if permissions to edit the page-record of the content of the parent page ($this->id)
				if ($permsEdit) {
					$params = '&edit['.$table.']['.$row['uid'].']=edit';
					$code = '<a href="#" onclick="' . htmlspecialchars(t3lib_BEfunc::editOnClick($params, $this->backPath, -1)) .
						'" title="' . $GLOBALS['LANG']->getLL('edit', 1) . '">' .
							$code .
							'</a>';
				}
			break;
			case 'show':
					// "Show" link (only pages and tt_content elements)
				if ($table == 'pages' || $table == 'tt_content') {
					$code = '<a href="#" onclick="'. htmlspecialchars(t3lib_BEfunc::viewOnClick(
							$table == 'tt_content' ? $this->id . '#' . $row['uid'] : $row['uid'])
						) . '" title="'.$GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.showPage', 1) . '">'.
						$code. '</a>';
				}
			break;
			case 'info':
					// "Info": (All records)
				$code = '<a href="#" onclick="'.htmlspecialchars('top.launchView(\'' . $table . '\', \'' . $row['uid'] .
					'\'); return false;') . '" title="'.$GLOBALS['LANG']->getLL('showInfo', 1) . '">' .
					$code.
					'</a>';
			break;
			default:
					// Output the label now:
				if ($table == 'pages') {
					$code = '<a href="' . htmlspecialchars($this->listURL($uid, '', 'firstElementNumber')) . '" onclick="setHighlight(' . $uid . ')">' . $code . '</a>';
				} else {
					$code = $this->linkUrlMail($code, $origCode);
				}
			break;
		}

		return $code;
	}

	/**
	 * Wrapping input code in link to URL or email if $testString is either.
	 *
	 * @param string $code code to wrap
	 * @param string $testString String which is tested for being a URL or email and which will be used for the link if so.
	 * @return string Link-Wrapped $code value, if $testString was URL or email.
	 */
	function linkUrlMail($code, $testString) {

			// Check for URL:
		$schema = parse_url($testString);
		if ($schema['scheme'] && t3lib_div::inList('http,https,ftp', $schema['scheme'])) {
			return '<a href="'.htmlspecialchars($testString).'" target="_blank">'.$code.'</a>';
		}

			// Check for email:
		if (t3lib_div::validEmail($testString)) {
			return '<a href="mailto:'.htmlspecialchars($testString).'" target="_blank">'.$code.'</a>';
		}

			// Return if nothing else...
		return $code;
	}

	/**
	 * Creates the URL to this script, including all relevant GPvars
	 * Fixed GPvars are id, table, imagemode, returlUrl, search_field, search_levels and showLimit
	 * The GPvars "sortField" and "sortRev" are also included UNLESS they are found in the $exclList variable.
	 *
	 * @param string $altId Alternative id value. Enter blank string for the current id ($this->id)
	 * @param string $table Tablename to display. Enter "-1" for the current table.
	 * @param string $exclList Commalist of fields NOT to include ("sortField", "sortRev" or "firstElementNumber")
	 * @return string URL
	 */
	function listURL($altId = '', $table = -1, $exclList = '') {
		$urlParameters = array();
		if (strcmp($altId, '')) {
			$urlParameters['id'] = $altId;
		} else {
			$urlParameters['id'] = $this->id;
		}
		if ($table === -1) {
			$urlParameters['table'] = $this->table;
		} else {
			$urlParameters['table'] = $table;
		}
		if ($this->thumbs) {
			$urlParameters['imagemode'] = $this->thumbs;
		}
		if ($this->returnUrl) {
			$urlParameters['returnUrl'] = $this->returnUrl;
		}
		if ($this->searchString) {
			$urlParameters['search_field'] = $this->searchString;
		}
		if ($this->searchLevels) {
			$urlParameters['search_levels'] = $this->searchLevels;
		}
		if ($this->showLimit) {
			$urlParameters['showLimit'] = $this->showLimit;
		}
		if ((!$exclList || !t3lib_div::inList($exclList, 'firstElementNumber')) && $this->firstElementNumber) {
			$urlParameters['pointer'] = $this->firstElementNumber;
		}
		if ((!$exclList || !t3lib_div::inList($exclList, 'sortField')) && $this->sortField) {
			$urlParameters['sortField'] = $this->sortField;
		}
		if ((!$exclList || !t3lib_div::inList($exclList, 'sortRev')) && $this->sortRev) {
			$urlParameters['sortRev'] = $this->sortRev;
		}

		return t3lib_BEfunc::getModuleUrl('web_list', $urlParameters);
	}

	/**
	 * Returns "requestUri" - which is basically listURL
	 *
	 * @return string Content of ->listURL()
	 */
	function requestUri() {
		return $this->listURL();
	}

	/**
	 * Makes the list of fields to select for a table
	 *
	 * @param string $table Table name
	 * @param boolean $dontCheckUser If set, users access to the field (non-exclude-fields) is NOT checked.
	 * @param boolean $addDateFields If set, also adds crdate and tstamp fields (note: they will also be added if user is admin or dontCheckUser is set)
	 * @return array Array, where values are fieldnames to include in query
	 */
	function makeFieldList($table, $dontCheckUser = 0, $addDateFields = 0) {

			// Init fieldlist array:
		$fieldListArr = array();

			// Check table:
		if (is_array($GLOBALS['TCA'][$table])
			&& isset($GLOBALS['TCA'][$table]['columns'])
			&& is_array($GLOBALS['TCA'][$table]['columns'])
		) {
			t3lib_div::loadTCA($table);

			if (isset($GLOBALS['TCA'][$table]['columns']) && is_array($GLOBALS['TCA'][$table]['columns'])) {
					// Traverse configured columns and add them to field array, if available for user.
				foreach ($GLOBALS['TCA'][$table]['columns'] as $fN => $fieldValue) {
					if ($dontCheckUser ||
							((!$fieldValue['exclude'] || $GLOBALS['BE_USER']->check('non_exclude_fields', $table . ':' . $fN))
									&& $fieldValue['config']['type']!='passthrough')) {
						$fieldListArr[]=$fN;
					}
				}

					// Add special fields:
				if ($dontCheckUser || $GLOBALS['BE_USER']->isAdmin()) {
					$fieldListArr[] = 'uid';
					$fieldListArr[] = 'pid';
				}

					// Add date fields
				if ($dontCheckUser || $GLOBALS['BE_USER']->isAdmin() || $addDateFields) {
					if ($GLOBALS['TCA'][$table]['ctrl']['tstamp']) {
						$fieldListArr[] = $GLOBALS['TCA'][$table]['ctrl']['tstamp'];
					}
					if ($GLOBALS['TCA'][$table]['ctrl']['crdate']) {
						$fieldListArr[] = $GLOBALS['TCA'][$table]['ctrl']['crdate'];
					}
				}

				// Add more special fields:
				if ($dontCheckUser || $GLOBALS['BE_USER']->isAdmin()) {
					if ($GLOBALS['TCA'][$table]['ctrl']['cruser_id']) {
						$fieldListArr[] = $GLOBALS['TCA'][$table]['ctrl']['cruser_id'];
					}
					if ($GLOBALS['TCA'][$table]['ctrl']['sortby']) {
						$fieldListArr[] = $GLOBALS['TCA'][$table]['ctrl']['sortby'];
					}
					if ($GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
						$fieldListArr[] = 't3ver_id';
						$fieldListArr[] = 't3ver_state';
						$fieldListArr[] = 't3ver_wsid';
					}
				}
			} else {
				t3lib_div::sysLog(
					sprintf('$TCA is broken for the table "%s": no required "columns" entry in $TCA.', $table),
					'core',
					t3lib_div::SYSLOG_SEVERITY_ERROR
				);
			}
		}
		return $fieldListArr;
	}

	/**
	 * Creates an instance of t3lib_pageTree which will select a page tree to $depth and return the object. In that object we will find the ids of the tree.
	 *
	 * @param integer $id Page id.
	 * @param integer $depth Depth to go down.
	 * @param string $perms_clause Select clause
	 * @return object t3lib_pageTree instance with created list of ids.
	 */
	function getTreeObject($id, $depth, $perms_clause) {
		$tree = t3lib_div::makeInstance('t3lib_pageTree');
		$tree->init('AND '.$perms_clause);
		$tree->makeHTML = 0;
		$tree->fieldArray = array('uid', 'php_tree_stop');
		if ($depth) {
			$tree->getTree($id, $depth, '');
		}
		$tree->ids[] = $id;
		return $tree;
	}

	/**
	 * Redirects to TCEforms (alt_doc) if a record is just localized.
	 *
	 * @param string $justLocalized String with table, orig uid and language separated by ":"
	 * @return void
	 */
	function localizationRedirect($justLocalized) {
		list($table,$orig_uid,$language) = explode(':', $justLocalized);

		if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['languageField']
			&& $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']) {
			$localizedRecord = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
					'uid',
					$table,
					$GLOBALS['TCA'][$table]['ctrl']['languageField'] . '=' . intval($language) . ' AND ' .
						$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=' . intval($orig_uid) .
						t3lib_BEfunc::deleteClause($table).
						t3lib_BEfunc::versioningPlaceholderClause($table)
				);

			if (is_array($localizedRecord)) {
					// Create parameters and finally run the classic page module for creating a new page translation
				$url = substr($this->listURL(), strlen($this->backPath));
				$params = '&edit['.$table.']['.$localizedRecord['uid'].']=edit';
				$returnUrl = '&returnUrl='.rawurlencode($url);
				$location = $GLOBALS['BACK_PATH'].'alt_doc.php?'.$params.$returnUrl;

				t3lib_utility_Http::redirect($location);
			}
		}
	}
}
?>