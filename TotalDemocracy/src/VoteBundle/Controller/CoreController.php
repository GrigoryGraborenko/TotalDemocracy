<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;

/*
 * TODO FIXES:
 * Add DOB for verification
 * Some way to log out of "new user" mode
 * Abstain vote
 * Optional telephone and "want to volunteer"
 * Preserve wrong data between errors
 * Show all, even stuff you can't vote on, if you choose
 * Remove old images
 * Ensure voters can't change their names easily?
 * Make sure that rego and verify both can be switched off for maintenance
 * Rate limit new email registrations
 * Rate limit re-votes
 * Load up email with social links
 * Report bug button on all pages
 *
 * TODO:
 * do g_initial data JS stuff, it's pretty useful
 * Migrations
 * Scrape!
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
