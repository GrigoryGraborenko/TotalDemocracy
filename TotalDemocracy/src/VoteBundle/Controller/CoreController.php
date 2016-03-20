<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;

/*
 * TODO FIXES:
 * Remove old images
 * Preserve wrong data between errors
 * Some way to log out of "new user" mode
 * Show all, even stuff you can't vote on, if you choose
 * Abstain vote?
 * Close vote
 * Ensure voters can't change their names easily?
 * Report bug button on all pages
 * Make sure that rego and verify both can be switched off for maintenance
 * Rate limit new email registrations
 * Rate limit re-votes
 * Load up email with social links
 * Flag users if imported from nationbuilder
 * Add concept of "user verified but mismatch"
 * Better verified page, with proper info from users
 * Checkbox for "live here"
 *
 * TODO:
 * do g_initial data JS stuff, it's pretty useful
 * Migrations
 * Scrape!
 * Special case for users with no email
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
