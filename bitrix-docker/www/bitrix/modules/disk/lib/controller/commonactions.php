<?php

namespace Bitrix\Disk\Controller;

use Bitrix\Disk\Driver;
use Bitrix\Main\Application;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Disk;
use Bitrix\Disk\ZipNginx;

class CommonActions extends BaseObject
{
	public function configureActions()
	{
		$configureActions = parent::configureActions();
		$configureActions['search'] = [
			'class' => Disk\Controller\Action\SearchAction::class,
			'+prefilters' => [
				new ActionFilter\CloseSession(),
			]
		];

		$configureActions['getArchiveLink'] = [
			'-prefilters' => [
				ActionFilter\HttpMethod::class,
			],
			'+prefilters' => [
				new ActionFilter\HttpMethod(
					[ActionFilter\HttpMethod::METHOD_POST]
				),
				new Disk\Internals\Engine\ActionFilter\HumanReadableError(),
			]
		];

		$configureActions['downloadArchive'] = [
			'-prefilters' => [
				ActionFilter\Csrf::class,
				ActionFilter\Authentication::class,
			],
			'+prefilters' => [
				new ActionFilter\Authentication(true),
				new Disk\Internals\Engine\ActionFilter\HumanReadableError(),
				new Disk\Internals\Engine\ActionFilter\CheckArchiveSignature(),
				new ActionFilter\CloseSession(),
			]
		];

		return $configureActions;
	}

	public function getAction(Disk\BaseObject $object)
	{
		return $this->get($object);
	}

	public function renameAction(
		Disk\BaseObject $object,
		string $newName,
		bool $autoCorrect = false,
		bool $generateUniqueName = false
	)
	{
		return $this->rename($object, $newName, $autoCorrect, $generateUniqueName);
	}

	public function moveAction(Disk\BaseObject $object, Disk\Folder $toFolder)
	{
		return $this->move($object, $toFolder);
	}

	public function markDeletedAction(Disk\BaseObject $object)
	{
		$this->markDeleted($object);
	}

	public function deleteAction(Disk\BaseObject $object)
	{
		if ($object instanceof Disk\File)
		{
			$this->deleteFile($object);
		}
		else
		{
			$this->deleteFolder($object);
		}
	}

	public function restoreAction(Disk\BaseObject $object)
	{
		return $this->restore($object);
	}

	public function restoreCollectionAction(Disk\Type\ObjectCollection $objectCollection)
	{
		$restoredIds = [];
		$currentUserId = $this->getCurrentUser()->getId();
		foreach ($objectCollection as $object)
		{
			/** @var Disk\BaseObject $object */
			$securityContext = $object->getStorage()->getSecurityContext($currentUserId);
			if ($object->canRestore($securityContext))
			{
				if (!$object->restore($currentUserId))
				{
					$this->errorCollection->add($object->getErrors());
					continue;
				}

				$restoredIds[] = $object->getRealObjectId();
			}
		}

		return [
			'restoredObjectIds' => $restoredIds,
		];
	}

	public function generateExternalLinkAction(Disk\BaseObject $object)
	{
		return $this->generateExternalLink($object);
	}

	public function disableExternalLinkAction(Disk\BaseObject $object)
	{
		return $this->disableExternalLink($object);
	}

	public function getExternalLinkAction(Disk\BaseObject $object)
	{
		return $this->getExternalLink($object);
	}

	public function getAllowedOperationsRightsAction(Disk\BaseObject $object)
	{
		return $this->getAllowedOperationsRights($object);
	}

	public function getArchiveLinkAction(Disk\Type\ObjectCollection $objectCollection)
	{
		$uri = $this->getActionUri(
			'downloadArchive',
			[
				'objectCollection' => $objectCollection->getIds(),
				'signature' => Disk\Security\ParameterSigner::getArchiveSignature($objectCollection->getIds()),
			]
		);

		return [
			'downloadArchiveUri' => $uri,
		];
	}

	public function downloadArchiveAction(Disk\Type\ObjectCollection $objectCollection): ZipNginx\Archive
	{
		$archiveName = 'archive' . date('y-m-d');

		return ZipNginx\Archive::createByObjects($archiveName, $objectCollection, $this->getCurrentUser()?->getId());
	}

	public function listRecentlyUsedAction()
	{
		$recentlyUsedManager = Driver::getInstance()->getRecentlyUsedManager();

		return [
			'files' => $recentlyUsedManager->getFileModelListByUser($this->getCurrentUser()),
		];
	}
}