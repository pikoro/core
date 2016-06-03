<?php
/**
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
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
namespace OC\Repair;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Class AvatarPermissions
 *
 * @package OC\Repair
 */
class AvatarPermissions implements IRepairStep {
	/** @var IDBConnection */
	private $connection;

	/**
	 * AvatarPermissions constructor.
	 *
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'Fix permissions so avatars can be stored again';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		$output->startProgress(2);
		$this->fixUserRootPermissions();
		$output->advance();
		$this->fixAvatarPermissions();
		$output->finishProgress();
	}

	/**
	 * Make sure all user roots have permissions 23 (all but share)
	 */
	protected function fixUserRootPermissions() {
		$qb = $this->connection->getQueryBuilder();
		$qb2 = $this->connection->getQueryBuilder();

		$qb->select('numeric_id')
			->from('storages')
			->where($qb->expr()->like('id', $qb2->createParameter('like')));

		// Nasty isNUll hack for oracle
		$or = $qb2->expr()->orX(
			$qb2->expr()->eq('path', $qb2->createNamedParameter('')),
			$qb2->expr()->isNull('path')
		);

		$qb2->update('filecache')
			->set('permissions', $qb2->createNamedParameter(23))
			->where($or)
			->andWhere($qb2->expr()->in('storage', $qb2->createFunction($qb->getSQL())))
			->andWhere($qb2->expr()->neq('permissions', $qb2->createNamedParameter(23)))
			->setParameter('like', 'home::%');


		$qb2->execute();
	}

	/**
	 * Make sure all avatar files in the user roots have permission 27
	 */
	protected function fixAvatarPermissions() {
		$qb = $this->connection->getQueryBuilder();
		$qb2 = $this->connection->getQueryBuilder();

		$qb->select('numeric_id')
			->from('storages')
			->where($qb->expr()->like('id', $qb2->createParameter('like')));

		$qb2->update('filecache')
			->set('permissions', $qb2->createNamedParameter(27))
			->where($qb2->expr()->like('path', $qb2->createNamedParameter('avatar.%')))
			->andWhere($qb2->expr()->in('storage', $qb2->createFunction($qb->getSQL())))
			->andWhere($qb2->expr()->neq('permissions', $qb2->createNamedParameter(27)))
			->setParameter('like', 'home::%');

		$qb2->execute();
	}

}

