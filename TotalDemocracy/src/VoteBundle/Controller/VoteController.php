<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;

use JMS\DiExtraBundle\Annotation as DI;

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

        $docs = $doc_repo->findAll();

        $output = array(
            "docs" => $docs
        );

        return $this->render('VoteBundle:Pages:vote.html.twig', $output);
    }
}
