<?php

namespace VoteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use VoteBundle\Entity\User;

class LoadUserData extends AbstractFixture implements OrderedFixtureInterface {

    /**
     * {@inheritDoc}
     * Note that user Teams are loaded as part of the LoadUserData
     */
    public function load(ObjectManager $manager) {

        $manager->persist($this->createUser('bob', 'brown', array("electorate-federal-griffith", "electorate-state-south-brisbane", "electorate-local-coorparoo")));
        $manager->persist($this->createUser('steve', 'smith'));
        $manager->persist($this->createUser('harry', 'henderson'));

        $manager->flush();

    }

    private function createUser($first_name, $last_name, $electorate_references = array()) {

        $user = new User();
        $user->setEmail($first_name . "@test.com");
        $user->setUsername($user->getEmail());
        $user->setPlainPassword('test');
        $user->setEnabled(true);
//        $user->addRole('ROLE_USER');
//        $user->addRole('ROLE_ADMIN');
//        $user->addRole('ROLE_SUPER_ADMIN');


        $this->addReference('user-' . $first_name, $user);

        foreach($electorate_references as $elect_ref) {

        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder() {
        return 3; // the order in which fixtures will be loaded
    }
}