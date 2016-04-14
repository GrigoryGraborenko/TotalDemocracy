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
 * Class ElectoralRollImportRepository
 * @package VoteBundle\Repository
 */
class ElectoralRollImportRepository extends EntityRepository {

    /**
     * @param $surname
     * @param $first_name
     * @return array
     */
    public function getBySurnameAndSimilarFirstName($surname, $first_name) {

        $qb = $this->createQueryBuilder('e');
        $query = $qb

            // filter by surname
            ->andWhere('e.surname = :surname')
            ->setParameter('surname', $surname)

            // has given names that start with first name
            ->andWhere('e.given_names LIKE :search')
            ->setParameter('search', $first_name . "%")

            ->getQuery();

        return $query->getResult();
    }

}
