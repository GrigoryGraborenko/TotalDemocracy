<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;

/*
 * TODO FIXES:
 * Send a confirm email
 * Domains for users
 * Only can vote in that domain
 * Remove old images
 * Ensure voters can't change their names easily?
 * Add local councils, electorates etc as you find them
 * Optional telephone, DOB and "want to volunteer"
 * Make sure that rego and verify both can be switched off for maintenance
 * Rate limit new email registrations
 * Rate limit re-votes
 *
 * TODO:
 * do g_initial data JS stuff, it's pretty useful
 * Allow voting
 * Migrations
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
