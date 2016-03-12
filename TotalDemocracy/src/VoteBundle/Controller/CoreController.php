<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;

/*
 * TODO FIXES:
 * Send a confirm email
 * Make sure that entering password is client-side tested
 * Actually store verified data
 * Ensure auto-complete works
 *
 * TODO:
 * do g_initial data JS stuff, it's pretty useful
 * Allow voting
 *
 * rego: enter an email. progress. verify. need to confirm email before you can be valid.
 *
 {% set filter = app.session.get('filter') %}
{% set account-filter = filter['accounts'] %}*
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

        return $this->render('VoteBundle:Pages:home.html.twig', array());
    }
}
