<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 19/06/2016
 * Time: 10:16 AM
 */

namespace VoteBundle\Service;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Validator;
use JMS\DiExtraBundle\Annotation as DI;

use Symfony\Component\DependencyInjection\ContainerAware;


/**
 * Class DocumentService
 * @package VoteBundle\Service
 *
 * Provides document and voting functions
 *
 */
class DocumentService {

    /** @var EntityManager em */
    private $em;

    /**
     * UserService constructor.
     * @param $em
     */
    public function __construct($em) {
        $this->em = $em;
    }

    /**
     * @param $selected_domains
     * @param $filter
     * @param null $user
     * @return mixed
     */
    public function getDocumentsWithVotes($selected_domains = NULL, $filter = NULL, $user = NULL) {

        $doc_repo = $this->em->getRepository('VoteBundle:Document');
        $vote_repo = $this->em->getRepository('VoteBundle:UserDocumentVote');

        // this will find all documents that belong to this list of domains, along with their tallies
        $docs_list = $doc_repo->getDocumentsWithVoteTotals(NULL, $selected_domains, $filter);

        $user_domain_ids = array();
        if(($user !== NULL) && ($user->getWhenVerified() !== NULL)) {
            foreach($user->getElectorates() as $electorate) {
                $user_domain_ids[] = $electorate->getDomain()->getId();
            }
        }

        // can optimize this later - make one single call to the DB with a list of doc ID's and a user ID
        foreach($docs_list as &$doc) {

            $doc['doc'] = $doc[0];
            unset($doc[0]);
            $can_vote_doc = in_array($doc['doc']->getDomain()->getId(), $user_domain_ids);
            $doc['is_voteable'] = $doc['doc']->getType() !== "appropriations";
            $doc['can_vote'] = $can_vote_doc;
            $doc['supporters'] = intval($doc['supporters']);
            $doc['opponents'] = intval($doc['opponents']);

            if(!$can_vote_doc) {
                continue;
            }

            $vote = $vote_repo->findOneBy(array("user" => $user->getId(), "document" => $doc['doc']->getId()));
            $doc['vote'] = $vote;
            $doc['yes_vote'] = $vote && $vote->getIsSupporter();
            $doc['no_vote'] = $vote && (!$vote->getIsSupporter());
        }
        unset($doc); // delete dangling reference, because PHP can be a very silly language

        return $docs_list;
    }

}
