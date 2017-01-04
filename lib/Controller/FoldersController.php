<?php

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Thomas Imbreckx <zinks@iozero.be>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * Mail
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Mail\Controller;

use Horde_Imap_Client_Exception;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Service\AccountService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FoldersController extends Controller {

	/** @var AccountService */
	private $accountService;

	/** @var string */
	private $currentUserId;

	/**  @var IMailManager */
	private $mailManager;

	/**
	 * @param type $appName
	 * @param IRequest $request
	 * @param AccountService $accountService
	 * @param string $UserId
	 * @param IMailManager $mailManager
	 */
	public function __construct($appName, IRequest $request,
		AccountService $accountService, $UserId, IMailManager $mailManager) {
		parent::__construct($appName, $request);
		$this->accountService = $accountService;
		$this->currentUserId = $UserId;
		$this->mailManager = $mailManager;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param int $accountId
	 * @return JSONResponse
	 */
	public function index($accountId) {
		$account = $this->accountService->find($this->currentUserId, $accountId);

		$folders = $this->mailManager->getFolders($account);
		return [
			'id' => $accountId,
			'email' => $account->getEmail(),
			'folders' => $folders,
			'delimiter' => reset($folders)->getDelimiter(),
		];
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function show() {
		$response = new JSONResponse();
		$response->setStatus(Http::STATUS_NOT_IMPLEMENTED);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function update() {
		$response = new JSONResponse();
		$response->setStatus(Http::STATUS_NOT_IMPLEMENTED);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @param int $accountId
	 * @param string $folderId
	 * @return JSONResponse
	 */
	public function destroy($accountId, $folderId) {
		try {
			$account = $this->accountService->find($this->currentUserId, $accountId);
			$imap = $account->getImapConnection();
			$imap->deleteMailbox($folderId);

			return new JSONResponse();
		} catch (DoesNotExistException $e) {
			return new JSONResponse(null, 404);
		}
	}

	/**
	 * @NoAdminRequired
	 * @param int $accountId
	 * @param string $mailbox
	 */
	public function create($accountId, $mailbox) {
		try {
			$account = $this->accountService->find($this->currentUserId, $accountId);
			$imap = $account->getImapConnection();

			// TODO: read http://tools.ietf.org/html/rfc6154
			$imap->createMailbox($mailbox);

			return new JSONResponse([
				'data' => [
					'id' => $mailbox
				]
				], Http::STATUS_CREATED);
		} catch (Horde_Imap_Client_Exception $e) {
			$response = new JSONResponse();
			$response->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
			return $response;
		} catch (DoesNotExistException $e) {
			return new JSONResponse();
		}
	}

	/**
	 * @NoAdminRequired
	 * @param $accountId
	 * @param $folders
	 * @return JSONResponse
	 */
	public function detectChanges($accountId, $folders) {
		try {
			$query = [];
			foreach ($folders as $folder) {
				$folderId = base64_decode($folder['id']);
				$parts = explode('/', $folderId);
				if (count($parts) > 1 && $parts[1] === 'FLAGGED') {
					continue;
				}
				if (isset($folder['error'])) {
					continue;
				}
				$query[$folderId] = $folder;
			}
			$account = $this->accountService->find($this->currentUserId, $accountId);
			$mailBoxes = $account->getChangedMailboxes($query);

			return new JSONResponse($mailBoxes);
		} catch (Horde_Imap_Client_Exception $e) {
			$response = new JSONResponse();
			$response->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
			return $response;
		} catch (DoesNotExistException $e) {
			return new JSONResponse();
		}
	}

}
