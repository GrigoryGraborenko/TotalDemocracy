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
        );

        $levels = array(
            "federal" => array("description" => "Federal Laws", "default" => "all")
            ,"state" => array("description" => "State Laws", "default" => "all")
            ,"local" => array("description" => "Local Laws & Applications", "default" => "all")
        );

        // if the user is verified, then the preferred defaults are the user's domains
        $user = $this->getPotentialUser($this->em);
        if($user !== NULL) {
            if($user->getWhenVerified() === NULL) {
                $output['cannot_vote_message'] = '<a href="' . $this->generateUrl("verify") . '">Verify</a> on the electoral role to vote';
            } else {
                $output['can_vote'] = true;
                $output['user'] = $user;
                foreach($user->getElectorates() as $electorate) {
                    $domain = $electorate->getDomain();
                    $levels[$domain->getLevel()]['default'] = $domain->getId();
                    $output[$domain->getLevel()] = $electorate;
                }
            }
        } else {
            $output["cannot_vote_message"] = 'Must <a href="' . $this->generateUrl("fos_user_security_login") . '">log in</a> or <a href="' . $this->generateUrl("signup") . '">sign up</a> to be able to vote';
        }

        $selected_domains = array();
        foreach($levels as $type => &$level) {

            $domains = $domain_repo->findBy(array("level" => $type));
            if((count($domains) === 1) && ($level['default'] !== "none")) {
                $level['default'] = "all";
            }
            if(array_key_exists($type, $input) && ((count($domains) > 1) || ($input[$type] === "none"))) {
                $level['selected'] = $input[$type];
            } else {
                $level['selected'] = $level['default'];
            }

            $domain_options = array(
                "all" => array("name" => ("All " . $level['description']))
                ,"none" => array("name" => ("No " . $level['description']))
            );
            if(count($domains) > 1) {
                $domain_options[""] = array("name" => "-----------");
                foreach($domains as $domain) {
                    if($level['selected'] === $domain->getId()) {
                        $selected_domains[] = $domain;
                    }
                    $domain_options[$domain->getId()] = array("name" => $domain->getName());
                }
            }
            if($level['selected'] === "all") {
                $selected_domains = array_merge($selected_domains, $domains);
            }
            $level['domains'] = $domain_options;
        }
        unset($level); // delete dangling reference, because PHP can be a very silly language

        $filter = NULL;
        if(array_key_exists("filter", $input)) {
            $filter = $input["filter"];
        }
        $output["filter"] = $filter;

        $docs_list = $this->get("vote.document")->getDocumentsWithVotes($selected_domains, $filter, $user);

        $output["doc_list"] = $docs_list;
        $output['domains_levels'] = $levels;

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
