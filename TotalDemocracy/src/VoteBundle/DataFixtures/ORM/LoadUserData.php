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

        $manager->persist($this->createUser('bob', false, array("electorate-federal-griffith", "electorate-state-south-brisbane", "electorate-local-coorparoo")));
        $manager->persist($this->createUser('steve', false, 'smith'));
        $manager->persist($this->createUser('harry', false, 'henderson'));
        $manager->persist($this->createUser('terry', false, 'archer'));
        $manager->persist($this->createUser('sally', true, 'winston'));
        $manager->persist($this->createUser('admin', true));

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

//        foreach($electorate_references as $elect_ref) {
//        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder() {
        return 2; // the order in which fixtures will be loaded
    }
}