<?php

namespace Bitrix\HumanResources\Repository;

use Bitrix\HumanResources\Contract\Service\EventSenderService;
use Bitrix\HumanResources\Contract\Repository\RoleRepository;
use Bitrix\HumanResources\Exception\CreationFailedException;
use Bitrix\HumanResources\Exception\UpdateFailedException;
use Bitrix\HumanResources\Item;
use Bitrix\HumanResources\Item\NodeMember;
use Bitrix\HumanResources\Item\Structure;
use Bitrix\HumanResources\Model;
use Bitrix\HumanResources\Model\NodeMemberTable;
use Bitrix\HumanResources\Service\Container;
use Bitrix\HumanResources\Enum\EventName;
use Bitrix\HumanResources\Type\MemberEntityType;
use Bitrix\HumanResources\Type\RelationEntityType;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\HumanResources\Contract;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main;
use Bitrix\Main\SystemException;

class NodeMemberRepository implements Contract\Repository\NodeMemberRepository
{
	private EventSenderService $eventSenderService;
	private RoleRepository $roleRepository;
	private static array $queryTotalCount = [];

	private const CACHE_TTL = 86400;

	/**
	 * @param EventSenderService|null $eventSenderService
	 * @param RoleRepository|null $roleRepository
	 */
	public function __construct(
		?EventSenderService $eventSenderService = null,
		?RoleRepository $roleRepository = null
	)
	{
		$this->eventSenderService = $eventSenderService ?? Container::getEventSenderService();
		$this->roleRepository = $roleRepository ?? Container::getRoleRepository();
	}

	private function convertModelToItem(Model\NodeMember $nodeMember): Item\NodeMember
	{
		return new Item\NodeMember(
			entityType: MemberEntityType::tryFrom($nodeMember->getEntityType()),
			entityId: $nodeMember->getEntityId(),
			nodeId: $nodeMember->getNodeId(),
			active: $nodeMember->getActive(),
			roles: $nodeMember->getRole()
				->getIdList(),
			id: $nodeMember->getId(),
			addedBy: $nodeMember->getAddedBy(),
			createdAt: $nodeMember->getCreatedAt(),
			updatedAt: $nodeMember->getUpdatedAt(),
		);
	}

	private function convertModelArrayToItem(array $nodeMember): Item\NodeMember
	{
		return new Item\NodeMember(
			entityType: MemberEntityType::tryFrom($nodeMember['ENTITY_TYPE']),
			entityId: $nodeMember['ENTITY_ID'] ?? null,
			nodeId: $nodeMember['NODE_ID'] ?? null,
			active: $nodeMember['ACTIVE'] ?? null,
			roles: [$nodeMember['HUMANRESOURCES_MODEL_NODE_MEMBER_ROLE_ID'] ?? null],
			id: $nodeMember['ID'] ?? null,
			addedBy: $nodeMember['ADDED_BY'] ?? null,
			createdAt: $nodeMember['CREATED_AT'] ?? null,
			updatedAt: $nodeMember['UPDATED_AT'] ?? null,
		);
	}

	/**
	 * @param \Bitrix\HumanResources\Item\NodeMember $nodeMember
	 *
	 * @return \Bitrix\HumanResources\Item\NodeMember
	 * @throws \Bitrix\HumanResources\Exception\CreationFailedException
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\DB\SqlQueryException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function create(Item\NodeMember $nodeMember): Item\NodeMember
	{
		if (!$this->validate($nodeMember))
		{
			throw (new CreationFailedException())->addError(
				new Main\Error('nodeMember is invalid')
			);
		}

		$nodeMemberEntity = Model\NodeMemberTable::getEntity()
			->createObject()
		;
		$currentUserId = CurrentUser::get()
			->getId()
		;

		$existedMember = $this->findByEntityTypeAndEntityIdAndNodeId(
			$nodeMember->entityType,
			$nodeMember->entityId,
			$nodeMember->nodeId
		);

		if ($existedMember)
		{
			if (!isset($nodeMember->role))
			{
				return $nodeMember;
			}

			Model\NodeMemberRoleTable::deleteList(['=MEMBER_ID' => $existedMember->id]);
			$existedMember->role = $nodeMember->role;
			$this->insertNodeMemberRole($existedMember, $currentUserId);

			return $existedMember;
		}

		if (isset($nodeMember->active))
		{
			$nodeMemberEntity->setActive($nodeMember->active);
		}

		if (!isset($nodeMember->role))
		{
			$employeeRoleId = Container::getRoleHelperService()->getEmployeeRoleId();

			$nodeMember->role = $employeeRoleId;
		}

		try
		{
			$nodeMemberCreateResult =
				$nodeMemberEntity
					->setNodeId($nodeMember->nodeId)
					->setAddedBy($currentUserId)
					->setEntityId($nodeMember->entityId)
					->setEntityType($nodeMember->entityType->name)
					->save()
			;

			if (!$nodeMemberCreateResult->isSuccess())
			{
				throw (new CreationFailedException())
					->setErrors($nodeMemberCreateResult->getErrorCollection())
				;
			}

			$nodeMember->id = $nodeMemberCreateResult->getId();

			$this->insertNodeMemberRole($nodeMember, $currentUserId);
			$nodeMemberEntity->entity->cleanCache();

			$this->eventSenderService->send(
				EventName::MEMBER_ADDED, [
					'member' => $nodeMember,
				]
			);
		}
		catch (\Exception $e)
		{
			throw (new CreationFailedException())->addError(
				new Main\Error($e->getMessage())
			);
		}

		return $nodeMember;
	}

	/**
	 * @param \Bitrix\HumanResources\Item\Collection\NodeMemberCollection $nodeMemberCollection
	 *
	 * @return \Bitrix\HumanResources\Item\Collection\NodeMemberCollection
	 * @throws CreationFailedException
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\DB\SqlQueryException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function createByCollection(
		Item\Collection\NodeMemberCollection $nodeMemberCollection
	): Item\Collection\NodeMemberCollection
	{
		$connection = Application::getConnection();
		try
		{
			$connection->startTransaction();
			foreach ($nodeMemberCollection as $nodeMember)
			{
				$this->create($nodeMember);
			}
			$connection->commitTransaction();
		}
		catch (\Exception $exception)
		{
			$connection->rollbackTransaction();
			throw $exception;
		}

		return $nodeMemberCollection;
	}

	/**
	 * @param \Bitrix\HumanResources\Item\NodeMember $nodeMember
	 *
	 * @return bool
	 */
	public function remove(Item\NodeMember $nodeMember): bool
	{
		try
		{
			Model\NodeMemberTable::delete($nodeMember->id);
			Model\NodeMemberRoleTable::deleteList(['=MEMBER_ID' => $nodeMember->id]);

			$this->eventSenderService->send(
				EventName::MEMBER_DELETED, [
					'member' => $nodeMember,
				]
			);

			return true;
		}
		catch (\Exception)
		{
		}

		return false;
	}

	/**
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\HumanResources\Exception\WrongStructureItemException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function findAllByNodeId(
		int $nodeId,
		bool $withAllChildNodes = false,
		int $limit = 100,
		int $offset = 0
	): Item\Collection\NodeMemberCollection
	{
		$nodeMemberQuery = $this->getBaseQuery(
			$nodeId,
			$withAllChildNodes,
			$limit,
			$offset
		)
		->setCacheTtl(self::CACHE_TTL)
		->cacheJoins(true)
		;

		$nodeMemberCollection = new Item\Collection\NodeMemberCollection();
		$nodeMemberEntities = $nodeMemberQuery->fetchAll();
		foreach ($nodeMemberEntities as $nodeMember)
		{
			$nodeMemberCollection->add($this->convertModelArrayToItem($nodeMember));
		}

		return $nodeMemberCollection;
	}

	private function getBaseQuery(
		int $nodeId,
		bool $withAllChildNodes = false,
		?int $limit = 100,
		?int $offset = 0
	): Query
	{
		$nodeMemberQuery =
			Model\NodeMemberTable::query()
				->setSelect(['*', 'ROLE'])
				->addOrder('ID')
				->setOffset($offset)
				->setLimit($limit)
				->setCacheTtl(self::CACHE_TTL)
				->cacheJoins(true)
		;

		if ($withAllChildNodes)
		{
			$nodeMemberQuery->registerRuntimeField(
					'ncn',
					new Reference(
						'ncn',
						Model\NodePathTable::class,
						Join::on('this.NODE_ID', 'ref.CHILD_ID'),
					),
				)
				->where('ncn.PARENT_ID', $nodeId)
			;
		}
		else
		{
			$nodeMemberQuery->where('NODE_ID', $nodeId);
		}

		return $nodeMemberQuery;
	}

	/**
	 * Finds a node member by their ID.
	 *
	 * @param int $memberId The ID of the node member to find.
	 *
	 * @return Item\NodeMember|null The found node member, or null if not found.
	 */
	public function findById(int $memberId): ?Item\NodeMember
	{
		try
		{
			$nodeMember = Model\NodeMemberTable::query()
				->setSelect(['*', 'ROLE'])
				->setLimit(1)
				->where('ID', $memberId)
				->fetchObject()
			;
			if ($nodeMember === null)
			{
				return null;
			}

			return $this->convertModelToItem($nodeMember);
		}
		catch (\Exception)
		{
			return null;
		}
	}

	/**
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function findByEntityTypeAndEntityIdAndNodeId(
		MemberEntityType $entityType,
		int $entityId,
		int $nodeId
	): ?Item\NodeMember
	{

		$nodeMember = NodeMemberTable::query()
			->setSelect(['*', 'ROLE'])
			->where('ENTITY_TYPE', $entityType->name)
			->where('NODE_ID', $nodeId)
			->where('ENTITY_ID', $entityId)
			->fetchObject()
		;

		return $nodeMember !== null ? $this->convertModelToItem($nodeMember) : null;
	}

	/**
	 * @param int $nodeId
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\DB\SqlQueryException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function removeAllMembersByNodeId(int $nodeId): void
	{
		NodeMemberTable::deleteList(['=NODE_ID' => $nodeId,]);
	}

	/**
	 * @param \Bitrix\HumanResources\Item\NodeMember $nodeMember
	 * @param int|null $currentUserId
	 *
	 * @return void
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\SystemException
	 */
	private function insertNodeMemberRole(Item\NodeMember $nodeMember, ?int $currentUserId): void
	{
		if ($nodeMember->role === null)
		{
			$employeeRole = $this->roleRepository->findByXmlId(Item\NodeMember::DEFAULT_ROLE_XML_ID['EMPLOYEE']);
			$nodeMember->role = $employeeRole->id;
		}

		Model\NodeMemberRoleTable::getEntity()
			->createObject()
			->setRoleId($nodeMember->role)
			->setMemberId($nodeMember->id)
			->setCreatedBy($currentUserId)
			->save()
		;
	}

	/**
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\HumanResources\Exception\WrongStructureItemException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function findAllByRoleIdAndNodeId(?int $roleId, ?int $nodeId): Item\Collection\NodeMemberCollection
	{
		$nodeMemberEntities = NodeMemberTable::query()
			->setSelect(['*'])
			->where('ROLE.ID', $roleId)
			->where('NODE_ID', $nodeId)
			->fetchAll()
		;

		$nodeMemberCollection = new Item\Collection\NodeMemberCollection();
		foreach ($nodeMemberEntities as $nodeMemberEntity)
		{
			$nodeMemberCollection->add($this->convertModelArrayToItem($nodeMemberEntity));
		}

		return $nodeMemberCollection;
	}

	/**
	 * Represents a member in the Item's node.
	 *
	 * @param Item\NodeMember $member The member to be represented.
	 *
	 * @return Item\NodeMember
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\DB\SqlQueryException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 * @throws UpdateFailedException
	 */
	public function update(Item\NodeMember $member): Item\NodeMember
	{
		if (!$this->validate($member))
		{
			throw (new UpdateFailedException())->addError(
				new Main\Error('nodeMember is invalid')
			);
		}

		$nodeMemberEntity = NodeMemberTable::getById($member->id)
			->fetchObject()
		;

		if (!$nodeMemberEntity)
		{
			return $member;
		}

		$updatedFields = [];

		if (isset($member->role))
		{
			$updatedFields[] = 'role';

			Model\NodeMemberRoleTable::deleteList(['=MEMBER_ID' => $member->id]);
			$this->insertNodeMemberRole($member, CurrentUser::get()->getId());
		}

		if (isset($member->active))
		{
			$updatedFields[] = 'active';

			$nodeMemberEntity->setActive($member->active);
		}

		if (
			$nodeMemberEntity->getNodeId() !== $member->nodeId
		)
		{
			if (
				$this->findByEntityTypeAndEntityIdAndNodeId(
					entityType: $member->entityType,
					entityId: $member->entityId,
					nodeId: $member->nodeId
				)
			)
			{
				throw (new UpdateFailedException())
					->addError(new Main\Error('Member already belongs to this node'))
				;
			}
			$updatedFields[] = 'nodeId';

			$nodeMemberEntity->setNodeId($member->nodeId);
		}

		$result = $nodeMemberEntity->save();

		if ($result->isSuccess() && $updatedFields)
		{
			$this->eventSenderService->send(
				EventName::MEMBER_UPDATED, [
					'member' => $member,
					'fields' => $updatedFields,
				]
			);
		}

		return $member;
	}

	public function setActiveByEntityTypeAndEntityId(
		MemberEntityType $entityType,
		int $entityId,
		bool $active,
	): Main\Result
	{
		$result = new Main\Result();

		$modelCollection = NodeMemberTable::query()
			->where('ENTITY_TYPE', $entityType->name)
			->where('ENTITY_ID', $entityId)
			->fetchCollection()
		;

		if ($modelCollection->isEmpty())
		{
			return $result;
		}

		foreach ($modelCollection as $model)
		{
			$model->setActive($active);
		}

		return $modelCollection->save();
	}

	/**
	 * @inheritDoc
	 */
	public function findAllByEntityIdAndEntityType(
		int $entityId,
		MemberEntityType $entityType
	): Item\Collection\NodeMemberCollection
	{
		$nodeMemberEntities = NodeMemberTable::query()
			->setSelect(['*'])
			->where('ENTITY_ID', $entityId)
			->where('ENTITY_TYPE', $entityType->name)
			->fetchAll()
		;

		$nodeMemberCollection = new Item\Collection\NodeMemberCollection();
		foreach ($nodeMemberEntities as $nodeMemberEntity)
		{
			$nodeMemberCollection->add($this->convertModelArrayToItem($nodeMemberEntity));
		}

		return $nodeMemberCollection;
	}

	public function findAllByNodeIdAndEntityType(
		int $nodeId,
		MemberEntityType $entityType,
		bool $withAllChildNodes = false,
		int $limit = 100,
		int $offset = 0,
	): Item\Collection\NodeMemberCollection
	{
		$nodeMemberQuery = $this->getBaseQuery(
			$nodeId,
			$withAllChildNodes,
			$limit,
			$offset,
		);

		$nodeMemberQuery->where('ENTITY_TYPE', $entityType->name);

		$nodeMemberCollection = new Item\Collection\NodeMemberCollection();
		$this->calculateCount($nodeMemberQuery, $nodeMemberCollection);

		$nodeMemberEntities = $nodeMemberQuery->fetchCollection();
		foreach ($nodeMemberEntities as $nodeMember)
		{
			$nodeMemberCollection->add($this->convertModelToItem($nodeMember));
		}

		return $nodeMemberCollection;
	}

	private function calculateCount(
		Query $nodeMemberQuery,
		Item\Collection\NodeMemberCollection $nodeMemberCollection
	): void
	{
		if (isset(self::$queryTotalCount['usersCount']))
		{
			$nodeMemberCollection->setTotalCount(self::$queryTotalCount['usersCount']);

			return;
		}

		$countQuery = clone $nodeMemberQuery;
		try
		{
			$count = $countQuery->setSelect(['CNT'])
				->registerRuntimeField('', new ExpressionField('CNT', 'COUNT(*)'))
				->setLimit(null)
				->setOffset(null)
				->setOrder(['CNT' => 'DESC'])
				->exec()
				->fetch()['CNT']
			;
			self::$queryTotalCount['usersCount'] = (int)$count;
		}
		catch (\Exception)
		{
			self::$queryTotalCount['usersCount'] = 0;
		}

		$nodeMemberCollection->setTotalCount(self::$queryTotalCount['usersCount']);
	}

	public function getCommonUsersFromRelation(
		RelationEntityType $entityType,
		int $entityId,
		array $usersToCompare
	): array
	{
		if (empty($usersToCompare))
		{
			return [];
		}
		$connection = Application::getConnection();

		$users = implode(', ', $usersToCompare);

		$relationEntityType = $entityType->name;
		$memberEntityType = MemberEntityType::USER->name;
		$nodeTableName = Model\NodeTable::getTableName();
		$nodePathTableName = Model\NodePathTable::getTableName();
		$nodeRelationTableName = Model\NodeRelationTable::getTableName();
		$nodeMemberTableName = Model\NodeMemberTable::getTableName();

		$query = <<<SQL
SELECT DISTINCT nm.ENTITY_ID as USER
FROM $nodeMemberTableName nm
INNER JOIN (
	select distinct n.ID
	from $nodeTableName n
		INNER JOIN $nodePathTableName np ON np.CHILD_ID = n.ID
		INNER JOIN $nodeRelationTableName nr ON (
		nr.WITH_CHILD_NODES = 'Y' AND (np.PARENT_ID = nr.NODE_ID OR nr.NODE_ID = n.ID)
		or
		nr.WITH_CHILD_NODES = 'N' AND nr.NODE_ID = n.ID
		)
		WHERE nr.ENTITY_TYPE = '$relationEntityType'
		AND nr.ENTITY_ID = $entityId
) as relation ON relation.ID = nm.NODE_ID
WHERE nm.ENTITY_ID IN ($users)
AND nm.ENTITY_TYPE = '$memberEntityType'
SQL;
		$result = $connection->query($query);

		return array_column($result->fetchAll(), 'USER');
	}

	public function findAllByRoleIdAndStructureId(
		?int $roleId,
		int $structureId
	): Item\Collection\NodeMemberCollection
	{
		$nodeMemberQuery = Model\NodeMemberTable::query()
			->setSelect(['ID', 'ENTITY_ID', 'ENTITY_TYPE', 'NODE_ID'])
			->registerRuntimeField(
				'role',
				new Reference(
					'role',
					Model\NodeMemberRoleTable::class,
					Join::on('this.ID', 'ref.MEMBER_ID'),
				),
			)
			->registerRuntimeField(
				'node',
				new Reference(
					'node',
					Model\NodeTable::class,
					Join::on('this.NODE_ID', 'ref.ID'),
				),
			)
			->where('role.ROLE_ID', $roleId)
			->where('node.STRUCTURE_ID', $structureId)
		;

		$nodeMemberCollection = new Item\Collection\NodeMemberCollection();
		foreach ($nodeMemberQuery->fetchAll() as $nodeMember)
		{
			$nodeMemberCollection->add($this->convertModelArrayToItem($nodeMember));
		}

		return $nodeMemberCollection;
	}

	/**
	 * @param Structure $structure
	 *
	 * @return array<int, int>
	 * @throws ArgumentException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	public function countAllByStructureAndGroupByNode(Item\Structure $structure): array
	{
		$countQuery =
			Model\NodeMemberTable::query()
				->setSelect(['CNT', 'NODE_ID'])
				->registerRuntimeField(
					'',
					new ExpressionField(
						'CNT',
						'COUNT(*)',
					),
				)
				->where('NODE.STRUCTURE_ID', $structure->id)
				->where('ACTIVE', 'Y')
				->setGroup('NODE_ID')
				->setCacheTtl(self::CACHE_TTL)
				->cacheJoins(true)
		;

		$nodeMemberCount = $countQuery->fetchAll();
		$result = [];

		foreach ($nodeMemberCount as $nodeMember)
		{
			$result[$nodeMemberCount['NODE_ID']] = $nodeMember['CNT'];
		}

		return $result;
	}

	/**
	 * Counts all members by the given node ID.
	 * This method takes a node ID as a parameter and returns the total count of members associated with that node.
	 *
	 * @param int $nodeId
	 *
	 * @return int The total count of members associated with the given node ID.
	 */
	public function countAllByByNodeId(int $nodeId): int
	{
		static $count = [];

		if (isset($count[$nodeId]))
		{
			return $count[$nodeId];
		}

		try
		{
			$countQuery =
				Model\NodeMemberTable::query()
					->setSelect(['CNT'])
					->registerRuntimeField(
						'',
						new ExpressionField(
							'CNT',
							'COUNT(*)',
						),
					)
					->where('NODE_ID', $nodeId)
					->where('ACTIVE', 'Y')
					->setCacheTtl(self::CACHE_TTL)
			;
			$result = $countQuery->fetch();
		}
		catch (\Exception $e)
		{
			return 0;
		}

		$count[$nodeId] = $result['CNT'] ?? 0;

		return $count[$nodeId];
	}

	/**
	 * Validates a node member.
	 *
	 * @param NodeMember $nodeMember The node member to validate.
	 *
	 * @return bool Returns true if the node member is valid, false otherwise.
	 * @throws ArgumentException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	private function validate(Item\NodeMember $nodeMember): bool
	{
		if ($nodeMember->nodeId <= 0 || $nodeMember->entityId <= 0)
		{
			return false;
		}

		$node = Container::getNodeRepository()
			->getById($nodeMember->nodeId)
		;

		if (!$node)
		{
			return false;
		}

		return true;
	}
}