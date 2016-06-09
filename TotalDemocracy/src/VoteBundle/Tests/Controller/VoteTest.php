<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 31/05/2016
 * Time: 5:36 PM
 */

namespace VoteBundle\Tests\Controller;

use VoteBundle\Tests\BaseFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class VoteTest extends BaseFunctionalTestCase {

    public function testVotingView() {

        $crawler = $this->client->request('GET', "/");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load voting page");

        $vote_buttons = $crawler->filter(".vote-on");
        $this->assertEmpty($vote_buttons, "Should not be able to vote without logging in");

        $user = $this->login(); // defaults to bob, who is "verified"
        $this->assertNotNull($user->getWhenVerified(), "User is not verified");

        $crawler = $this->client->request('GET', "/");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load voting page");

        $vote_buttons = $crawler->filter(".vote-on");
        $this->assertNotEmpty($vote_buttons, "Should be able to vote after logging in");

        $this->logout();
        $user = $this->login("sally@test.com"); // not verified
        $this->assertNull($user->getWhenVerified(), "User is verified");

        $crawler = $this->client->request('GET', "/");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load voting page");

        $vote_buttons = $crawler->filter(".vote-on");
        $this->assertEmpty($vote_buttons, "Should not be able to vote without being verified");

    }

    // TODO: test actual voting!

    public function filterProvider() {

        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine')->getManager();

        $state = $em->getRepository("VoteBundle:Domain")->findOneBy(array("level" => "state", "shortName" => "QLD"))->getId();
        $local = $em->getRepository("VoteBundle:Domain")->findOneBy(array("level" => "local", "name" => "BRISBANE CITY"))->getId();

        return array(
            array("all", "all", "all", NULL, false)
            ,array(NULL, NULL, NULL, NULL, false)
            ,array(NULL, NULL, NULL, "bill", false)
            ,array("none", "none", "none", NULL, false)
            ,array("none", $state, "none", NULL, false)
            ,array("none", $state, $local, NULL, false)
            ,array("none", $state, $local, "bill", false)
            ,array("none", $state, $local, "rsgtrsgdfgg", false)
            ,array("all", "all", "all", "rsgtrsgdfgg", false)
            ,array("none", "none", $local, NULL, false)
            ,array(NULL, NULL, $local, NULL, false)
            ,array("all", "all", "all", NULL, true)
            ,array(NULL, NULL, NULL, NULL, true)
            ,array(NULL, NULL, NULL, "bill", true)
            ,array("none", "none", "none", NULL, true)
            ,array("none", $state, "none", NULL, true)
            ,array("none", $state, $local, NULL, true)
            ,array("none", $state, $local, "bill", true)
            ,array("none", $state, $local, "rsgtrsgdfgg", true)
            ,array("all", "all", "all", "rsgtrsgdfgg", true)
            ,array("none", "none", $local, NULL, true)
            ,array(NULL, NULL, $local, NULL, true)
        );
    }

    /**
     * @dataProvider filterProvider
     */
    public function testFilters($federal, $state, $local, $filter, $is_logged_in) {

        $documents = $this->em->getRepository("VoteBundle:Document")->findAll();

        $params = array();
        if($federal !== NULL) {
            $params[] = "federal=$federal";
        }
        if($state !== NULL) {
            $params[] = "state=$state";
        }
        if($local !== NULL) {
            $params[] = "local=$local";
        }
        if($filter !== NULL) {
            $filter = strtolower($filter);
            $params[] = "filter=$filter";
        }

        if(count($params) > 0) {
            $url = "/?" . implode("&", $params);
        } else {
            $url = "/";
        }

        $default_domains = array();
        if($is_logged_in) {
            $user = $this->login();
            foreach($user->getElectorates() as $electorate) {
                $default_domains[] = $electorate->getDomain()->getId();
            }
        } else {
            $user = NULL;
        }

        $crawler = $this->client->request('GET', $url);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load voting page");

        $doc_ids = array();
        $doc_divs = $crawler->filter(".pd-document-internal");
        foreach($doc_divs as $doc_div) {
            $doc_ids[] = $doc_div->getAttribute("data-doc-block");
        }

        foreach($documents as $doc) {

            $domain = $doc->getDomain();
            $excluded = false;
            $is_not_default = !in_array($domain->getId(), $default_domains);
            if(($federal !== NULL) && ($domain->getLevel() === "federal") && ($federal === "none")) {
                $excluded = true;
            }
            if(($domain->getLevel() === "state") &&
                    ((($state === NULL) && ($user !== NULL) && $is_not_default) ||
                    (($state !== NULL) && (($state === "none") || (($state !== $domain->getId()) && ($state !== "all") && $is_not_default)))
                    )) {
                $excluded = true;
            }
            if(($domain->getLevel() === "local") &&
                    ((($local === NULL) && ($user !== NULL) && $is_not_default) ||
                    (($local !== NULL) && (($local === "none") || (($local !== $domain->getId()) && ($local !== "all") && $is_not_default)))
                )) {
                $excluded = true;
            }
            if(($filter !== NULL) && (strpos(strtolower($doc->getName()), $filter) === false) && (strpos(strtolower($doc->getSummary()), $filter) === false)) {
                $excluded = true;
            }

            if($excluded) {
                $this->assertNotContains($doc->getId(), $doc_ids, "Document should not have been included: " . json_encode($doc_ids));
            } else {
                $this->assertContains($doc->getId(), $doc_ids, "Document not included: " . json_encode($doc_ids));
            }
        }
    }

}
