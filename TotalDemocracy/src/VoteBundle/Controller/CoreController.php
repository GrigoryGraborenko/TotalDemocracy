<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;

/*
 * TODO:
 * Make it so you can see documents - server side render
 * Pick a front-end system - jquery for now, maybe react or emberjs later
 * do initial data stuff, it's pretty useful
 * try and render over the server-side stuff with client side shit
 * Allow voting
 */

/**
 * Class CoreController
 * @package VoteBundle\Controller
 */
class CoreController extends FOSRestController {

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request) {

        return $this->render('VoteBundle:Pages:home.html.twig', array("location" => "home"));
    }
}
