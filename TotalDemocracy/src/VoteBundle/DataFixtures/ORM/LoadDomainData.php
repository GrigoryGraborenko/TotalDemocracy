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

        $this->createDomain($manager, 'federal', 'Australia', array("Griffith"));
        $this->createDomain($manager, 'state', 'Queensland', array("South Brisbane"));
        $this->createDomain($manager, 'state', 'New South Wales');
        $this->createDomain($manager, 'state', 'Victoria');
        $this->createDomain($manager, 'state', 'Australian Capital Territory');
        $this->createDomain($manager, 'state', 'Northern Territory');
        $this->createDomain($manager, 'state', 'South Australia');
        $this->createDomain($manager, 'state', 'Western Australia');
        $this->createDomain($manager, 'state', 'Tasmania');
        $this->createDomain($manager, 'local', 'Brisbane City Council', array("Central Ward", "Coorparoo"));

        $manager->flush();

    }

    /**
     * @param $manager
     * @param $level
     * @param $name
     * @param array $electorates
     * @return Domain
     */
    private function createDomain($manager, $level, $name, $electorates = array()) {

        $domain = new Domain($level, $name);
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
