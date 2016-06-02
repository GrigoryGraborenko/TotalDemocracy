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

class RegistrationTest extends BaseFunctionalTestCase {

    // test registration
    public function testRegistration() {

        $this->attemptRegistration("bob@test.com", false); // already in the fixtures
        $this->attemptRegistration("test@test.com", true);
        $this->attemptRegistration("test@test.com", false);
        $this->attemptRegistration("testblah", false);
    }

    // test order of verification, registration

    // test tracking

    private function attemptRegistration($email, $should_succeed) {

        $crawler = $this->client->request('GET', "/signup");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load signup page");

        $form = $crawler->selectButton('Register')->form();
        $this->assertNotNull($form, 'No form found');

        $this->client->submit($form, array(
            'email'         => $email
            ,'understand'   => "on"
        ));

        $response = $this->client->getResponse();
        $this->assertEquals(302, $response->getStatusCode(), "Did not redirect");
        if($should_succeed) {
            $this->assertStringEndsWith("/verify", $response->headers->get('location'), "Failed to register $email");
        } else {
            $this->assertStringEndsWith("/signup", $response->headers->get('location'), "Should have failed to register $email");
        }

    }
}
