<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 31/05/2016
 * Time: 5:36 PM
 */

namespace VoteBundle\Tests\Controller;

use Symfony\Component\DomCrawler\Crawler;
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

    // tests voting for a bill, then changing your mind and voting no instead
    public function testVote() {

        $this->login(); // defaults to bob, who is "verified"

        $doc = $this->em->getRepository("VoteBundle:Document")->findOneBy(array("name" => "Bike Path"));
        $doc_id = $doc->getId();

        $crawler = $this->client->request('GET', "/");
        $doc_nodes = $crawler->filter(".pd-document-internal[data-doc-block='$doc_id']");
        $this->assertCount(1, $doc_nodes, "Should be one document node");

        $starting_yes = 2;
        $starting_no = 1;

        $yes_count = intval($doc_nodes->filter(".yes-count")->html());
        $no_count = intval($doc_nodes->filter(".no-count")->html());
        $this->assertEquals($starting_yes, $yes_count, "Should be $starting_yes yes votes");
        $this->assertEquals($starting_no, $no_count, "Should be $starting_no no votes");

        $this->client->request('POST', "/vote-on", array("id" => $doc_id, "vote" => "true"));
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode(), "Failed to vote");

        $output = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("success", $output, "Should have returned successful vote");
        $this->assertArrayHasKey("total_yes", $output, "Should have returned number of yes votes");
        $this->assertArrayHasKey("total_no", $output, "Should have returned number of no votes");
        $this->assertEquals($starting_yes + 1, $output["total_yes"], "Yes vote did not increase by one");
        $this->assertEquals($starting_no, $output["total_no"], "No vote changed");

        $crawler = $this->client->request('GET', "/");
        $doc_nodes = $crawler->filter(".pd-document-internal[data-doc-block='$doc_id']");
        $yes_count = intval($doc_nodes->filter(".yes-count")->html());
        $no_count = intval($doc_nodes->filter(".no-count")->html());
        $this->assertCount(1, $doc_nodes, "Should be one document node");
        $this->assertEquals($starting_yes + 1, $yes_count, "Should be $starting_yes + 1 yes votes");
        $this->assertEquals($starting_no, $no_count, "Should be $starting_no no votes");

        $this->client->request('POST', "/vote-on", array("id" => $doc_id, "vote" => "false"));
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode(), "Failed to vote");

        $output = json_decode($response->getContent(), true);
        $this->assertEquals($starting_yes, $output["total_yes"], "Yes vote changed");
        $this->assertEquals($starting_no + 1, $output["total_no"], "No vote did not increase by one");

        $crawler = $this->client->request('GET', "/");
        $doc_nodes = $crawler->filter(".pd-document-internal[data-doc-block='$doc_id']");
        $yes_count = intval($doc_nodes->filter(".yes-count")->html());
        $no_count = intval($doc_nodes->filter(".no-count")->html());
        $this->assertCount(1, $doc_nodes, "Should be one document node");
        $this->assertEquals($starting_yes, $yes_count, "Should be $starting_yes yes votes");
        $this->assertEquals($starting_no + 1, $no_count, "Should be $starting_no + 1 no votes");

    }

    public function filterProvider() {

        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine')->getManager();

        $state = $em->getRepository("VoteBundle:Domain")->findOneBy(array("level" => "state", "shortName" => "QLD"))->getId();
        $local = $em->getRepository("VoteBundle:Domain")->findOneBy(array("level" => "local", "name" => "BRISBANE CITY"))->getId();

        return array(
            array("all", NULL, false)
            ,array(NULL, NULL, false)
            ,array("federal", NULL, false)
            ,array("state", NULL, false)
            ,array("local", NULL, false)
            ,array(NULL, "bill", false)
            ,array("local", "bill", false)
            ,array(NULL, "rsgtrsgdfgg", false)
            ,array("all", "rsgtrsgdfgg", false)
            ,array($state, NULL, false)

            ,array("all", NULL, true)
            ,array(NULL, NULL, true)
            ,array("mine", NULL, true)
            ,array(NULL, "bill", true)
            ,array($state, NULL, true)
            ,array($local, NULL, true)
            ,array($state, "bill", true)
            ,array(NULL, "rsgtrsgdfgg", true)
            ,array($local, "rsgtrsgdfgg", true)
        );
    }

    /**
     * @dataProvider filterProvider
     */
    public function testFilters($domain_filter, $filter, $is_logged_in) {

        $documents = $this->em->getRepository("VoteBundle:Document")->findAll();

        $params = array();
        if($domain_filter !== NULL) {
            $params[] = "domain=$domain_filter";
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

        $user_domains = array();
        if($is_logged_in) {
            $default_domain = "mine";
            $user = $this->login();
            foreach($user->getElectorates() as $electorate) {
                $user_domains[] = $electorate->getDomain()->getId();
            }
        } else {
            $default_domain = "all";
            $user = NULL;
        }

        $crawler = $this->client->request('GET', $url);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load voting page");

        $doc_ids = array();
        $doc_divs = $crawler->filter(".pd-document-internal");
        foreach($doc_divs as $doc_div) {
            $doc_ids[] = $doc_div->getAttribute("data-doc-block");
        }

        if($domain_filter === NULL) {
            $domain_filter = $default_domain;
        }

        foreach($documents as $doc) {

            $domain = $doc->getDomain();

            if($domain_filter === "all") {
                $excluded = false;
            } else if (($domain_filter === "federal") || ($domain_filter === "state") || ($domain_filter === "local")) {
                $excluded = ($domain_filter !== $domain->getLevel());
            } else if($domain_filter === "mine") {
                $excluded = !in_array($domain->getId(), $user_domains);
            } else {
                $excluded = ($domain_filter !== $domain->getId());
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
