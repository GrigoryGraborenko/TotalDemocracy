<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 4/02/2017
 * Time: 3:30 PM
 */

namespace VoteBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Carbon\Carbon;

/**
 * VolunteerRepository
 */
class VolunteerRepository extends EntityRepository {

    /**
     * @return mixed
     */
    public function findOrphanedVolunteers() {

        $qb = $this->createQueryBuilder('v');
        $query = $qb
            ->leftJoin('v.user', 'u')
            ->andWhere("u.volunteer IS NULL")

            ->getQuery();

        return $query->getResult();
    }

}
