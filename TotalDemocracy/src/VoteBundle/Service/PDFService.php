<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 14/09/2015
 * Time: 9:11 AM
 */

namespace VoteBundle\Service;

use Doctrine\ORM\EntityManager;

use Smalot\PdfParser;
//use Smalot\PdfParser\Page;
//use Smalot\PdfParser\Object;

/**
 * Class PDFService
 * @package VoteBundle\Service
 *
 * Reads PDF files and checks them for electoral roll data
 *
 */
class PDFService
{

    /** @var EntityManager em */
    private $em;

    /** @var Service Container container */
    private $container;

    /** @var Logger logger */
    private $logger;

    /**
     * OptionService constructor.
     * @param $em
     * @param $container
     * @param $logger
     */
    public function __construct($em, $container, $logger) {
        $this->em = $em;
        $this->container = $container;
        $this->logger = $logger;
    }


    public function processElectoralPDF($filename, $temp_logger) {

//        $tst = "....";
//        $temp_logger->write(intval($tst), true);
//        if(is_numeric($tst)) {
//            $temp_logger->write("is number", true);
//        }
//        return;

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filename);

        $current_font = new \Smalot\PdfParser\Font($pdf);

        $page_list  = $pdf->getPages();

//        $text = $this->getPDFPageText($pages[2]);
//        $pages = array($page_list[2], $page_list[3]);

        $pages = array_slice($page_list, 2, 1);
//        $pages = array_slice($page_list, 2);

        $segments = array();
        foreach($pages as $page) {

//            $texts = $page->getText();
//            $this->logger->info("INFOx: " . $page->getContent());
            //$texts = $this->getPDFText($page, $current_font, $recursion_stack);
            $texts = $this->getPDFPageText($page, $pdf);

            foreach ($texts as $text) {
                $sections = explode('|', $text);
                if (count($sections) >= 2) {
                    foreach ($sections as $section) {
                        $segments[] = $section;
                    }
                }
            }

            $temp_logger->write("read page", true);

        }

        $index = 0;
        $sub_index = 0;
        $current_entry = NULL;
        $entries = array();

        foreach($segments as $segment) {
            if($current_entry === NULL) {
                $index++;
                $sub_index = 0;
                $current_entry = array('index' => $index, "index_str" => "");
            }
            if($sub_index === 0) {
                if(is_numeric($segment)) {
                    $current_entry["index_str"] .= $segment;
                }
                if(intval($current_entry["index_str"]) === $index) {
                    $sub_index++;
                }
            } else if($sub_index === 1) {
                $current_entry['name'] = $segment;
                $sub_index++;
            } else if($sub_index === 2) {
                $current_entry['address'] = $segment;

                $entries[] = $current_entry;

                $current_entry = NULL;
            }
            //$temp_logger->write(json_encode($segment), true);
        }

        $temp_logger->write("complete, found " . count($entries), true);

        foreach($entries as $entry) {
            $temp_logger->write(json_encode($entry), true);
        }

//        $temp_logger->write("===========", true);
//        foreach($misc as $entry) {
//            $temp_logger->write(json_encode($entry), true);
//        }

        //$temp_logger->write(json_encode($text), true);
        //$temp_logger->write($text, true);
    }

    /**
     * @param $page
     * @param $document
     * @return array
     */
    private function getPDFPageText($page, $document) {

        $current_font = new \Smalot\PdfParser\Font($document);

        if ($contents = $page->get('Contents')) {

            if ($contents instanceof \Smalot\PdfParser\Element\ElementMissing) {
                return array();
            } elseif ($contents instanceof \Smalot\PdfParser\Object) {
                $elements = $contents->getHeader()->getElements();

                if (is_numeric(key($elements))) {
                    $new_content = '';

                    foreach ($elements as $element) {
                        if ($element instanceof \Smalot\PdfParser\Element\ElementXRef) {
                            $new_content .= $element->getObject()->getContent();
                        } else {
                            $new_content .= $element->getContent();
                        }
                    }

                    $header   = new \Smalot\PdfParser\Header(array(), $document);
                    $contents = new \Smalot\PdfParser\Object($document, $header, $new_content);
                }
            } elseif ($contents instanceof ElementArray) {
                // Create a virtual global content.
                $new_content = '';

                foreach ($contents->getContent() as $content) {
                    $new_content .= $content->getContent() . "\n";
                }

                $header   = new Header(array(), $document);
                $contents = new Object($document, $header, $new_content);
            }

            $recursion_stack = array();
            return $this->getPDFText($contents, $current_font, $recursion_stack);
        }
        return array();
    }

    /**
     * @param $object
     * @param $current_font
     * @param $recursion_stack
     * @return array
     */
    private function getPDFText($object, $current_font, &$recursion_stack) {
        $text_array = array();
        $text                = '';
        $sections            = $object->getSectionsText($object->getContent());
//        $current_font        = new Font($this->document);
        $current_position_td = array('x' => false, 'y' => false);
        $current_position_tm = array('x' => false, 'y' => false);

        //array_push($recursion_stack, $object->getUniqueId());
        array_push($recursion_stack, spl_object_hash($object));

        //$this->logger->info("NUM SEC: " . count($sections));
        $this->logger->info("INFO: " . $object->getContent());

        foreach ($sections as $section) {

            $commands = $object->getCommandsText($section);

            foreach ($commands as $command) {

                switch ($command['o']) {
                    // set character spacing
                    case 'Tc':
                        break;

                    // move text current point
                    case 'Td':
                        $args = preg_split('/\s/s', $command['c']);
                        $y    = array_pop($args);
                        $x    = array_pop($args);
                        if ((floatval($x) <= 0) ||
                            ($current_position_td['y'] !== false && floatval($y) < floatval($current_position_td['y']))
                        ) {
                            // vertical offset
//                            $text .= "\n";
                            $text_array[] = $text;
                            $text = "";
                        } elseif ($current_position_td['x'] !== false && floatval($x) > floatval(
                                $current_position_td['x']
                            )
                        ) {
                            // horizontal offset
//                            $text .= '_';
                            //$text .= "[$x]";
                            if(floatval($x) > 10.0) {
                                $text .= '|';
                            }
                        }
                        $current_position_td = array('x' => $x, 'y' => $y);
                        break;

                    // move text current point and set leading
                    case 'TD':
                        $args = preg_split('/\s/s', $command['c']);
                        $y    = array_pop($args);
                        $x    = array_pop($args);
                        if (floatval($y) < 0) {
                            //$text .= "\n";
                            $text_array[] = $text;
                            $text = "";
                        } elseif (floatval($x) <= 0) {
                            $text .= ' ';
                        }
                        break;

                    case 'Tf':
//                        list($id,) = preg_split('/\s/s', $command['c']);
//                        $id           = trim($id, '/');
//                        $current_font = $page->getFont($id);
                        break;

                    case "'":
                    case 'Tj':
                        $command['c'] = array($command);
                    case 'TJ':
                        // Skip if not previously defined, should never happened.
                        if (is_null($current_font)) {
                            // Fallback
                            // TODO : Improve
                            $text .= $command['c'][0]['c'];
                            continue;
                        }

                        $sub_text = $current_font->decodeText($command['c']);
                        $text .= $sub_text;
                        break;

                    // set leading
                    case 'TL':
                        $text .= ' ';
                        break;

                    case 'Tm':
                        $args = preg_split('/\s/s', $command['c']);
                        $y    = array_pop($args);
                        $x    = array_pop($args);
                        if ($current_position_tm['y'] !== false) {
                            $delta = abs(floatval($y) - floatval($current_position_tm['y']));
                            if ($delta > 10) {
//                                $text .= "\n";
                                $text_array[] = $text;
                                $text = "";
                            }
                        }
                        $current_position_tm = array('x' => $x, 'y' => $y);
                        break;

                    // set super/subscripting text rise
                    case 'Ts':
                        break;

                    // set word spacing
                    case 'Tw':
                        break;

                    // set horizontal scaling
                    case 'Tz':
//                        $text .= "\n";
                        $text_array[] = $text;
                        $text = "";
                        break;

                    // move to start of next line
                    case 'T*':
//                        $text .= "\n";
                        $text_array[] = $text;
                        $text = "";
                        break;

                    case 'Da':
                        break;

                    case 'Do':
//                        if (!is_null($page)) {
//                            $args    = preg_split('/\s/s', $command['c']);
//                            $id      = trim(array_pop($args), '/ ');
//                            $xobject = $page->getXObject($id);
//
//                            if ( is_object($xobject) && !in_array($xobject->getUniqueId(), $recursion_stack) ) {
                                // Not a circular reference.
                                //$text .= $xobject->getText($page);
//                            }
//                        }
                        break;

                    case 'rg':
                    case 'RG':
                        break;

                    case 're':
                        break;

                    case 'co':
                        break;

                    case 'cs':
                        break;

                    case 'gs':
                        break;

                    case 'en':
                        break;

                    case 'sc':
                    case 'SC':
                        break;

                    case 'g':
                    case 'G':
                        break;

                    case 'V':
                        break;

                    case 'vo':
                    case 'Vo':
                        break;

                    default:
                }
            }
        }

        array_pop($recursion_stack);

        return $text_array;
    }


}