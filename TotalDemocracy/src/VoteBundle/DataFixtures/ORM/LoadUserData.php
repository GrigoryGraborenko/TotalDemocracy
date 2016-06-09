<?php

namespace VoteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use Carbon\Carbon;
use VoteBundle\Entity\User;

class LoadUserData extends AbstractFixture implements OrderedFixtureInterface {

    private $manager;

    /**
     * {@inheritDoc}
     * Note that user Teams are loaded as part of the LoadUserData
     */
    public function load(ObjectManager $manager) {

        $this->manager = $manager;

        $this->createUser('bob', false, array("electorate-federal-banks", "electorate-state-albert", "electorate-local-central-ward"))->setWhenVerified(Carbon::now("UTC"));
        $this->createUser('steve', false);
        $this->createUser('harry', false);
        $this->createUser('terry', false);
        $this->createUser('sally', true);
        $this->createUser('admin', true);

        $manager->flush();
    }

    private function createUser($first_name, $is_admin, $electorate_references = array()) {

        $user = new User();
        $user->setEmail($first_name . "@test.com");
        $user->setUsername($user->getEmail());
        $user->setPlainPassword('test');
        $user->setEnabled(true);
        if($is_admin) {
            $user->addRole('ROLE_ADMIN');
        }
//        $user->addRole('ROLE_USER');
//        $user->addRole('ROLE_ADMIN');
//        $user->addRole('ROLE_SUPER_ADMIN');


        $this->addReference('user-' . $first_name, $user);

        $this->manager->persist($user);
        foreach($electorate_references as $elect_ref) {
            $user->addElectorate($this->getReference($elect_ref));
        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder() {
        return 2; // the order in which fixtures will be loaded
    }
}