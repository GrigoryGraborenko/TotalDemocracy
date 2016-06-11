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

    // TODO: ensure that when confirming, the address is pre-filled from verification

    // test registration
    public function testRegistration() {

        $this->attemptRegistration("bob@test.com", false); // already in the fixtures
        $new_user = $this->attemptRegistration("test@test.com", true);
        $this->attemptRegistration("test@test.com", false);
        $this->attemptRegistration("testblah", false);

        $reg_event = $this->em->getRepository("VoteBundle:ServerEvent")->findOneBy(array("user" => $new_user, "name" => "registration"));
        $this->assertNotNull($reg_event, 'No registration event found');
        $this->assertNull($reg_event->getParent(), "Registration event should have no tracking event");

        $this->attemptLogin($new_user->getEmail(), "fsdsfs", false);

        $password = "pass1234";
        $this->attemptConfirmation($new_user, "tiny", false); // too short a password
        $this->attemptConfirmation($new_user, $password, true);

        $this->attemptLogin($new_user->getEmail(), "fdsf", false);
        $this->attemptLogin($new_user->getEmail(), $password, true);
    }

    // test order of verification, registration, confirmation

    public function testMenus() {

        $this->checkMenuNames(array("Register", "Vote", "Login"));

        $this->attemptRegistration("test@test.com", true);
        $this->checkMenuNames(array("Register", "Verify", "Vote", "Login"));

        $this->client->request('GET', "/signup");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load signup page");

        $this->checkMenuNames(array("Register", "Vote", "Login"));

        $this->login("admin@test.com", "test", true);
        $this->checkMenuNames(array("Verify", "Vote", "Settings", "Logout"));
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

    /**
     * @param $email
     * @param $should_succeed
     * @return null|object
     */
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

    /**
     * @param $user
     * @param $password
     * @param bool $expect_success
     */
    private function attemptConfirmation($user, $password, $expect_success = true) {

        $this->assertFalse($user->isEnabled(), "User should not be enabled");

        $email = $user->getEmail();
        $token = $user->getConfirmationToken();

        $crawler = $this->client->request('GET', "/signup-finish/$email/$token");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load signup confirm page");

        $form = $crawler->selectButton('Finish Registration')->form();
        $this->assertNotNull($form, 'No form found');

        if($password === NULL) {
            $password = "password";
        }

        $this->client->submit($form, array(
            'password'      => $password
            ,'phone'        => "123456787"
        ));

        $this->em->refresh($user);

        if($expect_success) {
            $this->assertTrue($user->isEnabled(), "User should be enabled");
        } else {
            $this->assertFalse($user->isEnabled(), "User should not be enabled");
        }
    }

    /**
     * @param $email
     * @param string $password
     * @param bool $should_succeed
     */
    private function attemptLogin($email, $password = "password", $should_succeed = true) {

        $crawler = $this->client->request('GET', "/login");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load login confirm page");

        $form = $crawler->selectButton('Login')->form();
        $this->assertNotNull($form, 'Could not find form');

        $this->client->submit($form, array(
            '_username'        => $email
            ,'_password'    => $password
        ));

        $response = $this->client->getResponse();
        $this->assertEquals(302, $response->getStatusCode(), "Did not redirect after login attempt");
        if($should_succeed) {
            $this->assertStringEndsNotWith("login", $response->headers->get('location'), "Failed to login in");
        } else {
            $this->assertStringEndsWith("login", $response->headers->get('location'), "Failed to login in");
        }

    }

    /**
     * @return array
     */
    private function checkMenuNames($expected) {

        $crawler = $this->client->request('GET', "/");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load home page");
        $links = $crawler->filter("#navbar a");
        $link_names = array();
        foreach($links as $link) {
            $link_names[] = $link->textContent;
        }
        $this->assertEmpty(array_merge(array_diff($expected, $link_names), array_diff($link_names, $expected)), "Incorrect menus, should be " . json_encode($expected) .  ", was " .json_encode($link_names));
    }
}
