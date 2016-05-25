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
 * Custom repository for documents
 *
 * Class DocumentRepository
 * @package VoteBundle\Repository
 */
class DocumentRepository extends EntityRepository {

    public function getDocumentsWithVoteTotals() {

        $qb = $this->createQueryBuilder('d');
        $query = $qb
            ->leftJoin('d.votes', 'v')
            ->addSelect("d as doc")

            ->addSelect("SUM(CASE WHEN v.isSupporter = true THEN 1 ELSE 0 END) as supporters")
            ->addSelect("SUM(CASE WHEN v.isSupporter = false THEN 1 ELSE 0 END) as opponents")
            ->addGroupBy("d")

            ->addOrderBy("d.whenCreated")

            ->getQuery();

        return $query->getResult();
    }

}
