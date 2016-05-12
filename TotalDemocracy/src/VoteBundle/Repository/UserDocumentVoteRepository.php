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
 * Custom repository for user documents votes
 *
 * Class UserDocumentVoteRepository
 * @package VoteBundle\Repository
 */
class UserDocumentVoteRepository extends EntityRepository {

    /**
     * @param $doc_id
     * @param $is_supporter
     * @return mixed
     */
    public function countVotes($doc_id, $is_supporter) {

        $qb = $this->createQueryBuilder('v');
        $query = $qb
            ->select("count(distinct(v.id))")
            ->innerJoin('v.document', 'd')

            // the id of the document
            ->andWhere('d.id = :id')
            ->setParameter('id', $doc_id)

            // if the user supports this document
            ->andWhere('v.isSupporter = :supporter')
            ->setParameter('supporter', $is_supporter)

            ->getQuery();

        return intval($query->getSingleScalarResult());
    }

}
