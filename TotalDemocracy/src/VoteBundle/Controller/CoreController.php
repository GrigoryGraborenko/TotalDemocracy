<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use JMS\DiExtraBundle\Annotation as DI;

use VoteBundle\Exception\BadRequestException;

/*
 * TODO FIXES:
 *

REDIRECT TO HTTPS!
check that status is correct - failed is a possibility
make sure that you scrape the finished ones too - otherwise they may never close

federal election message - These are the active bills currently up for vote
 OR
"active" or "closed" bills filter that has inactive disabled for now

CRON JOB

Add timeout for partially registered users - maybe 30 mins?

facebook sign-in
css fix for smaller devices - test on various widths
comm with greg about roll
trim all inputs
(
phone required if volunteer or member
loading anim on verify
turn of street verify on volunteer
    -> maybe only autocomplete for the first letter, do the rest client-side
hope-o'conner
)

b) If I vote I cannot cancel my vote
f) When someone searches all federal bills nothing comes up maybe you can put a message there saying "There is an election on and parliament has been dissolved."
h) Once someone has saved volunteer information do not collapse volunteer drop-down again, only allow unticking and ticking of volunteer button.
a) When you are verified and all is good, and you go to update your address, if the new address doesn't verify it still allows you to vote. If someone has been taken off the roll which does happen, overseas for 5 years etc they are not allowed to vote so we need to ensure that we don't allow them to vote if the updated address doesn't verify.
c) I am thinking the solution for someone who can't vote but wants to volunteer is have a volunteer tick box at the bottom of the verification page that shows phone number when clicked, it saves the first instance of the address and auto-populates the enrollment verified/finish verification page.
d) My verification email went into spam something to raise with Phil soon.

Verify error msg doesn't show on short screens, need to scroll down

 * Verification success and electorate listings should be prettier
 * Make sure that rego and verify both can be switched off for maintenance
 * Admin backend for candidates or volunteers
 *
 * Some way to log out of "new user" mode
 * Abstain vote?
 * Close vote
 * Ensure voters can't change their names easily?
 * Report bug button on all pages
 * Rate limit new email registrations
 * Rate limit re-votes
 * Load up email with social links
 * Flag users if imported from nationbuilder
 * Add concept of "user verified but mismatch"
 * Checkbox for "live here"
 * what about metadata on electoral roll import?

p) On the voting page people will want to be able to opt-in to email notifications for new or updated Bills for their filter combination, eg: notify me when new or updated Federal Bills with keyword environment in them. *
 *
 * TODO:
 * Special case for users with no email?
 * Admin for promoting users to candidate
 * Allowing uploads of electoral roll
 */

/**
 * Class CoreController
 * @package VoteBundle\Controller
 */
class CoreController extends FOSRestController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    private $em;

    /**
     * @Route("/home", name="homepage")
     */
    public function indexAction(Request $request) {

        return new RedirectResponse("http://www.peopledecide.org.au");
//        return $this->render('VoteBundle:Pages:home.html.twig', array());
    }

    /**
     * @Route("/error", name="error_page")
     */
    public function errorAction(Request $request) {

        return $this->render('VoteBundle:Errors:logic_error.html.twig', array());
    }

    /**
     * This is called by base twig on every page. It's responsible for grabbing output data and stuffing it into JS
     */
    public function getJSOutputAction(Request $request) {

        $js_out = $this->get('vote.js');

        $user = $this->getUser();
        if($user === NULL) { // if logged out
            $js_out->output("user", "-");
        } else {
            $js_out->output("user", $user->getUsername());
        }

        return $this->render("VoteBundle:Common:JSOutput.html.twig", array(
            'js_output_data' => $js_out->getParameters()
        ));
    }

    /**
     * This is called by base twig on every page. It's responsible for creating custom menus
     */
    public function getCMSMenuAction(Request $request) {

        $pages = $this->em->getRepository('VoteBundle:Page')->findBy(array("visible" => true), array("priority" => "DESC", "heading" => "ASC", "name" => "ASC"));

        $router = $this->get("router");

        $menus = array();
        foreach($pages as $page) {
            $heading = $page->getHeading();
            if(strtolower($heading) === "footer") {
                continue;
            }
            $url = $page->getUrl();
            if(strpos($url, "http") !== 0) {
                $url = $router->generate('cms-page', array("url" => $url));
            }
            if(!array_key_exists($heading, $menus)) {
                $menus[$heading] = array();
            }
            $active = $request->getPathInfo() === $url;
            $menus[$heading][] = array("name" => $page->getName(), "url" => $url, "active" => $active);
        }

        return $this->render("VoteBundle:Common:CMSMenu.html.twig", array(
            'menus' => $menus
        ));
    }

    /**
     * This is called by base twig on every page. It's responsible for creating custom menus
     */
    public function getCMSFooterAction(Request $request) {

        $pages = $this->em->getRepository('VoteBundle:Page')->findBy(array("visible" => true), array("priority" => "DESC", "heading" => "ASC", "name" => "ASC"));

        $footer_text = $this->get("vote.option")->getString("footer.text");

        $router = $this->get("router");

        $footers = array();
        foreach($pages as $page) {
            $heading = $page->getHeading();
            if(strtolower($heading) !== "footer") {
                continue;
            }
            $url = $page->getUrl();
            if(strpos($url, "http") !== 0) {
                $url = $router->generate('cms-page', array("url" => $url));
            }
            $active = $request->getPathInfo() === $url;
            $footers[] = array("name" => $page->getName(), "url" => $url, "active" => $active);
        }

        return $this->render("VoteBundle:Common:CMSFooter.html.twig", array(
            'footers' => $footers
            ,'footer_text' => $footer_text
        ));
    }

}
