<?php

/**
 * Extension to provide a search interface when applied to ContentController
 *
 * Originally created by Gabriele Brosulo <gabriele.brosulo@zirak.it>
 *
 * @author Firebrand <developers@firebrand.nz>
 * @creation-date 04-June-2015
 */
class CustomSearch extends Extension {

	static $allowed_actions = array(
			'SearchForm',
			'results',
	);

	/**
	 * Site search form
	 */
	public function SearchForm() {
		
		$searchText = _t('SearchForm.SEARCH', 'Search');

		if ($this->owner->request && $this->owner->request->getVar('Search')) {
			$searchText = $this->owner->request->getVar('Search');
		}

		$fields = new FieldList(
						new TextField('Search', false, $searchText)
		);
		$actions = new FieldList(
						new FormAction('results', _t('SearchForm.GO', 'Go'))
		);
		$form = new SearchForm($this->owner, 'SearchForm', $fields, $actions);
		return $form;
	}

	/**
	 * Process and render search results.
	 *
	 * @param array $data The raw request data submitted by user
	 * @param SearchForm $form The form instance that was submitted
	 * @param SS_HTTPRequest $request Request generated for this action
	 */
	public function getSearchResults($request) {

		$list = new ArrayList();
						
		$v = $request->getVars();
		if (!isset($v["start"]))
			$v["start"] = 0;

		$q = $v["Search"];
		$s = $v["start"];
		
		$input = DB::getConn()->addslashes($q);
		$data = DB::query(<<<EOF
SELECT 
	`pages`.`ID`, 
	`pages`.`ClassName`, 
	`pages`.`Title`, 
	GROUP_CONCAT(`do`.`Content` SEPARATOR ' ') as `Content`,
	`pages`.`PageID`,
	SUM(MATCH (`do`.`Title`, `do`.`Content`) AGAINST ('$input' IN NATURAL LANGUAGE MODE)) as `relevance`
FROM
	SearchableDataObjects as `pages`
JOIN
	SearchableDataObjects as `do`
ON
	`pages`.`ID` = `do`.`OwnerID` AND
	`pages`.`ClassName` = `do`.`OwnerClassName`
WHERE
	`pages`.`ID` = `pages`.`OwnerID` AND
    `pages`.`ClassName` = `pages`.`OwnerClassName`
GROUP BY
	`pages`.`ID`, 
	`pages`.`ClassName` 
HAVING
	`relevance`
ORDER BY
	`relevance` DESC
EOF
		);
		
		foreach ($data as $row) {
			
			$do = DataObject::get_by_id($row['ClassName'], $row['ID']);
			$do->Title = $row['Title'];
			$do->Content = $row['Content'];
						
			$list->push($do);
		}
			
		$ret = new PaginatedList($list);
		$ret->setPageLength(10);
		$ret->setPageStart($s);
		$ret->setTotalItems($list->count());
		$ret->setLimitItems(0);
		
		return $ret;
	}

	public function results($data, $form, $request) {
		
		$data = array(
				'Results' => $this->getSearchResults($request),
				'Query' => $form->getSearchQuery(),
				'Title' => _t('Search_results', 'Search Results')
		);
		return $this->owner->customise($data)->renderWith(array('Page_results', 'Page'));
	}

}
