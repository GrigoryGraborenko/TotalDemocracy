<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 26/02/2016
 * Time: 8:15 PM
 */

namespace VoteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use VoteBundle\Entity\Domain;
use VoteBundle\Entity\Electorate;

class LoadDomainData extends AbstractFixture implements OrderedFixtureInterface {

    /**
     * {@inheritDoc}
     * Note that user Teams are loaded as part of the LoadUserData
     */
    public function load(ObjectManager $manager) {

        $this->createDomain($manager, 'federal', 'Australia', NULL, array("Banks"));
        $this->createDomain($manager, 'state', 'Queensland', "QLD", array("Albert"));
        $this->createDomain($manager, 'state', 'New South Wales', "NSW");
        $this->createDomain($manager, 'state', 'Victoria', "VIC");
        $this->createDomain($manager, 'state', 'Australian Capital Territory', "ACT");
        $this->createDomain($manager, 'state', 'Northern Territory', "NT");
        $this->createDomain($manager, 'state', 'South Australia', "SA");
        $this->createDomain($manager, 'state', 'Western Australia', "WA");
        $this->createDomain($manager, 'state', 'Tasmania', "TAS");
        $this->createDomain($manager, 'local', 'BRISBANE CITY', NULL, array("Central Ward"));

        $manager->flush();
    }

    /**
     * @param $manager
     * @param $level
     * @param $name
     * @param array $electorates
     * @return Domain
     */
    private function createDomain($manager, $level, $name, $short_name = NULL, $electorates = array()) {

        $domain = new Domain($level, $name, $short_name);
        $this->addReference('domain-' . $level . '-' . strtolower(str_replace(' ', '-', $name)), $domain);
        $manager->persist($domain);
        $manager->flush();

        foreach($electorates as $electorate_name) {
            $electorate = new Electorate($domain, $electorate_name);
            $this->addReference('electorate-' . $level . '-' . strtolower(str_replace(' ', '-', $electorate_name)), $electorate);
            $manager->persist($electorate);
        }
        $manager->flush();

        return $domain;
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder() {
        return 1; // the order in which fixtures will be loaded
    }
}
