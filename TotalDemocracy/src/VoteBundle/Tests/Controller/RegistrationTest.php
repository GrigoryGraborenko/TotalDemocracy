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
        $new_user = $this->attemptRegistration("test@test.com", true);
        $this->attemptRegistration("test@test.com", false);
        $this->attemptRegistration("testblah", false);

        $reg_event = $this->em->getRepository("VoteBundle:ServerEvent")->findOneBy(array("user" => $new_user, "name" => "registration"));
        $this->assertNotNull($reg_event, 'No registration event found');
        $this->assertNull($reg_event->getParent(), "Registration event should have no tracking event");
    }

    // test menus as well, if they appear or not
    public function testMenus() {

    }

    public function testTracking() {

        $admin = $this->login("admin@test.com", "test", true);

        $crawler = $this->client->request('GET', "/settings");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load signup page");

        $track_button = $crawler->selectButton('Track');
        $this->assertNotEmpty($track_button, "Tracking button not found");
        $form = $track_button->form();
        $this->assertNotNull($form, 'No form found');

        $hours_len = 6;
        $context = "Stall";

        $this->client->submit($form, array(
            'time'       => $hours_len
            ,'context'   => $context
        ));
        $response = $this->client->getResponse();
        $this->assertEquals(302, $response->getStatusCode(), "Could not track");
        $this->client->followRedirect();

        $track_event = $this->em->getRepository("VoteBundle:ServerEvent")->findOneBy(array("user" => $admin, "name" => "registration.track"));
        $this->assertNotNull($track_event, "Could not find tracking event");
        $this->assertFalse($track_event->getProcessed(), "Tracking event not unprocessed");
        $json = $track_event->getJsonArray();
        $this->assertEquals($hours_len, $track_event->getAmount(), "Event hours not correct");
        $this->assertEquals($context, $json['context'], "Event context not correct");

        // should fail unless you log out first
        $this->client->request('GET', "/signup");
        $this->assertEquals(302, $this->client->getResponse()->getStatusCode(), "Should have failed to load signup page");

        $this->logout();

        Carbon::setTestNow(Carbon::now("UTC")->addHours($hours_len)->subMinutes(1));

        $new_user = $this->attemptRegistration("roger@test.com", true);

        $event_repo = $this->em->getRepository("VoteBundle:ServerEvent");
        $reg_event = $event_repo->findOneBy(array("user" => $new_user, "name" => "registration"));
        $this->assertNotNull($reg_event, 'No registration event found');
        $this->assertNotNull($reg_event->getParent(), 'No registration parent event found');
        $this->assertEquals($track_event->getId(), $reg_event->getParent()->getId(), "Registration event should tracking event as parent");

        $this->em->refresh($track_event);
        $this->assertFalse($track_event->getProcessed(), "Tracking event not unprocessed");

        Carbon::setTestNow(Carbon::now("UTC")->addMinutes(2));

        $new_user = $this->attemptRegistration("sarah@test.com", true);
        $this->em->refresh($track_event);
        $this->assertTrue($track_event->getProcessed(), "Tracking event not processed");

        $reg_event = $event_repo->findOneBy(array("user" => $new_user, "name" => "registration"));
        $this->assertNotNull($reg_event, 'No registration event found');
        $this->assertNull($reg_event->getParent(), 'Registration parent event found');
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

        $user_repo = $this->em->getRepository("VoteBundle:User");

        $response = $this->client->getResponse();
        $this->assertEquals(302, $response->getStatusCode(), "Did not redirect");
        if($should_succeed) {
            $this->assertStringEndsWith("/verify", $response->headers->get('location'), "Failed to register $email");
            $new_user = $user_repo->findOneBy(array("email" => $email));
            $this->assertNotNull($new_user, "Could not find new user");
            return $new_user;
        } else {
            $this->assertStringEndsWith("/signup", $response->headers->get('location'), "Should have failed to register $email");
        }
        return NULL;
    }
}
