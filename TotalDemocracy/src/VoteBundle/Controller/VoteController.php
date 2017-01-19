<?php

namespace VoteBundle\Controller;

use VoteBundle\Controller\CommonController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

use JMS\DiExtraBundle\Annotation as DI;

use VoteBundle\Exception\BadRequestException;
use VoteBundle\Entity\UserDocumentVote;

/**
 * Class VoteController
 * @package VoteBundle\Controller
 */
class VoteController extends CommonController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    protected $em;

    /**
     * @Route("/", name="vote")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function voteAction(Request $request) {

        $input = $request->query->all();

        $domain_repo = $this->em->getRepository('VoteBundle:Domain');

        $output = array(
            "can_vote" => false
            ,"is_verified" => false
        );

        $default_filter = "all";
        $user_domains = array();
        $filters = array(
            "all" => "Everything"
            ,"federal" => "Only Federal Bills"
            ,"state" => "Only State Bills"
            ,"local" => "Only Local Bills & Applications"
        );

        // if the user is verified, then the preferred defaults are the user's domains
        $user = $this->getPotentialUser($this->em);
        if($user !== NULL) {
            if($user->getWhenVerified() === NULL) {

                $verify_error = $user->getKeyJson("verify.error");
                $err_msg = "";
                if($verify_error !== NULL) {
                    $err_msg = $verify_error . " ";
                }
                $err_msg .= '<a href="' . $this->generateUrl("verify") . '">Verify</a> on the electoral role to vote.';

                $output['cannot_vote_message'] = $err_msg;
            } else {
                $output['is_verified'] = true;
                if($user->isEnabled()) {
                    $output['can_vote'] = true;
                } else {
                    $output['cannot_vote_message'] = 'Confirm your email address to vote, check your inbox.';
                }
                $output['user'] = $user;
                $filters[""] = "-------------";
                $filters["mine"] = "Only My Bills & Applications";
                $default_filter = "mine";
                foreach($user->getElectorates() as $electorate) {
                    $domain = $electorate->getDomain();
//                    $levels[$domain->getLevel()]['default'] = $domain->getId();
                    $output[$domain->getLevel()] = $electorate;

                    if($domain->getLevel() !== "federal") {
                        $filters[$domain->getId()] = $domain->getName();
                    }
                    $user_domains[] = $domain;
                }
            }
        } else {
            $output["cannot_vote_message"] = 'Must <a href="' . $this->generateUrl("fos_user_security_login") . '">log in</a> or <a href="' . $this->generateUrl("signup") . '">sign up</a> to be able to vote';
        }

        if(array_key_exists("domain", $input)) {
            $current_filter = $input["domain"];
        } else {
            $current_filter = $default_filter;
        }

        $output['filters'] = $filters;
        $output['default_filter'] = $default_filter;
        $output['current_filter'] = $current_filter;

        $this->get("vote.js")->output("default_domain", $default_filter);

        if($current_filter === "all") {
            $selected_domains = $domain_repo->findAll();
        } else if(($current_filter === "federal") || ($current_filter === "state") || ($current_filter === "local")) {
            $selected_domains = $domain_repo->findBy(array("level" => $current_filter));
        } else if($current_filter === "mine") {
            $selected_domains = $user_domains;
        } else {
            $single_domain = $domain_repo->find($current_filter);
            if($single_domain) {
                $selected_domains = array($single_domain);
            } else {
                $selected_domains = array();
            }
        }

        $filter = NULL;
        if(array_key_exists("filter", $input)) {
            $filter = $input["filter"];
        }
        $output["filter"] = $filter;

        $docs_list = $this->get("vote.document")->getDocumentsWithVotes($selected_domains, $filter, $user);

        $doc_levels = array("federal" => array(), "state" => array(), "local" => array());
        foreach($docs_list as $doc_info) {
            $doc_levels[$doc_info['doc']->getDomain()->getLevel()][] = $doc_info;
        }
        $output["doc_levels"] = $doc_levels;

        return $this->render('VoteBundle:Pages:vote.html.twig', $output);
    }

    /**
     * @Route("/vote-on", name="vote_on", options={"expose"=true})
     * @Method("POST");
     */
    public function voteOnAction(Request $request) {

        $user = $this->getUser();
        if($user === NULL) {
            throw new BadRequestException("Not signed in");
        }
        if($user->getWhenVerified() === NULL) {
            throw new BadRequestException("Not verified on the electoral roll");
        }
        if(!$user->isEnabled()) {
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
        if($doc->getType() === "appropriations") {
            throw new BadRequestException("Cannot vote on appropriations bills yet");
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

        // opens up the political process to the masses
        if($vote === NULL) {
            $vote = new UserDocumentVote($user, $doc, $is_support);
            $this->em->persist($vote);
        } else {
            // TODO: rate limit re-votes?
            $vote->setIsSupporter($is_support);
        }
        $this->em->flush();

        $output = array(
            "success" => true
            ,"doc_id" => $doc->getId()
            ,"total_yes" => $vote_repo->countVotes($doc->getId(), true)
            ,"total_no" => $vote_repo->countVotes($doc->getId(), false)
        );

        $view = $this->view($output, 200);
        $view->setFormat('json');
        return $this->handleView($view);
    }

}
