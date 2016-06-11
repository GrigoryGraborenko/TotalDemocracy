<?php
/**
 * Created by PhpStorm.
 * User: Grisha
 */

namespace VoteBundle\Service;

use Carbon\Carbon;
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

    /**
     * @param $filename
     * @return array
     * @throws \Exception
     */
    public function processElectoralPDF($filename) {

        $street_types = array("St", "Rd", "Ave", "Tce", "Cres", "Cl", "Pl", "Ln", "Dr", "Ct", "La", "Crct", "Blvd", "Pde", "Way", "Pkwy", "Esp", "Gr");

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filename);

        $page_list  = $pdf->getPages();

        // get the date that the roll is valid for
        $cover_texts = explode(" ", $page_list[0]->getText()); // text comes from the PDF library with extra spaces
        $day_array = array();
        $month_array = array();
        $year_array = array();
        foreach($cover_texts as $text) {
            if(is_numeric($text)) {
                if(count($month_array) > 0) {
                    $year_array[] = $text;
                } else {
                    $day_array[] = $text;
                }
            } else if(count($day_array) > 0) {
                $month_array[] = $text;
            }
        }
        $valid_date = implode("", $day_array) . " " . implode("", $month_array) . " " . implode("", $year_array);
        $valid_date = Carbon::parse($valid_date, "UTC");

        $pages = array_slice($page_list, 2);
        $segments = array();
        foreach($pages as $page) {

            $texts = $this->getPDFPageText($page, $pdf); // custom re-write of library code

            foreach ($texts as $text) {
                $sections = explode('|', $text); // split by the arbitrary delimiter picked
                if (count($sections) >= 2) {
                    foreach ($sections as $section) {
                        $segments[] = $section;
                    }
                }
            }
        }

        $index = 0;
        $sub_index = 0;
        $current_entry = NULL;
        $entries = array();
        $suburbs = array();

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
                $index_test = intval($current_entry["index_str"]);
                if($index_test === $index) {
                    $sub_index++;
                } else if($index_test === ($index + 1)) { // sometimes the electoral roll just skips a number for no good reason
                    $this->logger->info("Skipped index $index");
                    $index++;
                    $sub_index++;
                }
            } else if($sub_index === 1) {
                $current_entry['name'] = $segment;
                $current_entry['surname'] = array();
                $current_entry['given_names'] = array();
                $on_surname = true;
                foreach(explode(" ", $segment) as $chunk) {
                    if((strtoupper($chunk) === $chunk) && $on_surname) {
                        $current_entry['surname'][] = $chunk;
                    } else {
                        $current_entry['given_names'][] = $chunk;
                        $on_surname = false;
                    }
                }
                $current_entry['surname'] = implode(" ", $current_entry['surname']);
                $current_entry['given_names'] = implode(" ", $current_entry['given_names']);

                $sub_index++;
            } else if($sub_index === 2) {
                $current_entry['address'] = $segment;
                $current_entry['unit'] = NULL;
                $current_entry['street_number'] = NULL;
                $current_entry['street'] = NULL;
                $current_entry['street_type'] = NULL;
                $current_entry['suburb'] = NULL;

                if(is_numeric($segment) && (intval($segment) === ($index + 1))) { // this is for malformed data caused by super long names
                    $current_entry['address'] = $current_entry['name'];
                    $current_entry['street_type'] = "UNKNOWN";

                    $entries[] = $current_entry;
                    $index++;
                    $sub_index = 1;
                    $current_entry = array('index' => $index, "index_str" => $segment);

                } else {
                    if($segment !== "Address Suppressed") {
                        $chunks = explode(" ", $segment);
                        $number = array_shift($chunks);
                        if(is_numeric($number)) {
                            $current_entry['street_number'] = intval($number);
                        } else {
                            $number = explode("/", $number);
                            if(count($number) >= 2) {
                                $current_entry['unit'] = intval($number[0]);
                                $current_entry['street_number'] = intval($number[1]);
                            }
                        }
                        for($i = 1; $i < (count($chunks) - 1); $i++) {
                            if(in_array($chunks[$i], $street_types)) {
                                break;
                            }
                        }
                        if($i < (count($chunks) - 1)) { // normal address
                            $current_entry['street'] = implode(" ", array_slice($chunks, 0, $i));
                            $current_entry['street_type'] = $chunks[$i];
                            $current_entry['suburb'] = implode(" ", array_slice($chunks, $i + 1));
                            if(!in_array($current_entry['suburb'], $suburbs)) {
                                $suburbs[] = $current_entry['suburb'];
                            }
                        } else { // unusual address, assume everything other than suburb name is the "street" name of unknown type

                            $full = implode(" ", $chunks);
                            foreach($suburbs as $suburb) {
                                $pos = strpos($full, $suburb);
                                if($pos !== false) {
                                    $current_entry['suburb'] = substr($full, $pos);
                                    $current_entry['street'] = substr($full, 0, $pos);
                                    $current_entry['street_type'] = "UNKNOWN";
                                    break;
                                }
                            }

                            if($current_entry['suburb'] === NULL) { // chances are the suburb name was shortened
                                $current_entry['street'] = $full;
                                $current_entry['street_type'] = "UNKNOWN";
                            }
                        }
                    }
                    $entries[] = $current_entry;
                    $current_entry = NULL;
                }
            }
        }

        return array(
            "entries" => $entries
            ,"suburbs" => $suburbs
            ,"valid_date" => $valid_date
        );
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