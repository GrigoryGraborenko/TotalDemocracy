<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 10/07/2015
 * Time: 12:55 PM
 */

namespace VoteBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;

/**
 * Custom repository for electorates
 *
 * Class ElectorateRepository
 * @package VoteBundle\Repository
 */
class ElectorateRepository extends EntityRepository {

    /*
    public function getElectorate($level, $name) {

        $qb = $this->createQueryBuilder('e');
        $query = $qb

            ->innerJoin('e.domain', 'd')

            // the level of the domain
            ->andWhere('d.level = :level')
            ->setParameter('level', $level)

            // the name of the electorate
            ->andWhere('e.name = :name')
            ->setParameter('name', $name)

            ->getQuery();

        return $query->getOneOrNullResult();
    }*/

}
