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

    public function getDocumentsWithVoteTotals($max_results = NULL, $domains = NULL, $filter = NULL) {

        $qb = $this->createQueryBuilder('d');
        $qb
            ->leftJoin('d.votes', 'v')
            ->addSelect("d as doc")

            ->addSelect("SUM(CASE WHEN v.isSupporter = true THEN 1 ELSE 0 END) as supporters")
            ->addSelect("SUM(CASE WHEN v.isSupporter = false THEN 1 ELSE 0 END) as opponents")
            ->addGroupBy("d")

            ->addOrderBy("d.dateCreated", "DESC")
            ->addOrderBy("d.whenCreated", "DESC")
        ;
        if($max_results !== NULL) {
            $qb->setMaxResults($max_results);
        }
        if($domains !== NULL) {
            $qb ->andWhere("d.domain IN (:domains)")
                ->setParameter("domains", $domains);
        }
        if($filter !== NULL) {
            $qb ->andWhere("(d.name LIKE :filter) OR (d.summary LIKE :filter)")
                ->setParameter("filter", "%" . $filter . "%");
        }

        return $qb->getQuery()->getResult();
    }

}
