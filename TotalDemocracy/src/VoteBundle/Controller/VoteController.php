<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;

use JMS\DiExtraBundle\Annotation as DI;

use VoteBundle\Exception\BadRequestException;
use VoteBundle\Entity\UserDocumentVote;

/**
 * Class VoteController
 * @package VoteBundle\Controller
 */
class VoteController extends FOSRestController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    private $em;

    /**
     * @Route("/vote", name="vote")
     */
    public function indexAction(Request $request) {

        $doc_repo = $this->em->getRepository('VoteBundle:Document');
        $vote_repo = $this->em->getRepository('VoteBundle:UserDocumentVote');
        $domain_repo = $this->em->getRepository('VoteBundle:Domain');

        $output = array(
            "can_vote" => false
            ,"cannot_vote_message" => "Must sign in to be able to vote"
        );

        $user = $this->getUser();
        $domains = array();
        if($user) {
            if($user->getWhenVerified() === NULL) {
                $output['cannot_vote_message'] = '<a href="' . $this->generateUrl("verify") . '">Verify</a> on the electoral role to vote';
            } else {
                $output['can_vote'] = true;
                $output['user'] = $user;
                foreach($user->getElectorates() as $electorate) {
                    $domains[] = $electorate->getDomain();
                }
            }
        }
        if(count($domains) <= 0) {
            $domains = $domain_repo->findBy(array("level" => "federal"));
        }
        $docs = $doc_repo->findBy(array(
            "domain" => $domains
        ));
        $docs_list = array();
        foreach($docs as $doc) {
            $combined = array("doc" => $doc);
            $docs_list[] = $combined;
        }

        if($output['can_vote']) {
            // can optimize this later - make one single call to the DB with a list of doc ID's and a user ID
            foreach($docs_list as &$doc) {
                $vote = $vote_repo->findOneBy(array("user" => $user->getId(), "document" => $doc['doc']->getId()));
                $doc['vote'] = $vote;
                $doc['yes_vote'] = $vote && $vote->getIsSupporter();
                $doc['no_vote'] = $vote && (!$vote->getIsSupporter());
            }
            unset($doc); // delete dangling reference, because PHP can be a very silly language
        }

        $output["doc_list"] = $docs_list;

        return $this->render('VoteBundle:Pages:vote.html.twig', $output);
    }

    /**
     * @Route("/vote-on", name="vote_on", options={"expose"=true})
     * @Method("POST");
     */
    public function voteAction(Request $request) {

        $user = $this->getUser();
        if($user === NULL) {
            throw new BadRequestException("Not signed in");
        }
        if($user->getWhenVerified() === NULL) {
            throw new BadRequestException("Not verified on the electoral roll");
        }

        $input = $request->request->all();
        if((!array_key_exists("id", $input)) || (!array_key_exists("vote", $input))) {
            throw new BadRequestException("Incorrect parameters");
        }

        $is_support = $input['vote'] == "true"; // might need to be cast, so don't use ===
        $doc_repo = $this->em->getRepository('VoteBundle:Document');
        $vote_repo = $this->em->getRepository('VoteBundle:UserDocumentVote');

        $doc = $doc_repo->find($input['id']);
        if($doc === NULL) {
            throw new BadRequestException("Cannot find document");
        }
        $domain = $doc->getDomain();
        $in_domain = false;
        foreach($user->getElectorates() as $electorate) {
            if($electorate->getDomain()->getId() === $domain->getId()) {
                $in_domain = true;
                break;
            }
        }
        if(!$in_domain) {
            throw new BadRequestException("Cannot vote in " . $domain->getName());
        }

        $vote = $vote_repo->findOneBy(array("user" => $user->getId(), "document" => $input['id']));
        if($vote === NULL) {
            $vote = new UserDocumentVote($user, $doc, $is_support);
            $this->em->persist($vote);
        } else {
            // TODO: rate limit re-votes?
            $vote->setIsSupporter($is_support);
        }
        $this->em->flush();

        $output = array("success" => true);

        $view = $this->view($output, 200);
        $view->setFormat('json');
        return $this->handleView($view);
    }

}
