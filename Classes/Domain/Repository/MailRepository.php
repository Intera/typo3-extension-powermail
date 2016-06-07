<?php
namespace In2code\Powermail\Domain\Repository;

use In2code\Powermail\Utility\Div;
use \TYPO3\CMS\Extbase\Persistence\QueryInterface;
use \TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 in2code GmbH <info@in2code.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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
 * MailRepository
 *
 * @package powermail
 * @license http://www.gnu.org/licenses/lgpl.html
 * 			GNU Lesser General Public License, version 3 or later
 */
class MailRepository extends \TYPO3\CMS\Extbase\Persistence\Repository {

	/**
	 * fieldRepository
	 *
	 * @var \In2code\Powermail\Domain\Repository\FieldRepository
	 * @inject
	 */
	protected $fieldRepository;

	/**
	 * Find all mails in given PID (BE List)
	 *
	 * @param int $pid
	 * @param array $settings TypoScript Config Array
	 * @param array $piVars Plugin Variables
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult
	 */
	public function findAllInPid($pid = 0, $settings = array(), $piVars = array()) {
		$query = $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
		$query->getQuerySettings()->setIgnoreEnableFields(TRUE);

		// initial filter
		$and = array(
			$query->equals('deleted', 0),
			$query->equals('pid', $pid)
		);

		// filter
		if (isset($piVars['filter'])) {
			foreach ((array) $piVars['filter'] as $field => $value) {

				// Standard Fields
				if (!is_array($value)) {
					// Fulltext Search
					if ($field === 'all' && !empty($value)) {
						$or = array(
							$query->like('sender_name', '%' . $value . '%'),
							$query->like('sender_mail', '%' . $value . '%'),
							$query->like('subject', '%' . $value . '%'),
							$query->like('receiver_mail', '%' . $value . '%'),
							$query->like('sender_ip', '%' . $value . '%'),
							$query->like('answers.value', '%' . $value . '%')
						);
						$and[] = $query->logicalOr($or);
					}

					// Time Filter Start
					elseif ($field === 'start' && !empty($value)) {
						$and[] = $query->greaterThan('crdate', strtotime($value));
					}

					// Time Filter Stop
					elseif ($field === 'stop' && !empty($value)) {
						$and[] = $query->lessThan('crdate', strtotime($value));
					}

					// Hidden Filter
					elseif ($field === 'hidden' && !empty($value)) {
						$and[] = $query->equals($field, ($value - 1));
					}

					// Other Fields
					elseif (!empty($value)) {
						$and[] = $query->like($field, '%' . $value . '%');
					}
				}

				// Answer Fields
				if (is_array($value)) {
					foreach ((array) $value as $answerField => $answerValue) {
						if (empty($answerValue)) {
							continue;
						}
						$and[] = $query->equals('answers.field', $answerField);
						$and[] = $query->like('answers.value', '%' . $answerValue . '%');
					}
				}
			}
		}

		// create constraint
		$constraint = $query->logicalAnd($and);
		$query->matching($constraint);

		// set sorting
		$sortby = ($settings['sortby'] ? $settings['sortby'] : 'crdate');
		$order = ($settings['order'] === 'asc' ? QueryInterface::ORDER_ASCENDING : QueryInterface::ORDER_DESCENDING);
		if (isset($piVars['sorting'])) {
			foreach ((array) $piVars['sorting'] as $key => $value) {
				$sortby = $key;
				$order = ($value === 'asc' ? QueryInterface::ORDER_ASCENDING : QueryInterface::ORDER_DESCENDING);
				break;
			}
		}
		$sortby = preg_replace('/[^a-zA-Z0-9_-]/', '', $sortby);
		$query->setOrderings(
			array(
				$sortby => $order
			)
		);

		// go for it
		$mails = $query->execute();
		return $mails;
	}

	/**
	 * Find first mail in given PID
	 *
	 * @param int $pid
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult
	 */
	public function findFirstInPid($pid = 0) {
		$query = $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
		$query->getQuerySettings()->setIgnoreEnableFields(TRUE);

		// initial filter
		$and = array(
			$query->equals('deleted', 0),
			$query->equals('pid', $pid)
		);

		// create constraint
		$constraint = $query->logicalAnd($and);
		$query->matching($constraint);

		// sorting
		$query->setOrderings(
			array(
				'crdate' => QueryInterface::ORDER_DESCENDING
			)
		);

		// set limit
		$query->setLimit(1);

		// go for it
		$mails = $query->execute();
		return $mails->getFirst();
	}

	/**
	 * Find mails by given UID (also hidden and don't care about starting page)
	 *
	 * @param int $uid
	 * @return \In2code\Powermail\Domain\Model\Mail
	 */
	public function findByUid($uid) {
		$query = $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
		$query->getQuerySettings()->setIgnoreEnableFields(TRUE);
		$query->getQuerySettings()->setLanguageMode(NULL);

		$and = array(
			$query->equals('uid', $uid),
			$query->equals('deleted', 0)
		);
		$query->matching(
			$query->logicalAnd($and)
		);

		return $query->execute()->getFirst();
	}

	/**
	 * Find mails in UID List
	 *
	 * @param string $uidList Commaseparated UID List of mails
	 * @param array $sorting array('field' => 'asc')
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult
	 */
	public function findByUidList($uidList, $sorting = array()) {
		$uids = GeneralUtility::trimExplode(',', $uidList, TRUE);
		$query = $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
		$query->getQuerySettings()->setIgnoreEnableFields(TRUE);

		// initial filter
		$and = array(
			$query->equals('deleted', 0),
			$query->in('uid', $uids)
		);

		// create constraint
		$constraint = $query->logicalAnd($and);
		$query->matching($constraint);

		// sorting
		$query->setOrderings(
			array(
				'crdate' => QueryInterface::ORDER_DESCENDING
			)
		);
		foreach ((array) $sorting as $field => $order) {
			if (empty($order)) {
				continue;
			}

			$field = preg_replace('/[^a-zA-Z0-9_-]/', '', $field);
			$query->setOrderings(
				array (
					$field => (
						$order === 'asc' ? QueryInterface::ORDER_ASCENDING : QueryInterface::ORDER_DESCENDING
					)
				)
			);
		}

		// go for it
		$mails = $query->execute();
		return $mails;
	}

	/**
	 * @param string $marker
	 * @param string $value
	 * @param \In2code\Powermail\Domain\Model\Form $form
	 * @param int $pageUid
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
	 */
	public function findByMarkerValueForm($marker, $value, $form, $pageUid) {
		$query = $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
		$and = array(
			$query->equals('answers.field', $this->fieldRepository->findByMarkerAndForm($marker, $form->getUid())),
			$query->equals('answers.value', $value),
			$query->equals('pid', $pageUid)
		);
		$query->matching($query->logicalAnd($and));
		return $query->execute();
	}

	/**
	 * Query for Pi2
	 *
	 * @param \array $settings TypoScript Settings
	 * @param \array $piVars Plugin Variables
	 * @param int $mailUid Optional UID of a mail record. If set, only one Mail object will be returned.
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface|\In2code\Powermail\Domain\Model\Mail A single mail
	 * record if mail UID is provided or a query result object.
	 */
	public function findListBySettings($settings, $piVars, $mailUid = NULL) {
		$query = $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);

		/**
		 * FILTER start
		 */
		$and = array(
			$query->greaterThan('uid', 0)
		);

		if (isset($mailUid)) {
			$and[] = $query->equals('uid', $mailUid);
		}

		// FILTER: form
		if (intval($settings['main']['form']) > 0) {
			$and[] = $query->equals('form', $settings['main']['form']);
		}

		// FILTER: pid
		if (intval($settings['main']['pid']) > 0) {
			$and[] = $query->equals('pid', $settings['main']['pid']);
		}

		// FILTER: delta
		if (intval($settings['list']['delta']) > 0) {
			$and[] = $query->greaterThan('crdate', (time() - $settings['list']['delta']));
		}

		// FILTER: showownonly
		if ($settings['list']['showownonly']) {
			$and[] = $query->equals('feuser', $GLOBALS['TSFE']->fe_user->user['uid']);
		}

		if ($settings['main']['requiredFields']) {
			$requiredFieldUidArray = GeneralUtility::intExplode(',', $settings['main']['requiredFields'], TRUE);
			if (!empty($requiredFieldUidArray)) {
				$mailUidsWhereRequiredFieldsAreNotEmpty = $this->findWhereAnswerFieldsAreNotEmpty($requiredFieldUidArray, $settings['main']['form']);
				if (empty($mailUidsWhereRequiredFieldsAreNotEmpty)) {
					$and[] = $query->equals('uid', 0);
				} else {
					$and[] = $query->in('uid', $mailUidsWhereRequiredFieldsAreNotEmpty);
				}
			}
		}

		// FILTER: abc
		if (isset($piVars['filter']['abc'])) {
			$and[] = $query->equals('answers.field', $settings['search']['abc']);
			$and[] = $query->like('answers.value', $piVars['filter']['abc'] . '%');
		}

		// FILTER: field
		if (isset($piVars['filter'])) {
			// fulltext
			if (!empty($piVars['filter']['_all'])) {
				$and[] = $query->like('answers.value', '%' . $piVars['filter']['_all'] . '%');
			}

			// single field search
			$filter = array();
			foreach ((array) $piVars['filter'] as $field => $value) {
				if (is_numeric($field) && !empty($value)) {
					$filterAnd = array(
						$query->equals('answers.field', $field),
						$query->like('answers.value', '%' . $value . '%')
					);
					$filter[] = $query->logicalAnd($filterAnd);
				}
			}

			if (count($filter) > 0) {
				// TODO AND
				$and[] = $query->logicalOr($filter);
			}

		}

		// FILTER: create constraint
		$constraint = $query->logicalAnd($and);
		$query->matching($constraint);

		// sorting
		$query->setOrderings(
			array(
				'crdate' => QueryInterface::ORDER_DESCENDING
			)
		);

		// set limit
		if (intval($settings['list']['limit']) > 0) {
			$query->setLimit(intval($settings['list']['limit']));
		}

		/**
		 * FINISH
		 */
		if (isset($mailUid)) {
			$mails = $query->execute()->getFirst();
		} else {
			$mails = $query->execute();
		}
		return $mails;
	}

	/**
	 * Returns an array of mail UIDs where the answers to the given fields
	 * are not empty.
	 *
	 * @param array $fieldUidArray
	 * @param int $mailFormUid
	 * @return array
	 */
	protected function findWhereAnswerFieldsAreNotEmpty(array $fieldUidArray, $mailFormUid = NULL) {

		$mailUids = array();

		$joinStatements = '';
		$whereStatements = '1=1';
		$counter = 1;

		if (isset($mailForm)) {
			$mailFormUid = (int)$mailFormUid;
			if ($mailFormUid !== 0) {
				$whereStatements .= ' AND mail.form=' . $mailFormUid;
			}
		}

		foreach ($fieldUidArray as $fieldUid) {
			$fieldName = 'answer'. $counter;
			$joinStatements .= " JOIN tx_powermail_domain_model_answers as $fieldName on ($fieldName.mail=mail.uid and $fieldName.field=$fieldUid)";
			$whereStatements .= " AND ($fieldName.uid IS NOT NULL AND $fieldName.value != '')";
			$counter++;
		}

		$db = $this->getDatabaseConnection();
		$result = $db->exec_SELECTquery(
			'mail.uid',
			'tx_powermail_domain_model_mails mail' . $joinStatements,
			$whereStatements
		);

		while ($row = $db->sql_fetch_row($result)) {
			$mailUids[] = $row[0];
		}

		return $mailUids;
	}

	/**
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}
}