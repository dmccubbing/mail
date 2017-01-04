<?php

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Mail\Tests\Controller;

use Horde_Imap_Client_Exception;
use Horde_Imap_Client_Socket;
use OCA\Mail\Account;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Controller\FoldersController;
use OCA\Mail\Folder;
use OCA\Mail\Service\AccountService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;

class FoldersControllerTest extends PHPUnit_Framework_TestCase {

	/** @var string */
	private $appName = 'mail';

	/** @var IRequest|PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var AccountService|PHPUnit_Framework_MockObject_MockObject */
	private $accountService;

	/** @var string */
	private $userId = 'john';

	/** @var IMailManager|PHPUnit_Framework_MockObject_MockObject */
	private $mailManager;

	/** @var FoldersController */
	private $controller;

	public function setUp() {
		$this->request = $this->createMock(IRequest::class);
		$this->accountService = $this->createMock(AccountService::class);
		$this->mailManager = $this->createMock(IMailManager::class);
		$this->controller = new FoldersController($this->appName, $this->request, $this->accountService, $this->userId, $this->mailManager);
	}

	public function testIndex() {
		$account = $this->createMock(Account::class);
		$folder = $this->createMock(Folder::class);
		$accountId = 28;
		$this->accountService->expects($this->once())
			->method('find')
			->with($this->equalTo($this->userId), $this->equalTo($accountId))
			->willReturn($account);
		$this->mailManager->expects($this->once())
			->method('getFolders')
			->with($this->equalTo($account))
			->willReturn([
				$folder
		]);
		$account->expects($this->once())
			->method('getEmail')
			->willReturn('user@example.com');
		$folder->expects($this->once())
			->method('getDelimiter')
			->willReturn('.');

		$result = $this->controller->index($accountId);

		$expected = [
			'id' => 28,
			'email' => 'user@example.com',
			'folders' => [
				$folder,
			],
			'delimiter' => '.',
		];
		$this->assertEquals($expected, $result);
	}

	public function testShow() {
		$result = $this->controller->show();
		$this->assertEquals(Http::STATUS_NOT_IMPLEMENTED, $result->getStatus());
	}

	public function testUpdate() {
		$result = $this->controller->update();
		$this->assertEquals(Http::STATUS_NOT_IMPLEMENTED, $result->getStatus());
	}

	public function testDestroy() {
		$accountId = 28;
		$folderId = 'my folder';
		$account = $this->createMock(Account::class);
		$this->accountService->expects($this->once())
			->method('find')
			->with($this->userId, $accountId)
			->will($this->returnValue($account));
		$imapConnection = $this->createMock(Horde_Imap_Client_Socket::class);
		$account->expects($this->once())
			->method('getImapConnection')
			->will($this->returnValue($imapConnection));
		$imapConnection->expects($this->once())
			->method('deleteMailbox')
			->with($folderId);

		$this->controller->destroy($accountId, $folderId);
	}

	public function testDestroyAccountNotFound() {
		$accountId = 28;
		$folderId = 'my folder';
		$this->accountService->expects($this->once())
			->method('find')
			->with($this->userId, $accountId)
			->will($this->throwException(new DoesNotExistException('folder not found')));

		$response = $this->controller->destroy($accountId, $folderId);
		$expected = new JSONResponse(null, 404);

		$this->assertEquals($expected, $response);
	}

	public function testDestroyFolderNotFound() {
		// TODO: write test
	}

	public function testCreate() {
		$accountId = 13;
		$folderId = 'new folder';
		$account = $this->createMock(Account::class);
		$this->accountService->expects($this->once())
			->method('find')
			->with($this->userId, $accountId)
			->will($this->returnValue($account));
		$imapConnection = $this->createMock(Horde_Imap_Client_Socket::class);
		$account->expects($this->once())
			->method('getImapConnection')
			->will($this->returnValue($imapConnection));
		$imapConnection->expects($this->once())
			->method('createMailbox')
			->with($folderId);

		$response = $this->controller->create($accountId, $folderId);

		$expected = new JSONResponse([
			'data' => [
				'id' => $folderId
			]
			], Http::STATUS_CREATED);

		$this->assertEquals($expected, $response);
	}

	public function testCreateWithError() {
		$accountId = 13;
		$folderId = 'new folder';
		$account = $this->createMock(Account::class);
		$this->accountService->expects($this->once())
			->method('find')
			->with($this->userId, $accountId)
			->will($this->returnValue($account));
		$imapConnection = $this->createMock(Horde_Imap_Client_Socket::class);
		$account->expects($this->once())
			->method('getImapConnection')
			->will($this->returnValue($imapConnection));
		$imapConnection->expects($this->once())
			->method('createMailbox')
			->with($folderId)
			->will($this->throwException(new Horde_Imap_Client_Exception()));

		$response = $this->controller->create($accountId, $folderId);

		$expected = new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);

		$this->assertEquals($expected, $response);
	}

	public function testCreateSubFolder() {
		// TODO: write test
	}

	public function testDetectChanges() {
		// TODO: write test
	}

}
