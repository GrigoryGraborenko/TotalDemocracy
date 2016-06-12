<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 31/05/2016
 * Time: 5:36 PM
 */

namespace VoteBundle\Tests\Controller;

use Symfony\Component\Security\Acl\Exception\Exception;
use VoteBundle\Tests\BaseFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class ProfileTest extends BaseFunctionalTestCase {

    public function testProfileUpdate() {

        $this->client->request('GET', "/settings");
        $this->assertEquals(302, $this->client->getResponse()->getStatusCode(), "Should not have loaded settings page before logging in");

        $user = $this->login();

        $crawler = $this->client->request('GET', "/settings");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load settings page");

        $track_button = $crawler->selectButton('Update');
        $this->assertNotEmpty($track_button, "Update button not found");
        $form = $track_button->form();
        $this->assertNotNull($form, 'No form found');

        $old_phone = $user->getPhone();
        $new_phone = "12345678";

        $this->assertNotEquals($new_phone, $old_phone, "Cannot change phone number update if the new phone is already the same as the old");

        $old_member = $user->getIsMember();
        $new_member = !$old_member;

        $input = array(
            'phone'         => $new_phone
        );
        if($new_member) {
            $input['isMember'] = "on";
        }

        $this->client->submit($form, $input);

        $this->em->refresh($user);

        $this->assertEquals($new_member, $user->getIsMember(), "Membership status not changed");
        $this->assertEquals($new_phone, $user->getPhone(), "Phone number not changed");
    }

    public function testPasswordChange() {

        $old_pass = "test";
        $new_pass = "password123";

        $this->attemptPasswordChange($old_pass, $new_pass, "fssddsfg", false);
        $this->attemptPasswordChange($old_pass, "tiny", "tiny", false);

        $this->attemptPasswordChange("Fdsfdf", $new_pass, $new_pass, false, $old_pass);
        $this->attemptPasswordChange($old_pass, $new_pass, $new_pass, true);
    }

    private function attemptPasswordChange($old_pass, $new_pass, $new_repeat_pass, $should_succeed, $original_pass = NULL) {

        if($original_pass === NULL) {
            $original_pass = $old_pass;
        }

        $this->attemptLogin("bob@test.com", $original_pass, true);

        $crawler = $this->client->request('GET', "/settings");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load settings page");

        $track_button = $crawler->selectButton('Change Password');
        $this->assertNotEmpty($track_button, "Update button not found");
        $form = $track_button->form();
        $this->assertNotNull($form, 'No form found');

        $this->client->submit($form, array(
            "old-password" => $old_pass
            ,"password" => $new_pass
            ,"repeat-password" => $new_repeat_pass
        ));

        $this->logout();

        if($should_succeed) {
            $this->attemptLogin("bob@test.com", $original_pass, false);
            $this->attemptLogin("bob@test.com", $new_pass, true);
        } else {
            $this->attemptLogin("bob@test.com", $new_pass, false);
            $this->attemptLogin("bob@test.com", $original_pass, true);
        }

        $this->logout();
    }

}
