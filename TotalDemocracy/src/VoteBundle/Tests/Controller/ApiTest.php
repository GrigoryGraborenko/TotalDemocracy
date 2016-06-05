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

class APITest extends BaseFunctionalTestCase {

    // test external documents
    public function testExternalDocuments() {

        $this->client->request('GET', "/api/documents");
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode(), "Failed to load external documents page");
        $this->assertEquals("*", $response->headers->get("Access-Control-Allow-Origin"), "Cross origin allowed");

        $count = 3;
        $crawler = $this->client->request('GET', "/api/documents/$count");
        $doc_nodes = $crawler->filter(".pd-document");
        $this->assertCount($count, $doc_nodes, "Should have returned $count documents");
    }

}
