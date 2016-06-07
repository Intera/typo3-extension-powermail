<?php
namespace In2code\Powermail\Controller;

use \In2code\Powermail\Utility\Div,
	\In2code\Powermail\Domain\Model\Mail,
	\TYPO3\CMS\Core\Utility\GeneralUtility,
	\TYPO3\CMS\Extbase\Utility\LocalizationUtility,
	\TYPO3\CMS\Extbase\Persistence\Generic\QueryResult,
	\TYPO3\CMS\Core\Messaging\AbstractMessage;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Alex Kellner <alexander.kellner@in2code.de>, in2code.de
 *
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
 * Controller for powermail frontend output
 * (former part of the powermail_frontend extension)
 *
 * @package powermail
 * @license http://www.gnu.org/licenses/lgpl.html
 * 			GNU Lesser General Public License, version 3 or later
 */
class OutputController extends AbstractController {

	/**
	 * Show mails in a list
	 *
	 * @return void
	 */
	public function listAction() {
		// get all mails
		$mails = $this->mailRepository->findListBySettings($this->settings, $this->piVars);
		$this->view->assign('mails', $mails);

		// get fields for iteration
		if ($this->settings['list']['fields']) {
			$fieldArray = GeneralUtility::trimExplode(',', $this->settings['list']['fields'], TRUE);
		} else {
			$fieldArray = $this->div->getFieldsFromForm($this->settings['main']['form']);
		}
		$fields = $this->fieldRepository->findByUids($fieldArray);
		$searchFields = $this->fieldRepository->findByUids(GeneralUtility::trimExplode(',', $this->settings['search']['fields'], TRUE));
		$this->view->assignMultiple(
			array(
				'searchFields' => $searchFields,
				'fields' => $fields,
				'piVars' => $this->piVars,
				'abc' => Div::getAbcArray()
			)
		);
		$this->assignMultipleActions();
	}

	/**
	 * Show single mail
	 *
	 * @param int $mail
	 * @return void
	 */
	public function showAction($mail) {

		if ($this->settings['single']['disable']) {
			$mail = 0;
		}

		$mail = $this->getMailOrRedirectToList($mail);

		$this->view->assign('mail', $mail);

		// get fields for iteration
		if ($this->settings['single']['fields']) {
			$fieldArray = GeneralUtility::trimExplode(',', $this->settings['single']['fields'], TRUE);
		} else {
			$fieldArray = $this->div->getFieldsFromForm($this->settings['main']['form']);
		}
		$this->view->assign('fields', $this->fieldRepository->findByUids($fieldArray));
		$this->view->assign('mail', $mail);
		$this->assignMultipleActions();
	}

	/**
	 * Edit mail
	 *
	 * @param int $mail
	 * @return void
	 */
	public function editAction($mail) {

		$mail = $this->getMailOrRedirectToList($mail);

		$this->view->assign('mail', $mail);

		// get fields for iteration
		if ($this->settings['edit']['fields']) {
			$fieldArray = GeneralUtility::trimExplode(',', $this->settings['edit']['fields'], TRUE);
		} else {
			$fieldArray = $this->div->getFieldsFromForm($this->settings['main']['form']);
		}
		$fields = $this->fieldRepository->findByUids($fieldArray);
		$this->view->assign('selectedFields', $fields);
		$this->view->assign('mail', $mail);
		$this->assignMultipleActions();
	}

	/**
	 * Rewrite Arguments to receive a clean mail object
	 *
	 * @return void
	 */
	public function initializeUpdateAction() {
		$arguments = $this->request->getArguments();
		if (!$this->div->isAllowedToEdit($this->settings, $arguments['field']['__identity'])) {
			$this->controllerContext = $this->buildControllerContext();
			$this->addFlashmessage(
				LocalizationUtility::translate('PowermailFrontendEditFailed', 'powermail'),
				'',
				AbstractMessage::ERROR
			);
			$this->forward('list');
		}
		$this->reformatParamsForAction();
	}

	/**
	 * Update mail
	 *
	 * @param \In2code\Powermail\Domain\Model\Mail $mail
	 * @dontvalidate $mail
	 * @return void
	 */
	public function updateAction(Mail $mail) {

		$this->getMailOrRedirectToList($mail->getUid());

		if ($this->div->isAllowedToEdit($this->settings, $mail)) {

			/**
			 * @var \In2code\Powermail\Domain\Model\Answer $newAnswer
			 * @var \In2code\Powermail\Domain\Model\Answer $answer
			 */
			foreach ($mail->getAnswers() as $newAnswer) {
				// This is needed because the answer was not read from persistence before.
				$answer = $this->answerRepository->findByFieldAndMail($newAnswer->getField()->getUid(), $mail);
				$answer->setValue($newAnswer->getValue());
				$this->answerRepository->update($answer);
			}
			$this->flashMessageContainer->add(
				LocalizationUtility::translate('PowermailFrontendEditConfirm', 'powermail')
			);
		} else {
			$this->flashMessageContainer->add(
				LocalizationUtility::translate('PowermailFrontendEditFailed', 'powermail')
			);
		}

		$this->redirect('edit', NULL, NULL, array('mail' => $mail));
	}

	/**
	 * Check authentication
	 *
	 * @return void
	 */
	public function initializeDeleteAction() {
		$arguments = $this->request->getArguments();
		if (!$this->div->isAllowedToEdit($this->settings, $arguments['mail'])) {
			$this->controllerContext = $this->buildControllerContext();
			$this->addFlashmessage(
				LocalizationUtility::translate('PowermailFrontendDeleteFailed', 'powermail'),
				'',
				AbstractMessage::ERROR
			);
			$this->forward('list');
		}
	}

	/**
	 * Delete mail
	 *
	 * @param \In2code\Powermail\Domain\Model\Mail $mail
	 * @return void
	 */
	public function deleteAction(Mail $mail) {
		$this->assignMultipleActions();
		$this->mailRepository->remove($mail);
		$this->addFlashmessage(LocalizationUtility::translate('PowermailFrontendDeleteSuccessful', 'powermail'));
	}

	/**
	 * Export mails
	 *
	 * @param array $export Field Array with mails and format
	 * @dontvalidate $export
	 * @return void
	 */
	public function exportAction($export = array()) {
		if (!$this->settings['list']['export']) {
			return;
		}
		$mails = $this->mailRepository->findByUidList($export['fields']);

		// get field array for output
		if ($this->settings['list']['fields']) {
			$fieldArray = GeneralUtility::trimExplode(',', $this->settings['list']['fields'], TRUE);
		} else {
			$fieldArray = $this->div->getFieldsFromForm($this->settings['main']['form']);
		}
		$fields = $this->fieldRepository->findByUids($fieldArray);

		if ($export['format'] === 'xls') {
			$this->forward('exportXls', NULL, NULL, array('mails' => $mails, 'fields' => $fields));
		}
		$this->forward('exportCsv', NULL, NULL, array('mails' => $mails, 'fields' => $fields));
	}

	/**
	 * Export mails XLS
	 *
	 * @param QueryResult $mails mails objects
	 * @param array $fields uid field list
	 * @dontvalidate $mails
	 * @dontvalidate $fields
	 * @return 	void
	 */
	public function exportXlsAction(QueryResult $mails = NULL, $fields = array()) {
		$this->view->assign('mails', $mails);
		$this->view->assign('fields', $fields);
	}

	/**
	 * Export mails CSV
	 *
	 * @param QueryResult $mails mails objects
	 * @param array $fields uid field list
	 * @dontvalidate $mails
	 * @dontvalidate $fields
	 * @return void
	 */
	public function exportCsvAction(QueryResult $mails = NULL, $fields = array()) {
		$this->view->assign('mails', $mails);
		$this->view->assign('fields', $fields);
	}

	/**
	 * RSS Action List
	 *
	 * @return void
	 */
	public function rssAction() {
		$mails = $this->mailRepository->findListBySettings($this->settings, $this->piVars);
		$this->view->assign('mails', $mails);
		$this->assignMultipleActions();
	}

	/**
	 * Tries to fetch the mail with the given UID and the current settings from the database.
	 * If no mail object can be fetched the user is redirected to the list with a flash messsage.
	 *
	 * @param int $mailUid
	 * @return Mail
	 */
	protected function getMailOrRedirectToList($mailUid) {

		$mailUid = (int)$mailUid;
		$mail = NULL;

		if ($mailUid) {
			$mail = $this->mailRepository->findListBySettings($this->settings, array(), $mailUid);
		}

		if (!$mail instanceof Mail) {
			$this->addFlashMessage(LocalizationUtility::translate('PowermailFrontendMailNotAccessible', 'powermail'));
			$this->redirect('list');
		}

		return $mail;
	}

	/**
	 * Object initialization
	 *
	 * @return void
	 */
	public function initializeObject() {
		Div::mergeTypoScript2FlexForm($this->settings, 'Pi2');
	}

	/**
	 * Assign variables
	 *
	 * @return void
	 */
	protected function assignMultipleActions() {
		if (empty($this->settings['single']['pid'])) {
			$this->settings['single']['pid'] = $GLOBALS['TSFE']->id;
		}
		if (empty($this->settings['list']['pid'])) {
			$this->settings['list']['pid'] = $GLOBALS['TSFE']->id;
		}
		if (empty($this->settings['edit']['pid'])) {
			$this->settings['edit']['pid'] = $GLOBALS['TSFE']->id;
		}
		$this->view->assign('singlePid', $this->settings['single']['pid']);
		$this->view->assign('listPid', $this->settings['list']['pid']);
		$this->view->assign('editPid', $this->settings['edit']['pid']);
	}

	/**
	 * Action initialization
	 *
	 * @return void
	 */
	protected function initializeAction() {
		parent::initializeAction();

		// check if ts is included
		if (!isset($this->settings['staticTemplate'])) {
			$this->controllerContext = $this->buildControllerContext();
			$this->addFlashMessage(
				LocalizationUtility::translate('error_no_typoscript_pi2', 'powermail'),
				'',
				AbstractMessage::ERROR
			);
		}
	}
}