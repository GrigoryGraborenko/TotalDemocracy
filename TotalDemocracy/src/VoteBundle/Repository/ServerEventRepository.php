<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 18-May-16
 * Time: 5:36 PM
 */

namespace VoteBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Carbon\Carbon;

/**
 * ServerEventRepository
 */
class ServerEventRepository extends EntityRepository {

    /**
     * @param $name
     * @param $user_id
     * @param $since
     * @param null $is_processed
     * @return array
     */
    public function findEvents($name, $user_id, $since, $is_processed = NULL) {

        $qb = $this->createQueryBuilder('e');
        $qb
            // filter by user
            ->andWhere('e.user = :user_id')
            ->setParameter('user_id', $user_id)

            // filter by name
            ->andWhere('e.name = :name')
            ->setParameter('name', $name)

            // filter by ones created since a certain time
            ->andWhere("e.date_created >= :since")
            ->setParameter('since', $since)

            ->orderBy("e.date_created", "DESC")
        ;

        if($is_processed !== NULL) {
            // filter by name
            $qb ->andWhere('e.processed = :processed')
                ->setParameter('processed', $is_processed);
        }

        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * @param $name
     * @param $json_contains
     * @return array
     */
    public function findByJson($name, $json_contains) {

        $qb = $this->createQueryBuilder('e');
        $qb
            // filter by name
            ->andWhere("e.name = :name")
            ->setParameter('name', $name)

            // filter by json likeness
//            ->andWhere("e.json LIKE :json")
            ->andWhere($qb->expr()->like('e.json', ":json"))
            ->setParameter('json', "%" . $json_contains . "%")
        ;

        $query = $qb->getQuery();
        return $query->getResult();
    }

}