<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 10/06/2017
 * Time: 11:19 AM
 */

namespace VoteBundle\Controller;

use Carbon\Carbon;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;

use JMS\DiExtraBundle\Annotation as DI;

use VoteBundle\Controller\CommonController;

use VoteBundle\Exception\ErrorRedirectException;

/**
 * Class PageController
 * @package VoteBundle\Controller
 */
class PageController extends CommonController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    protected $em;

    /**
     * @Route("/p/{url}", name="cms-page")
     *
     * @param Request $request
     * @param $url
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws ErrorRedirectException
     */
    public function pageAction(Request $request, $url) {

        $page = $this->em->getRepository('VoteBundle:Page')->findOneBy(array("visible" => true, "url" => $url));
        if(!$page) {
            throw new ErrorRedirectException("error_page", "Page does not exist");
        }
        $params = $page->getJsonParamsArray();

        $indices_needed = false;
        $indices = array();
        foreach($params["sections"] as &$param) {
            if($param["type"] === "index") {
                $indices_needed = true;
            } else if(!$indices_needed) {
                continue;
            }
            if(($param["type"] === "major") || ($param["type"] === "medium") || ($param["type"] === "minor")) {
                $element_id = str_replace(" ", "-", strtolower(substr($param["text"], 0, 12)));
                $param["id"] = $element_id;
//                if($param["type"] === "medium") {
//                    $param["index_style"] = "margin-left: 20px";
//                } else if($param["type"] === "minor") {
//                    $param["index_style"] = "margin-left: 40px";
//                }
                if($param["type"] === "minor") {
                    $param["index_style"] = "margin-left: 20px";
                }
                $indices[] = $param;
            }
        }

        return $this->render("VoteBundle:Pages:cms.html.twig", array(
            'sections' => $params["sections"]
            ,'indices' => $indices
        ));
    }

}